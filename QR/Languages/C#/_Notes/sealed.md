## C#
### `sealed`

#### [Back to C# contents](../_Contents.md)

*What does the `sealed` keyword do, and when should you use it?*

Short answer: **`sealed` stops inheritance.** A `sealed` class can't be used as a base class, and a `sealed override` method can't be overridden again further down the hierarchy.

It can be used in two places:

| Where | Meaning |
| --- | --- |
| On a **class** | No class can inherit from it |
| On an **overriding member** (`sealed override`) | Subclasses can't override that member any further |

> Note: in the code samples, a `// ❌` comment describes **the line it's on**: that line fails to compile with the error shown.

### 1. Sealed classes

```csharp
public sealed class PaymentReceipt
{
    public string Id { get; }
    public decimal Amount { get; }

    public PaymentReceipt(string id, decimal amount)
    {
        Id = id;
        Amount = amount;
    }
}

public class FakeReceipt : PaymentReceipt { }   // ❌ CS0509: cannot derive from sealed type 'PaymentReceipt'
```

Everything else works as normal:

- You can **create instances**: `new PaymentReceipt("R1", 10m)`.
- It can **inherit** from a base class and **implement interfaces**.
- You can write **extension methods** for it.

Only one thing is blocked: being the **base** of another class.

```csharp
public sealed class SmsSender : NotificationSenderBase, INotificationSender   // ✅ fine
{
    ...
}
```

### 2. Sealed members (`sealed override`)

Use it to stop overriding **partway down** a hierarchy. It only applies to members that are **overriding** a `virtual`/`abstract` member.

```csharp
public class Shape
{
    public virtual double Area() => 0;
    public virtual string Describe() => "Shape";
}

public class Rectangle : Shape
{
    public double Width { get; init; }
    public double Height { get; init; }

    // Area must stay Width * Height for every kind of rectangle
    public sealed override double Area() => Width * Height;

    public override string Describe() => $"Rectangle {Width}x{Height}";
}

public class Square : Rectangle
{
    public override double Area() => 42;               // ❌ CS0239: cannot override sealed member
    public override string Describe() => "Square";     // ✅ still overridable
}
```

