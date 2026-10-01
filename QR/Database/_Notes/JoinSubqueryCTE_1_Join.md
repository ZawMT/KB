## Database
### JOIN in depth

#### [Back to Database contents](../_Contents.md)

- A detailed look at **JOIN**, part of [JOIN vs Subquery vs CTE](JoinSubqueryCTE_0_Overview.md).
- A JOIN **combines rows from two tables side by side**, matching them on a condition.
- Use it when you need **columns from more than one table** in the result.

### Sample data

All examples use these two small tables:

```sql
CREATE TABLE customers (
    customer_id  int PRIMARY KEY,
    name         text,
    city         text
);

CREATE TABLE orders (
    order_id     int PRIMARY KEY,
    customer_id  int REFERENCES customers(customer_id),   -- NULL = guest checkout
    total        numeric(10,2)
);

INSERT INTO customers VALUES
    (1, 'Alice', 'London'),
    (2, 'Bob',   'Paris'),
    (3, 'Carol', 'Berlin');      -- has no orders

INSERT INTO orders VALUES
    (101, 1,    30.00),
    (102, 1,    20.00),
    (103, 2,    50.00),
    (104, NULL, 15.00);          -- guest order, no customer
```

```
customers                         orders
┌─────────────┬───────┬────────┐  ┌──────────┬─────────────┬───────┐
│ customer_id │ name  │ city   │  │ order_id │ customer_id │ total │
├─────────────┼───────┼────────┤  ├──────────┼─────────────┼───────┤
│ 1           │ Alice │ London │  │ 101      │ 1           │ 30.00 │
│ 2           │ Bob   │ Paris  │  │ 102      │ 1           │ 20.00 │
│ 3           │ Carol │ Berlin │  │ 103      │ 2           │ 50.00 │
└─────────────┴───────┴────────┘  │ 104      │ NULL        │ 15.00 │
                                  └──────────┴─────────────┴───────┘
```

- **Carol** has no orders. **Order 104** has no customer.
- These two "unmatched" rows are what make the JOIN types behave differently.

### Types of JOIN

#### Overview

| JOIN | Keeps | Unmatched rows |
|---|---|---|
| `INNER JOIN` | Only rows that **match on both sides** | Dropped from both sides |
| `LEFT [OUTER] JOIN` | **All** rows from the **left** table + matches | Left kept, right side filled with `NULL` |
| `RIGHT [OUTER] JOIN` | **All** rows from the **right** table + matches | Right kept, left side filled with `NULL` |
| `FULL [OUTER] JOIN` | **All** rows from **both** tables | Both kept, missing side filled with `NULL` |
| `CROSS JOIN` | **Every combination** (no condition) | n/a |
| Self join | A table joined **to itself** | Depends on the type used |
| Semi join | Left rows that **have** a match (each once) | Written with `EXISTS` |
| Anti join | Left rows that have **no** match | Written with `NOT EXISTS` or `LEFT JOIN ... IS NULL` |
| `LATERAL` / `APPLY` | Right side computed **per left row** | `CROSS` = inner, `OUTER` = left |

- `OUTER` is optional: `LEFT JOIN` = `LEFT OUTER JOIN`.
- `JOIN` alone means `INNER JOIN`.

#### 1. INNER JOIN: only matches

```sql
SELECT c.name, o.order_id, o.total
FROM customers c
INNER JOIN orders o ON o.customer_id = c.customer_id;
```

| name | order_id | total |
|---|---|---|
| Alice | 101 | 30.00 |
| Alice | 102 | 20.00 |
| Bob | 103 | 50.00 |

- **Carol** is dropped (no orders), and **order 104** is dropped (no customer).
- **Alice appears twice**, once per matching order (see **Row multiplication** below).
- **Use when:** you only care about rows that have a partner. This is the most common join.

#### 2. LEFT JOIN: everything from the left, plus matches

```sql
SELECT c.name, o.order_id, o.total
FROM customers c
LEFT JOIN orders o ON o.customer_id = c.customer_id;
```

| name | order_id | total |
|---|---|---|
| Alice | 101 | 30.00 |
| Alice | 102 | 20.00 |
| Bob | 103 | 50.00 |
| Carol | NULL | NULL |

- **Carol is kept**, with `NULL`s where order data would be.
- **Use when:** the left side is the "main list" and the right side is **optional** extra data.
  - "All customers, with their orders **if any**."
  - "All products, with their discount **if one exists**."
