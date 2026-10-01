## C#
### Dependency Injection (DI)

#### [Back to C# contents](../_Contents.md)

*What is dependency injection, and how does it work in .NET?*

**Dependency injection** means a class **receives** the objects it needs (its *dependencies*) from outside, instead of **creating** them itself with `new`.

It's one way to apply the **Dependency Inversion Principle** (the "D" in SOLID): depend on **abstractions** (interfaces), not concrete classes.

### The problem: hard-coded dependencies

```csharp
public class OrderService
{
    private readonly SqlOrderRepository _repo = new SqlOrderRepository(); // hard-coded
    private readonly SmtpEmailSender _email = new SmtpEmailSender();      // hard-coded

    public void PlaceOrder(Order order)
    {
        _repo.Save(order);
        _email.Send(order.CustomerEmail, "Order placed");
    }
}
```

What's wrong with this:

- **Tight coupling**: `OrderService` is tied to SQL and SMTP. Switching to another database or email provider means editing this class.
- **Hard to test**: a unit test of `PlaceOrder` would hit a real database and send real emails.
- **Hidden dependencies**: you can't tell what `OrderService` needs without reading its body.

### The fix: inject through the constructor

```csharp
public interface IOrderRepository
{
    void Save(Order order);
}

public interface IEmailSender
{
    void Send(string to, string message);
}

public class OrderService
{
    private readonly IOrderRepository _repo;
    private readonly IEmailSender _email;

    public OrderService(IOrderRepository repo, IEmailSender email)
    {
        _repo = repo;
        _email = email;
    }

    public void PlaceOrder(Order order)
    {
        _repo.Save(order);
        _email.Send(order.CustomerEmail, "Order placed");
    }
}
```

Now `OrderService` only knows about **interfaces**. Whoever creates it decides which implementations to pass in.

#### Manual DI (no framework)

DI doesn't need a framework. You can wire things up yourself:

```csharp
var repo = new SqlOrderRepository("Server=...;Database=Shop");
var email = new SmtpEmailSender("smtp.example.com");
var service = new OrderService(repo, email);

service.PlaceOrder(order);
```

This is fine for small apps. In bigger apps the object graph gets large, so we let a **DI container** build it.

### The three kinds of injection

| Kind | How | When to use |
| --- | --- | --- |
| **Constructor injection** | Dependencies are constructor parameters | The default. Required dependencies; the object is always valid after construction |
| **Method injection** | Passed as a method parameter | The dependency is only needed for one call, or changes per call |
| **Property injection** | Set through a public property | Optional dependencies. Not supported by the built-in .NET container |

```csharp
// Method injection
public void Export(IReportFormatter formatter) { ... }

// Property injection
public ILogger Logger { get; set; } = NullLogger.Instance;
```

### The built-in .NET container

.NET has a container in `Microsoft.Extensions.DependencyInjection`. ASP.NET Core, Worker Services and MAUI use it out of the box.

There are two steps:

1. **Register** services: map an abstraction to an implementation and pick a lifetime.
2. **Resolve** services: the container creates the objects, filling in their constructor parameters.

```csharp
// Program.cs (ASP.NET Core)
var builder = WebApplication.CreateBuilder(args);

// 1. Register
builder.Services.AddScoped<IOrderRepository, SqlOrderRepository>();
builder.Services.AddTransient<IEmailSender, SmtpEmailSender>();
builder.Services.AddScoped<OrderService>();

var app = builder.Build();

// 2. Resolve: the framework injects OrderService into the endpoint
app.MapPost("/orders", (Order order, OrderService service) =>
{
    service.PlaceOrder(order);
    return Results.Ok();
});

app.Run();
```

When `OrderService` is requested, the container:

1. Looks at its constructor: it needs `IOrderRepository` and `IEmailSender`.
2. Resolves those (and *their* dependencies, recursively).
3. Calls the constructor and returns the object.

#### Console app (no ASP.NET Core)

```csharp
using Microsoft.Extensions.DependencyInjection;

var services = new ServiceCollection();
services.AddSingleton<IOrderRepository, InMemoryOrderRepository>();
services.AddSingleton<IEmailSender, ConsoleEmailSender>();
services.AddTransient<OrderService>();

using var provider = services.BuildServiceProvider();

var service = provider.GetRequiredService<OrderService>();
service.PlaceOrder(new Order { CustomerEmail = "a@b.com" });
```

### Lifetimes

| Lifetime | Registration | Instances | Typical use |
| --- | --- | --- | --- |
| **Singleton** | `AddSingleton` | One for the whole app | Config, caches, `HttpClient` factories. Must be thread-safe |
| **Scoped** | `AddScoped` | One per scope (= per HTTP request in ASP.NET Core) | `DbContext`, unit of work, per-request state |
| **Transient** | `AddTransient` | A new one every time it's requested | Lightweight, stateless services |

A quick demo showing the difference:

```csharp
public class Probe
{
    public Guid Id { get; } = Guid.NewGuid();
}

var services = new ServiceCollection();
services.AddTransient<Probe>();   // try AddScoped / AddSingleton
using var provider = services.BuildServiceProvider();

using (var scope1 = provider.CreateScope())
{
    var a = scope1.ServiceProvider.GetRequiredService<Probe>();
    var b = scope1.ServiceProvider.GetRequiredService<Probe>();
    Console.WriteLine(a.Id == b.Id);   // Transient: False | Scoped: True | Singleton: True
}

using (var scope2 = provider.CreateScope())
{
    var c = scope2.ServiceProvider.GetRequiredService<Probe>();
    // Compared with scope1's object: Scoped -> different, Singleton -> same
}
```

