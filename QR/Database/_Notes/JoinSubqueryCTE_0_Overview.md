## Database
### JOIN vs Subquery vs CTE

#### [Back to Database contents](../_Contents.md)

They **overlap, but they're not the same thing**. They're different kinds of tool:

- **JOIN** is an **operation**: it combines rows from two tables **side by side**.
- **Subquery** is a **placement**: a query written **inside** another query.
- **CTE** (Common Table Expression) is a **naming device**: a query given a **name** with `WITH`, so the main query can refer to it.

They overlap because many problems can be solved with any of the three, often with the **same plan** underneath. Each can also do things the others can't.

### One problem, three ways

*"Show customers in London who have unpaid orders"* (using the `lab` tables from [Indexing Demo](IndexingDemo.md)):

**JOIN**
```sql
SELECT DISTINCT c.customer_id, c.name
FROM lab.customers c
JOIN lab.orders o ON o.customer_id = c.customer_id
WHERE c.city = 'London' AND o.status = 'unpaid';
```

**Subquery**
```sql
SELECT c.customer_id, c.name
FROM lab.customers c
WHERE c.city = 'London'
  AND EXISTS (SELECT 1 FROM lab.orders o
              WHERE o.customer_id = c.customer_id AND o.status = 'unpaid');
```

**CTE**
```sql
WITH unpaid_customers AS (
    SELECT DISTINCT customer_id
    FROM lab.orders
    WHERE status = 'unpaid'
)
SELECT c.customer_id, c.name
FROM lab.customers c
JOIN unpaid_customers u ON u.customer_id = c.customer_id
WHERE c.city = 'London';
```

- All three return the **same customers**.
- The **CTE version still uses a JOIN**. A CTE doesn't replace joins. It **organises** a query, which may contain joins.

### 1. JOIN: combining tables side by side

```
customers              orders                  JOIN result
┌────┬───────┐        ┌────┬─────────┐        ┌────┬───────┬────┬─────────┐
│ id │ name  │   +    │ id │ cust_id │   →    │ id │ name  │ id │ cust_id │
└────┴───────┘        └────┴─────────┘        └────┴───────┴────┴─────────┘
```

- **Purpose:** you need **columns from more than one table** in the result.
- Types: `INNER`, `LEFT`, `RIGHT`, `FULL OUTER`, `CROSS`. More detail in [JOIN in depth](JoinSubqueryCTE_1_Join.md).
- ⚠️ **It can multiply rows.**
  - A customer with 5 unpaid orders appears **5 times**, which is why the JOIN example needed `DISTINCT`.
  - Common bug: joining to a "many" table, then getting inflated `SUM`s or counts.

### 2. Subquery: a query inside a query

More detail in [Subquery in depth](JoinSubqueryCTE_2_Subquery.md).

| Where | Example | Use |
|---|---|---|
| **`WHERE` with `EXISTS` / `IN`** | `WHERE EXISTS (SELECT 1 FROM orders ...)` | **Filter** by whether related rows exist |
| **`WHERE` with a single value** | `WHERE total > (SELECT AVG(total) FROM orders)` | Compare with a computed value |
| **`SELECT` list** (scalar subquery) | `(SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.customer_id) AS order_count` | Add one computed value per row |
| **`FROM`** (derived table) | `FROM (SELECT ... GROUP BY ...) AS t` | Treat a result as a temporary table |

- **Correlated subquery:** refers to the outer query (`o.customer_id = c.customer_id`).
  - Conceptually it runs **once per outer row**, but the planner usually rewrites it into a join.
- **`EXISTS` doesn't multiply rows.** It only asks "is there at least one match?", so there are no duplicates and no `DISTINCT`. Plans call this a **semi-join**.
- **`NOT EXISTS`** is the clean way to ask "customers with **no** orders".
  - ⚠️ Avoid `NOT IN` when the subquery can return `NULL`: a single `NULL` makes `NOT IN` return **no rows at all**.

### 3. CTE: a named query

More detail in [CTE in depth](JoinSubqueryCTE_3_CTE.md).

```sql
WITH monthly_totals AS (
    SELECT customer_id, date_trunc('month', created_at) AS month, SUM(total) AS spent
    FROM lab.orders
    GROUP BY customer_id, date_trunc('month', created_at)
),
big_spenders AS (
    SELECT customer_id
    FROM monthly_totals
    GROUP BY customer_id
    HAVING AVG(spent) > 500
)
SELECT c.name, c.city
FROM lab.customers c
JOIN big_spenders b ON b.customer_id = c.customer_id;
```

- **Purpose: readability.** It breaks a complex query into **named steps** that read top to bottom, instead of nested subqueries.
  - This is the "consistent level of detail" idea from [Abstraction](../../Languages/_Notes/Abstraction.md), applied to SQL.
- **Reuse within one statement:** a CTE can be referenced several times in the main query.
- **Scope:** exists **only for that one statement**. It isn't stored anywhere (unlike a view or a temp table).

#### What only a CTE can do: recursion

