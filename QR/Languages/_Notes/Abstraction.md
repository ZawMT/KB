## Languages
### What makes a good abstraction

#### [Back to Languages contents](../_Contents.md)

A good abstraction lets you **use something without knowing how it works, and still be right about what it does.** Most of the rules below follow from that.

### Core qualities

#### 1. Clear, single purpose
- This is the **Single Responsibility Principle**, or "cohesion."
- If it does one thing, a change to that thing touches **one place**. If it does three things, unrelated changes collide in the same code.
- **Trap: "single" depends on the level you look at.** `OrderService` has one purpose (orders) but can still hide pricing, persistence and email.
  - Better test: **it has one reason to change**, meaning one actor or stakeholder who would ask for changes (Robert Martin).
- **Opposite trap: splitting too far.** 40 tiny classes that each forward a call are shallow (see #3).
- **Quick check:** describe it in one sentence **without "and" or "or"**.

#### 2. The name matches the behaviour
- This is the **principle of least surprise**.
- The name *is* the interface for most readers. They read `getUser()` and trust it. If it also writes to the DB or sends an email, the name is **lying**, and that causes bugs.
- Common mismatches:
  - `get` / `is` / `check` that have **side effects**. `isValid()` shouldn't modify anything.
  - **Vague names** like `process()`, `handle()`, `Manager`, `Helper` and `Utils`. They match any behaviour, so they tell you nothing.
  - **Stale names**, where the code changed and the name didn't.
- Name what it does (*what*), not how it does it (*how*):
  - ✅ `PaymentGateway.charge()`
  - ❌ `StripeHttpClient.postChargeRequest()`
- Naming is a design tool. If you **can't find an honest name**, the purpose probably isn't single.

> **#1 and #2 support each other:**
> single purpose → an honest name is easy.
> Honest name hard to find → purpose probably isn't single.

#### 3. Deep, not shallow (Ousterhout)
- **Deep:** a small interface hiding a lot of complexity.
  - e.g. Unix `open / read / write / close` is five calls in front of files, devices, pipes and sockets.
- **Shallow:** the interface is about as complex as what it hides.
  - e.g. `UserManager.getUser()` that just calls `db.users.find()`. You pay for an extra layer and get nothing back.
- Something can have a single purpose and a perfect name and **still** be a pointless pass-through wrapper, so depth needs its own check.

#### 4. Hides the right things (Parnas)
- Hide **decisions likely to change**: storage engine, wire format, retry policy and so on.
- Still expose what callers **need to reason about**: cost, failure modes, ordering guarantees.

#### 5. Doesn't leak (much)
- **Law of leaky abstractions** (Spolsky): every non-trivial abstraction leaks somewhere.
  - An ORM leaks SQL performance.
  - A network call can't really behave like a local call.
- Good abstractions leak **predictably** and give you a **sanctioned escape hatch** (raw SQL, a timeout parameter).

#### 6. Consistent level of detail
- Everything inside it works at the **same level**, answering the same *kind* of question:
  - **What** are we doing? (business steps)
  - **How** is it done? (mechanics)

**Everyday analogy: making tea**

| Mixed levels ❌ | Consistent level ✅ |
|---|---|
| 1. Boil water | 1. Boil water |
| 2. Open the cupboard, grip the mug handle with your right hand, lift it 20 cm, place it on the counter | 2. Get a mug |
| 3. Add the tea bag | 3. Add the tea bag |
| 4. Pour | 4. Pour |

- On the left, step 2 zooms in to muscle movements, so the least important step takes up most of your attention.

**In code: mixed levels ❌**

```python
def register_user(form):
    validate(form)

    # suddenly: low-level string/byte fiddling
    email = form["email"].strip().lower()
    if "@" not in email or email.count("@") > 1:
        raise ValueError("bad email")
    salt = os.urandom(16)
    hashed = hashlib.pbkdf2_hmac("sha256", form["pw"].encode(), salt, 100_000)

    save_user(email, hashed, salt)
    send_welcome_email(email)
```

- `validate`, `save_user` and `send_welcome_email` are **business steps**.
- The middle section is **mechanics** (string cleanup, salts, hash algorithms).
- The reader keeps switching between *what* and *how*.

**In code: consistent level ✅**

```python
def register_user(form):
    validate(form)
    email = normalize_email(form["email"])
    password_hash = hash_password(form["pw"])
    save_user(email, password_hash)
    send_welcome_email(email)
```

- Every line is a business step, so the function reads like a **summary of the process**.
- The mechanics still exist but are pushed **down** into `normalize_email` and `hash_password`, at *their* level.

**Why it matters**
- **Readability:** you understand the function at a glance.
- **Change isolation:** switching hash algorithms only touches `hash_password`, and `register_user` never changes.
- **Reveals missing abstractions:** a chunk that's "lower" than its neighbours is usually an **unnamed concept** waiting to be extracted.

**How to spot it**
- Read top to bottom and mark each line as **"what"** or **"how"**. If the answer switches partway through, the levels are mixed.
- Related: the **Stepdown Rule** (*Clean Code*). Code reads like a top-down story, and each function sits one level of detail below its caller.

#### 7. Hard to misuse
- Invalid states **can't be represented**, required steps **can't be skipped**, and defaults are **safe**.
- Types help, e.g. an `Email` type instead of `string`.

#### 8. Stable interface, free-to-change internals
- Callers depend on the **contract**.
- You can rewrite everything behind it **without touching callers**.

### When to create one
- **Rule of three:** wait until you've seen the pattern about 3 times. Abstracting from one example means guessing.
- **"Duplication is far cheaper than the wrong abstraction"** (Sandi Metz).
  - A bad abstraction attracts flags and special cases (`if (isAdminReport && !legacyMode)`) until nobody understands it.
- Abstract at boundaries that **really vary**: external services, I/O, business rules that change.
- ❌ Don't abstract "in case we switch databases someday."

### Warning signs of a bad abstraction

| Symptom | What it usually means |
|---|---|
| Boolean/mode parameters keep piling up | Two concepts forced into one |
| Callers must know the internals to use it correctly | It leaks or hides the wrong things |
| Every change touches the abstraction **and** all callers | The boundary is in the wrong place |
| Pass-through methods that only forward calls | Shallow layer, no value |
| Hard to describe in one sentence | The concept isn't clear |
| Name is `Manager` / `Helper` / `Utils` | Purpose isn't single, or isn't known |

### Quick checklist
Before introducing an abstraction, ask:
1. What exactly does it **hide**, and is that likely to change?
2. Can a caller use it correctly after reading **only the interface**?
3. Is the interface meaningfully **simpler** than the implementation?
4. Can I describe it in **one sentence without "and"**?
5. Does the **name** honestly match what it does?
6. Is everything inside at the **same level** of detail?

If you can't answer yes to most of these, keep the concrete code for now.

### Further reading
- *A Philosophy of Software Design* by John Ousterhout (deep vs shallow modules)
- "On the Criteria To Be Used in Decomposing Systems into Modules" by David Parnas, 1972 (information hiding)
- "The Law of Leaky Abstractions" by Joel Spolsky
- "The Wrong Abstraction" by Sandi Metz
- *Clean Code* by Robert C. Martin (SRP, Stepdown Rule)