`sealed` on a **new** method (one that isn't an `override`) is a compile error:

```csharp
public class Rectangle : Shape
{
    // ❌ Compile error CS0238 on this line: 'sealed' is only allowed together with 'override'
    public sealed double Perimeter() => 2 * (Width + Height);

    // ✅ Correct: a plain new method (already can't be overridden)
    public double Perimeter() => 2 * (Width + Height);
}
```

Adding a new method does **not** need `override` or `sealed`. A normal (non-`virtual`) method already can't be overridden, so `sealed` on it would block something that's already impossible.

| Method | Can subclasses override it? |
| --- | --- |
| `public double Perimeter()` | ❌ (closed by default) |
| `public virtual double Perimeter()` | ✅ |
| `public override double Area()` | ✅ (still open) |
| `public sealed override double Area()` | ❌ |

### What's already sealed

| Type | Sealed? |
| --- | --- |
| `struct`, `record struct`, `enum` | **Always** (value types can't be inherited) |
| `static class` | Effectively yes (it's `abstract` + `sealed` in IL) |
| `string`, `int`, `DateTime` … | Yes |
| `class`, `record` (class) | **No** — open by default unless you add `sealed` |

```csharp
public sealed record Money(decimal Amount, string Currency);   // records can be sealed too
```

### Why use `sealed`?

#### 1. Design intent: "this class was not built to be extended"

Inheritance is a **contract**. If a class is open, subclasses can override its `virtual` members and depend on its `protected` members. Every future change must keep them working.

Sealing says: *this is the final version, don't build on top of it.* You're free to change its internals later without breaking someone's subclass.

A common rule of thumb: **design for inheritance, or prohibit it.**

#### 2. Protecting invariants and security

A subclass could override behavior and break rules the class relies on:

```csharp
public class PasswordValidator
{
    public virtual bool IsValid(string password) => password.Length >= 12;
}

public class LazyValidator : PasswordValidator
{
    public override bool IsValid(string password) => true;   // 😬 bypasses the rule
}
```

If `PasswordValidator` were `sealed` (and passed around as itself), nobody could swap in a weaker version.

This is why `string` is sealed: code everywhere relies on strings being **immutable**. A mutable subclass of `string` would break that guarantee.

#### 3. Performance (small but free)

- **Devirtualization**: when the JIT knows the exact type, it can call methods **directly** (and inline them) instead of a virtual call.
- **Faster type checks and casts**: `obj is MySealedType` only needs to compare one type — there can't be any subclasses to check.
- **Faster array stores**: storing into a `T[]` has a covariance check, which is cheaper when `T` is sealed.

```csharp
public sealed class Circle : Shape
{
    public override double Area() => Math.PI * R * R;
    public double R { get; init; }
}

Circle c = new() { R = 2 };
double a = c.Area();   // JIT knows it's exactly Circle → direct call, can be inlined
```

The gain is usually small, but it costs nothing. The .NET team sealed many internal classes for this reason, and analyzer **CA1852** suggests sealing internal types that have no subclasses.

### `sealed` and testing

A common worry: *"if I seal it, I can't mock it."*

- Mocking libraries (Moq, NSubstitute) mock by **subclassing**, so they can't mock a sealed class. (They can't mock non-`virtual` members of an open class either.)
- The fix is **not** to unseal — depend on an **interface** instead:

```csharp
public interface IClock
{
    DateTime UtcNow { get; }
}

public sealed class SystemClock : IClock          // sealed implementation
{
    public DateTime UtcNow => DateTime.UtcNow;
}

public class InvoiceService(IClock clock)         // consumers depend on the interface
{
    public bool IsOverdue(Invoice i) => clock.UtcNow > i.DueDate;
}

// In tests: mock IClock, not SystemClock
```

The concrete class stays sealed; flexibility comes from **interfaces and composition**, not inheritance.

### `sealed` vs. `static` vs. `abstract`

| | `sealed` | `static` | `abstract` |
| --- | --- | --- | --- |
| Can create instances | ✅ | ❌ | ❌ |
| Can be a base class | ❌ | ❌ | ✅ (that's the point) |
| Instance members | ✅ | ❌ | ✅ |
| Typical use | Final concrete classes | Helpers, extension methods | Base classes with missing pieces |

`abstract` and `sealed` together on a class are a compile error (`CS0418`) — "must be inherited" and "can't be inherited" contradict each other. (`static` is the way to get that combination.)

### Unsealing later vs. sealing later

| Change | Breaking? |
| --- | --- |
| `sealed` → open | ✅ Safe. Nothing that compiled before stops compiling |
| open → `sealed` | ❌ **Breaking**. Any existing subclass stops compiling |

So in **libraries** and shared code it's safer to **start sealed** and open up later only if a real need appears.

### When to use / when not to

**Seal when:**

- The class isn't designed with `virtual` members / `protected` hooks for extension.
- It's a value-like or data class (DTOs, records, value objects like `Money`).
- It enforces security or correctness rules that subclasses could break.
- It's an `internal` / `private` class with no subclasses (free performance, CA1852).

**Leave open when:**

- The class is **meant** to be a base class (it has `virtual` / `abstract` members and documented extension points).
- A framework needs to subclass it at runtime, e.g. **EF Core lazy-loading proxies** and some mocking/AOP tools create subclasses of your entities. Sealed entities won't work with those features.

### Key points

- `sealed` on a class → **can't be inherited**. On a `sealed override` member → **can't be overridden again**.
- Structs, enums and `string` are already sealed. Classes and records are **open by default**.
- Reasons: **clear design intent**, **protected invariants**, **small performance gains** (devirtualization, faster type checks).
- For testability, seal the class and depend on an **interface**.
- Going from sealed to open is safe; going from open to sealed is a **breaking change**.
