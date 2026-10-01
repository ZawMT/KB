## Database
### Indexing

#### [Back to Database contents](../_Contents.md)

- An **index** is a separate data structure that lets the database **find rows without scanning the whole table**.
- Like the index at the back of a book: instead of reading every page to find "replication", you look it up and jump to page 214.
- Hands-on scripts for pgAdmin: [Indexing Demo](IndexingDemo.md)

### Without vs with an index

```sql
SELECT * FROM Customers WHERE email = 'alice@x.com';
```

- **No index:** the database reads **every row** and checks each one, which is a **full table scan**. 10 million rows means 10 million checks.
- **Index on `email`:** the database looks the value up in the index, gets the row's location, and reads just that row in **about 3–4 page reads**.

### What the DBMS creates

`CREATE INDEX` makes the DBMS read the table, **sort** the indexed values and build a **B-tree** from them.
- It's **stored in pages** in the database files, like table data, so it uses disk space and gets cached in memory.
- Each entry is **key value(s) + a pointer to the row**.
- It's **not a table you can query**, but it's a real separate structure. You can see its size with `\di+` (PostgreSQL) or `sp_spaceused` (SQL Server).
- The DBMS **keeps it in sync automatically**: every insert, update and delete updates the index in the same transaction.
- **Exception:** a **clustered index** (SQL Server) isn't a separate copy. The table itself is stored as that B-tree.

```
Index on Customers(email)              Customers table (heap)
┌──────────────────┬─────────┐         ┌─────┬───────┬──────────────┐
│ alice@x.com      │ → row 7 │         │ ... │ Bob   │ bob@y.com    │
│ bob@y.com        │ → row 2 │         │ ... │ ...   │ ...          │
│ carol@z.com      │ → row 9 │         │ ... │ Alice │ alice@x.com  │
└──────────────────┴─────────┘         └─────┴───────┴──────────────┘
        sorted                                  unsorted
```

### How a B-tree works

The **default** index in both SQL Server and PostgreSQL.

```
                  [ M ]                      ← root
               /         \
        [ D  H ]          [ R  W ]           ← internal pages
       /   |   \         /   |   \
   [A-C] [D-G] [H-L]  [M-Q] [R-V] [W-Z]      ← leaf pages: sorted keys + row pointers
```

- Keys are kept **sorted**, so the database searches by **narrowing down**, like a dictionary.
- The tree stays **shallow**: billions of rows usually need only **3–5 levels**, because each page holds hundreds of entries.
- A B-tree supports:
  - **equality:** `=`
  - **ranges:** `<`, `>`, `BETWEEN`
  - **prefix searches:** `LIKE 'abc%'`
  - **`ORDER BY`** without a separate sort step

#### Walking the tree: `WHERE customer_id = 724301`

```
Level 0 (root page)
┌──────────────────────────────────────────────┐
│  < 250000  │  250000–499999  │  500000–749999 │ ≥ 750000 │
└──────────────────────────────────────┬───────┘
                                       │  724301 is in 500000–749999
Level 1 (internal page)                ▼
┌──────────────────────────────────────────────┐
│ ... │ 720000–722499 │ 722500–724999 │ ... │
└──────────────────────────┬───────────────────┘
                           │  724301 is in 722500–724999
Level 2 (leaf page)        ▼
┌──────────────────────────────────────────────┐
│ 724299 → (page 18233, slot 4)               │
│ 724300 → (page 18233, slot 5)               │
│ 724301 → (page 18233, slot 6)   ◄── found   │
│ 724302 → (page 18234, slot 1)               │
└──────────────────────────────────────────────┘
                           │
                           ▼
Table: read page 18233, slot 6 → the full row
```

- **3 index pages + 1 table page = 4 page reads**, instead of reading the whole table.
- **Range** (`BETWEEN 724301 AND 724310`): walk down to `724301`, then **read sideways** along the leaf pages, which are linked in order.
- That sideways walk is also why a B-tree returns rows **already sorted** for `ORDER BY`.

### Do we write "B-tree" in queries?

**No.** SQL is **declarative**: you say **what** you want, and the DBMS decides **how**.

- You only mention it when **creating** the index, and even then it's optional because it's the default:
  ```sql
  CREATE INDEX ix_customers_email ON Customers(email);
  CREATE INDEX ix_customers_email ON Customers USING btree (email);   -- PostgreSQL, explicit
  ```
  - PostgreSQL: write `USING ...` only for a **different** structure, e.g. `USING gin (attributes)`.
  - SQL Server: no `USING`. Use keywords like `CLUSTERED`, `NONCLUSTERED` or `COLUMNSTORE`.
