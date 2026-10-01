# Facade Pattern

> **Category:** Structural
> **Intent:** Provide **one simple, unified interface** to a set of interfaces in a **complex subsystem**, making the subsystem easier to use.

## Contents

- [The Problem](#the-problem)
- [Real-World Analogy](#real-world-analogy)
- [Structure](#structure)
- [C# Example: Placing an Order](#c-example-placing-an-order)
  - [The Subsystem](#the-subsystem)
  - [Without a Facade](#without-a-facade)
  - [With a Facade](#with-a-facade)
- [Example: Simplifying a Library](#example-simplifying-a-library)
- [Facade with Dependency Injection](#facade-with-dependency-injection)
- [Facades You Already Use in .NET](#facades-you-already-use-in-net)
- [Facade vs. Similar Patterns](#facade-vs-similar-patterns)
- [Pitfalls](#pitfalls)
- [When to Use / When to Avoid](#when-to-use--when-to-avoid)
- [Key Takeaways](#key-takeaways)

---

## The Problem

Placing an order in an online shop involves many parts:

- Check **inventory**
- Charge **payment**
- Reserve stock
- Create a **shipment**
- Send a **confirmation email**
- Roll back if something fails

If every caller (web controller, mobile API, admin tool, background job) does all these steps itself:

- The same **multi-step workflow is duplicated** in many places.
- Callers are **coupled** to 5+ classes and must know the **correct order** of calls.
- A change to the workflow (e.g. "add fraud check before payment") must be made **everywhere**.

The fix: put one class **in front of** the subsystem that exposes a simple method like `PlaceOrder(...)`.

---

## Real-World Analogy

A **restaurant waiter**. You don't walk into the kitchen and talk to the chef, the dishwasher and the cashier separately. You tell the waiter "I'd like the curry", and they coordinate everything behind the scenes. The kitchen staff still exist and can still be reached directly if needed — the waiter just makes the common case easy.

---

## Structure

```
   Client A    Client B    Client C
        \         |         /
         \        |        /
          ▼       ▼       ▼
        ┌─────────────────────┐
        │   OrderFacade       │   ← simple API: PlaceOrder(...)
        └─────────┬───────────┘
   ┌──────────┬───┴──────┬────────────┬───────────┐
   ▼          ▼          ▼            ▼           ▼
Inventory  Payment   Shipping   Notification   Fraud...
            (complex subsystem — many classes)
```

| Role | Responsibility |
| --- | --- |
| **Facade** | Knows which subsystem classes to call and **in what order**; exposes a simple API |
| **Subsystem classes** | Do the real work; **don't know** the facade exists |
| **Client** | Talks to the facade instead of the subsystem |

Important: the facade **doesn't hide** the subsystem completely — advanced clients can still use the subsystem classes directly.

---

## C# Example: Placing an Order

### The Subsystem

```csharp
public class InventoryService
{
    public bool IsInStock(string sku, int qty) { Console.WriteLine($"  Inventory: checking {sku} x{qty}"); return true; }
    public void Reserve(string sku, int qty)   => Console.WriteLine($"  Inventory: reserved {sku} x{qty}");
    public void Release(string sku, int qty)   => Console.WriteLine($"  Inventory: released {sku} x{qty}");
}

public class PaymentService
{
    public string Charge(string customerId, decimal amount)
    {
        Console.WriteLine($"  Payment: charged {amount:C} to {customerId}");
        return Guid.NewGuid().ToString("N")[..8];
    }

    public void Refund(string transactionId) => Console.WriteLine($"  Payment: refunded {transactionId}");
}

public class ShippingService
{
    public string CreateShipment(string sku, int qty, string address)
    {
        Console.WriteLine($"  Shipping: shipment created to {address}");
        return "TRACK-12345";
    }
}

public class NotificationService
{
    public void SendOrderConfirmation(string email, string trackingNo) =>
        Console.WriteLine($"  Email: confirmation sent to {email} (tracking {trackingNo})");
}
```

### Without a Facade

Every caller repeats this:

```csharp
public class CheckoutController
{
    // ...5 injected services...

    public IActionResult Checkout(OrderRequest req)
    {
        if (!_inventory.IsInStock(req.Sku, req.Quantity))
            return BadRequest("Out of stock");

        _inventory.Reserve(req.Sku, req.Quantity);
        string txId;
        try
        {
            txId = _payment.Charge(req.CustomerId, req.Total);
        }
        catch
        {
            _inventory.Release(req.Sku, req.Quantity);
            throw;
        }

        var tracking = _shipping.CreateShipment(req.Sku, req.Quantity, req.Address);
        _notification.SendOrderConfirmation(req.Email, tracking);
        return Ok(tracking);
    }
}
```

…and the mobile API, admin tool and background job each have their own copy.

### With a Facade

```csharp
public record OrderRequest(string CustomerId, string Email, string Sku, int Quantity, decimal Total, string Address);
public record OrderResult(bool Success, string? TrackingNumber, string? Error);

public class OrderFacade
{
    private readonly InventoryService _inventory;
    private readonly PaymentService _payment;
    private readonly ShippingService _shipping;
    private readonly NotificationService _notification;

    public OrderFacade(
        InventoryService inventory,
        PaymentService payment,
        ShippingService shipping,
        NotificationService notification)
    {
        _inventory = inventory;
        _payment = payment;
        _shipping = shipping;
        _notification = notification;
    }

    public OrderResult PlaceOrder(OrderRequest req)
    {
        if (!_inventory.IsInStock(req.Sku, req.Quantity))
            return new OrderResult(false, null, "Out of stock");

        _inventory.Reserve(req.Sku, req.Quantity);

        string transactionId;
        try
        {
            transactionId = _payment.Charge(req.CustomerId, req.Total);
        }
        catch (Exception ex)
        {
            _inventory.Release(req.Sku, req.Quantity);       // compensate
            return new OrderResult(false, null, $"Payment failed: {ex.Message}");
        }

        try
        {
            var tracking = _shipping.CreateShipment(req.Sku, req.Quantity, req.Address);
            _notification.SendOrderConfirmation(req.Email, tracking);
            return new OrderResult(true, tracking, null);
        }
        catch (Exception ex)
        {
            _payment.Refund(transactionId);                    // compensate
            _inventory.Release(req.Sku, req.Quantity);
            return new OrderResult(false, null, $"Shipping failed: {ex.Message}");
        }
    }
}
```

Client code becomes one line:

```csharp
var result = orderFacade.PlaceOrder(
    new OrderRequest("C-001", "a@b.com", "SKU-42", 2, 59.98m, "Yangon"));

Console.WriteLine(result.Success ? $"Done: {result.TrackingNumber}" : result.Error);
```

Output:

```
  Inventory: checking SKU-42 x2
  Inventory: reserved SKU-42 x2
  Payment: charged $59.98 to C-001
  Shipping: shipment created to Yangon
  Email: confirmation sent to a@b.com (tracking TRACK-12345)
Done: TRACK-12345
```

What the facade gave us:

- **One place** for the workflow, ordering and rollback logic.
- Callers depend on **one class** instead of four.
- Changing the workflow (adding a fraud check) = editing **only the facade**.

---

## Example: Simplifying a Library

Facades are also great for taming a **verbose third-party API** — exposing only what your app needs.

```csharp
// Raw usage of a PDF library is many steps: document, page, fonts, layout, streams...
public interface IPdfReportGenerator
{
    byte[] CreateInvoice(Invoice invoice);
}

public class PdfReportGenerator : IPdfReportGenerator
{
    public byte[] CreateInvoice(Invoice invoice)
    {
        // All the library-specific detail lives here, in one place:
        // var doc = new PdfDocument();
        // var page = doc.AddPage();
        // var gfx = XGraphics.FromPdfPage(page);
        // gfx.DrawString(...); ... tables, totals, fonts ...
        // using var ms = new MemoryStream(); doc.Save(ms);
        // return ms.ToArray();
        throw new NotImplementedException();
    }
}
```

Rest of the app:

```csharp
byte[] pdf = _pdf.CreateInvoice(invoice);
```

Bonus: putting the facade behind an **interface** makes it easy to mock and to swap the PDF library later.

---

## Facade with Dependency Injection

```csharp
builder.Services.AddScoped<InventoryService>();
builder.Services.AddScoped<PaymentService>();
builder.Services.AddScoped<ShippingService>();
builder.Services.AddScoped<NotificationService>();
builder.Services.AddScoped<IOrderFacade, OrderFacade>();   // expose the facade via an interface

app.MapPost("/orders", (OrderRequest req, IOrderFacade orders) =>
{
    var result = orders.PlaceOrder(req);
    return result.Success ? Results.Ok(result) : Results.BadRequest(result.Error);
});
```

In layered / Clean Architecture apps, **application services** (a.k.a. use-case handlers) are effectively facades: the controller calls one method, the service coordinates repositories, domain objects and infrastructure.

---

## Facades You Already Use in .NET

| .NET API | Hides |
| --- | --- |
| `File.ReadAllText(path)` | Opening a `FileStream`, creating a `StreamReader`, reading, disposing |
| `WebApplication.CreateBuilder(args)` | Configuration sources, logging, DI container, Kestrel, environment setup |
| `HttpClient.GetStringAsync(url)` | Building request, sending, checking response, reading content |
| `DbContext` (EF Core) | Connections, change tracking, SQL generation, transactions |
| `JsonSerializer.Serialize(obj)` | Writers, buffers, converters, options |

Compare:

```csharp
// With the facade
string text = File.ReadAllText("notes.txt");

// What it hides
using var stream = new FileStream("notes.txt", FileMode.Open, FileAccess.Read);
using var reader = new StreamReader(stream, Encoding.UTF8);
string text2 = reader.ReadToEnd();
```

---

## Facade vs. Similar Patterns

| Pattern | Wraps | Interface | Purpose |
| --- | --- | --- | --- |
| **Facade** | **Many** objects | **New, simpler** | Make a subsystem easy to use |
| **Adapter** | Usually **one** object | **Converts** to an existing expected one | Make incompatible things fit |
| **Decorator** | One object | **Same** | Add behavior |
| **Mediator** | Many objects | — | Objects talk **to each other through** it (two-way); facade is one-way (client → subsystem) |

Facade **defines a new** interface; Adapter **reuses an existing** one.

---

## Pitfalls

1. **God object** — the facade grows to cover *everything* (`OrderFacade` with 40 methods). Split by use case: `OrderPlacementFacade`, `OrderReturnsFacade`…
2. **Business logic creeping in** — a facade should **coordinate**, not hold domain rules. Rules like "discount for VIP" belong in the domain/subsystem.
3. **Hiding too much** — if advanced callers can't reach needed features, they'll start bypassing the facade inconsistently. Keep the subsystem accessible.
4. **Leaking subsystem types** — returning subsystem-specific objects from facade methods re-couples clients to the subsystem. Return your own DTOs/records.
5. **Pass-through facade** — a facade whose methods just forward one-to-one to a single class adds a layer without simplifying anything.

---

## When to Use / When to Avoid

**Use when:**

- A subsystem is **complex** and most clients only need a **common, simple** path.
- The same **multi-step workflow** is repeated in several places.
- You want to **decouple** clients from many subsystem classes (fewer dependencies, easier refactoring).
- Layering: a facade as the **entry point** to each layer / module.
- Wrapping a **verbose third-party library**.

**Avoid when:**

- The subsystem is already simple.
- The "facade" would just forward calls one-to-one.

---

## Key Takeaways

- Facade = **one simple class in front of a complex subsystem**.
- It **coordinates** subsystem calls (order, error handling, rollback) — callers make **one call**.
- Subsystem classes **don't know** about the facade and **remain usable** directly.
- Keep facades **focused per use case** to avoid a god object.
- Facade = **new simpler interface over many**; Adapter = **convert one to an existing interface**.
