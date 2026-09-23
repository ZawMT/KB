# Blocks, Procs, Lambdas, and Wrapped Methods

Code: [`05/bpl.rb`](../05/bpl.rb)

## The core distinction
All four represent "a chunk of code you can run later," but they differ in whether they're an **object**, whether they're **named**, and how strict they are about **arguments** and **return**.

| | Named? | Object? | Storable in a variable? |
|---|---|---|---|
| `def...end` method | Yes | No (a method, not an object) | No, unless wrapped |
| Block | No | No | No - must be captured first |
| Proc | No | Yes | Yes |
| Lambda | No | Yes (a `Proc`, `lambda? == true`) | Yes |

## Block
Not an object - just syntax attached to a method call (`do...end` or `{}`). It has no independent existence; it can't be written on its own, only as `some_method { ... }`. A method receives it implicitly and either:
- runs it with **`yield`**, without ever naming/capturing it as a parameter, or
- captures it explicitly as a `Proc` via **`&name`** in the parameter list.

```ruby
def run_with_block
  yield("from a block")
end
run_with_block { |msg| puts "Block says: #{msg}" }

def run_with_captured_block(&blk)
  blk.call("captured block")
end
run_with_captured_block { |msg| puts "Captured says: #{msg}" }
```

**`&` does its work on the receiving end (the `def`), not the call site.** At the call site, the literal block `{ |msg| ... }` is just attached syntax - nothing is "assigned" yet. `&blk` in the parameter list is what converts the incoming block into a `Proc` object and binds it to `blk`.

The reverse also works: if you already have a `Proc`/lambda/Method object, `&` unwraps it back into block form to pass along:
```ruby
existing = Proc.new { |msg| puts msg }
run_with_captured_block(&existing)
```

### `yield`
- Calls whatever block was attached to the current method call, directly, without creating a Proc object (slightly cheaper than `&blk`).
- Works regardless of how many *regular* parameters the method has - the block is always separate from the named parameter list.
- Can be called more than once in the same method.
- Raises `LocalJumpError` if no block was given - guard with `block_given?`.
- **Reusability payoff**: the method owns the fixed procedure (loop, setup/teardown), the block owns the variable behavior. Same method, different block per call, different outcome - this is how `each`, `map`, `select`, `times`, etc. all work under the hood.

## Proc
An actual object (`Proc.new { ... }` or `proc { ... }`). Storable, passable, callable later via `.call`, `.()`, or `[]`.
```ruby
say = Proc.new { |name| "hi #{name}" }
say.call("Alice")
say.("Bob")
say["Cleo"]
```
**Lenient about argument count** - missing args become `nil`, extra args are ignored (no error either way).

## Lambda
Also a `Proc` under the hood (`lambda.is_a?(Proc) == true`, `lambda.lambda? == true`), but stricter:
- **Enforces arity** - wrong number of arguments raises `ArgumentError`.
- **`return` only exits the lambda itself**, not the enclosing method.

```ruby
say = lambda { |name| "hi #{name}" }
say2 = ->(name) { "hi #{name}" }   # arrow literal, same thing
```

### The `return` difference (the practical gotcha)
```ruby
def proc_return_demo
  p = Proc.new { return "from proc" }
  p.call
  "never reached"   # proc's return exits the whole enclosing method
end

def lambda_return_demo
  l = lambda { return "from lambda" }
  result = l.call
  "method continues, got: #{result}"   # lambda's return only exits the lambda
end
```
A bare `Proc`'s `return` tries to return from the method that *created* it - if that method has already returned (e.g. the proc escaped and is called later), this raises `LocalJumpError`. Lambdas don't have this danger.

## Wrapping a named method as an object
A `def...end` method is not an object by itself - can't be stored or passed around directly. `method(:name)` wraps it into a `Method` object, which behaves like a Proc/lambda from that point on:
```ruby
def square(n) = n * n

squarer = method(:square)
squarer.call(5)          # => 25
squarer.(6)               # => 36

[1, 2, 3].map(&squarer)   # => [1, 4, 9] - .to_proc + & lets it act as a block
```
`squarer.is_a?(Proc)` is `false` (Method is its own class) until explicitly converted with `.to_proc`.

## Rule of thumb
- **Block** - one-off inline behavior for a single call, don't need to store/reuse it. Use `yield` if you don't need it as an object; use `&blk` if you do.
- **Proc** - need a reusable, storable callable, and don't mind lenient arguments or the `return`-escapes-the-method risk.
- **Lambda** - need a reusable, storable callable that behaves more like a real function (checked arguments, contained `return`). Generally the safer default over `Proc` when in doubt.
- **`method(:name)`** - need to treat an already-defined named method as a passable object (e.g. to hand to `map`/`select` via `&`).
