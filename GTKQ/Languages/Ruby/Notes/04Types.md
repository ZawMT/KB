# Types - Conversion and a Few Type Details

## "Casting" vs "conversion"
Ruby is dynamically typed, and there's no type declaration for variables or class attributes (e.g. `attr_accessor :age` doesn't restrict `age` to numbers - it'll accept anything).

Ruby folks usually call `.to_i`/`.to_s`/etc. **"type conversion"** rather than "casting." In languages like C, casting often means reinterpreting the *same* underlying bits as a different type. In Ruby, `.to_i` etc. actually create a **brand new object** of a different class - so it's conversion, not casting in the strict sense. Still commonly called "casting" colloquially.

## Common conversion methods
| Method | Converts to | Example |
|---|---|---|
| `.to_i` | Integer | `"42".to_i` -> `42` |
| `.to_f` | Float | `"3.14".to_f` -> `3.14` |
| `.to_s` | String | `42.to_s` -> `"42"` |
| `.to_a` | Array | |
| `.to_sym` | Symbol | `"name".to_sym` -> `:name` |
| `.to_h` | Hash | |
| `.to_r` | Rational (exact fraction) | `"0.5".to_r` -> `(1/2)` |
| `.to_c` | Complex (real + imaginary) | `2.to_c` -> `(2+0i)` |

`gets` always returns a `String`, so converting to the right type on input (e.g. `gets.chomp.to_i` for an age) is your own responsibility - nothing in the class enforces it.

## Symbols (`:name`)
A Symbol is a lightweight, immutable label/identifier - meant for things that act as labels, not as text data.
- **Same symbol = same object in memory.** `:name.object_id == :name.object_id` is always `true`; two separate `"name"` Strings are *not* the same object, even with identical content. This makes Symbols cheap to compare (identity check, not character-by-character).
- **Immutable** - can't be mutated like a String can.
- Typical uses: hash keys (`{ name: "Alice" }` is shorthand for `{ :name => "Alice" }`), method names in metaprogramming, fixed sets of states/options (`:pending`, `:active`, `:done`).

## Integer - min/max
Unlike languages with a fixed-size `int` (e.g. 32-bit range in Java/C), Ruby's `Integer` has **no fixed min or max** - it automatically grows to arbitrary precision, limited only by available memory.
```ruby
2**100
# => 1267650600228229401496703205376
```
(Older Ruby versions had separate `Fixnum`/`Bignum` classes for small vs. large integers; these were unified into a single `Integer` class as of Ruby 2.4+.)

## Float - min/max
`Float` *does* have real limits, since it's IEEE 754 double-precision under the hood:
```ruby
Float::MAX      # => 1.7976931348623157e+308
Float::MIN      # => 2.2250738585072014e-308 (smallest positive normal value, not most negative)
Float::EPSILON  # => 2.220446049250313e-16   (smallest difference recognizable near 1.0)
```
