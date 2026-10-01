## Database
### CTE in depth

#### [Back to Database contents](../_Contents.md)

- A detailed look at **CTEs**, part of [JOIN vs Subquery vs CTE](JoinSubqueryCTE_0_Overview.md).
- A CTE is a **named query**, defined with `WITH`, that the main query can refer to.

```sql
WITH customer_totals AS (            -- the CTE: a name + a query
    SELECT customer_id, SUM(total) AS spent
    FROM orders
    GROUP BY customer_id
)
SELECT * FROM customer_totals;       -- the main query uses it like a table
```

### What "CTE" means

**CTE = Common Table Expression**

| Word | Meaning |
|---|---|
| **Table** | Its result is shaped like a **table**: rows and columns |
| **Expression** | It's **computed from a query**, not stored. An expression that produces a table, like `price * qty` produces a number. |
| **Common** | Defined **once** at the top and **shared** by the whole statement. The main query (or a later CTE) can refer to it, as many times as needed. |

- **Table expression** is the SQL standard's general term for anything that produces a table: a table name, a derived table `(SELECT ...) AS t`, a CTE.
- The difference is **"common"**:
  ```sql
  -- Derived table: written inline, used in that one place
  SELECT * FROM (SELECT ...) AS t;

  -- CTE: written once, shared
  WITH t AS (SELECT ...)
  SELECT * FROM t JOIN t AS t2 ON ...;
  ```
- **Other names:**
  - **"WITH clause"** / **"WITH query"**, after the keyword (the PostgreSQL docs use *WITH Queries*)
  - **"Subquery factoring"**, Oracle's term: "factoring out" a subquery so it's written once
- Added to the SQL standard in **SQL:1999**, together with **recursive** CTEs.
- **Scope:** a CTE lives for **one statement only**. When the statement finishes, it's gone.

### Is it essential?

**Most of the time a CTE is a readability tool, not a necessity.** Almost anything written with a CTE can also be written with subqueries, unless it's **recursive**.

| Level | Situation |
|---|---|
| **Essential** (no real alternative in one statement) | Recursive queries; data-modifying CTEs (PostgreSQL) |
| **Strongly preferred** (the alternative is clearly worse) | Reusing an intermediate result; multi-step logic; filtering on window functions; de-duplicating rows |
| **Just nicer** | Any query that's easier to read split into named steps |

### 1. Essential: recursive queries

The **only thing a subquery or join cannot do** in a single query: walk a hierarchy or graph of **unknown depth**.

```sql
WITH RECURSIVE category_tree AS (
    -- Part 1: ANCHOR (the starting rows). Runs once and doesn't refer to category_tree.
    SELECT category_id, name, parent_id, 1 AS depth
    FROM categories WHERE parent_id IS NULL

    UNION ALL

    -- Part 2: RECURSIVE PART. Runs repeatedly.
    -- Here "category_tree" = only the rows added in the PREVIOUS round
    SELECT c.category_id, c.name, c.parent_id, t.depth + 1
    FROM categories c
    JOIN category_tree t ON c.parent_id = t.category_id
)
SELECT * FROM category_tree;
```

- Uses: org charts, category trees, folder structures, bill of materials (parts made of parts), graph paths (flight connections, network routes).
- Also useful for generating sequences where `generate_series` doesn't exist (MySQL, older SQL Server).

#### How it can refer to itself

`category_tree` names the whole chunk, and the chunk refers to `category_tree`. That isn't circular, because **inside** the chunk it means **only the rows found in the previous round**, not the whole result.

The database runs it as a **loop**:

```
1. Run the anchor                      → round 0's rows
2. Repeat:
     run the recursive part, using ONLY the previous round's rows as "category_tree"
     → new rows
     if there are no new rows: stop
3. The final category_tree = all rounds combined (UNION ALL)
```

#### Walkthrough