- **The most common outer join.** Most people write `LEFT` and put the main table first.

#### 3. RIGHT JOIN: everything from the right, plus matches

```sql
SELECT c.name, o.order_id, o.total
FROM customers c
RIGHT JOIN orders o ON o.customer_id = c.customer_id;
```

| name | order_id | total |
|---|---|---|
| Alice | 101 | 30.00 |
| Alice | 102 | 20.00 |
| Bob | 103 | 50.00 |
| NULL | 104 | 15.00 |

- **Order 104 is kept**, with `NULL` for the customer.
- `A RIGHT JOIN B` is the **same** as `B LEFT JOIN A`. It's just written from the other side.
- **Use when:** rarely. Most teams **swap the table order and use `LEFT JOIN`**, so every query reads "main table first".

#### 4. FULL OUTER JOIN: everything from both sides

```sql
SELECT c.name, o.order_id, o.total
FROM customers c
FULL OUTER JOIN orders o ON o.customer_id = c.customer_id;
```

| name | order_id | total |
|---|---|---|
| Alice | 101 | 30.00 |
| Alice | 102 | 20.00 |
| Bob | 103 | 50.00 |
| Carol | NULL | NULL |
| NULL | 104 | 15.00 |

- Both unmatched rows are kept.
- **Use when:** **comparing or reconciling** two sources.
  - "Compare last month's price list with this month's: what's new, removed, or in both?"
  - "Match bank transactions against our payment records, and show what's missing on **either** side."
- ⚠️ **MySQL doesn't support `FULL OUTER JOIN`.** Use `LEFT JOIN ... UNION ... RIGHT JOIN` instead.

#### 5. CROSS JOIN: every combination

```sql
SELECT c.name, s.size
FROM customers c
CROSS JOIN (VALUES ('S'), ('M'), ('L')) AS s(size);
```

- **No `ON` condition.** Every left row is paired with every right row: 3 customers × 3 sizes = **9 rows**.
- Also called a **Cartesian product**.

```
sizes          colours            sizes CROSS JOIN colours
┌──────┐      ┌───────┐          ┌──────┬───────┐
│ S    │      │ Red   │          │ S    │ Red   │
│ M    │  ×   │ Blue  │    →     │ S    │ Blue  │
│ L    │      └───────┘          │ M    │ Red   │
└──────┘                         │ M    │ Blue  │
                                 │ L    │ Red   │
                                 │ L    │ Blue  │
                                 └──────┴───────┘
```

- Rarely what you want for **related** tables (customers × orders), but very useful when you **deliberately need every combination**.

##### Use 1: Generating combinations

```sql
SELECT p.name, s.size, c.colour
FROM products p
CROSS JOIN (VALUES ('S'), ('M'), ('L'))  AS s(size)
CROSS JOIN (VALUES ('Red'), ('Blue'))    AS c(colour)
WHERE p.name = 'T-shirt';
```

- 1 product × 3 sizes × 2 colours = **6 variants**, without typing them out.
- Other examples: every **room × time slot** (booking grid), every **team × team** (fixtures), every **seat row × seat number** (seat map).

##### Use 2: Filling gaps, showing the zeros (the most useful one in reporting)

A normal `GROUP BY` only shows what **exists**. If a store sold nothing on Tuesday, Tuesday is **missing**, not shown as 0.

```sql
-- ❌ Missing days don't appear at all
SELECT store_id, sale_date, SUM(amount)
FROM sales
GROUP BY store_id, sale_date;
```

**Fix:** CROSS JOIN builds the **complete grid** (every store × every date), then a `LEFT JOIN` adds the actual data:

```sql
-- PostgreSQL
SELECT s.store_id, d.day::date AS sale_date, COALESCE(SUM(x.amount), 0) AS total
FROM stores s
CROSS JOIN generate_series(DATE '2026-09-01', DATE '2026-09-30', INTERVAL '1 day') AS d(day)
LEFT JOIN sales x ON x.store_id = s.store_id AND x.sale_date = d.day::date
GROUP BY s.store_id, d.day
ORDER BY s.store_id, d.day;
```

- Every store gets **30 rows**, and days with no sales show **0**.
- **CROSS JOIN → LEFT JOIN pattern:** first build every row that *should* exist, then attach what *does* exist.
- Essential for **charts**, where a missing day would otherwise be quietly skipped instead of showing a dip.
- In a data warehouse, **`DimDate`** (see [Normalisation](Normalisation.md)) plays the role of the date list.
- `generate_series` is explained below.

