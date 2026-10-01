# Adapter Pattern

> **Category:** Structural
> **Intent:** Convert the interface of a class into **another interface the client expects**, so classes that couldn't work together because of incompatible interfaces can.

Also known as: **Wrapper**.

## Contents

- [The Problem](#the-problem)
- [Real-World Analogy](#real-world-analogy)
- [Structure](#structure)
- [Object Adapter (recommended)](#object-adapter-recommended)
- [Class Adapter](#class-adapter)
- [Two-Way Translation: Data Conversion](#two-way-translation-data-conversion)
- [Adapting Several Vendors](#adapting-several-vendors)
- [Adapters You Already Use in .NET](#adapters-you-already-use-in-net)
- [Adapter with Dependency Injection](#adapter-with-dependency-injection)
- [Adapter vs. Similar Patterns](#adapter-vs-similar-patterns)
- [Pitfalls](#pitfalls)
- [When to Use / When to Avoid](#when-to-use--when-to-avoid)
- [Key Takeaways](#key-takeaways)

---

## The Problem

Your app has its own payment abstraction:

```csharp
public interface IPaymentProcessor
{
    PaymentResult Pay(decimal amount, string currency, string customerId);
}

public record PaymentResult(bool Success, string TransactionId);
```

Your business code depends on `IPaymentProcessor`. Now you need to use a third-party SDK you **can't modify**, and its API looks completely different:

```csharp
// Third-party SDK (you can't change this)
public class LegacyPayGateway
{
    public LegacyResponse MakeCharge(LegacyChargeRequest request) { ... }
}

public class LegacyChargeRequest
{
    public long AmountInCents { get; set; }
    public string CurrencyCode { get; set; } = "";
    public string AccountRef { get; set; } = "";
}

public class LegacyResponse
{
    public int StatusCode { get; set; }   // 0 = OK
    public string Reference { get; set; } = "";
}
```

Options:

- ❌ Change your business code to call `LegacyPayGateway` directly → vendor details leak everywhere.
- ❌ Change the SDK → you don't own it.
- ✅ Write an **adapter** that implements `IPaymentProcessor` and translates calls to `LegacyPayGateway`.

---

## Real-World Analogy

A **travel plug adapter**. Your laptop charger (client) has a UK plug; the wall socket (adaptee) is European. The adapter doesn't change the laptop or the wall — it sits in between and converts one shape to the other.

---

## Structure

```
 Client ──uses──► ITarget                       Adaptee
                     ▲                    ┌──────────────────┐
                     │ implements         │ SpecificRequest()│
              ┌──────┴───────┐   wraps    └──────────────────┘
              │   Adapter    │ ─────────────────►  ▲
              │  Request()   │  calls SpecificRequest()
              └──────────────┘
```

| Role | In the example |
| --- | --- |
| **Target** | `IPaymentProcessor` — the interface the client expects |
| **Adaptee** | `LegacyPayGateway` — the existing, incompatible class |
| **Adapter** | `LegacyPayAdapter` — implements Target, wraps Adaptee |
| **Client** | `CheckoutService` — only knows about Target |

---

## Object Adapter (recommended)

The adapter **holds a reference** to the adaptee (composition).

```csharp
public class LegacyPayAdapter : IPaymentProcessor
{
    private readonly LegacyPayGateway _gateway;

    public LegacyPayAdapter(LegacyPayGateway gateway) => _gateway = gateway;

    public PaymentResult Pay(decimal amount, string currency, string customerId)
    {
        // 1. Translate the request: our model -> vendor model
        var request = new LegacyChargeRequest
        {
            AmountInCents = (long)Math.Round(amount * 100m),
            CurrencyCode  = currency.ToUpperInvariant(),
            AccountRef    = customerId
        };

        // 2. Delegate to the adaptee
        LegacyResponse response = _gateway.MakeCharge(request);

        // 3. Translate the response: vendor model -> our model
        return new PaymentResult(response.StatusCode == 0, response.Reference);
    }
}
```

Client code never sees the vendor types:

```csharp
public class CheckoutService
{
    private readonly IPaymentProcessor _payments;

    public CheckoutService(IPaymentProcessor payments) => _payments = payments;

    public void Checkout(Cart cart)
    {
        var result = _payments.Pay(cart.Total, "usd", cart.CustomerId);
        if (!result.Success)
            throw new InvalidOperationException("Payment failed");
    }
}

// Wiring
IPaymentProcessor processor = new LegacyPayAdapter(new LegacyPayGateway());
var checkout = new CheckoutService(processor);
```

- ✅ Works with sealed classes and with any subclass of the adaptee.
- ✅ One adapter can wrap several objects if needed.
- ✅ Favours composition over inheritance.

---

## Class Adapter

The adapter **inherits** from the adaptee and implements the target interface.

```csharp
public class LegacyPayClassAdapter : LegacyPayGateway, IPaymentProcessor
{
    public PaymentResult Pay(decimal amount, string currency, string customerId)
    {
        var response = MakeCharge(new LegacyChargeRequest   // inherited method
        {
            AmountInCents = (long)Math.Round(amount * 100m),
            CurrencyCode  = currency.ToUpperInvariant(),
            AccountRef    = customerId
        });

        return new PaymentResult(response.StatusCode == 0, response.Reference);
    }
}
```

- ❌ Doesn't work if the adaptee is `sealed`.
- ❌ C# has single class inheritance — you use up your one base class.
- ❌ Exposes **all** of the adaptee's public members through the adapter.
- In C#, prefer the **object adapter**.

| | Object Adapter | Class Adapter |
| --- | --- | --- |
| Mechanism | Composition (field) | Inheritance |
| Sealed adaptee | ✅ | ❌ |
| Adapts subclasses of adaptee | ✅ | ❌ |
| Can override adaptee behavior | ❌ | ✅ |
| Hides adaptee's API | ✅ | ❌ |

---

## Two-Way Translation: Data Conversion

Adapters often convert **data shapes** too — e.g. an old XML-based service behind a modern interface:

```csharp
// What your app wants
public interface IWeatherService
{
    Task<Weather> GetCurrentAsync(string city);
}

public record Weather(string City, double TemperatureC, string Summary);

// Existing class returning XML strings with Fahrenheit
public class OldWeatherClient
{
    public Task<string> FetchXmlAsync(string location) { ... }
    // <weather><loc>Yangon</loc><tempF>91.4</tempF><desc>Sunny</desc></weather>
}

public class OldWeatherAdapter(OldWeatherClient client) : IWeatherService
{
    public async Task<Weather> GetCurrentAsync(string city)
    {
        string xml = await client.FetchXmlAsync(city);
        var doc = XDocument.Parse(xml);

        double tempF = (double)doc.Root!.Element("tempF")!;

        return new Weather(
            City: (string)doc.Root.Element("loc")!,
            TemperatureC: Math.Round((tempF - 32) * 5 / 9, 1),
            Summary: (string)doc.Root.Element("desc")!);
    }
}
```

The rest of the app works with `Weather` records in Celsius and never knows XML is involved.

---

## Adapting Several Vendors

A very common real-world use: one interface, one adapter **per vendor**.

```csharp
public interface ISmsSender
{
    Task SendAsync(string phone, string text);
}

public class TwilioSmsAdapter(TwilioClient twilio) : ISmsSender
{
    public Task SendAsync(string phone, string text) =>
        twilio.Messages.CreateAsync(to: phone, body: text);
}

public class VonageSmsAdapter(VonageClient vonage) : ISmsSender
{
    public Task SendAsync(string phone, string text) =>
        vonage.SendSmsAsync(new SmsRequest { To = phone, Message = text });
}
```

(The vendor client APIs above are simplified for illustration.)

Switching vendors = changing **one DI registration**. Business code is untouched. This is the core idea behind **Ports & Adapters (Hexagonal Architecture)**: your domain defines the *ports* (interfaces), infrastructure provides *adapters*.

---

## Adapters You Already Use in .NET

| .NET type | Adapts |
| --- | --- |
| `StreamReader` / `StreamWriter` | A byte `Stream` → a text (`string`/`char`) API |
| `ReadOnlyCollection<T>` | An `IList<T>` → a read-only collection |
| `Task.Factory.FromAsync` | Old `BeginX/EndX` async pattern → `Task` |
| `TaskCompletionSource<T>` | Callback/event-based APIs → `Task` |
| EF Core value converters | A domain type → a database column type |

Example — adapting a callback API to `async/await` with `TaskCompletionSource`:

```csharp
// Old API: callback-based
public class LegacyDownloader
{
    public void Download(string url, Action<byte[]> onDone, Action<Exception> onError) { ... }
}

// Adapter: Task-based
public static class LegacyDownloaderExtensions
{
    public static Task<byte[]> DownloadAsync(this LegacyDownloader d, string url)
    {
        var tcs = new TaskCompletionSource<byte[]>();
        d.Download(url,
            onDone:  bytes => tcs.SetResult(bytes),
            onError: ex    => tcs.SetException(ex));
        return tcs.Task;
    }
}

byte[] data = await new LegacyDownloader().DownloadAsync("https://example.com/file");
```

(An extension method can act as a lightweight adapter when you only need to add a different-shaped method.)

---

## Adapter with Dependency Injection

```csharp
builder.Services.AddSingleton<LegacyPayGateway>();                    // the adaptee
builder.Services.AddScoped<IPaymentProcessor, LegacyPayAdapter>();    // the adapter
builder.Services.AddScoped<CheckoutService>();                        // the client
```

The container injects `LegacyPayGateway` into the adapter, and the adapter into `CheckoutService`. To switch providers later:

```csharp
builder.Services.AddScoped<IPaymentProcessor, StripePaymentAdapter>();
```

---

## Adapter vs. Similar Patterns

All of these **wrap** another object — the difference is **why**:

| Pattern | Interface | Purpose |
| --- | --- | --- |
| **Adapter** | **Changes** it (to the one the client expects) | Make incompatible things work together |
| **Decorator** | **Keeps** the same interface | Add behavior |
| **Proxy** | **Keeps** the same interface | Control access (lazy, security, remote) |
| **Facade** | **New, simpler** interface over **many** objects | Simplify a subsystem |

---

## Pitfalls

1. **Leaky adapter** — returning vendor types (`LegacyResponse`) from the adapter defeats the purpose. Translate **both** request and response.
2. **Business logic inside the adapter** — an adapter should only **translate**. Validation, pricing rules, etc. belong in the domain.
3. **Mismatched semantics** — some things don't map cleanly (e.g. vendor has no refunds, or uses different error codes). Decide explicitly: throw `NotSupportedException`, map errors to your own exception types, document it.
4. **Exception translation** — catch vendor-specific exceptions and rethrow your own, otherwise callers must catch vendor exceptions anyway.
5. **Too many adapters** — if you're adapting your *own* code, it might be better to just change the code.

---

## When to Use / When to Avoid

**Use when:**

- Integrating a **third-party library / legacy code** you can't change.
- You want to **isolate** vendor APIs so they can be swapped.
- Several classes do the same job with different APIs, and you want **one interface** for them.
- Converting between **async styles** or **data formats**.

**Avoid when:**

- You own both sides — just make the interfaces match.
- The adaptee already fits the interface closely enough.

---

## Key Takeaways

- Adapter = **implements the interface the client wants**, **wraps** the object it actually has, and **translates** between them.
- Prefer the **object adapter** (composition) in C#.
- Translate requests, responses **and** exceptions — don't leak the adaptee's types.
- It's the backbone of **Ports & Adapters**: swap vendors by changing one registration.
- Adapter **changes** an interface; Decorator and Proxy **keep** it; Facade **simplifies** many.
