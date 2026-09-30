## C#
### IQueryable vs IEnumerable

#### [Back to C# contents](../_Contents.md)

Interview question: *What is the difference between `IEnumerable` and `IQueryable`?*

Short answer: **`IEnumerable` filters in memory in your app. `IQueryable` builds a query that runs at the data source, usually the database.**

### The core difference

| | `IEnumerable<T>` | `IQueryable<T>` |
|---|---|---|
| Namespace | `System.Collections.Generic` | `System.Linq` |
| Where the work happens | In memory, in your C# process | At the source, e.g. translated to SQL |
| What LINQ methods take | `Func<T, bool>` (compiled code) | `Expression<Func<T, bool>>` (an expression tree, i.e. code as data) |
| Typical use | Lists, arrays, data already loaded | EF Core `DbSet`, remote data sources |
| Relationship | Base interface | Inherits from `IEnumerable<T>` |

With EF Core:

```csharp
// IQueryable: the Where becomes SQL. The DB returns only the matching rows.
IQueryable<User> q = db.Users.Where(u => u.Age > 30);
// SQL: SELECT ... FROM Users WHERE Age > 30

// IEnumerable: every row is loaded first, then filtered in C#.
IEnumerable<User> e = db.Users;             // or db.Users.AsEnumerable()
var result = e.Where(u => u.Age > 30);
// SQL: SELECT ... FROM Users   (whole table!)
```

`IQueryable` keeps the lambda as an **expression tree**, so the provider (EF Core) can read it and translate it into SQL. `IEnumerable` gets a compiled delegate, which can only be run in memory. It can't be translated.

**In common:** both use **deferred execution**. Nothing runs until you enumerate, e.g. with `foreach`, `.ToList()`, `.Count()` or `.First()`.

One-liner: *"IQueryable is for building the query, IEnumerable is for iterating the results."*

### Is this only about Entity Framework?

No, but EF is where you'll run into it most.

- **`IEnumerable` is general C#.** It has nothing to do with databases. Any sequence you can loop over implements it: arrays, `List<T>`, `Dictionary`, the result of a `yield return` method, `File.ReadLines(...)`. LINQ-to-Objects works on `IEnumerable`.
- **`IQueryable` is a general LINQ interface.** It's defined in `System.Linq`, not in EF. It says "I'll take your query as an expression tree, and a **provider** decides what to do with it".

| Provider | Translates LINQ into |
|---|---|
| **EF Core** / EF6 | SQL (SQL Server, PostgreSQL, SQLite, …) |
| LINQ to SQL (old, pre-EF) | SQL Server SQL |
| MongoDB C# driver (`collection.AsQueryable()`) | MongoDB aggregation queries |
| Azure Cosmos DB SDK | Cosmos SQL |
| NHibernate (`session.Query<T>()`) | SQL |
| OData client | URL query strings (`?$filter=...`) |

So the "can't translate my C# method" problem (below) happens with **every** `IQueryable` provider, not just EF. Each provider only understands a limited set of methods.

**Trap: `AsQueryable()` on a list**

```csharp
var list = new List<int> { 1, 2, 3 };
IQueryable<int> q = list.AsQueryable();
```

This compiles, but there's no database behind it. The provider just compiles the expression tree and runs it in memory. You get no benefit. It's mostly used in unit tests, to fake an `IQueryable` data source.

Interview answer: *"`IEnumerable` is for in-memory iteration. `IQueryable` is an abstraction that lets a LINQ provider translate the query and run it somewhere else. EF Core is the most common provider, and it translates to SQL."*

### When to use `AsEnumerable()` on purpose

Use it when **the rest of the query** uses a C# method that EF can't translate into SQL.

**The problem**

```csharp
// Your own C# helper. EF Core has no idea what's inside it.
static bool IsVip(Customer c) => c.Orders.Sum(o => o.Total) > 10_000 && c.Email.EndsWith("@corp.com");

var vips = db.Customers
    .Where(c => c.Country == "SG")
    .Where(c => IsVip(c))        // ❌ EF can't turn IsVip into SQL
    .ToList();
```