##### Use 3: Attaching a single-row value to every row

Cross joining with a **one-row** result just adds its columns to every row, without multiplying anything:

```sql
-- Each order's share of the grand total
SELECT o.order_id, o.total,
       ROUND(100.0 * o.total / t.grand_total, 1) AS pct_of_total
FROM orders o
CROSS JOIN (SELECT SUM(total) AS grand_total FROM orders) t;
```

- Any number × 1 row = the same number of rows.
- Also handy for **parameters**: `CROSS JOIN (SELECT DATE '2026-01-01' AS start_date) params`, so the value is defined once and used in many places (DRY inside a query).
- Modern alternative for this case: window functions, e.g. `SUM(total) OVER ()`.

##### Use 4: Comparing every pair

```sql
-- Every pair of customers in the same city (duplicate checks, matching, etc.)
SELECT a.name, b.name, a.city
FROM customers a
CROSS JOIN customers b
WHERE a.city = b.city
  AND a.customer_id < b.customer_id;   -- skip self-pairs and (B, A) duplicates
```

- Cross join + `WHERE` is equivalent to an inner join (`JOIN ... ON a.city = b.city`). The cross join just makes "all pairs, then filter" explicit.
- `a.customer_id < b.customer_id` keeps each pair **once** and skips pairing a row with itself.
- ⚠️ Grows as **n²**: 10,000 customers means 100 million pairs before filtering.

##### Use 5: Generating test data

Cross joins **multiply** rows quickly (see also [Indexing Demo](IndexingDemo.md)):

```sql
-- 1,000 × 1,000 = 1,000,000 rows from two small series
SELECT a.n * 1000 + b.n AS id
FROM generate_series(0, 999) AS a(n)
CROSS JOIN generate_series(0, 999) AS b(n);
```

##### Use 6: Unpivoting columns into rows

```sql
-- quarterly_sales(region, q1, q2, q3, q4)  →  one row per region per quarter
SELECT r.region, q.quarter,
       CASE q.quarter WHEN 'Q1' THEN r.q1 WHEN 'Q2' THEN r.q2
                      WHEN 'Q3' THEN r.q3 WHEN 'Q4' THEN r.q4 END AS amount
FROM quarterly_sales r
CROSS JOIN (VALUES ('Q1'), ('Q2'), ('Q3'), ('Q4')) AS q(quarter);
```

- Turns a "wide" table into a "long" one, which is easier to group, filter and chart.
- Tidier alternatives: `CROSS JOIN LATERAL (VALUES ...)` (PostgreSQL), `CROSS APPLY (VALUES ...)` or `UNPIVOT` (SQL Server).

##### ⚠️ Cautions

- **Size explodes:** rows = left × right. **Multiply the row counts** in your head before running one.
- **Accidental cross joins** come from:
  - the old comma style with a forgotten `WHERE`: `FROM customers, orders`
  - an `ON` condition that's always true, or joins on the wrong column
  - **Symptoms:** a query suddenly slow or huge, or totals many times too big. 10,000 × 10,000 rows = **100 million** rows.
- **Make it intentional and visible:** write `CROSS JOIN` explicitly, never a comma.
- In `EXPLAIN`, it usually appears as a **Nested Loop** with **no join condition**.

| Use | Pattern |
|---|---|
| All combinations (variants, grids, fixtures) | `A CROSS JOIN B` |
| Show missing rows as zero | `A CROSS JOIN dates` → `LEFT JOIN` facts → `COALESCE(..., 0)` |
| Attach a single value or parameter to every row | `CROSS JOIN (SELECT ... one row)` |
| Compare every pair | Self cross join + `WHERE a.id < b.id` |
| Multiply rows for test data | `CROSS JOIN generate_series(...)` |
| Unpivot columns into rows | `CROSS JOIN (VALUES ...)` |

> A CROSS JOIN is useful **when every combination is what you want**, and a bug when it isn't. Always write it explicitly so readers can tell which.

##### `generate_series`: a function used as a table

- A built-in **PostgreSQL** function whose output can be used **anywhere a table can**: in `FROM`, joins and CTEs.
- Most functions return **one value** (`upper('abc')` → `'ABC'`). **Set-returning functions** (the SQL standard calls them **table functions**) return **many rows**.
- Nothing is stored. The rows are produced **while the query runs** and then discarded.

