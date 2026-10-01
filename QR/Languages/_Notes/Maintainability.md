## Languages
### What makes code maintainable

#### [Back to Languages contents](../_Contents.md)

- **Maintainable code** is code that people, including you six months from now, can **understand, change and fix safely and quickly**.
- Most of a codebase's life is spent being **read and changed**, not written, so maintainability usually matters more than cleverness or saving a few lines.

> **The test:** how long does it take someone new to make a **correct** change, and how **confident** are they that nothing else broke?

Everything below either **shortens that time** or **raises that confidence**.

### 1. Easy to understand

#### Clear names
- Name things after **what they mean**:
  - ✅ `unpaid_invoices`, `calculate_shipping_cost()`
  - ❌ `list2`, `process()`
- The name should **match the behaviour** (see [Abstraction](Abstraction.md)).

#### Small, focused units
- Each function or class has **one clear purpose** and works at **one level of detail**.
- If a function needs scrolling, or a comment saying "now do part 2", it's probably two functions.

#### Simple over clever
- **KISS** ("Keep It Simple, Stupid"): the obvious solution beats the clever one-liner.
- **YAGNI** ("You Aren't Gonna Need It"): don't build for imagined future requirements. Unused flexibility is still code to read and maintain.

#### Consistent
- One style, and **one way** of doing common things (error handling, logging, data access) throughout the codebase.
- When something is consistent, readers only have to learn it **once**.

#### Comments explain *why*, not *what*
- ❌ `i += 1  # increment i`
- ✅ `# Retry once: the payment API returns 503 during its nightly restart`
- The code shows **what** it does. Comments record **reasons, constraints and decisions** the code can't express.

### 2. Easy to change

#### High cohesion, low coupling
- **Cohesion:** things that change together **live together**.
- **Coupling:** modules depend on each other **as little as possible**, and only through clear interfaces.
- **Goal:** a change in one place doesn't ripple through ten others.

#### Good abstractions at the right boundaries
- Hide decisions **likely to change** (database, external APIs, business rules) behind interfaces.
- Don't wrap everything. **Shallow layers** make code *harder* to follow (see [Abstraction](Abstraction.md)).

#### DRY vs WET

**DRY: "Don't Repeat Yourself"** (from *The Pragmatic Programmer*, Andy Hunt & Dave Thomas)
- *"Every piece of **knowledge** must have a single, unambiguous, authoritative representation within a system."*
- It's about **knowledge** (a business rule, a formula, a config value), **not** about lines of code that happen to look alike.

**WET: the opposite of DRY**, a joke acronym with several versions:
- "**Write Everything Twice**"
- "**We Enjoy Typing**"
- "**Waste Everyone's Time**"

**WET code** (the same rule copied in several places):
```python
# checkout.py
if order.total > 100:
    shipping = 0

# invoice.py
if invoice.amount > 100:
    shipping_fee = 0

# email_templates.py
"Free shipping on orders over $100!"
```
- The business decides free shipping now starts at $150, so you must find and change **every copy**.
- Miss one, and the checkout, the invoice and the email **disagree**. This is the code version of an **update anomaly** (see [Normalisation](../../Database/_Notes/Normalisation.md)).

**DRY code** (the knowledge lives in one place):
```python
# shipping.py
FREE_SHIPPING_THRESHOLD = 100

def qualifies_for_free_shipping(amount):
    return amount > FREE_SHIPPING_THRESHOLD
```
- Change it **once**, and everything that uses it stays consistent.

**But: over-DRY is also a problem**
- Two pieces of code that **look** the same but **mean** different things should stay separate:
  ```python
  # Same calculation today, but different business rules
  def employee_discount(price): return price * 0.9   # HR policy
  def bulk_discount(price):     return price * 0.9   # Sales policy
  ```
  - Merging them into one `discount()` couples HR and Sales. When one policy changes, you end up adding a flag, and then another.
- *"Duplication is far cheaper than the wrong abstraction"* (Sandi Metz).
- **AHA**, "Avoid Hasty Abstractions" (Kent C. Dodds): prefer duplication over the **wrong** abstraction, and wait until the right one is clear.
- **Rule of three:** tolerate the first copy, and extract on the **third** occurrence, once the pattern is clear.

