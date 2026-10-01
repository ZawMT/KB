## Database
### Subquery in depth

#### [Back to Database contents](../_Contents.md)

- A detailed look at **subqueries**, part of [JOIN vs Subquery vs CTE](JoinSubqueryCTE_0_Overview.md).
- A subquery is a **query written inside another query**.
- Examples use the sample `customers` and `orders` tables from [JOIN in depth](JoinSubqueryCTE_1_Join.md):
  - Customers: Alice (1), Bob (2), Carol (3, **no orders**)
  - Orders: 101 = 30 (Alice), 102 = 20 (Alice), 103 = 50 (Bob), 104 = 15 (**no customer**, `customer_id` is `NULL`)

### 1. Kinds of subquery

| Kind | Returns | Example | Rule |
|---|---|---|---|
| **Scalar** | 1 row, 1 column | `(SELECT MAX(total) FROM orders)` | Must return **at most one row** |
| **List** | Many rows, 1 column | `WHERE id IN (SELECT customer_id FROM orders)` | Used with `IN`, `ANY`, `ALL` |
| **Table** (derived table) | Many rows, many columns | `FROM (SELECT ...) AS t` | Behaves like a temporary table |
| **Existence** | True / false | `WHERE EXISTS (SELECT 1 FROM ...)` | Only checks whether a row exists |

Each can also be:
- **Uncorrelated:** runs on its own, conceptually **once**. Example: `(SELECT AVG(total) FROM orders)`.
- **Correlated:** refers to the outer query, so it conceptually runs **per outer row**. Example: `WHERE o.customer_id = c.customer_id`.

#### Where subqueries can appear

| Where | Example | Use |
|---|---|---|
| **`WHERE` with `EXISTS` / `IN`** | `WHERE EXISTS (SELECT 1 FROM orders ...)` | **Filter** by whether related rows exist |
| **`WHERE` with a single value** | `WHERE total > (SELECT AVG(total) FROM orders)` | Compare with a computed value |
| **`SELECT` list** (scalar) | `(SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.customer_id) AS order_count` | Add one computed value per row |
| **`FROM`** (derived table) | `FROM (SELECT ... GROUP BY ...) AS t` | Treat a result as a temporary table |

### 2. Traps that cause real bugs

#### ⚠️ A scalar subquery must return at most one row

```sql
SELECT name,
       (SELECT total FROM orders o WHERE o.customer_id = c.customer_id) AS total
FROM customers c;
```

- Alice has **2 orders**, so this fails:
  - PostgreSQL: `more than one row returned by a subquery used as an expression`
  - SQL Server: `Subquery returned more than 1 value`
- It might work in testing (one order per customer) and **fail later in production**.
- **Zero rows** returns `NULL`, not an error. That's why Carol gets `NULL`.
- **Fix:** aggregate (`MAX`, `SUM`, `COUNT`), or limit it deliberately (`ORDER BY ... LIMIT 1` / `TOP 1`).

#### ⚠️ `NOT IN` with a NULL returns nothing

```sql
WHERE customer_id NOT IN (SELECT customer_id FROM orders)   -- order 104 has NULL → no rows at all
```

- `x NOT IN (1, 2, NULL)` is never `TRUE`.
- **Always prefer `NOT EXISTS`**, which handles `NULL`s correctly (see [JOIN in depth](JoinSubqueryCTE_1_Join.md), anti join).

#### ⚠️ An unqualified column can silently refer to the outer query

```sql
-- orders has no column called "name"... but customers does
SELECT * FROM customers
WHERE customer_id IN (SELECT customer_id FROM orders WHERE name = 'Alice');
```

- You might expect an error ("column name doesn't exist in orders").
- Instead, SQL looks **outward** and finds `customers.name`. The subquery quietly becomes **correlated**, and the result is wrong with **no error**.
- Classic dangerous version, a typo in the selected column:
  ```sql
  DELETE FROM customers
  WHERE customer_id IN (SELECT customer_id FROM archived_ids);
  -- if archived_ids actually has a column "id", not "customer_id",
  -- customer_id resolves to customers.customer_id → matches EVERY row → deletes everything
  ```
- **Fix:** **always qualify columns with table aliases inside subqueries** (`o.customer_id`, `a.id`). Then a typo produces an **error** instead of a silent bug.

#### ⚠️ `ORDER BY` inside a subquery means nothing

- `FROM (SELECT ... ORDER BY total) t` does **not** guarantee the outer query's order.
- SQL Server rejects it unless you also use `TOP` / `OFFSET`. PostgreSQL allows it but doesn't promise to keep the order.
- **Order the outermost query.** Use `ORDER BY` inside only together with `LIMIT` / `TOP`, where it decides *which* rows are kept.

#### ⚠️ `ALL` with an empty subquery is always true

- "Greater than all of nothing" counts as true. See **ANY and ALL** below.

### 3. Rules that differ between databases