```
categories
┌─────────────┬─────────────┬───────────┐
│ category_id │ name        │ parent_id │
├─────────────┼─────────────┼───────────┤
│ 1           │ Electronics │ NULL      │
│ 2           │ Phones      │ 1         │
│ 3           │ Laptops     │ 1         │
│ 4           │ Smartphones │ 2         │
│ 5           │ Clothing    │ NULL      │
└─────────────┴─────────────┴───────────┘

Electronics (1)          Clothing (5)
├── Phones (2)
│   └── Smartphones (4)
└── Laptops (3)
```

| Round | `category_tree` inside the recursive part | New rows found |
|---|---|---|
| **0** (anchor) | n/a | Electronics (1, depth 1), Clothing (5, depth 1) |
| **1** | {1, 5} | Phones (2, depth 2), Laptops (3, depth 2) |
| **2** | {2, 3}: round 1's rows **only** | Smartphones (4, depth 3) |
| **3** | {4} | **None → stop** |

**Final result** (all rounds combined):

| category_id | name | depth |
|---|---|---|
| 1 | Electronics | 1 |
| 5 | Clothing | 1 |
| 2 | Phones | 2 |
| 3 | Laptops | 2 |
| 4 | Smartphones | 3 |

| Where `category_tree` appears | What it means |
|---|---|
| **Inside** the recursive part | Only the **previous round's new rows**, a small changing set |
| **Outside**, in `SELECT * FROM category_tree` | The **final result**: all rounds combined |

- Each round looks only at the **newest** rows, so it never re-processes the same rows.
- The loop **ends** when a round finds nothing (no deeper children).
- ⚠️ A **cycle in the data** (A's parent is B, B's parent is A) finds new rows forever. Protect against it with `WHERE t.depth < 50`, the `CYCLE` clause (PostgreSQL 14+) or `MAXRECURSION` (SQL Server, default **100**). Details in the [Overview](JoinSubqueryCTE_0_Overview.md).
- **Compared with a recursive function:** the anchor is the **base case** and the recursive part is the **recursive step**. But the database runs it as a **loop, one level at a time** (breadth-first), which is why rows come out grouped by depth.

#### Rules
- The **anchor must not** refer to the CTE, because something has to start the loop.
- The two parts are joined with **`UNION ALL`** (or `UNION`, which also removes duplicates).
- The recursive part refers to the CTE **once**.
- PostgreSQL needs **`WITH RECURSIVE`**. SQL Server writes just `WITH` and detects it automatically.

### 2. Essential (PostgreSQL): data-modifying CTEs

In PostgreSQL, a CTE can contain `INSERT`, `UPDATE` or `DELETE` with `RETURNING`. The main query can use the affected rows, **all in one statement**, so it's **all or nothing**:

```sql
-- Move orders older than 2 years into an archive table, atomically
WITH moved AS (
    DELETE FROM orders
    WHERE created_at < now() - INTERVAL '2 years'
    RETURNING *
)
INSERT INTO orders_archive
SELECT * FROM moved;
```

- **Without it:** two statements (`INSERT ... SELECT`, then `DELETE`) in a transaction, and **both must use exactly the same condition**, or rows get lost or duplicated.
- Other uses: insert a parent row and its children together, or log what was updated.
- **SQL Server's equivalent:** the `OUTPUT` clause: `DELETE ... OUTPUT deleted.* INTO orders_archive`.

### 3. Strongly preferred: reusing an intermediate result

```sql
WITH customer_totals AS (
    SELECT customer_id, SUM(total) AS spent
    FROM orders
    GROUP BY customer_id
)
SELECT c.name, t.spent
FROM customer_totals t
JOIN customers c ON c.customer_id = t.customer_id
WHERE t.spent > (SELECT AVG(spent) FROM customer_totals);   -- used again
```

- With subqueries, you'd **copy the same `GROUP BY` subquery twice**. That's **WET** code inside SQL (see [Maintainability](../../Languages/_Notes/Maintainability.md)).
- ⚠️ **How many times is it computed?**
  - **PostgreSQL 12+:** a CTE referenced twice is computed **once** and reused (*materialized*). Control it with `AS MATERIALIZED` / `AS NOT MATERIALIZED`.
  - **SQL Server:** **always inlined**, so it may be computed **twice**. If that's expensive, use a **temp table**.

### 4. Strongly preferred: multi-step logic