```sql
-- Walk an org chart: everyone under manager 1, at any depth
WITH RECURSIVE reports AS (
    SELECT employee_id, manager_id, name, 1 AS level          -- start: direct reports
    FROM employees WHERE manager_id = 1
    UNION ALL
    SELECT e.employee_id, e.manager_id, e.name, r.level + 1   -- repeat: reports of the previous level
    FROM employees e
    JOIN reports r ON e.manager_id = r.employee_id
)
SELECT * FROM reports;
```

- The second part **repeats**, adding one level each time, until a round finds **no new rows**.
- Used for **hierarchies and graphs**: org charts, category trees, bill of materials, folder structures.
- SQL Server writes it **without** the word `RECURSIVE`: `WITH reports AS (...)`.

#### Why JOINs can only go a "fixed" number of levels

There's no specific number set by the database. **The depth is decided by how many joins you write**, because each self-join adds exactly one level.

```sql
SELECT e1.name AS level1, e2.name AS level2, e3.name AS level3
FROM employees e1
LEFT JOIN employees e2 ON e2.manager_id = e1.employee_id   -- level 2
LEFT JOIN employees e3 ON e3.manager_id = e2.employee_id   -- level 3
WHERE e1.manager_id = 1;
```

- This handles **exactly 3 levels**, because there are 3 copies of the table (`e1`, `e2`, `e3`).
- Someone at **level 4** is **silently missed**.
- 10 levels means 10 joins. You must **know the maximum depth in advance**, and the query breaks when the hierarchy grows deeper.
- A recursive CTE's depth is decided **by the data when it runs**: 3 levels today, 12 next year, and the same query handles both.

#### Recursion limits (safety, not design)

These exist to stop **infinite loops**, e.g. bad data where A manages B and B manages A.

| Database | Limit |
|---|---|
| **SQL Server** | **100** levels by default; beyond that the query fails with an error. Change with `OPTION (MAXRECURSION n)`: up to **32,767**, or `0` for unlimited. |
| **PostgreSQL** | **No limit.** A cycle loops until memory or a timeout stops it. Protect with a level check (`WHERE r.level < 50`) or the **`CYCLE`** clause (PostgreSQL 14+). |
| **Oracle** | Supports recursive `WITH`, plus its own older `CONNECT BY` syntax with `NOCYCLE` to handle loops. |

```sql
-- SQL Server: allow up to 500 levels
WITH reports AS ( ... )
SELECT * FROM reports
OPTION (MAXRECURSION 500);
```

| | Self-JOINs | Recursive CTE |
|---|---|---|
| Depth decided by | **How many joins you write** | **The data**, at run time |
| Deeper data than expected | Silently missed | Handled automatically |
| Limit | Whatever you wrote | Safety limit only (SQL Server: 100 by default; PostgreSQL: none) |

### Performance: is one faster?

In modern databases, **usually not**. The planner often rewrites them into the **same plan**:
- `EXISTS` / `IN` subqueries usually become **semi-joins**.
- Derived tables and simple CTEs are usually **inlined** into the main query.

There are a few differences between databases worth knowing:

| | PostgreSQL | SQL Server |
|---|---|---|
| **CTE behaviour** | Since **v12**: inlined, unless referenced **more than once** (then computed once and stored, i.e. *materialized*). Force it with `AS MATERIALIZED` / `AS NOT MATERIALIZED`. **Before v12: always materialized**, which blocked index use and was a common performance trap. | **Always inlined.** A CTE referenced 3 times may be **computed 3 times**. If that's expensive, use a **temp table** instead. |

- Don't guess: **check the plan** with `EXPLAIN (ANALYZE, BUFFERS)` (or the actual plan in SSMS) and compare (see [Indexing Demo](IndexingDemo.md)).

### When to use which

| You want to... | Use |
|---|---|
| Show columns from several tables together | **JOIN** |
| Filter by "has related rows" / "has no related rows" | **`EXISTS` / `NOT EXISTS`** subquery (no duplicates) |
| Compare with one computed value (e.g. above average) | **Scalar subquery** |
| Break a complex query into readable steps | **CTE** |
| Use the same intermediate result more than once | **CTE** (check its behaviour in your database) |
| Walk a hierarchy of unknown depth | **Recursive CTE** (the only option) |

> **JOIN** combines tables, a **subquery** nests one query inside another, and a **CTE** names a query so it can be read and reused, or used recursively.
> They often produce the **same plan**. Choose the one that states your **intent** most clearly, then check the plan if performance matters.

### Practice
1. In the `lab` schema, write *"customers who have **never** placed an order"* in two ways:
   - `LEFT JOIN ... WHERE o.order_id IS NULL`
   - `NOT EXISTS`

   Run both with `EXPLAIN (ANALYZE, BUFFERS)`. Are the plans the same? (First add a few customers with ids 100001–100005, since every existing customer probably has orders.)
2. If employee 5 is listed as manager of employee 2, and employee 2 as manager of employee 5, what happens to the recursive CTE in PostgreSQL? How does adding `WHERE r.level < 50` change that?