```sql
SELECT * FROM generate_series(1, 5);   -- 5 rows: 1, 2, 3, 4, 5
```

**Reading the line from Use 2**

```sql
CROSS JOIN generate_series(DATE '2026-09-01', DATE '2026-09-30', INTERVAL '1 day') AS d(day)
```

| Part | Meaning |
|---|---|
| `generate_series(start, stop, step)` | A row for each value from `start` to `stop`, going up by `step` |
| `DATE '2026-09-01'` | A date literal (typed constant) |
| `INTERVAL '1 day'` | The step size |
| `AS d` | **Table alias**: refer to this "table" as `d` |
| `(day)` | **Column alias**: name its single output column `day` |

- It acts like a 30-row table `d` with one column `day`, so `d.day` works like `s.store_id`.
- **Why `d.day::date`?** With dates and an interval step, PostgreSQL works with **timestamps** (`2026-09-01 00:00:00`). `::date` casts back to a plain date so it matches `sale_date`.

**Common forms**

```sql
SELECT * FROM generate_series(1, 10);                  -- 1, 2, ..., 10
SELECT * FROM generate_series(0, 100, 25);             -- 0, 25, 50, 75, 100
SELECT * FROM generate_series(10, 1, -1);              -- counting down
SELECT * FROM generate_series(
    TIMESTAMP '2026-09-01 09:00', TIMESTAMP '2026-09-01 17:00', INTERVAL '30 minutes'
);                                                      -- time slots
```

**Other table functions in PostgreSQL**

| Function | Turns... into rows |
|---|---|
| `generate_series(...)` | A range of numbers or timestamps |
| `unnest(array)` | Each element of an array |
| `json_array_elements(...)` / `jsonb_array_elements(...)` | Each element of a JSON array |
| `jsonb_each(...)` | Each key/value pair of a JSON object |
| `regexp_split_to_table(text, pattern)` | Each piece of a split string |
| Your own: `CREATE FUNCTION ... RETURNS TABLE (...)` | Whatever your function returns |

```sql
SELECT unnest(string_to_array('0711,0722', ',')) AS phone;   -- one row per phone (the 1NF example)
```

**Equivalents in other databases** (`generate_series` is PostgreSQL-specific)

| Database | Equivalent |
|---|---|
| **SQL Server 2022+** | `GENERATE_SERIES(start, stop [, step])`, **numbers only**. For dates, add the numbers with `DATEADD` |
| **Older SQL Server** | A recursive CTE, a permanent **numbers (tally) table**, or a cross join of system views |
| **Oracle** | `SELECT LEVEL FROM dual CONNECT BY LEVEL <= 30` |
| **MySQL 8+** | A recursive CTE |

```sql
-- SQL Server 2022: the same 30 dates
SELECT DATEADD(day, value, CAST('2026-09-01' AS date)) AS day
FROM GENERATE_SERIES(0, 29);
```

- SQL Server also has other table-valued functions: `STRING_SPLIT`, `OPENJSON`, and your own **inline table-valued functions** (TVFs).
- **Why it pairs well with CROSS JOIN:** a calendar grid needs a date list that **doesn't exist** in any table. `generate_series` creates it on the fly, and `CROSS JOIN` pairs it with every store. A data warehouse would use a permanent **`DimDate`** table instead.

#### 6. Self join: a table joined to itself

Not a separate keyword, just a table used **twice** with **different aliases**.

```sql
-- Each employee with their manager's name
SELECT e.name AS employee, m.name AS manager
FROM employees e
LEFT JOIN employees m ON m.employee_id = e.manager_id;
```

- `LEFT` keeps the CEO, who has no manager.
- **Use when:**
  - **Hierarchies, one level at a time:** employee → manager, category → parent category.
  - **Comparing rows in the same table:** customers in the **same city**, or overlapping bookings.
- For hierarchies of **unknown depth**, use a **recursive CTE** instead. Each self join adds exactly one level (see [JOIN vs Subquery vs CTE](JoinSubqueryCTE_0_Overview.md)).

#### 7. Semi join: "has at least one match"

There's no `SEMI JOIN` keyword in SQL. You write it with **`EXISTS`** (or `IN`), and the plan shows it as a semi join.

