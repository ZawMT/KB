# Observer Pattern

> **Category:** Behavioral
> **Intent:** Define a **one-to-many** dependency so that when one object (the **subject**) changes state, all its dependents (**observers**) are **notified automatically**.

Also known as: **Publish/Subscribe** (in-process), **Event-Subscriber**, **Listener**.

## Contents

- [The Problem](#the-problem)
- [Real-World Analogy](#real-world-analogy)
- [Structure](#structure)
- [1. Classic Implementation (interfaces)](#1-classic-implementation-interfaces)
- [2. C# Events (the idiomatic way)](#2-c-events-the-idiomatic-way)
  - [Event Basics](#event-basics)
  - [Standard Event Pattern: `EventHandler<T>`](#standard-event-pattern-eventhandlert)
  - [Unsubscribing and Memory Leaks](#unsubscribing-and-memory-leaks)
- [3. `IObservable<T>` / `IObserver<T>`](#3-iobservablet--iobservert)
- [4. `INotifyPropertyChanged` (UI data binding)](#4-inotifypropertychanged-ui-data-binding)
- [5. Observer with Dependency Injection (domain events)](#5-observer-with-dependency-injection-domain-events)
- [Push vs. Pull](#push-vs-pull)
- [Observer vs. Pub/Sub with a Message Broker](#observer-vs-pubsub-with-a-message-broker)
- [Pitfalls](#pitfalls)
- [When to Use / When to Avoid](#when-to-use--when-to-avoid)
- [Key Takeaways](#key-takeaways)

---

## The Problem

A stock price changes. Several parts of the app care:

- The **dashboard** must refresh.
- An **alert service** must notify users if the price drops below a threshold.
- An **audit logger** must record it.

Naive approach — the stock calls each one directly:

```csharp
public class Stock
{
    private readonly Dashboard _dashboard;
    private readonly AlertService _alerts;
    private readonly AuditLogger _audit;

    public void UpdatePrice(decimal price)
    {
        Price = price;
        _dashboard.Refresh(this);
        _alerts.Check(this);
        _audit.Log(this);
        // add a new listener → edit this class again
    }
}
```

Problems:

- `Stock` is **coupled** to every class that cares about it.
- Adding/removing a listener means **editing `Stock`** (violates Open/Closed).
- Listeners can't subscribe/unsubscribe **at runtime**.

Alternative: listeners **poll** (`while (true) check stock.Price`) — wasteful and laggy.

The fix: the stock keeps a **list of subscribers** and notifies whoever is on the list. It doesn't know who they are, only that they implement a common interface.

---

## Real-World Analogy

**A YouTube channel / newsletter subscription.** You subscribe once; whenever a new video is published, you're notified. The channel doesn't know anything about you except that you're on the list. You can unsubscribe at any time, and the channel doesn't change when subscribers come and go.

---

## Structure

```
        ISubject                                  IObserver
  + Attach(observer)                       + Update(subject / data)
  + Detach(observer)                                 ▲
  + Notify()                                         │ implements
        ▲                              ┌─────────────┼─────────────┐
        │ implements               Dashboard   AlertService   AuditLogger
   ┌────┴─────┐
   │  Stock   │ ── holds List<IObserver> ──► calls Update() on each
   └──────────┘
```

| Role | In the example |
| --- | --- |
| **Subject** (Publisher, Observable) | `Stock` — holds state + list of observers |
| **Observer** (Subscriber, Listener) | `IStockObserver` |
| **Concrete Observers** | `Dashboard`, `PriceAlert`, `AuditLogger` |

Flow:

1. Observers **subscribe** (attach) to the subject.
2. Subject's state **changes**.
3. Subject **loops over its observers** and calls their update method.
4. Observers can **unsubscribe** (detach) at any time.

---

## 1. Classic Implementation (interfaces)

Good for understanding the mechanics. In real C# you'd usually use events (next section).

```csharp
public interface IStockObserver
{
    void OnPriceChanged(Stock stock, decimal oldPrice);
}

public class Stock
{
    private readonly List<IStockObserver> _observers = new();

    public string Symbol { get; }
    public decimal Price { get; private set; }

    public Stock(string symbol, decimal price)
    {
        Symbol = symbol;
        Price = price;
    }

    public void Attach(IStockObserver observer) => _observers.Add(observer);
    public void Detach(IStockObserver observer) => _observers.Remove(observer);

    public void UpdatePrice(decimal newPrice)
    {
        if (newPrice == Price) return;          // notify only on real changes

        var old = Price;
        Price = newPrice;
        Notify(old);
    }

    private void Notify(decimal oldPrice)
    {
        // Copy the list: an observer might Detach itself during notification
        foreach (var observer in _observers.ToList())
            observer.OnPriceChanged(this, oldPrice);
    }
}
```

Observers:

```csharp
public class Dashboard : IStockObserver
{
    public void OnPriceChanged(Stock stock, decimal oldPrice) =>
        Console.WriteLine($"[Dashboard] {stock.Symbol}: {oldPrice} → {stock.Price}");
}

public class PriceAlert : IStockObserver
{
    private readonly decimal _threshold;
    public PriceAlert(decimal threshold) => _threshold = threshold;

    public void OnPriceChanged(Stock stock, decimal oldPrice)
    {
        if (stock.Price < _threshold)
            Console.WriteLine($"[Alert] {stock.Symbol} dropped below {_threshold}!");
    }
}

public class AuditLogger : IStockObserver
{
    public void OnPriceChanged(Stock stock, decimal oldPrice) =>
        Console.WriteLine($"[Audit] {DateTime.UtcNow:O} {stock.Symbol} {oldPrice}->{stock.Price}");
}
```

Usage:

```csharp
var msft = new Stock("MSFT", 420m);

var dashboard = new Dashboard();
msft.Attach(dashboard);
msft.Attach(new PriceAlert(400m));
msft.Attach(new AuditLogger());

msft.UpdatePrice(415m);
Console.WriteLine();

msft.Detach(dashboard);
msft.UpdatePrice(395m);
```

Output:

```
[Dashboard] MSFT: 420 → 415
[Audit] 2026-10-01T10:00:00.0000000Z MSFT 420->415

[Alert] MSFT dropped below 400!
[Audit] 2026-10-01T10:00:01.0000000Z MSFT 415->395
```

`Stock` has **no idea** what `Dashboard`, `PriceAlert` or `AuditLogger` are.

---

## 2. C# Events (the idiomatic way)

C# has the Observer pattern **built into the language**: `delegate` + `event`. The compiler manages the subscriber list for you.

### Event Basics

```csharp
public class Stock
{
    public string Symbol { get; }
    public decimal Price { get; private set; }

    public event Action<Stock, decimal>? PriceChanged;   // the subscriber list

    public Stock(string symbol, decimal price) { Symbol = symbol; Price = price; }

    public void UpdatePrice(decimal newPrice)
    {
        if (newPrice == Price) return;
        var old = Price;
        Price = newPrice;
        PriceChanged?.Invoke(this, old);                  // notify all subscribers
    }
}
```

```csharp
var msft = new Stock("MSFT", 420m);

msft.PriceChanged += (s, old) => Console.WriteLine($"[Dashboard] {s.Symbol}: {old} → {s.Price}");
msft.PriceChanged += (s, old) => { if (s.Price < 400m) Console.WriteLine("[Alert] below 400!"); };

msft.UpdatePrice(395m);
```

- `+=` = **Attach**, `-=` = **Detach**, `Invoke` = **Notify**.
- The `event` keyword means outside code can **only** `+=` / `-=`. It **can't** invoke the event or wipe the list (`= null`) — only the owner class can.
- `?.Invoke` handles "no subscribers" (the delegate is `null`).

### Standard Event Pattern: `EventHandler<T>`

.NET convention: `(object? sender, TEventArgs e)`.

```csharp
public class PriceChangedEventArgs : EventArgs
{
    public PriceChangedEventArgs(decimal oldPrice, decimal newPrice)
    {
        OldPrice = oldPrice;
        NewPrice = newPrice;
    }

    public decimal OldPrice { get; }
    public decimal NewPrice { get; }
}

public class Stock
{
    public string Symbol { get; }
    public decimal Price { get; private set; }

    public event EventHandler<PriceChangedEventArgs>? PriceChanged;

    public Stock(string symbol, decimal price) { Symbol = symbol; Price = price; }

    public void UpdatePrice(decimal newPrice)
    {
        if (newPrice == Price) return;
        var old = Price;
        Price = newPrice;
        OnPriceChanged(new PriceChangedEventArgs(old, newPrice));
    }

    // Convention: protected virtual On<EventName> so subclasses can hook in
    protected virtual void OnPriceChanged(PriceChangedEventArgs e) =>
        PriceChanged?.Invoke(this, e);
}
```

Subscribing with a method instead of a lambda (so it can be removed later):

```csharp
public class Dashboard
{
    public void Watch(Stock stock)   => stock.PriceChanged += HandlePriceChanged;
    public void Unwatch(Stock stock) => stock.PriceChanged -= HandlePriceChanged;

    private void HandlePriceChanged(object? sender, PriceChangedEventArgs e)
    {
        var stock = (Stock)sender!;
        Console.WriteLine($"[Dashboard] {stock.Symbol}: {e.OldPrice} → {e.NewPrice}");
    }
}
```

### Unsubscribing and Memory Leaks

The **subject holds a reference to every subscriber** (through the delegate). If a short-lived object subscribes to a long-lived one and never unsubscribes, the short-lived object **can never be garbage-collected**.

```csharp
// Long-lived (e.g. singleton) subject
public static class Market
{
    public static event EventHandler<PriceChangedEventArgs>? AnyPriceChanged;
}

// Short-lived subscriber (e.g. a window/page/view model)
public class StockPage : IDisposable
{
    public StockPage()  => Market.AnyPriceChanged += OnPrice;    // subscribe
    public void Dispose() => Market.AnyPriceChanged -= OnPrice;  // ✅ always unsubscribe

    private void OnPrice(object? s, PriceChangedEventArgs e) { /* update UI */ }
}
```

Rules of thumb:

- If a subscriber lives **shorter** than the subject → **unsubscribe** (usually in `Dispose`).
- Lambdas can't be removed with `-=` unless you **store them in a variable** first.
- This is the **#1 cause of memory leaks** in event-heavy .NET apps (WPF, WinForms, MAUI).

---

## 3. `IObservable<T>` / `IObserver<T>`

.NET's built-in interfaces for **streams of values over time** (`System` namespace). The basis of **Reactive Extensions (Rx.NET)**.

```csharp
public interface IObserver<in T>
{
    void OnNext(T value);           // a new value
    void OnError(Exception error);  // the stream failed
    void OnCompleted();             // no more values
}

public interface IObservable<out T>
{
    IDisposable Subscribe(IObserver<T> observer);   // returns a "unsubscribe" handle
}
```

A minimal implementation:

```csharp
public record PriceTick(string Symbol, decimal Price);

public class PriceFeed : IObservable<PriceTick>
{
    private readonly List<IObserver<PriceTick>> _observers = new();

    public IDisposable Subscribe(IObserver<PriceTick> observer)
    {
        _observers.Add(observer);
        return new Unsubscriber(() => _observers.Remove(observer));
    }

    public void Publish(PriceTick tick)
    {
        foreach (var o in _observers.ToList()) o.OnNext(tick);
    }

    public void End()
    {
        foreach (var o in _observers.ToList()) o.OnCompleted();
        _observers.Clear();
    }

    private sealed class Unsubscriber(Action unsubscribe) : IDisposable
    {
        public void Dispose() => unsubscribe();
    }
}

public class ConsolePriceObserver : IObserver<PriceTick>
{
    public void OnNext(PriceTick t)        => Console.WriteLine($"{t.Symbol} @ {t.Price}");
    public void OnError(Exception ex)      => Console.WriteLine($"Error: {ex.Message}");
    public void OnCompleted()              => Console.WriteLine("Feed closed");
}
```

```csharp
var feed = new PriceFeed();

using (feed.Subscribe(new ConsolePriceObserver()))   // Dispose() = unsubscribe
{
    feed.Publish(new PriceTick("MSFT", 420m));
    feed.Publish(new PriceTick("MSFT", 418m));
}

feed.Publish(new PriceTick("MSFT", 410m));   // nobody listening any more
```

With **Rx.NET** (`System.Reactive` package), you get LINQ over event streams:

```csharp
priceTicks
    .Where(t => t.Symbol == "MSFT")
    .Throttle(TimeSpan.FromSeconds(1))
    .Subscribe(t => Console.WriteLine($"MSFT: {t.Price}"));
```

| | C# `event` | `IObservable<T>` |
| --- | --- | --- |
| Unsubscribe | `-=` with the same delegate | `Dispose()` the returned handle |
| Completion / error signals | ❌ | ✅ `OnCompleted`, `OnError` |
| Composition (filter, merge, throttle) | ❌ | ✅ with Rx.NET |
| Best for | Simple notifications, UI events | Streams of data over time |

---

## 4. `INotifyPropertyChanged` (UI data binding)

The Observer pattern that powers **WPF, MAUI, WinUI and Blazor** data binding. The UI **observes** the view model; when a property changes, the bound control updates.

```csharp
using System.ComponentModel;
using System.Runtime.CompilerServices;

public class StockViewModel : INotifyPropertyChanged
{
    private decimal _price;

    public decimal Price
    {
        get => _price;
        set
        {
            if (_price == value) return;
            _price = value;
            OnPropertyChanged();                         // notify: "Price" changed
            OnPropertyChanged(nameof(IsBelowTarget));    // dependent property too
        }
    }

    public bool IsBelowTarget => Price < 400m;

    public event PropertyChangedEventHandler? PropertyChanged;

    protected void OnPropertyChanged([CallerMemberName] string? name = null) =>
        PropertyChanged?.Invoke(this, new PropertyChangedEventArgs(name));
}
```

`ObservableCollection<T>` does the same for lists (`INotifyCollectionChanged`): add/remove an item → the bound list control updates.

---

## 5. Observer with Dependency Injection (domain events)

In backend apps, a common form is: **"when X happens, run all handlers for X"**, where handlers are discovered from DI. The publisher doesn't know the handlers.

```csharp
// The event
public record OrderPlaced(int OrderId, string CustomerEmail, decimal Total);

// Observer interface
public interface IEventHandler<in TEvent>
{
    Task HandleAsync(TEvent e, CancellationToken ct = default);
}

// Subject / publisher
public interface IEventPublisher
{
    Task PublishAsync<TEvent>(TEvent e, CancellationToken ct = default);
}

public class EventPublisher(IServiceProvider sp) : IEventPublisher
{
    public async Task PublishAsync<TEvent>(TEvent e, CancellationToken ct = default)
    {
        foreach (var handler in sp.GetServices<IEventHandler<TEvent>>())
            await handler.HandleAsync(e, ct);
    }
}
```

Handlers (observers):

```csharp
public class SendConfirmationEmail : IEventHandler<OrderPlaced>
{
    public Task HandleAsync(OrderPlaced e, CancellationToken ct)
    {
        Console.WriteLine($"Email to {e.CustomerEmail}: order {e.OrderId} confirmed");
        return Task.CompletedTask;
    }
}

public class AwardLoyaltyPoints : IEventHandler<OrderPlaced>
{
    public Task HandleAsync(OrderPlaced e, CancellationToken ct)
    {
        Console.WriteLine($"Awarded {(int)e.Total} points for order {e.OrderId}");
        return Task.CompletedTask;
    }
}
```

Registration and use:

```csharp
builder.Services.AddScoped<IEventPublisher, EventPublisher>();
builder.Services.AddScoped<IEventHandler<OrderPlaced>, SendConfirmationEmail>();
builder.Services.AddScoped<IEventHandler<OrderPlaced>, AwardLoyaltyPoints>();

public class OrderService(IEventPublisher events)
{
    public async Task PlaceOrderAsync(Order order)
    {
        // ... save order ...
        await events.PublishAsync(new OrderPlaced(order.Id, order.Email, order.Total));
    }
}
```

Adding a new reaction (e.g. "update analytics") = **add a new handler class + one registration**. `OrderService` never changes.

Libraries like **MediatR** (`INotification` / `INotificationHandler<T>`) provide exactly this.

---

## Push vs. Pull

| Model | How | Pros | Cons |
| --- | --- | --- | --- |
| **Push** | Subject sends the data: `OnPriceChanged(oldPrice, newPrice)` | Observers get exactly what they need | Subject must guess what observers need |
| **Pull** | Subject sends itself: `Update(stock)`, observer reads what it wants | Flexible | Observers depend on the subject's API |

Most C# events are a **mix**: `sender` (pull) + `EventArgs` (push).

---

## Observer vs. Pub/Sub with a Message Broker

| | Observer | Pub/Sub (broker) |
| --- | --- | --- |
| Where | **In-process**, same app | **Across** processes / services |
| Coupling | Subject holds direct references to observers | Publisher and subscriber **don't know each other**; broker in the middle |
| Delivery | Synchronous by default | Usually **asynchronous** |
| Durability | Lost if the app crashes | Broker can persist / retry |
| Examples | C# events, `IObservable<T>`, MediatR notifications | RabbitMQ, Kafka, Azure Service Bus, Redis Pub/Sub |

Same idea, different scale.

---

## Pitfalls

1. **Memory leaks** — forgotten subscriptions keep subscribers alive. Unsubscribe in `Dispose`.
2. **One failing observer breaks the rest** — an exception in one handler stops the `foreach` (or the multicast delegate). If others must still run, call each handler in its own `try/catch`:

   ```csharp
   foreach (EventHandler<PriceChangedEventArgs> h in PriceChanged?.GetInvocationList() ?? [])
   {
       try { h(this, e); }
       catch (Exception ex) { Console.WriteLine($"Handler failed: {ex.Message}"); }
   }
   ```

3. **Unpredictable order** — don't rely on observers running in a particular order.
4. **Slow observers block the subject** — notifications are synchronous; a slow handler delays `UpdatePrice`. Offload heavy work (queue, background service).
5. **Cascading updates / infinite loops** — observer A changes the subject, which notifies A again… Guard with "only notify on real change" (`if (newPrice == Price) return;`).
6. **Thread safety** — subscribing/notifying from multiple threads can race. Copy the delegate to a local before invoking (`?.Invoke` already does this); lock around list changes in a hand-rolled subject.
7. **`async void` handlers** — event handlers that are `async void` swallow exceptions into the void and can't be awaited. Prefer `Task`-returning handler interfaces (section 5) for async work.
8. **Hard to follow** — "who reacts to this?" isn't visible in the code of the subject. Name events clearly and keep handlers discoverable.

---

## When to Use / When to Avoid

**Use when:**

- A change in one object must trigger reactions in **an unknown or changing set** of other objects.
- You want the source to stay **decoupled** from the things that react (Open/Closed).
- UI must reflect model changes (**data binding**).
- Handling **streams of events** over time (sensors, prices, user input) → `IObservable<T>`/Rx.

**Avoid when:**

- There's exactly **one** fixed dependent — a direct method call is clearer.
- The reaction must happen in a **guaranteed order** or **transactionally** with the change — explicit calls are easier to reason about.
- Notifications cross **process boundaries** → use a message broker instead.

---

## Key Takeaways

- Observer = subject keeps a **list of subscribers** and **notifies** them on change; it doesn't know who they are.
- In C#, the idiomatic Observer is **`event` + `EventHandler<T>`** (`+=` subscribe, `-=` unsubscribe).
- Use **`IObservable<T>`** (and Rx.NET) for **streams** with completion/error and LINQ-style composition.
- **`INotifyPropertyChanged`** is Observer for UI binding.
- In backend apps, **domain events + DI handlers** (or MediatR notifications) apply the same idea.
- Biggest gotchas: **forgotten unsubscribes (leaks)**, **exceptions in one handler**, **slow synchronous handlers**.
