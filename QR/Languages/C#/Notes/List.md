## C#
### List: what happens when an entry is removed

#### [Back to C# contents](../_Contents.md)

Interview question: *What happens when an entry is deleted from a list in C#?*

### `List<T>` is backed by an array

Inside, a `List<T>` is basically this:

```csharp
private T[] _items;    // the backing array (its length = Capacity)
private int _size;     // how many slots are actually used (= Count)
private int _version;  // bumped on every change
```

### What `list.RemoveAt(index)` does

For `list = [A, B, C, D, E]` with capacity 8, calling `RemoveAt(1)` removes `B`:

```
before:  _items = [A, B, C, D, E, _, _, _]   _size = 5
                      ↑ remove
step 1:  _size-- → 4
step 2:  Array.Copy: shift everything after index 1 one slot to the left
         _items = [A, C, D, E, E, _, _, _]
step 3:  clear the now-unused last slot (if T is/contains a reference type)
         _items = [A, C, D, E, null, _, _, _]
step 4:  _version++
```

1. **Elements after the removed one are shifted left** with `Array.Copy`. The list has no gaps, so indexes stay contiguous. This is why removal is **O(n)**: removing near the front means moving almost everything. Removing the **last** element is O(1), because nothing needs to move.
2. **The old last slot is cleared** to `default`. Otherwise the array would still hold a reference to that object, and the garbage collector couldn't free it. For value types with no references (like `int`), .NET skips this step.
3. **`_version` is incremented.** That's how a `foreach` notices that the list changed.

### What `list.Remove(item)` does

`Remove(item)` = `IndexOf(item)` + `RemoveAt(index)`:

- It **searches linearly** with `EqualityComparer<T>.Default`, so it uses `Equals`/`IEquatable<T>`. That's O(n) too.
- It removes **only the first match**.
- It returns `bool`: `false` if nothing was found. It doesn't throw.

### Things interviewers usually look for

**Capacity doesn't shrink.** Removing items lowers `Count`, but the backing array stays the same size. Call `TrimExcess()` if you want to free that memory.

**The object isn't destroyed.** Removing it from the list only removes one reference to it. If other code still holds a reference, the object lives on. The GC frees it only when nothing references it anymore.

**Removing inside `foreach` throws**, because of `_version`:

```csharp
foreach (var x in list)
    if (x < 0) list.Remove(x);   // ❌ InvalidOperationException: Collection was modified
```

Some safe alternatives:

```csharp
list.RemoveAll(x => x < 0);            // ✅ best: one pass, O(n) total

for (int i = list.Count - 1; i >= 0; i--)  // ✅ loop backwards, so shifting doesn't skip items
    if (list[i] < 0) list.RemoveAt(i);
```

Looping **forwards** with `RemoveAt(i)` doesn't throw, but it **skips** the element that shifted into position `i`.

**Removing many items one by one is O(n²).** `RemoveAll` does it in a single pass with O(n), because it compacts the list once instead of shifting after every removal.

**Other collections behave differently:**

| Collection | Remove cost | Why |
|---|---|---|
| `List<T>` | O(n) | shift + linear search |
| `LinkedList<T>` (given the node) | O(1) | just re-link neighbours, no shifting |
| `HashSet<T>` / `Dictionary<K,V>` | O(1) average | hash lookup, no ordering to maintain |

### Interview answer

*"`List<T>` wraps an array. Removing an item shifts all later elements left with `Array.Copy`, clears the last slot so the GC can collect the object, decrements `Count` and bumps an internal version. Capacity stays the same. It's O(n), except for the last element, and changing the list during `foreach` throws because of that version check."*