- **Your query stays the same** before and after creating an index. Only the **plan** changes:
  ```
  -- before the index
  Seq Scan on customers
    Filter: (email = 'alice@x.com')

  -- after the index (same SQL!)
  Index Scan using ix_customers_email on customers
    Index Cond: (email = 'alice@x.com')
  ```
- The **query planner** sees the index, estimates the cost of a scan vs the index (using **statistics**) and picks the cheaper option.
- The plan is the **only place** you see whether an index was used.

#### Hints: forcing an index (last resort)
- **SQL Server:** `SELECT * FROM Customers WITH (INDEX(ix_customers_email)) WHERE ...`
- **PostgreSQL:** none built in. Use the `pg_hint_plan` extension, or `SET enable_seqscan = off;` for testing only.
- ⚠️ **Avoid in normal code.** A hint freezes one decision while the data keeps changing. If the planner chooses badly, fix the **cause**: stale statistics or a non-SARGable query.

#### Looking inside a real B-tree (optional, educational)
```sql
CREATE EXTENSION pageinspect;
SELECT * FROM bt_metap('ix_customers_email');          -- root page, number of levels
SELECT * FROM bt_page_items('ix_customers_email', 1);   -- entries on page 1
```

### Types of index

Index types aren't one flat list. Each index can be described in **four independent ways**:

```
CREATE UNIQUE INDEX ix_orders ON Orders(customer_id, created_at) INCLUDE (total) WHERE status = 'active';
       └─ 2. rules ─┘                 └──── 3. columns: composite ────┘ └ covering ┘ └── 3. filtered ──┘
                        (B-tree by default = 4. structure; non-clustered = 1. storage)
```

#### 1. Storage: how it relates to the table

| Type | What it is | Where |
|---|---|---|
| **Clustered** | The table **is** the index: rows stored in key order. **One per table.** | SQL Server (PK by default) |
| **Non-clustered / secondary** | Separate structure: sorted keys + pointers to rows. **Many per table.** | Both (PostgreSQL has only this kind) |

- With a non-clustered index, after finding the key the database usually does an extra **lookup** to fetch the rest of the row (SQL Server: *key lookup*; PostgreSQL: fetch from the **heap**).
- **PostgreSQL has no clustered index.** Tables are always unordered **heaps**. `CLUSTER` re-sorts a table once, but the order isn't maintained.

#### 2. Rules: does it enforce anything?

| Type | What it is | Where |
|---|---|---|
| **Unique** | Speeds lookups **and** rejects duplicate values | Both (auto-created for `PRIMARY KEY` and `UNIQUE`) |
| **Non-unique** | Just speeds lookups | Both (the default) |

#### 3. Contents: what goes into it

| Type | What it is | Example |
|---|---|---|
| **Single-column** | One column | `(email)` |
| **Composite** | Several columns. Order matters. | `(last_name, first_name)` |
| **Covering** | Holds every column a query needs, so no row lookup | `(customer_id) INCLUDE (total)` |
| **Filtered / partial** | Only rows matching a condition, so it's smaller | `... WHERE status = 'active'` |
| **Expression / computed** | Indexes the result of an expression | PG: `(LOWER(email))`; SQL Server: index on a computed column |
| **Descending** | Stored in descending order. Useful for mixed-order sorts. | `(customer_id, created_at DESC)` |

**Leftmost prefix rule (composite indexes)**
- `(last_name, first_name)` is sorted by `last_name`, then `first_name` within it, like a phone book.
- It helps queries on `last_name`, or `last_name` + `first_name`, but **not** `first_name` alone. A phone book can't find everyone called "John".
- Put the columns you filter by most often (with `=`) **first**.

#### 4. Structure: the data structure underneath

| Type | Good for | Where |
|---|---|---|
| **B-tree** | Equality, ranges, sorting: **almost everything** | Both (default) |
| **Hash** | Equality (`=`) only, no ranges or sorting | PostgreSQL; SQL Server only for in-memory tables |
| **GIN** | Values with many items: arrays, JSONB, full-text | PostgreSQL |
| **GiST / SP-GiST** | Geometry, ranges, nearest-neighbour | PostgreSQL (PostGIS) |
| **BRIN** | Huge tables naturally ordered on disk (time-series, logs). Very small. | PostgreSQL |
| **Columnstore** | Analytics (OLAP): stores data by column and compresses it | SQL Server |
| **Full-text** | Searching words inside text | SQL Server (PG uses GIN + `tsvector`) |
| **Spatial** | Geography and geometry | SQL Server (PG uses GiST) |
| **XML** | Querying XML columns | SQL Server |
| **Vector** (HNSW, IVFFlat) | Similarity search on embeddings (AI / RAG) | PG via `pgvector`; recent SQL Server / Azure SQL versions |

