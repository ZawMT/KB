## Database
### Normalisation vs Denormalisation

#### [Back to Database contents](../_Contents.md)

- **Normalisation:** organise tables so **each fact is stored in exactly one place**.
- **Denormalisation:** **deliberately store some facts more than once**, usually to make reads faster or simpler.

### The problem: one big table

| order_id | customer_name | customer_email | customer_city | product | price | qty |
|---|---|---|---|---|---|---|
| 1 | Alice | alice@x.com | London | Pen | 2.00 | 3 |
| 2 | Alice | alice@x.com | London | Book | 15.00 | 1 |
| 3 | Bob | bob@y.com | Paris | Pen | 2.00 | 5 |

Repeated data causes three kinds of **anomalies**:
- **Update anomaly:** Alice changes her email, so you must update **every** row. Miss one and the data contradicts itself.
- **Insert anomaly:** you can't add a new customer until they place an order.
- **Delete anomaly:** delete Bob's only order and you lose the fact that Bob exists.

### Normalisation

Split the data so each fact lives once, and link the tables with **keys**:

```
Customers (customer_id PK, name, email, city)
Products  (product_id PK, name, price)
Orders    (order_id PK, customer_id FK, order_date)
OrderItems(order_id FK, product_id FK, qty, PK(order_id, product_id))
```

- Alice's email is now in **one row**. Change it once and every order sees the new value.

#### The normal forms
Each one builds on the one before.

- **1NF** has two parts:
  1. **Each cell holds a single value**, with no lists and no repeating groups.
     - ❌ `phones = "0711, 0722"` (a list in one cell)
     - ❌ `phone1, phone2, phone3` columns (a **repeating group**: the same problem spread across columns)
     - ✅ A separate `CustomerPhones(customer_id, phone)` table, with one row per phone
  2. **Each row can be identified by a key**: one or more columns whose values are unique for every row, so there are no exact duplicate rows.
- **2NF:** with a **composite key**, every column depends on the **whole** key, not just part of it.
  - ❌ `OrderItems(order_id, product_id, qty, product_name)`: `product_name` depends only on `product_id`
  - ✅ Move it to `Products`
- **3NF:** non-key columns depend **only on the key**, not on other non-key columns.
  - ❌ `Customers(id, postcode, city)`: city is determined by postcode
  - ✅ Move it out to `Postcodes(postcode, city)`
- **BCNF** and the higher forms are stricter versions of the same idea and rarely matter day to day.

> **Mnemonic** (rewording Bill Kent, 1983): every non-key column must provide a fact **about the key, the whole key, and nothing but the key.**

| Form | Rule | Mnemonic phrase |
|---|---|---|
| **1NF** | Single values, no repeating groups, rows identified by a key | *about the key*: there is a key, and each column is **one** fact about it |
| **2NF** | No column depends on only **part** of a composite key | *the whole key* |
| **3NF** | No column depends on another non-key column | *nothing but the key* |

- A cell like `"0711, 0722"` isn't **one fact** about the customer. It's several facts bundled together, so it fails "a fact about the key."
- ⚠️ Strictly, Kent's sentence explained **2NF and 3NF**. Mapping "the key" to 1NF is a popular simplification, so you'll see it in tutorials but not in the formal definitions.

- **3NF is the usual target** for transactional (OLTP) databases.

### Denormalisation

**Deliberately adding redundancy back** to a normalised design, usually to avoid expensive joins or calculations on reads.

Common examples:
- **Copying a value:** `customer_name` stored on `Orders` so order lists don't need a join.
- **Stored totals:** `order_total` on `Orders` instead of summing `OrderItems` every time.
- **Counters:** `posts.comment_count` instead of `COUNT(*)` over `comments`.
- **Snapshots:** `unit_price` **at the time of purchase** stored in `OrderItems`.
  - This one is **required for correctness**, because the product's price can change later and the order must keep the price that was paid.
- **Precomputed results:** e.g. a "Top restaurants" list computed hourly or daily and stored for the app to read. It trades freshness for speed.
- **Reporting tables:** data warehouses use **star schemas** (see below).

**The cost:** you must **keep the copies in sync**, using app code, triggers or batch jobs. If you get that wrong, the data contradicts itself again.

#### Is there a formula?
- **No formal formula.** Normalisation has precise rules you can check mechanically, but there are no "denormal forms."
- It's driven by requirements, specifically **measured read patterns**. It still isn't guesswork, because there's a repeatable process and a set of well-known patterns.

**The process**
1. **Start normalised** (3NF) so you have a correct baseline.
2. **Find the real problem.** Which queries are slow or frequent? Check query plans (`EXPLAIN`) and production metrics.
3. **Try cheaper fixes first:** indexes, query rewrites, caching, read replicas (see [Caching](Caching.md)). These often solve it without redundancy.
4. **Denormalise only that hot path**, using a known pattern (below).
5. **Decide how the copy stays in sync**, and how stale it's allowed to be.
6. **Measure again** to confirm it helped.