EF Core 3.0 and later **throw** at runtime:

```
System.InvalidOperationException: The LINQ expression 'DbSet<Customer>().Where(c => IsVip(c))'
could not be translated. Either rewrite the query in a form that can be translated, or switch to
client evaluation explicitly by inserting a call to 'AsEnumerable', 'AsAsyncEnumerable', 'ToList', or 'ToListAsync'.
```

EF only sees a call to a method named `IsVip`. It can't look inside a compiled method.

**The fix: filter in SQL first, then switch to memory**

```csharp
var vips = db.Customers
    .Where(c => c.Country == "SG")      // IQueryable → becomes SQL WHERE
    .Include(c => c.Orders)             // load what IsVip needs
    .AsEnumerable()                     // ← switch point: from here on, runs in C#
    .Where(c => IsVip(c))               // IEnumerable → runs in memory
    .ToList();
```

- Everything **before** `AsEnumerable()` goes into the SQL: `SELECT ... WHERE Country = 'SG'`.
- Everything **after** it runs on the rows that came back.

**Why "the *rest* of the query"?** A query is a chain of steps, and `AsEnumerable()` splits it into two parts. Do **as much as possible** in SQL first, so the database sends back fewer rows. Then switch to memory **only for the remaining steps** SQL can't handle. Calling `AsEnumerable()` too early loads the whole table:

```csharp
db.Customers.AsEnumerable()             // ❌ SELECT * FROM Customers (every country!)
    .Where(c => c.Country == "SG")      //    filtered in memory. Wasteful.
    .Where(c => IsVip(c))
```

Rule: **put the switch as late as possible, and keep everything before it translatable.**

**Other things EF can't translate**

```csharp
// Regex
.Where(p => Regex.IsMatch(p.Code, @"^[A-Z]{3}\d{4}$"))

// String formatting / culture-specific methods
.Where(u => u.Name.ToString("X", CultureInfo.InvariantCulture) ...)

// Your own formatting method
.Where(o => FormatInvoiceNo(o) == "INV-2026-001")
```

Some things **do** translate, because EF has built-in mappings for them: `string.Contains`, `StartsWith`, `ToUpper`, `DateTime.Year`, `Math.Abs`, `EF.Functions.Like(...)`, and so on. Rule of thumb: EF knows specific framework methods, never your own methods.

**`Select` is the exception**

In the **final** `Select` (the projection), EF Core *can* call your C# method. It fetches the columns with SQL and then runs your method on each row:

```csharp
var list = db.Customers
    .Where(c => c.Country == "SG")                          // SQL
    .Select(c => new { c.Name, Label = MakeLabel(c.Name) }) // ✅ OK: MakeLabel runs in C# per row
    .ToList();
```

It only throws when untranslatable code is in a place that affects **which rows** come back (`Where`, `OrderBy`, `GroupBy`, `Join`).

**Better than `AsEnumerable()`, if you can: rewrite it so EF can translate it**

```csharp
var vips = db.Customers
    .Where(c => c.Country == "SG"
             && c.Orders.Sum(o => o.Total) > 10_000
             && c.Email.EndsWith("@corp.com"))   // all translatable → one SQL query
    .ToList();
```

For reuse, keep the logic as an `Expression<Func<...>>` instead of a normal method. EF can read an expression tree:

```csharp
static readonly Expression<Func<Customer, bool>> IsVipExpr =
    c => c.Orders.Sum(o => o.Total) > 10_000 && c.Email.EndsWith("@corp.com");

db.Customers.Where(IsVipExpr).ToList();   // ✅ translated to SQL
```

Key point: **a `Func` is compiled code EF can't read; an `Expression<Func>` is data EF can translate.**

### Should a repository return `IQueryable`?

Two risks: callers can add any query they like, so query logic **spreads outside the data layer**; and the query might **run after the `DbContext` has been disposed**.

Setup, a repository that returns `IQueryable`:

```csharp
public class OrderRepository
{
    private readonly AppDbContext _db;
    public OrderRepository(AppDbContext db) => _db = db;

    public IQueryable<Order> GetOrders() => _db.Orders;   // returns the query itself, not the results
}
```

**Risk 1: Query logic spreads outside the data layer**

```csharp
// In a controller
var recent = _repo.GetOrders()
    .Where(o => o.CreatedAt > DateTime.UtcNow.AddDays(-7))
    .OrderByDescending(o => o.Total)
    .ToList();

// In a service somewhere else
var unpaid = _repo.GetOrders()
    .Where(o => o.Status == "Unpaid" && !o.IsDeleted)
    .Include(o => o.Customer)
    .ToList();

// In a background job
var big = _repo.GetOrders()
    .Where(o => o.Total > 1000)          // ⚠ forgot !o.IsDeleted
    .ToList();
```

- **Rules are copied around.** "Exclude deleted orders" is written in some places and forgotten in others.
- **Hard to find all the queries.** To add an index or change a column, you have to search the whole codebase for queries on `Orders`.
- **Runtime surprises in other layers.** A caller might add an untranslatable `.Where(o => IsVip(o))`, and the exception shows up in the controller.
- **Harder to test.** You can't easily mock "whatever query the caller might build".

Alternative: the repository owns the queries and returns results.

```csharp
public class OrderRepository
{
    private readonly AppDbContext _db;
    public OrderRepository(AppDbContext db) => _db = db;

    private IQueryable<Order> Active => _db.Orders.Where(o => !o.IsDeleted);  // the rule lives in ONE place

    public List<Order> GetRecent(int days) =>
        Active.Where(o => o.CreatedAt > DateTime.UtcNow.AddDays(-days))
              .OrderByDescending(o => o.Total)
              .ToList();

    public List<Order> GetUnpaidWithCustomer() =>
        Active.Where(o => o.Status == "Unpaid")
              .Include(o => o.Customer)
              .ToList();
}
```

`IQueryable` is still used here, but **inside** the repository. Callers only get `List<Order>` back.

**Risk 2: The query runs after the `DbContext` has been disposed**

Because of **deferred execution**, the query can run later, after the context is gone:

```csharp
public class OrderRepository
{
    public IQueryable<Order> GetOrders()
    {
        using var db = new AppDbContext();   // disposed when this method returns
        return db.Orders.Where(o => !o.IsDeleted);
    }                                        // ← db.Dispose() happens here
}

// Caller
var orders = repo.GetOrders();       // no DB call yet, just a query definition
foreach (var o in orders)            // ← NOW it tries to run the SQL... using a disposed context
{
    Console.WriteLine(o.Id);
}
```

```
System.ObjectDisposedException: Cannot access a disposed context instance.
Object name: 'AppDbContext'.
```

Fix: run the query while the context is still alive, by materializing it with `ToList()`:

```csharp
public List<Order> GetOrders()
{
    using var db = new AppDbContext();
    return db.Orders.Where(o => !o.IsDeleted).ToList();   // ✅ SQL runs here, before dispose
}
```

In ASP.NET Core this is less common, because the `DbContext` is usually injected with a **scoped** lifetime and lives for the whole HTTP request. It still happens with:

- code that creates the context manually with `using`
- background work that outlives the request, e.g. `Task.Run(...)` or a queued job holding an `IQueryable`
- returning an `IQueryable` from an API action when a serializer enumerates it after the scope has ended

**Balance**

This is a debated topic, not a strict rule. Some teams **do** return `IQueryable` on purpose, e.g. for OData or flexible paging and filtering APIs.

Interview answer: *"Returning `IQueryable` is flexible, but it lets query logic leak out of the data layer, and deferred execution can run the query after the context is disposed. I'd usually return materialized results or purpose-specific methods, and use `IQueryable` internally."*

### Other related follow-ups

- **What about `ICollection` / `IList`?** Those are already in-memory collections with `Count` and add/remove. That's a different concern from how the query runs.