#### Which ones you'll actually use
1. **B-tree, non-clustered** on columns in `WHERE`, `JOIN` and `ORDER BY`, plus **foreign keys**.
2. **Unique**, mostly created for you by `PRIMARY KEY` and `UNIQUE`.
3. **Composite** with the right column order, made **covering** for hot queries.
4. **Filtered / partial** when queries always target a small subset.
5. **Special structures** only for special data: GIN for JSONB or full-text, columnstore for analytics, vector for embeddings.

> Start with a **B-tree**. Choose its **columns**, **order** and **includes** from real queries. Reach for a special structure only when the data or query pattern needs one.

### Primary keys and automatic indexes

| Constraint | Index created automatically? |
|---|---|
| `PRIMARY KEY` | ✅ Unique index (clustered by default in SQL Server) |
| `UNIQUE` | ✅ Unique index (non-clustered in SQL Server) |
| `FOREIGN KEY` | ❌ **No**, in both PostgreSQL and SQL Server |
| Ordinary columns | ❌ No |

- The DBMS **needs** an index to enforce uniqueness. Without one, every insert would scan the table.
- **PostgreSQL:** creates a unique B-tree named `<table>_pkey`. It's a secondary index, so the table isn't stored in PK order. See it with `\d tablename`.
- **SQL Server:** creates a **unique clustered index** by default, unless the table already has a clustered index or you write `PRIMARY KEY NONCLUSTERED`. See it with `EXEC sp_helpindex 'tablename';`.

#### ⚠️ Foreign keys are not indexed automatically

```sql
CREATE TABLE Orders (
    order_id     int PRIMARY KEY,                          -- indexed ✅
    customer_id  int REFERENCES Customers(customer_id)     -- NOT indexed ❌
);

CREATE INDEX ix_orders_customer_id ON Orders(customer_id);  -- add it yourself
```

Foreign key columns are used in:
- **joins**
- **filters** ("all orders for customer 42")
- **deletes on the parent**: deleting a customer makes the DB check `Orders` for references. Without an index, that's a **full scan per deleted row**, plus heavy locking.

(MySQL InnoDB *does* index foreign keys automatically, which is why people often assume every database does.)

#### Choosing the primary key (mainly SQL Server)
The PK is usually the **clustered** index, so it decides how the table is physically stored:
- ✅ **Ever-increasing** (`int IDENTITY`, `bigint`): new rows go at the end, so inserts stay fast and tidy.
- ⚠️ **Random GUIDs** (`NEWID()`): rows land at random positions, causing **page splits** and fragmentation.
  - Options: `NEWSEQUENTIALID()`, or a **non-clustered** GUID PK with clustering on something else.
- ✅ **Narrow** (4–8 bytes): every non-clustered index stores the clustered key as its row pointer, so a wide key makes **all** indexes bigger.
- PostgreSQL: matters less (no clustering), but random UUIDs still scatter index inserts. **UUIDv7** (time-ordered) helps.

### Pros and cons

**Pros**
- ✅ **Much faster reads:** lookups, joins, `WHERE`, `ORDER BY`, `GROUP BY`.
- ✅ **Enforces uniqueness** (unique indexes).
- ✅ **Faster joins**, especially with indexed foreign keys.
- ✅ **Better cache use:** fewer pages read, so hot data stays in memory (see [Caching](Caching.md)).

**Cons**
- ❌ **Slower writes:** every `INSERT`, `UPDATE` (on indexed columns) and `DELETE` must update **every** relevant index.
- ❌ **Extra storage:** each index is a partial copy of the data. Over-indexed tables can have indexes bigger than the table.
- ❌ **Maintenance:** **fragmentation** (SQL Server) or **bloat** (PostgreSQL), and statistics must stay current.
- ❌ **Not always used:** the planner may decide a full scan is cheaper.

### When an index can't help

- **Functions on the column:** `WHERE YEAR(order_date) = 2025`
  - ✅ Rewrite as a range: `WHERE order_date >= '2025-01-01' AND order_date < '2026-01-01'`
- **Leading wildcard:** `LIKE '%smith'`, because sorted order doesn't help when you don't know the start.
- **Low selectivity:** e.g. `is_active` where 95% of rows match, so a scan is cheaper.
- **Wrong column order** in a composite index (leftmost prefix rule).
- **Type mismatch:** comparing a `varchar` column to a number can force a conversion on every row.
- A query written so it **can** use an index is called **SARGable** ("Search ARGument able").

