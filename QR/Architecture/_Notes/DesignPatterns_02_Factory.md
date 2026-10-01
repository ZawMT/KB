# Factory Pattern

> **Category:** Creational
> **Intent:** Move object **creation** out of the code that **uses** the object, so callers depend on an **abstraction** instead of calling `new` on concrete classes.

## Contents

- [The Problem](#the-problem)
- [The Factory Family](#the-factory-family)
- [1. Simple Factory](#1-simple-factory)
- [2. Factory Method (GoF)](#2-factory-method-gof)
  - [Structure](#structure)
  - [C# Example](#c-example)
  - [Template Method + Factory Method](#template-method--factory-method)
- [3. Static Factory Methods](#3-static-factory-methods)
- [Modern C#: Factories with Dependency Injection](#modern-c-factories-with-dependency-injection)
  - [Factory delegate (`Func<T>`)](#factory-delegate-funct)
  - [Factory class that resolves from DI](#factory-class-that-resolves-from-di)
  - [Keyed services (.NET 8+)](#keyed-services-net-8)
- [Simple Factory vs. Factory Method vs. Abstract Factory](#simple-factory-vs-factory-method-vs-abstract-factory)
- [Pitfalls](#pitfalls)
- [When to Use / When to Avoid](#when-to-use--when-to-avoid)
- [Key Takeaways](#key-takeaways)

---

## The Problem

Imagine a notification feature that can send Email, SMS or Push messages:

```csharp
public class OrderService
{
    public void NotifyCustomer(string channel, string message)
    {
        if (channel == "email")
        {
            var sender = new EmailSender("smtp.example.com");
            sender.Send(message);
        }
        else if (channel == "sms")
        {
            var sender = new SmsSender("api-key");
            sender.Send(message);
        }
        else if (channel == "push")
        {
            var sender = new PushSender();
            sender.Send(message);
        }
    }
}
```

What's wrong:

- `OrderService` knows **every concrete sender** and **how to build each one** (hosts, API keys…).
- The same `if/else` chain gets **copied** wherever a sender is needed.
- Adding "WhatsApp" means editing **every** copy → violates the **Open/Closed Principle**.
- Hard to unit test: real senders are created inside the method.

The fix: put creation in **one place** (a *factory*) and let callers work with an **interface**.

---

## The Factory Family

"Factory" is used loosely for several related ideas:

| Name | What it is | GoF pattern? |
| --- | --- | --- |
| **Simple Factory** | One class/method with a `switch` that returns the right object | No (an idiom) |
| **Factory Method** | A base class declares an abstract "create" method; **subclasses** decide what to create | ✅ Yes |
| **Static Factory Method** | A named static method instead of a constructor (`TimeSpan.FromSeconds(5)`) | No (an idiom) |
| **Abstract Factory** | An object that creates a **family** of related objects | ✅ Yes (separate note) |

All of them share one goal: **callers ask for an object; they don't `new` it themselves.**

The shared interface used in the examples below:

```csharp
public interface INotificationSender
{
    void Send(string to, string message);
}

public class EmailSender : INotificationSender
{
    private readonly string _smtpHost;
    public EmailSender(string smtpHost) => _smtpHost = smtpHost;

    public void Send(string to, string message) =>
        Console.WriteLine($"[Email via {_smtpHost}] To {to}: {message}");
}

public class SmsSender : INotificationSender
{
    private readonly string _apiKey;
    public SmsSender(string apiKey) => _apiKey = apiKey;

    public void Send(string to, string message) =>
        Console.WriteLine($"[SMS] To {to}: {message}");
}

public class PushSender : INotificationSender
{
    public void Send(string to, string message) =>
        Console.WriteLine($"[Push] To {to}: {message}");
}
```

---

## 1. Simple Factory

One place decides **which** class to create and **how**.

```csharp
public enum Channel { Email, Sms, Push }

public static class NotificationSenderFactory
{
    public static INotificationSender Create(Channel channel) => channel switch
    {
        Channel.Email => new EmailSender("smtp.example.com"),
        Channel.Sms   => new SmsSender("api-key"),
        Channel.Push  => new PushSender(),
        _ => throw new ArgumentOutOfRangeException(nameof(channel), channel, null)
    };
}
```

Usage:

```csharp
INotificationSender sender = NotificationSenderFactory.Create(Channel.Sms);
sender.Send("+95 9 123 456", "Your order has shipped");
```

- ✅ Creation logic is in **one** place; callers only see `INotificationSender`.
- ✅ Very easy to understand — the most common "factory" in real code.
- ❌ Adding a new channel still means **editing the `switch`** (not fully Open/Closed).
- ❌ A static factory is hard to replace in tests (make it an instance class behind an interface if that matters).

---

## 2. Factory Method (GoF)

> Define an interface for creating an object, but let **subclasses** decide which class to instantiate.

Instead of a `switch`, the "create" step becomes an **abstract method**. Each subclass overrides it. Adding a new type = adding a new subclass, **no existing code changes**.

### Structure

```
        Creator (abstract)                         Product (interface)
  ┌───────────────────────────┐              ┌──────────────────────┐
  │ + Notify(to, msg)         │ ── uses ──►  │ INotificationSender  │
  │ # CreateSender() abstract │              └──────────▲───────────┘
  └────────────▲──────────────┘                         │ implements
               │ inherits                     ┌─────────┴─────────┐
   ┌───────────┴────────────┐            EmailSender          SmsSender
EmailNotifier          SmsNotifier            ▲                   ▲
 CreateSender() ──────── creates ─────────────┘                   │
                       CreateSender() ─────── creates ────────────┘
```

| Role | In the example |
| --- | --- |
| **Product** | `INotificationSender` |
| **Concrete Product** | `EmailSender`, `SmsSender` |
| **Creator** | `Notifier` (abstract, declares `CreateSender()`) |
| **Concrete Creator** | `EmailNotifier`, `SmsNotifier` (override `CreateSender()`) |

### C# Example

```csharp
// Creator
public abstract class Notifier
{
    // The Factory Method
    protected abstract INotificationSender CreateSender();

    // Business logic that USES the product, without knowing its concrete type
    public void Notify(string to, string message)
    {
        var sender = CreateSender();
        sender.Send(to, message);
    }
}

// Concrete Creators
public class EmailNotifier : Notifier
{
    protected override INotificationSender CreateSender() => new EmailSender("smtp.example.com");
}

public class SmsNotifier : Notifier
{
    protected override INotificationSender CreateSender() => new SmsSender("api-key");
}
```

Usage:

```csharp
Notifier notifier = new EmailNotifier();
notifier.Notify("a@b.com", "Welcome!");

notifier = new SmsNotifier();
notifier.Notify("+95 9 123 456", "Welcome!");
```

Output:

```
[Email via smtp.example.com] To a@b.com: Welcome!
[SMS] To +95 9 123 456: Welcome!
```

Adding WhatsApp later — **only new code**, nothing edited:

```csharp
public class WhatsAppSender : INotificationSender
{
    public void Send(string to, string message) =>
        Console.WriteLine($"[WhatsApp] To {to}: {message}");
}

public class WhatsAppNotifier : Notifier
{
    protected override INotificationSender CreateSender() => new WhatsAppSender();
}
```

### Template Method + Factory Method

Factory Method usually lives inside a bigger algorithm in the base class. The base class controls the **steps**; the subclass only supplies the **object**:

```csharp
public abstract class ReportExporter
{
    protected abstract IReportWriter CreateWriter();   // Factory Method

    public void Export(Report report, string path)      // Template Method
    {
        var writer = CreateWriter();
        writer.WriteHeader(report.Title);
        foreach (var row in report.Rows)
            writer.WriteRow(row);
        writer.Save(path);
    }
}

public class PdfReportExporter : ReportExporter
{
    protected override IReportWriter CreateWriter() => new PdfWriter();
}

public class CsvReportExporter : ReportExporter
{
    protected override IReportWriter CreateWriter() => new CsvWriter();
}
```

Real .NET example: `DbProviderFactory.CreateConnection()` / `CreateCommand()` — each provider (`SqlClientFactory`, `NpgsqlFactory`…) returns its own connection type.

---

## 3. Static Factory Methods

A **named static method** that returns an instance, used instead of (or alongside) a constructor. Not the GoF pattern, but very common in .NET.

Examples you already use:

```csharp
TimeSpan.FromSeconds(30);
Task.FromResult(42);
Guid.NewGuid();
File.Create("log.txt");
```

Writing your own:

```csharp
public sealed class Money
{
    public decimal Amount { get; }
    public string Currency { get; }

    private Money(decimal amount, string currency)
    {
        Amount = amount;
        Currency = currency;
    }

    public static Money Usd(decimal amount) => new(amount, "USD");
    public static Money Mmk(decimal amount) => new(amount, "MMK");
    public static Money Zero(string currency) => new(0m, currency);

    public static Money Parse(string text)     // "12.50 USD"
    {
        var parts = text.Split(' ');
        return new Money(decimal.Parse(parts[0]), parts[1]);
    }
}

var price = Money.Usd(12.50m);
```

Why use them over constructors:

- **Descriptive names** — `Money.Usd(5)` reads better than `new Money(5, "USD")`.
- Can **return a cached** instance (`Money.Zero`) or a **subtype**.
- Can **validate** or do work (`Parse`) before creating.
- Constructors can't be async; a static `CreateAsync()` can:

```csharp
public class DataStore
{
    private DataStore() { }

    public static async Task<DataStore> CreateAsync(string path)
    {
        var store = new DataStore();
        await store.LoadAsync(path);
        return store;
    }

    private Task LoadAsync(string path) => Task.CompletedTask;
}
```

---

## Modern C#: Factories with Dependency Injection

In a DI-based app, the **container is already a big factory**. You still need your own factory when the right object depends on **runtime data** (user's choice, a request value, a config flag).

### Factory delegate (`Func<T>`)

```csharp
builder.Services.AddTransient<EmailSender>(_ => new EmailSender("smtp.example.com"));
builder.Services.AddTransient<SmsSender>(_ => new SmsSender("api-key"));
builder.Services.AddTransient<PushSender>();

builder.Services.AddSingleton<Func<Channel, INotificationSender>>(sp => channel => channel switch
{
    Channel.Email => sp.GetRequiredService<EmailSender>(),
    Channel.Sms   => sp.GetRequiredService<SmsSender>(),
    Channel.Push  => sp.GetRequiredService<PushSender>(),
    _ => throw new ArgumentOutOfRangeException(nameof(channel))
});

public class OrderService(Func<Channel, INotificationSender> senderFactory)
{
    public void Ship(Order order) =>
        senderFactory(order.PreferredChannel).Send(order.Contact, "Shipped!");
}
```

### Factory class that resolves from DI

More explicit and easier to mock:

```csharp
public interface INotificationSenderFactory
{
    INotificationSender Create(Channel channel);
}

public class NotificationSenderFactory(IServiceProvider sp) : INotificationSenderFactory
{
    public INotificationSender Create(Channel channel) => channel switch
    {
        Channel.Email => sp.GetRequiredService<EmailSender>(),
        Channel.Sms   => sp.GetRequiredService<SmsSender>(),
        Channel.Push  => sp.GetRequiredService<PushSender>(),
        _ => throw new ArgumentOutOfRangeException(nameof(channel))
    };
}

builder.Services.AddSingleton<INotificationSenderFactory, NotificationSenderFactory>();

public class OrderService(INotificationSenderFactory factory)
{
    public void Ship(Order order) =>
        factory.Create(order.PreferredChannel).Send(order.Contact, "Shipped!");
}
```

Note: using `IServiceProvider` **inside a factory** is the accepted exception to the "no service locator" rule — the factory's whole job is creation.

⚠️ If the factory is a **singleton** and the senders are **scoped**, resolve them from a scope (`IServiceScopeFactory`), otherwise you get a captive dependency.

### Keyed services (.NET 8+)

The container can do the lookup for you — no `switch` at all:

```csharp
builder.Services.AddKeyedTransient<INotificationSender>(Channel.Email, (_, _) => new EmailSender("smtp.example.com"));
builder.Services.AddKeyedTransient<INotificationSender>(Channel.Sms,   (_, _) => new SmsSender("api-key"));
builder.Services.AddKeyedTransient<INotificationSender, PushSender>(Channel.Push);

public class OrderService(IServiceProvider sp)
{
    public void Ship(Order order)
    {
        var sender = sp.GetRequiredKeyedService<INotificationSender>(order.PreferredChannel);
        sender.Send(order.Contact, "Shipped!");
    }
}
```

Real .NET example: **`IHttpClientFactory`** — `factory.CreateClient("github")` returns a correctly configured, pooled `HttpClient`.

---

## Simple Factory vs. Factory Method vs. Abstract Factory

| | Simple Factory | Factory Method | Abstract Factory |
| --- | --- | --- | --- |
| Decides via | `switch` / `if` | Subclass override | Choosing a factory object |
| Creates | One product | One product | A **family** of related products |
| Add a new type | Edit the `switch` | Add a new subclass | Add a new factory class |
| Open/Closed | ❌ | ✅ | ✅ |
| Mechanism | Usually a static method | **Inheritance** | **Composition** (pass the factory in) |
| Complexity | Low | Medium | Higher |

Abstract Factory is covered in its own note.

---

## Pitfalls

1. **Over-engineering** — a factory around a class that has one implementation and a trivial constructor adds nothing. Just use `new` (or DI).
2. **Class explosion** — Factory Method needs one creator subclass per product; with many products this grows quickly.
3. **Giant `switch`** — a Simple Factory that knows 30 types becomes a maintenance hotspot. Consider a dictionary of registrations or keyed DI services.
4. **Hidden service locator** — injecting `IServiceProvider` everywhere and calling it a "factory". Keep that only inside dedicated factory classes.
5. **Lifetime mistakes** — a singleton factory handing out scoped services (see DI warning above).
6. **Returning concrete types** — `EmailSender Create()` defeats the purpose; return the **interface**.

---

## When to Use / When to Avoid

**Use when:**

- The concrete type is only known at **runtime** (user setting, file extension, request value).
- Creating the object is **complex** (config, validation, several steps).
- You want callers to depend on an **interface**, not concrete classes.
- You expect to **add new types** later without touching existing code (Factory Method).
- You need **async** creation or **named** constructors (static factory methods).

**Avoid when:**

- There's only one implementation and creation is trivial.
- The DI container can already create it (just inject it).

---

## Key Takeaways

- A factory **separates creating** an object from **using** it.
- **Simple Factory**: one `switch` in one place — simplest, most common.
- **Factory Method**: abstract `Create…()` in a base class, subclasses decide — new types without editing existing code.
- **Static factory methods**: named, cache-able, validatable, can be async (`CreateAsync`).
- In modern .NET, combine factories with **DI**: `Func<T>` delegates, factory interfaces, keyed services, `IHttpClientFactory`.
- Always **return the abstraction** (interface), not the concrete class.