| Rule | PostgreSQL | SQL Server |
|---|---|---|
| Derived table needs an alias: `FROM (SELECT ...) AS t` | Required before v16, optional from v16 | **Required** |
| Multi-column `IN`: `WHERE (a, b) IN (SELECT x, y ...)` | ✅ Supported | ❌ Not supported, so use `EXISTS` |
| `ORDER BY` in a subquery without `TOP` | Allowed (but no effect on outer order) | ❌ Error |

- Simplest habit: **always alias derived tables**, and use `EXISTS` for multi-column checks.

### 4. ANY and ALL

They compare **one value with a list of values**, usually returned by a subquery.

```sql
value  operator  ANY (subquery)    -- TRUE if the comparison is true for AT LEAST ONE value
value  operator  ALL (subquery)    -- TRUE if the comparison is true for EVERY value
```

- `operator` is one of `=`, `<>`, `>`, `>=`, `<`, `<=`.
- **`SOME`** is another name for `ANY`. They're identical.

#### `> ALL`: bigger than every value

*"Orders bigger than **all** of Alice's orders"*

```sql
SELECT order_id, total
FROM orders
WHERE total > ALL (SELECT total FROM orders WHERE customer_id = 1);   -- Alice: (30, 20)
```

| order_id | total |
|---|---|
| 103 | 50 |

- 50 > 30 ✅ and 50 > 20 ✅, so it's kept.
- 30 > 30 ❌, so 101 is dropped. A single failed comparison is enough.
- **Same as** `> (SELECT MAX(total) ...)`: bigger than all means bigger than the largest.

#### `> ANY`: bigger than at least one value

*"Orders bigger than **any** of Alice's orders"*

```sql
SELECT order_id, total
FROM orders
WHERE total > ANY (SELECT total FROM orders WHERE customer_id = 1);   -- (30, 20)
```

| order_id | total |
|---|---|
| 101 | 30 |
| 103 | 50 |

- 30 > 20 ✅, so 101 is kept. Beating just one value is enough.
- 20 and 15 aren't bigger than either value, so they're dropped.
- **Same as** `> (SELECT MIN(total) ...)`: bigger than at least one means bigger than the smallest.

#### Most uses have a simpler equivalent

| `ANY` / `ALL` | Means | Simpler equivalent |
|---|---|---|
| `x = ANY (subquery)` | equals at least one | **`x IN (subquery)`** |
| `x <> ALL (subquery)` | differs from every one | **`x NOT IN (subquery)`**, with the same `NULL` trap |
| `x > ALL (subquery)` | bigger than the largest | `x > (SELECT MAX(...))` |
| `x > ANY (subquery)` | bigger than the smallest | `x > (SELECT MIN(...))` |
| `x < ALL (subquery)` | smaller than the smallest | `x < (SELECT MIN(...))` |
| `x < ANY (subquery)` | smaller than the largest | `x < (SELECT MAX(...))` |

- That's why `ANY`/`ALL` are **rare in practice**. `IN`, `NOT EXISTS`, `MAX` and `MIN` say the same thing and are more familiar.

#### ⚠️ Edge cases: empty lists and NULLs

This is where `ANY`/`ALL` differ from their "simple equivalents":

| Situation | `ANY` | `ALL` | `MAX` / `MIN` version |
|---|---|---|---|
| **Empty subquery** (no rows) | `FALSE`: no value to satisfy it | **`TRUE`**: nothing failed | `MAX` returns `NULL`, so the comparison isn't true and the row is dropped |
| **Subquery contains `NULL`** | `TRUE` if some value matches, otherwise `NULL` (row dropped) | `NULL` unless some value fails → row dropped | `MAX`/`MIN` **ignore** `NULL`s |

```sql
-- Customer 999 has no orders
WHERE total > ALL (SELECT total FROM orders WHERE customer_id = 999)   -- TRUE → EVERY order returned
WHERE total > (SELECT MAX(total) FROM orders WHERE customer_id = 999)  -- NULL → NO orders returned
```

- They look equivalent but give **opposite results** on empty data. Decide which behaviour you actually want.

#### Prefer `MAX` / `MIN`: simpler and usually safer

```sql
-- Same result as the > ALL example (order 103), but clearer
SELECT order_id, total
FROM orders
WHERE total > (SELECT MAX(total) FROM orders WHERE customer_id = 1);
```

- **Simpler:** "bigger than Alice's **largest** order" states the intent directly, and every SQL reader recognises `MAX`.
- **Usually safer:** in the two edge cases, the `MAX` version matches what people mean:

| Edge case | `> ALL (...)` | `> (SELECT MAX(...))` | Usually wanted |
|---|---|---|---|
| **No rows** (e.g. Carol, `customer_id = 3`) | "Bigger than all of nothing" is TRUE → **every** order | `MAX` = `NULL` → **no** orders | **No** orders |
| **A `NULL` in the list** (an order with no total) | Comparison with `NULL` is unknown → **no row can pass** | `MAX` **ignores** `NULL`, compares with the largest real value | Ignore the missing value |