| | WET (too little sharing) | DRY (done right) | Over-DRY (too much sharing) |
|---|---|---|---|
| **What it looks like** | Same rule copy-pasted in many places | Each piece of **knowledge** in one place | Unrelated code forced into one function because it *looks* similar |
| **When the rule changes** | Edit every copy; miss one → bugs | Edit one place | One change breaks unrelated callers |
| **Typical symptom** | **Shotgun surgery**, inconsistent behaviour | Clear names, one source of truth | Boolean flags and special cases piling up |
| **Fix** | Extract the shared knowledge | (Keep it) | Duplicate again, then re-split by meaning |

> **Rule of thumb:** DRY up **knowledge**, not **coincidence**. Ask: *if one copy changes, must the other change too?* If yes, share it. If not, leave the copies separate.

#### Explicit dependencies
- **Pass dependencies in** (e.g. **dependency injection**) instead of hiding them in globals or creating them deep inside functions.
- It's clear what the code needs, things can be swapped, and testing gets easier.

#### Avoid hidden state and side effects
- Prefer functions that **take inputs and return outputs**.
- Shared mutable state and surprising side effects are a major source of "I changed X and Y broke."

### 3. Safe to change

#### Automated tests
- Tests give you the **confidence** to change code. Without them, every change is risky, so people avoid touching code, and it decays.
- Test **behaviour**, not internal details, so refactoring doesn't break the tests.
- A rough split: many fast **unit** tests, fewer **integration** tests, and a few **end-to-end** tests.

#### Clear error handling
- Fail **early and loudly** with useful messages. Don't silently swallow exceptions.
- **Validate input at the boundaries** (API, user input, files).

#### Types and contracts
- Type hints (Python), static types (C#, TypeScript) and database constraints (`NOT NULL`, `FOREIGN KEY`) catch mistakes **before** they run, and they **document intent**.

### 4. Supported by the process around the code

- **Version control:** small, focused commits with clear messages, so history explains **why** things changed.
- **Code review:** a second reader catches unclear code while it's still easy to fix.
- **Automation:** linters, formatters (`black`, `ruff`, `dotnet format`) and **CI** running tests on every change, so style and checks don't depend on memory.
- **Short, current documentation:**
  - a README: how to run, test and deploy
  - **ADRs** (Architecture Decision Records) for key decisions
- **Observability:** good logs and metrics make production problems quick to diagnose.
- **Managed dependencies:** pinned versions and regular updates, so you never face a huge, risky upgrade all at once.

### Warning signs (code smells)

| Smell | What it usually means |
|---|---|
| "Don't touch that file, nobody knows how it works" | Missing tests, unclear design |
| Small change → edits in many files (**shotgun surgery**) | High coupling, or WET code with knowledge spread out |
| One class that does everything (**god class**) | Low cohesion, too many responsibilities |
| Long functions with deep nesting | Mixed levels of detail, missing abstractions |
| Boolean flags piling up on a function | Two concepts forced into one (over-DRY) |
| Copy-pasted logic with small differences | WET code: a missing (or wrong) shared abstraction |
| Comments explaining confusing code | The code itself should be clearer |
| Magic numbers: `if status == 3` | Missing named constants or enums |
| Tests that break on every refactor | Tests coupled to implementation details |

### Quick checklist

Before considering code done, ask:
1. Could someone new understand this **without asking me**?
2. Do the **names** tell the truth?
3. Does each function or class have **one purpose** and work at **one level of detail**?
4. If this requirement changes, **how many places** do I need to edit?
5. Is any **business rule duplicated** (WET)? Or have I merged things that only **look** alike (over-DRY)?
6. Are there **tests** that would catch it if I broke this?
7. Do errors **fail clearly**, with a useful message?
8. Is it the **simplest** thing that works?

### How this connects to other notes

- [Abstraction](Abstraction.md): single purpose, honest names, consistent level, deep vs shallow.
- [Normalisation](../../Database/_Notes/Normalisation.md): "each fact in one place" is the database version of **DRY**. "Denormalise only when measured" is the same as "don't optimise early."
- [Indexing](../../Database/_Notes/Indexing.md): "verify and review" is the same habit as testing and refactoring: check, measure, and revisit as things change.

### Further reading
- *The Pragmatic Programmer* by Andy Hunt and Dave Thomas (DRY)
- *Clean Code* by Robert C. Martin (naming, small functions, code smells)
- *Refactoring* by Martin Fowler (code smells and how to fix them)
- "The Wrong Abstraction" by Sandi Metz
- "AHA Programming" by Kent C. Dodds