```sql
-- Customers who have placed at least one order
SELECT c.name
FROM customers c
WHERE EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = c.customer_id);
```

| name |
|---|
| Alice |
| Bob |

- **Alice appears once**, even with two orders, so **no `DISTINCT`** is needed.
- Compare with `INNER JOIN`, which returns Alice **twice**.
- **Use when:** you want to **filter** by related rows but **don't need their columns**.

#### 8. Anti join: "has no match"

```sql
-- Customers who have never ordered: two common ways
-- A. NOT EXISTS
SELECT c.name
FROM customers c
WHERE NOT EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = c.customer_id);

-- B. LEFT JOIN + IS NULL
SELECT c.name
FROM customers c
LEFT JOIN orders o ON o.customer_id = c.customer_id
WHERE o.order_id IS NULL;
```

| name |
|---|
| Carol |

- Both usually produce the **same plan** (an anti join).
- **`NOT EXISTS` states the intent more clearly**: "customers where no order exists".
- In `LEFT JOIN ... IS NULL`, test a column that's **never NULL in real rows**, like the primary key (`o.order_id`), not an optional column.
- ⚠️ **Avoid `NOT IN`** if the subquery can return `NULL`:
  ```sql
  SELECT name FROM customers
  WHERE customer_id NOT IN (SELECT customer_id FROM orders);   -- returns NOTHING
  ```
  - Order 104's `customer_id` is `NULL`, and `x NOT IN (1, 2, NULL)` is never `TRUE`, so **no rows** come back.

#### 9. LATERAL / APPLY: "for each row, run this"

A normal join's right side is **independent** of the left. With `LATERAL` (PostgreSQL, Oracle) or `APPLY` (SQL Server, Oracle), the right side can **refer to the current left row**, like a per-row function.

Classic use: **"top N per group"**, e.g. each customer's 2 latest orders.

```sql
-- PostgreSQL
SELECT c.name, o.order_id, o.total
FROM customers c
LEFT JOIN LATERAL (
    SELECT order_id, total
    FROM orders
    WHERE orders.customer_id = c.customer_id     -- refers to the current customer
    ORDER BY order_id DESC
    LIMIT 2
) o ON true;
```

```sql
-- SQL Server
SELECT c.name, o.order_id, o.total
FROM customers c
OUTER APPLY (
    SELECT TOP 2 order_id, total
    FROM orders
    WHERE orders.customer_id = c.customer_id
    ORDER BY order_id DESC
) o;
```

| PostgreSQL | SQL Server | Behaves like |
|---|---|---|
| `CROSS JOIN LATERAL (...)` / `JOIN LATERAL (...) ON true` | `CROSS APPLY` | INNER: drops left rows with no result |
| `LEFT JOIN LATERAL (...) ON true` | `OUTER APPLY` | LEFT: keeps left rows, with `NULL`s |

- **Use when:** the right side needs `LIMIT`/`TOP`, `ORDER BY` or a calculation **per left row**.
- Alternative for "top N per group": `ROW_NUMBER() OVER (PARTITION BY ...)` in a subquery or CTE.
- With an index on `orders(customer_id, order_id DESC)`, each lookup is a quick index seek.

### ON vs WHERE: the most common LEFT JOIN trap

For **INNER** joins, putting a condition in `ON` or `WHERE` gives the **same result**. For **outer** joins, it **doesn't**.

```sql
-- ❌ Condition in WHERE: Carol disappears
SELECT c.name, o.order_id, o.total
FROM customers c
LEFT JOIN orders o ON o.customer_id = c.customer_id
WHERE o.total > 25;
```

| name | order_id | total |
|---|---|---|
| Alice | 101 | 30.00 |
| Bob | 103 | 50.00 |

```sql
-- ✅ Condition in ON: all customers kept
SELECT c.name, o.order_id, o.total
FROM customers c
LEFT JOIN orders o ON o.customer_id = c.customer_id
                  AND o.total > 25;
```

| name | order_id | total |
|---|---|---|
| Alice | 101 | 30.00 |
| Bob | 103 | 50.00 |
| Carol | NULL | NULL |

**Why:**
- `ON` decides **which right rows match**. Unmatched left rows are **still kept**, with `NULL`s.
- `WHERE` runs **after** the join and **filters the result**. Carol's row has `total = NULL`, and `NULL > 25` isn't `TRUE`, so it's removed.
- A `WHERE` condition on the **right** table **silently turns a LEFT JOIN into an INNER JOIN**.