- **Performance:** about the same. With an index on `orders(customer_id, total)`, PostgreSQL finds the `MAX` by reading **one index entry**. In `EXPLAIN`, look for `Index Only Scan` and `Limit` inside an `InitPlan`.
- **When `ALL` / `ANY` are still worth it:**
  - `= ANY (array)` in PostgreSQL, to pass a list as one parameter (below).
  - When you **want** "empty list → true", e.g. "orders that beat every competing bid; with no bids, every order qualifies."
  - To **recognise** them when reading other people's SQL.

> **Rule of thumb:** prefer `MAX` / `MIN`, `IN` and `NOT EXISTS`. Use `ALL` / `ANY` only when you need their specific behaviour.

#### Where `ANY` is really useful: PostgreSQL arrays

In PostgreSQL, `ANY` also works with **arrays**, not just subqueries:

```sql
SELECT * FROM orders WHERE order_id = ANY (ARRAY[101, 103]);
-- same as: WHERE order_id IN (101, 103)
```

- **Passing a list as one parameter:** `WHERE order_id = ANY ($1)`, where the app passes one array like `[101, 103]`.
  - With `IN`, the number of placeholders changes with the list size: `IN ($1, $2, $3...)`.
- The query text stays **identical** whatever the list length, which suits **prepared statements** and **plan caching** (see [Caching](Caching.md)).

#### Database support

| | PostgreSQL | SQL Server | Oracle | MySQL |
|---|---|---|---|---|
| `ANY` / `ALL` with a subquery | ✅ | ✅ | ✅ | ✅ |
| With a literal list: `> ALL (10, 20)` | ❌ (use an array) | ❌ | ✅ | ❌ |
| With an array: `= ANY (ARRAY[...])` | ✅ | ❌ | ❌ | ❌ |

### 5. Performance

- **`EXISTS` / `IN` are usually fine.** The planner turns them into **semi joins**.
- **Correlated subqueries in `WHERE`** are usually rewritten into joins too. Check the plan if in doubt.
- **Scalar subqueries in the `SELECT` list** are the ones to watch:
  ```sql
  SELECT c.name,
         (SELECT COUNT(*)        FROM orders o WHERE o.customer_id = c.customer_id),
         (SELECT SUM(total)      FROM orders o WHERE o.customer_id = c.customer_id),
         (SELECT MAX(created_at) FROM orders o WHERE o.customer_id = c.customer_id)
  FROM customers c;
  ```
  - That's three lookups **per customer**. In plans, look for `SubPlan` (PostgreSQL) with a high `loops=` count.
  - With an index on `orders(customer_id)` it's often fine. Without one, it's three scans per row.
  - **Better:** one `LEFT JOIN` to a pre-aggregated CTE, or a `LATERAL` / `APPLY` that computes all three at once.
- As always: **check the plan** instead of guessing (see [Indexing Demo](IndexingDemo.md)).

### 6. When a subquery is the natural choice

- **Comparing with an aggregate.** `WHERE` can't use aggregates directly:
  ```sql
  WHERE total > AVG(total)                                -- ❌ not allowed
  WHERE total > (SELECT AVG(total) FROM orders)           -- ✅
  ```
- **Filtering by existence:** `EXISTS` / `NOT EXISTS`.
- **Inside `UPDATE` / `DELETE`:**
  ```sql
  DELETE FROM customers c
  WHERE NOT EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = c.customer_id);
  ```
  - ⚠️ Run it as a `SELECT` first (`SELECT * FROM customers c WHERE NOT EXISTS ...`) to check which rows will be affected.

### 7. Readability

- **More than one level of nesting:** switch to a **CTE**. Named steps read top to bottom, and nested brackets don't.
- Keep each subquery **small and single-purpose**, the same "one purpose, one level" idea as [Abstraction](../../Languages/_Notes/Abstraction.md).

### Summary

> 1. A scalar subquery must return **≤ 1 row**. Zero rows gives `NULL`.
> 2. Use **`NOT EXISTS`**, never `NOT IN`, when `NULL`s are possible.
> 3. **Qualify every column** inside a subquery, so that missing columns can't silently match the outer table.
> 4. `ORDER BY` inside a subquery doesn't order the final result.
> 5. **`ANY`** = true for at least one value, **`ALL`** = true for every value. ⚠️ `ALL` with an **empty** list is always true. Prefer `MAX` / `MIN`, `IN` and `NOT EXISTS`.
> 6. Watch **scalar subqueries in `SELECT`**: they run per row.
> 7. More than one level of nesting → use a **CTE**.

### Practice

1. Predict the result, then run it:
   ```sql
   SELECT name FROM customers
   WHERE customer_id IN (SELECT customer_id FROM customers WHERE city = 'Paris');
   ```
   Then change the inner query to `SELECT o.customer_id FROM orders o`. What changes, and why?
2. `SELECT order_id FROM orders WHERE total < ALL (SELECT total FROM orders WHERE customer_id = 2);` (Bob has one order: 50). What's returned?
3. The same query with `customer_id = 3` (Carol has no orders). How many rows come back, and why?
4. Rewrite the three scalar subqueries in **Performance** as one `LEFT JOIN` to a pre-aggregated CTE.