**The usual patterns**

| Pattern | Example | Removes |
|---|---|---|
| Copy a column | `customer_name` on `Orders` | A join |
| Stored total | `order_total` on `Orders` | A `SUM` over child rows |
| Counter | `comment_count` on `posts` | A `COUNT(*)` |
| Snapshot | `unit_price` at time of purchase | Wrong history (this one is for correctness) |
| Precomputed table | Daily "Top restaurants" | A heavy aggregate |
| Materialized view | DB-managed stored query result | Repeated expensive query |
| Flattened table | Star schema dimension | Chains of joins |

**Questions to ask before each one**
- **Read/write ratio:** is it read far more often than written? Frequent writes make copies expensive to maintain.
- **Freshness:** can it be minutes or hours out of date, or must it be exact?
- **Sync method:** same transaction, trigger, background job or materialized view refresh?
- **What breaks if it drifts?** A slightly wrong view count is harmless, but a wrong account balance isn't.

> Normalisation follows **rules**. Denormalisation is a **trade-off you justify with measurements**.

### Comparison

| | Normalised | Denormalised |
|---|---|---|
| Each fact stored | Once | Possibly many times |
| Writes | Simple and safe | Must update every copy |
| Reads | May need many joins | Fewer joins, faster |
| Consistency risk | Low | Higher |
| Storage | Less | More |
| Typical use | Transactional apps (OLTP) | Reporting and analytics (OLAP), caches, read-heavy hot paths |

> **Rule of thumb:** normalise first, then denormalise **on purpose**, only where you've **measured** a real performance need.

### OLTP vs OLAP

#### What "online" means
- **Not "on the internet"**: the terms are older than the web.
- "Online" means **interactive and immediate**, as opposed to **batch** processing:
  - **Batch (offline):** in the mainframe era, transactions were collected and processed together later, often overnight. You dropped off a deposit and the balance updated the next day.
  - **Online:** the user is "on the line" with the system, and each request is processed **right away** while they wait. Early examples were airline booking (SABRE, 1960s) and bank tellers' terminals.
- **OLTP:** transactions are processed interactively, as they happen.
- **OLAP:** analysts run queries interactively and get answers in seconds or minutes, instead of submitting a report job and waiting. Coined by E. F. Codd (inventor of the relational model) in 1993.
- A system that's only on an internal network, with no internet at all, can still be OLTP.

#### OLTP: Online Transaction Processing
The workload that **runs the business day to day**.

- **Many concurrent users**, often thousands at once.
- **Short transactions** that finish in milliseconds.
- **Small queries**, usually looking rows up by key (`WHERE order_id = 42`).
- **Frequent writes**: inserts, updates, deletes.
- **Correctness is critical**, so it relies on **ACID** transactions:
  - **Atomic:** all or nothing
  - **Consistent:** rules and constraints are never broken
  - **Isolated:** concurrent transactions don't interfere
  - **Durable:** once committed, the data survives a crash
- Examples: PostgreSQL, MySQL, SQL Server, Oracle.

#### OLAP: Online Analytical Processing
The workload that **analyses the business**.

- e.g. "Total sales per region per month for the last 3 years."

| | OLTP | OLAP |
|---|---|---|
| Purpose | Run the business | Analyse the business |
| Typical query | Fetch or update a few rows | Scan and aggregate millions of rows |
| Operations | Many inserts/updates/deletes | Mostly reads, loaded in bulk |
| Users | Many app users at once | Fewer analysts and dashboards |
| Speed goal | Each transaction in milliseconds | Big queries in seconds or minutes |
| Data | Current state | Historical, often years of it |
| Schema design | **Normalised** (usually 3NF) | **Denormalised** (star schema) |
| Examples | PostgreSQL, MySQL, SQL Server | Snowflake, BigQuery, Redshift, ClickHouse |

- **Why OLTP normalises:** writes are constant, so each fact should live in one place. An update then touches one row.
- **Why OLAP denormalises:** data is written in bulk and then mostly read, so avoiding joins across billions of rows matters more.
- **Common setup:** run the app on an OLTP database and copy the data regularly into an OLAP warehouse via **ETL/ELT** (Extract, Transform, Load). Heavy analytics then never slows down the live app.

**Quick test:** doing something (one event, a few rows, happening now) → **OLTP**. Asking about many things (lots of rows, over a period, summarised) → **OLAP**.

| Food-delivery app task | Type |
|---|---|
| A customer places an order | OLTP |
| A driver marks an order as delivered | OLTP |
| Average delivery time per city last quarter | OLAP |
| Top 10 restaurants by revenue this year | OLAP |

### Star schema

The most common way to design tables in an OLAP data warehouse. It's named for its shape: one table in the centre with others around it, like the points of a star.