> **Rule:** with a LEFT JOIN, conditions on the **right** table usually belong in **`ON`**. Conditions on the **left** (main) table belong in **`WHERE`**.

### ⚠️ Row multiplication (fan-out)

A join returns **one row per matching pair**, so joining to a "many" side **multiplies** rows. That's correct behaviour, but it breaks aggregates if you forget about it.

Add a `payments` table: order 101 was paid in two instalments.

```
payments: (order_id 101, amount 15), (order_id 101, amount 15), (order_id 103, amount 50)
```

```sql
-- ❌ Total order value per customer, joined to payments as well
SELECT c.name, SUM(o.total) AS order_value, SUM(p.amount) AS paid
FROM customers c
JOIN orders o        ON o.customer_id = c.customer_id
LEFT JOIN payments p ON p.order_id    = o.order_id
GROUP BY c.name;
```

| name | order_value | paid |
|---|---|---|
| Alice | **80.00** ❌ (real: 50.00) | 30.00 |
| Bob | 50.00 | 50.00 |

- Order 101 (30.00) matched **2 payments**, so it appears **twice**: 30 + 30 + 20 = **80**.

```sql
-- ✅ Aggregate each "many" side first, then join one row per order
WITH paid_per_order AS (
    SELECT order_id, SUM(amount) AS paid
    FROM payments
    GROUP BY order_id
)
SELECT c.name, SUM(o.total) AS order_value, SUM(p.paid) AS paid
FROM customers c
JOIN orders o              ON o.customer_id = c.customer_id
LEFT JOIN paid_per_order p ON p.order_id    = o.order_id
GROUP BY c.name;
```

- **How to spot it:** totals are **too big**, or `COUNT(*)` is larger than expected. Check the row count **before** `GROUP BY`.
- **Fixes:**
  - **Pre-aggregate** in a CTE or derived table (above). This is where JOIN and **CTE** work together.
  - Use **`EXISTS`** if you only need to filter, not to bring in columns.
- ❌ **Don't** "fix" it with `DISTINCT` or `SUM(DISTINCT ...)`. Two different payments of the same amount would be wrongly merged.

### More things to know