#### Captive dependency (common bug)

A service must not depend on something with a **shorter** lifetime.

```csharp
services.AddSingleton<ReportCache>();   // lives forever
services.AddScoped<AppDbContext>();     // should live for one request

public class ReportCache
{
    public ReportCache(AppDbContext db) { ... }  // the DbContext gets "captured" forever
}
```

The singleton keeps the first `DbContext` for the rest of the app's life, so a per-request object becomes shared across requests and threads.

- In Development, ASP.NET Core **validates scopes** and throws: `Cannot consume scoped service ... from singleton ...`.
- Fix: make the outer service scoped too, or inject `IServiceScopeFactory` and create a scope when needed:

```csharp
public class ReportCache
{
    private readonly IServiceScopeFactory _scopeFactory;

    public ReportCache(IServiceScopeFactory scopeFactory) => _scopeFactory = scopeFactory;

    public void Refresh()
    {
        using var scope = _scopeFactory.CreateScope();
        var db = scope.ServiceProvider.GetRequiredService<AppDbContext>();
        // use db, it's disposed with the scope
    }
}
```

### Other registration forms

```csharp
// Factory: you control construction (e.g. need a config value)
services.AddSingleton<IEmailSender>(sp =>
{
    var config = sp.GetRequiredService<IConfiguration>();
    return new SmtpEmailSender(config["Smtp:Host"]!);
});

// Existing instance: created by you, NOT disposed by the container
services.AddSingleton<IClock>(new SystemClock());

// Concrete type only (no interface)
services.AddScoped<OrderService>();

// Several implementations of one interface -> inject IEnumerable<T>
services.AddTransient<INotifier, EmailNotifier>();
services.AddTransient<INotifier, SmsNotifier>();

public class AlertService(IEnumerable<INotifier> notifiers)   // receives both
{
    public void Alert(string msg)
    {
        foreach (var n in notifiers) n.Notify(msg);
    }
}

// If you resolve a single INotifier, the LAST registration wins (SmsNotifier)
```

#### Keyed services (.NET 8+)

When you need a *specific* implementation by name:

```csharp
services.AddKeyedSingleton<IPaymentGateway, StripeGateway>("stripe");
services.AddKeyedSingleton<IPaymentGateway, PaypalGateway>("paypal");

public class CheckoutService([FromKeyedServices("stripe")] IPaymentGateway gateway)
{
    ...
}
```

### Primary constructors (C# 12)

Primary constructors shorten DI classes. The parameters are available throughout the class body:

```csharp
public class OrderService(IOrderRepository repo, IEmailSender email)
{
    public void PlaceOrder(Order order)
    {
        repo.Save(order);
        email.Send(order.CustomerEmail, "Order placed");
    }
}
```

Note: primary constructor parameters are **not** `readonly`, so they can be reassigned inside the class. If that matters, assign them to `private readonly` fields.

### Disposal

- The container **disposes** `IDisposable` / `IAsyncDisposable` services **it created**:
  - Scoped and transient ones: when the **scope** ends (end of the HTTP request).
  - Singletons: when the **app** shuts down.
- Instances you passed in yourself (`AddSingleton(new X())`) are **not** disposed by the container.
- Watch out: disposable **transients resolved from the root provider** are kept until app shutdown, which can look like a memory leak. Resolve them from a scope.

### Why DI helps testing

Since `OrderService` depends on interfaces, a test can pass in fakes:

```csharp
public class FakeOrderRepository : IOrderRepository
{
    public List<Order> Saved { get; } = new();
    public void Save(Order order) => Saved.Add(order);
}

public class FakeEmailSender : IEmailSender
{
    public List<string> SentTo { get; } = new();
    public void Send(string to, string message) => SentTo.Add(to);
}

[Fact]
public void PlaceOrder_SavesOrder_AndSendsEmail()
{
    var repo = new FakeOrderRepository();
    var email = new FakeEmailSender();
    var service = new OrderService(repo, email);   // no container needed in unit tests

    service.PlaceOrder(new Order { CustomerEmail = "a@b.com" });

    Assert.Single(repo.Saved);
    Assert.Equal("a@b.com", email.SentTo[0]);
}
```

(Mocking libraries such as Moq or NSubstitute can generate these fakes for you.)

### Anti-pattern: Service Locator

```csharp
public class OrderService
{
    private readonly IServiceProvider _sp;

    public OrderService(IServiceProvider sp) => _sp = sp;

    public void PlaceOrder(Order order)
    {
        var repo = _sp.GetRequiredService<IOrderRepository>();   // hidden dependency
        repo.Save(order);
    }
}
```

This hides what the class really needs, and missing registrations only fail at **runtime, deep inside a method**. Prefer constructor injection. (`IServiceScopeFactory` in background or singleton code is the accepted exception.)

### Key points

- DI = a class **receives** its dependencies instead of creating them.
- Prefer **constructor injection** and depend on **interfaces**.
- The container **creates** objects, **injects** their dependencies recursively, and **disposes** what it created.
- Lifetimes: **Singleton** (one per app), **Scoped** (one per request), **Transient** (new each time).
- Never let a longer-lived service hold a shorter-lived one (**captive dependency**).
- Avoid injecting `IServiceProvider` into normal classes (**service locator**).