Nested subqueries are read **inside-out**. CTEs read **top to bottom**, like a recipe:

```sql
WITH paid_orders AS (                -- step 1: filter
    SELECT * FROM orders WHERE status = 'paid'
),
monthly AS (                         -- step 2: aggregate
    SELECT customer_id, date_trunc('month', created_at) AS month, SUM(total) AS spent
    FROM paid_orders
    GROUP BY customer_id, date_trunc('month', created_at)
),
ranked AS (                          -- step 3: rank
    SELECT *, RANK() OVER (PARTITION BY month ORDER BY spent DESC) AS rnk
    FROM monthly
)
SELECT * FROM ranked WHERE rnk <= 3; -- step 4: top 3 customers per month
```

- Each step has a **name** that explains its purpose (consistent level of detail, see [Abstraction](../../Languages/_Notes/Abstraction.md)).
- A later CTE can use an earlier one (`monthly` uses `paid_orders`).
- **Debug step by step:** temporarily change the final `SELECT` to `SELECT * FROM monthly` and inspect that step alone.

### 5. Strongly preferred: filtering on window functions

`WHERE` runs **before** window functions are calculated, so you can't filter on them directly:

```sql
SELECT *, ROW_NUMBER() OVER (...) AS rn FROM orders WHERE rn = 1;   -- ❌ rn doesn't exist yet
```

- You need a wrapper (a subquery or a CTE). The CTE is usually clearer (step 3 → final `SELECT` above).
- Some databases (Snowflake, BigQuery, DuckDB) have a `QUALIFY` clause for this. PostgreSQL and SQL Server don't.

### 6. Strongly preferred: removing duplicate rows

Keep one row per duplicate group and delete the rest:

```sql
-- SQL Server: delete through the CTE
WITH ranked AS (
    SELECT *, ROW_NUMBER() OVER (PARTITION BY email ORDER BY customer_id) AS rn
    FROM customers
)
DELETE FROM ranked WHERE rn > 1;
```

```sql
-- PostgreSQL: CTEs aren't updatable, so join back by key
WITH ranked AS (
    SELECT customer_id, ROW_NUMBER() OVER (PARTITION BY email ORDER BY customer_id) AS rn
    FROM customers
)
DELETE FROM customers c
USING ranked r
WHERE c.customer_id = r.customer_id AND r.rn > 1;
```

- ⚠️ As with any `DELETE`, run the CTE as a `SELECT` first to see which rows will go.

### When a CTE is not the right tool

| Situation | Better choice |
|---|---|
| The result is needed by **several statements**, or is large and reused | **Temp table**: it can be indexed, and it lasts for the session |
| The logic is reused across **many queries or reports** | **View**, or materialized view (see [Caching](Caching.md)) |
| An expensive CTE referenced many times **in SQL Server** | **Temp table**, because SQL Server may recompute the CTE |
| A simple one-off filter | A plain `WHERE` or `EXISTS`. Don't wrap simple queries just for style. |

### Summary

> - **CTE = Common Table Expression**: a named, computed table, defined **once** and **shared** within **one statement**.
> - **Essential** only for **recursion** (and PostgreSQL's **data-modifying CTEs**).
> - **Strongly preferred** for **reusing** an intermediate result, **multi-step** logic, **window-function filters** and **de-duplication**.
> - Otherwise, a **readability tool**, and a very good one: named steps, read top to bottom, easy to debug.
> - In a recursive CTE, the self-reference means **only the previous round's rows**.
> - For results needed across statements, use a **temp table** or **view**.

### Practice

1. In the walkthrough, what would round 1 return if the anchor were `WHERE category_id = 2` (start from Phones)? What would the final result contain?
2. In the `lab` schema from [Indexing Demo](IndexingDemo.md), write *"each customer's most recent order"* using `ROW_NUMBER()` in a CTE. Then write it with a nested subquery. Which is easier to read? How would `LEFT JOIN LATERAL` (from [JOIN in depth](JoinSubqueryCTE_1_Join.md)) compare?
3. Rewrite the **reusing an intermediate result** example without a CTE. How many copies of the `GROUP BY` do you need?