#### NULL never matches NULL
- `ON a.x = b.x` is **not TRUE** when both are `NULL` (`NULL = NULL` is unknown).
- That's why order 104 (`customer_id NULL`) never matches any customer.
- If you really need NULLs to match: PostgreSQL `IS NOT DISTINCT FROM`; SQL Server 2022+ `IS NOT DISTINCT FROM`, or `ISNULL(a.x, -1) = ISNULL(b.x, -1)` (which can't use an index).

#### Multiple joins: a LEFT then an INNER
```sql
SELECT c.name, o.order_id, p.amount
FROM customers c
LEFT JOIN orders o   ON o.customer_id = c.customer_id
INNER JOIN payments p ON p.order_id   = o.order_id;     -- ❌ Carol disappears again
```
- The `INNER JOIN` needs `o.order_id`, which is `NULL` for Carol, so her row is dropped.
- Once you start a chain of `LEFT JOIN`s from the main table, **keep the following joins `LEFT`**, unless dropping those rows is intended.

#### Non-equi joins
The `ON` condition doesn't have to be `=`:
```sql
-- Which price band does each order fall into?
SELECT o.order_id, o.total, b.band_name
FROM orders o
JOIN price_bands b ON o.total >= b.min_total AND o.total < b.max_total;
```
- Useful for ranges, date validity periods (SCD Type 2 from [Normalisation](Normalisation.md)) and overlap checks.

#### Syntax variants
| Syntax | Notes |
|---|---|
| `JOIN ... ON a.id = b.id` | ✅ Standard, always clear. **Prefer this.** |
| `JOIN ... USING (customer_id)` | Shorter when the column names are the same. PostgreSQL, Oracle, MySQL; **not SQL Server**. |
| `NATURAL JOIN` | ❌ Joins on **every** column with the same name. Adding a column (e.g. `updated_at` to both tables) silently changes the join. Avoid it. |
| `FROM a, b WHERE a.id = b.id` | ❌ Old (pre-1992) comma style. Forgetting the `WHERE` gives an accidental cross join. |

#### Indexes for joins
- Index the **foreign key** columns used in `ON`, e.g. `orders(customer_id)`. Neither PostgreSQL nor SQL Server does this automatically (see [Indexing](Indexing.md)).

### How the database runs a join (physical join types)

The JOIN **types** above describe **what** result you get. The database separately chooses **how** to compute it. You'll see these names in `EXPLAIN` plans:

| Algorithm | How it works | Good when |
|---|---|---|
| **Nested Loop** | For each outer row, look up matches in the inner table | Outer side is **small**, and the inner side has an **index** on the join column |
| **Hash Join** | Build a hash table from the smaller side, then probe it with the other | **Large**, unsorted inputs with an **equality** condition (`=`) |
| **Merge Join** | Both sides **sorted** on the join key, then walk them together like a zip | Both inputs already sorted (e.g. via indexes), or large sorted inputs |

- The planner chooses based on table sizes, indexes and statistics. Same SQL, different algorithm depending on the data.
- Warning sign: a **Nested Loop** with a huge `loops=` count on an inner side that **has no index**.
- Names in other databases: SQL Server shows *Nested Loops*, *Hash Match*, *Merge Join*. Oracle shows `NESTED LOOPS`, `HASH JOIN`, `MERGE JOIN`.

### When to use what

| You want... | Use |
|---|---|
| Rows with a partner on **both** sides | `INNER JOIN` |
| A main list, plus optional related data | `LEFT JOIN` (main table first) |
| Everything from both sides, to compare / reconcile | `FULL OUTER JOIN` |
| Every combination (variants, calendar grid) | `CROSS JOIN` |
| Rows related to other rows in the **same** table | Self join |
| Filter by "has a related row", no columns needed | `EXISTS` (semi join), not `JOIN` + `DISTINCT` |
| Filter by "has **no** related row" | `NOT EXISTS` (or `LEFT JOIN ... IS NULL`), not `NOT IN` |
| Top N / a calculation **per row** | `LATERAL` (PG, Oracle) / `APPLY` (SQL Server, Oracle) |
| Hierarchy of unknown depth | Recursive **CTE**, not repeated self joins |
| Join to an **aggregated** "many" side | Pre-aggregate in a **CTE** / subquery, then `JOIN` |

#### JOIN vs subquery vs CTE, from the JOIN point of view
- **JOIN** when you need **columns** from the other table in the result.
- **Subquery (`EXISTS` / `NOT EXISTS`)** when the other table is only used to **filter**. No row multiplication, and the intent is clearer.
- **CTE** to **prepare** a side before joining it (pre-aggregate, filter, rank), or to keep a multi-join query **readable**.

### A note on Venn diagrams

JOINs are often shown as overlapping circles. They help remember **which unmatched rows are kept**, but they're **misleading** about everything else:
- They suggest each row appears **at most once**. In reality one row can appear **many times** (fan-out).
- They don't show `CROSS JOIN`, `LATERAL` or non-equi joins.
- A better mental model: **"a join pairs rows that satisfy the `ON` condition. Outer joins also keep unpaired rows from one or both sides, padded with `NULL`."**

### Summary

> - **INNER** = matches only. **LEFT** = all of the left plus matches. **FULL** = everything. **CROSS** = every combination.
> - **Semi / anti joins** are written with `EXISTS` / `NOT EXISTS`.
> - Watch for: **`WHERE` on the right side of a LEFT JOIN**, **row multiplication** in aggregates, and **`NOT IN` with `NULL`s**.

### Practice
Using the sample data:
1. How many rows does `customers CROSS JOIN orders` return?
2. Write "each customer with their **number of orders**, including customers with **0**". Which join type? Should you use `COUNT(*)` or `COUNT(o.order_id)`, and why?
3. Write "all orders over 25, with the customer's name **if** there is a customer". Where does the `total > 25` condition go: `ON` or `WHERE`? (Careful: the main table is different this time.)
4. In the `lab` schema from [Indexing Demo](IndexingDemo.md), compare the plans of `NOT EXISTS` and `LEFT JOIN ... IS NULL` for "customers with no orders". Which join algorithm does PostgreSQL choose?
5. Write "every customer × every month of 2026, with their order count", including **0** for months with no orders. Which joins do you need, and in what order? (The sample `orders` table has no date column, so add an `order_date` column with a few 2026 dates first.)
6. Before running it, write down what `SELECT * FROM generate_series(1, 3) a CROSS JOIN generate_series(1, 2) b;` returns. Then run it in pgAdmin to check.