### Link to normalisation

- An index is a **controlled form of redundancy**: a sorted copy of some columns.
- Like denormalisation, it speeds up reads and costs writes and storage.
- **Unlike** denormalisation, the **DBMS keeps it in sync automatically**, so it can never be wrong.
- That's why indexes are the **first "cheaper fix"** to try before denormalising (see [Normalisation](Normalisation.md)).

### Our responsibilities (beyond `CREATE INDEX`)

You never *use* an index explicitly, because the planner decides that. But you're responsible for making sure the right indexes exist and stay healthy.

| When | What you do |
|---|---|
| **Design** | Choose indexes from **real queries**: columns, order, covering or not |
| **Writing queries** | Write **SARGable** queries that can use the index |
| **Verify** | Check the execution plan shows the index being used |
| **Monitor** | Find **missing**, **unused** and **duplicate** indexes |
| **Maintain** | Keep **statistics** current and deal with **fragmentation / bloat** |
| **Review** | Revisit indexes as the app and its queries change |

- In many teams, a **DBA** (or cloud automation) handles statistics and fragmentation, while **developers** handle choosing indexes, writing SARGable queries and checking plans.

### Verify (one query, one index)

**When:** whenever you add or change an index or a query.
**Question:** *"Does this index make this query faster?"*

#### Step 1: Run with the actual plan
- **PostgreSQL**
  ```sql
  EXPLAIN (ANALYZE, BUFFERS)
  SELECT order_id, total FROM Orders WHERE customer_id = 42;
  ```
  - `EXPLAIN` alone shows the **planned** approach.
  - `ANALYZE` **really runs** the query and adds actual times and rows. For `UPDATE`/`DELETE`, wrap it in `BEGIN; ... ROLLBACK;`.
  - `BUFFERS` shows the pages read from cache or disk.
- **SQL Server**
  ```sql
  SET STATISTICS IO, TIME ON;
  SELECT order_id, total FROM Orders WHERE customer_id = 42;
  ```
  - In SSMS, turn on **Include Actual Execution Plan** (Ctrl+M).
  - The *Messages* tab shows **logical reads** and CPU/elapsed time.

#### Step 2: Read the plan

| Good sign | Warning sign |
|---|---|
| PG: `Index Scan`, `Index Only Scan`, `Bitmap Index Scan` | PG: `Seq Scan` on a big table |
| SQL Server: `Index Seek` | SQL Server: `Table Scan`, `Clustered Index Scan`, `Index Scan` |
| No extra row lookups (covering index) | Many `Key Lookup` (SQL Server) / heap fetches (PG) |
| No `Sort` node with `ORDER BY` | Explicit `Sort` on many rows |

Before vs after an index (PostgreSQL):
```
Seq Scan on orders  (cost=0.00..18334.00 rows=98 width=12)
                    (actual time=0.03..85.4 rows=102 loops=1)
  Filter: (customer_id = 42)
  Rows Removed by Filter: 999898
  Buffers: shared read=8334

Index Scan using ix_orders_customer on orders  (cost=0.42..396.1 rows=98 width=12)
                    (actual time=0.02..0.31 rows=102 loops=1)
  Index Cond: (customer_id = 42)
  Buffers: shared hit=106
```
- `Seq Scan` → `Index Scan`, 85 ms → 0.3 ms, about 1 million rows checked → 102, and 8,334 pages → 106.
- **Estimated vs actual rows** (`rows=98` vs `rows=102`) should be close. If they're far apart, the **statistics are stale**, so run `ANALYZE` (PG) or `UPDATE STATISTICS` (SQL Server).

#### Step 3: Compare before and after
- Record **time**, **pages read** and **plan shape**.
- Pages read is more reliable than time, because time varies with server load and caching.

#### Step 4: Test in realistic conditions
- **Realistic data volume:** on a 100-row dev table, `Seq Scan` is *correct*.
- **Different parameter values:** a typical customer vs a huge one can get **different plans**.
- **Write cost:** on write-heavy tables, compare insert/update times with and without the index.

### Review (the whole database)

**When:** on a schedule (monthly or quarterly), after releases, when something gets slow, or when data grows a lot.
**Question:** *"Are these still the right indexes for how the app is used now?"*