#### Fact table (the centre)
- Holds **events or measurements**, the things you add up, count or average.
- One row per event, such as one sale, one click or one delivery.
- Contains:
  - **measures**: numbers like `quantity`, `amount`, `discount`
  - **foreign keys** pointing to the dimension tables
- **Very long**: millions or billions of rows.

#### Dimension tables (the points)
- Hold **descriptive context** to filter and group by: **who, what, where, when**.
- Examples: customer, product, store, date.
- **Wide** (many descriptive columns) and relatively **short**.

#### Example

```
                 DimDate
          (date_key, date, month,
           quarter, year, weekday)
                    |
 DimCustomer        |         DimProduct
(customer_key, ——— FactSales ——— (product_key, name,
 name, city,      (date_key,      category, brand)
 country)          customer_key,
                   product_key,
                   store_key,
                   quantity,
                   amount)
                    |
                 DimStore
          (store_key, name,
           region, manager)
```

```sql
SELECT d.year, p.category, s.region, SUM(f.amount) AS revenue
FROM FactSales f
JOIN DimDate    d ON f.date_key    = d.date_key
JOIN DimProduct p ON f.product_key = p.product_key
JOIN DimStore   s ON f.store_key   = s.store_key
WHERE d.year >= 2024
GROUP BY d.year, p.category, s.region;
```

- Every query has the same shape: **measures from the fact table, sliced by dimensions**.

#### Why it's denormalised
- In 3NF, products would be split into `Products → Categories → Brands`.
- A star schema **flattens** them into one `DimProduct` table, so the `category` text repeats for every product.
- The redundancy is deliberate:
  - Every dimension is **one join away**, which keeps queries simple and fast.
  - BI tools like Power BI and Tableau understand it easily.
  - Data is **loaded in bulk** (ETL), not edited row by row, so update anomalies matter much less.

#### Snowflake schema
- The dimensions are **normalised** (`DimProduct → DimCategory → DimBrand`), so the points branch out further.
- **Pro:** less redundancy and less storage.
- **Con:** more joins and more complex queries.
- **Star is usually preferred** for simplicity and speed.

| | Star schema |
|---|---|
| Centre | **Fact** table: events and numeric measures, very many rows |
| Points | **Dimension** tables: descriptive context (who/what/where/when) |
| Design style | Denormalised dimensions, one join from fact to each dimension |
| Used in | OLAP / data warehouses |
| Popularised by | Ralph Kimball, *The Data Warehouse Toolkit* |

### Facts and dimensions compared with OLTP tables

> **Mental model:** fact tables ≈ **transaction** tables, and dimension tables ≈ **master / reference** tables.

| OLTP | Star schema |
|---|---|
| Transaction tables (`Orders`, `OrderItems`, `Payments`) | → **Fact tables** (`FactSales`, `FactPayments`) |
| Master / reference tables (`Customers`, `Products`, `Stores`) | → **Dimension tables** (`DimCustomer`, `DimProduct`, `DimStore`) |

That's a good starting point, but it differs in four ways.

**1. Not every fact table is a list of transactions**
- **Transaction facts:** one row per event, e.g. a sale.
- **Periodic snapshot facts:** one row per **thing per period**, e.g. each product's stock level at the end of each day, or each account's balance at month end.
- **Accumulating snapshot facts:** one row per **process**, updated as it moves through its stages, e.g. an order with `ordered_date`, `shipped_date` and `delivered_date`.
- **Factless fact tables:** record that something happened, with no numbers, e.g. student attendance.

**2. Some dimensions have no master table behind them**
- **`DimDate`** is **generated**, with one row per day and columns like `month`, `quarter`, `is_weekend` and `is_holiday`.
- Some dimensions are built from small leftover attributes (flags, status codes) grouped together.

**3. Dimensions are flattened**
- Master tables are normalised (`Products → Categories → Brands`), while a dimension merges them into **one wide table**.

**4. Dimensions often keep history**
- In OLTP, a customer moving from London to Paris **overwrites** the city.
- In a warehouse you often want old sales to **still count under London**, so you add a **new row** with validity dates. One customer can then have several dimension rows.
- This is a **Slowly Changing Dimension (SCD)**, and **Type 2** is the add-a-new-row approach.
- That's why dimensions use their own **surrogate keys** (`customer_key`) instead of the OLTP ID (`customer_id`).

### Practice
1. `Students(student_id, name, course_code, course_title, lecturer, lecturer_email)`: which anomalies can you find, and how would you split it into 3NF?
2. For a food-delivery app, what would the main **fact table** be, and what are three **dimension tables** around it?
3. A table records **each driver's completed deliveries and hours online, per day**. Is that a transaction fact, a periodic snapshot or an accumulating snapshot?
4. Would a "driver" master table map straight to a dimension, or would you want **SCD** history? What if a driver moves to another city?