#### Action 1: Find the queries that cost the most
- Optimise by **total time** (calls × average), not the slowest single query. A 5 ms query run a million times a day beats a 3-second report run once.
- **PostgreSQL:** the `pg_stat_statements` extension
  ```sql
  SELECT query, calls, total_exec_time, mean_exec_time, rows
  FROM pg_stat_statements
  ORDER BY total_exec_time DESC
  LIMIT 10;
  ```
  - Also set `log_min_duration_statement = 500` to log queries over 500 ms.
- **SQL Server:** **Query Store** (on by default from 2022): *Top Resource Consuming Queries* and *Regressed Queries* (queries that got slower, often after a plan change).
- Then run **Verify** on the top offenders.

#### Action 2: Find unused indexes (candidates to drop)
- **PostgreSQL**
  ```sql
  SELECT relname AS table, indexrelname AS index, idx_scan,
         pg_size_pretty(pg_relation_size(indexrelid)) AS size
  FROM pg_stat_user_indexes
  WHERE idx_scan = 0
  ORDER BY pg_relation_size(indexrelid) DESC;
  ```
- **SQL Server:** `sys.dm_db_index_usage_stats`. Look for high `user_updates` with few `user_seeks + user_scans + user_lookups`.
- ⚠️ Before dropping:
  - Counters **reset** on restart or stats reset, so make sure they cover monthly or yearly jobs.
  - **Never drop** indexes enforcing `PRIMARY KEY` / `UNIQUE`.
  - Check **read replicas**, because reports may use an index only there.

#### Action 3: Find duplicate and overlapping indexes
- `(customer_id)` is redundant if `(customer_id, created_at)` exists.
- Two indexes on the same columns, often created by different developers.

#### Action 4: Missing-index hints (carefully)
- **SQL Server:** `sys.dm_db_missing_index_details` and the green hints in plans. These are **per-query suggestions** that often overlap, so **merge** them.
- **PostgreSQL:** nothing built in. Look for `Seq Scan` on big tables in your top queries, or high `seq_scan` / `seq_tup_read` in `pg_stat_user_tables`.

#### Action 5: Check index health
- **Size vs table:** indexes many times bigger than the table suggest over-indexing.
- **SQL Server fragmentation:** `sys.dm_db_index_physical_stats`. Common guide: **reorganise** at 5–30%, **rebuild** above 30% (matters less on SSDs).
- **PostgreSQL bloat:** check that autovacuum keeps up. Use `REINDEX CONCURRENTLY` for badly bloated indexes.

#### Action 6: Change, then verify again
- Add, merge or drop **one at a time**, then Verify and watch Query Store / `pg_stat_statements`.
- In production, create without blocking writes: `CREATE INDEX CONCURRENTLY` (PG) or `WITH (ONLINE = ON)` (SQL Server Enterprise / Azure SQL).

#### Maintenance commands

| Task | PostgreSQL | SQL Server |
|---|---|---|
| Update statistics | `ANALYZE` (usually automatic via **autovacuum**) | Auto-update on by default; `UPDATE STATISTICS` |
| Clean up / defragment | `VACUUM` (automatic), `REINDEX [CONCURRENTLY]` | `ALTER INDEX ... REORGANIZE` / `REBUILD` |

### Summary

| | Verify | Review |
|---|---|---|
| **Scope** | One query, one index | The whole database |
| **When** | When adding or changing an index or query | On a schedule, after releases, when slow |
| **Tools (PG)** | `EXPLAIN (ANALYZE, BUFFERS)` | `pg_stat_statements`, `pg_stat_user_indexes`, slow query log |
| **Tools (SQL Server)** | Actual Execution Plan, `SET STATISTICS IO, TIME` | Query Store, `sys.dm_db_index_usage_stats`, missing-index DMVs |

> Index the columns used in `WHERE`, `JOIN` and `ORDER BY` **in your real, frequent queries**, check they're actually used, and don't index every column "just in case."

### Practice
1. For `Orders(order_id PK, customer_id, status, created_at, total)`, which index (and column order) would you create for the query below? Can you make it **covering**?
   ```sql
   SELECT order_id, total
   FROM Orders
   WHERE customer_id = 42 AND created_at >= '2025-01-01'
   ORDER BY created_at DESC;
   ```
2. Which index type would you choose for each?
   - Products whose JSONB `attributes` contain `{"colour": "red"}` (PostgreSQL)
   - "Unpaid invoices", where 99% are paid
   - A 5-billion-row sensor log, always queried by time range (PostgreSQL)
3. Using the tree diagram, how many page reads does `WHERE customer_id = 100` take? Why does `WHERE customer_id + 1 = 101` force a full scan?
4. In a database you know, list the foreign key columns. Which have an index?
