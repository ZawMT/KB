## Database
### Caching and Read Replicas

#### [Back to Database contents](../_Contents.md)

- **Caching:** keep data in fast memory so the database (or the app) **avoids repeating work**.
- **Read replicas:** extra **copies of the whole database** that take read traffic away from the main server.
- Both are "cheaper fixes" to try **before denormalising** (see [Normalisation](Normalisation.md)).

### Caching

There are three layers: what the database does **by itself**, stored results you **set up inside** the database, and caches you add **in front of** it.

#### A. Built into the database (automatic)

**Data page cache (the most important one)**
- Data is stored in fixed-size **pages**: 8 KB in both PostgreSQL and SQL Server.
- Pages that have been read are kept in **RAM**, so the next read of the same data skips the disk.
  - **SQL Server: buffer pool.** It deliberately uses most of the server's memory (limited by `max server memory`).
  - **PostgreSQL: `shared_buffers`**, plus it relies heavily on the **operating system's file cache**. That's why `shared_buffers` is often set to only about **25% of RAM**.
- Frequently used ("hot") data is effectively served from memory.

**Plan cache**
- Before running a query, the database works out **how** to run it (which indexes, which join order). That's the **execution plan**, and creating it costs CPU.
  - **SQL Server:** a server-wide **plan cache**, reused for the same parameterised query.
  - **PostgreSQL:** plans are cached only **per connection**, for **prepared statements**. There's no shared plan cache.
- This caches **how** to run a query, not its **results**.

**What neither has: a query result cache**
- Neither stores "this query → these rows" and returns them later. Running the same query twice **does the work twice**. It's faster the second time only because the pages are already in memory.
- MySQL had a result cache but **removed it in 8.0**, because keeping it correct under writes caused more problems than it solved.

#### B. Stored results inside the database (you set them up)

These are the closest the databases get to caching results, and they're a form of **denormalisation**.

- **PostgreSQL materialized view**
  - Stores a query's result as a table.
  - You refresh it yourself with `REFRESH MATERIALIZED VIEW`. Adding `CONCURRENTLY` means readers aren't blocked during the refresh.
  - The data can be **stale** between refreshes.
- **SQL Server indexed view**
  - A view with a clustered index, so its result is stored on disk.
  - **Updated automatically on every write**, so it's always current but makes writes slower.
  - Strict rules about which queries are allowed.

#### C. Application-level cache (outside the database)

For real "don't even ask the database" caching, apps use an in-memory store like **Redis** or **Memcached**.

**Cache-aside pattern** (the most common one)

```
1. App checks Redis for key "restaurant:42"
2. Found?      → return it (no DB call)
3. Not found?  → query the DB, store the result in Redis with a TTL, return it
4. On update   → write to the DB, then delete/refresh the Redis key
```

- **TTL (time to live):** the entry expires automatically after a set time, which limits how stale it can get.
- **Hard part: invalidation**, keeping the cache correct when the data changes.
  - *"There are only two hard things in computer science: cache invalidation and naming things."*

#### Who does the caching?

| Layer | Who does it | What you do |
|---|---|---|
| **A. Page cache, plan cache** | The DBMS, automatically | Nothing to turn on, but you can help or hurt it |
| **B. Materialized / indexed views** | You define them, the DBMS stores them | Create the view and decide when it refreshes |
| **C. Redis / Memcached** | You, entirely | Write the cache logic in your app code |

**A is automatic, but you still influence it**
- You never write "cache this page". The DBMS decides what stays in memory.
- **Memory settings:** `max server memory` (SQL Server) and `shared_buffers` (PostgreSQL) control how much RAM the cache gets. Usually set by the DBA or cloud provider.
- **Indexes:** an index lookup reads a few pages. A full table scan pulls in many pages and can **push out hot data**.
- **Parameterised queries** let the plan cache work:
  - ✅ `WHERE id = @id` (SQL Server) or `$1` (PostgreSQL) gives one plan that gets reused.
  - ❌ `"WHERE id = " + id` creates different query text for every value, filling SQL Server's plan cache with single-use plans.
  - They also protect against **SQL injection**, another reason to always use them.
- **Select only the columns you need:** less data read means less memory used.

**B and C are explicit: you choose and build them**
- **Materialized / indexed views:** you decide which query result is worth storing, and (in PostgreSQL) when to refresh it.
- **Redis / Memcached:** your app code checks the cache, fills it, sets TTLs and invalidates entries. This is usually what developers mean by "we added caching".

> The DBMS **automatically** caches *data pages* and *query plans*.
> Caching **query results**, so the work isn't repeated at all, is **explicit**: you set it up with materialized views or an app-level cache like Redis.

- That's why "just add caching" is a real design decision: **what** to cache, for **how long**, and how to keep it **correct**.

### Read replicas

A **read replica** is a **copy of the database on another server**, kept in sync with the main one, that accepts **reads only**.

```
           writes + reads
App ───────────────────────► Primary
 │                              │
 │                              │  replication (changes stream over continuously)
 │                              ▼
 └──── reads only ─────────► Replica 1, Replica 2, ...
```

#### How it works
- Every change on the primary is first written to a **log**:
  - **PostgreSQL:** the **WAL** (write-ahead log)
  - **SQL Server:** the **transaction log**
- That log is **streamed to the replicas**, and they replay the same changes.
  - **PostgreSQL:** **streaming replication**, with replicas running as **hot standbys** that can serve reads.
  - **SQL Server:** **Always On Availability Groups** with **readable secondary** replicas.
  - **Cloud:** AWS RDS, Azure SQL and others let you add one with a click.

#### Why use them
- **Scale reads:** most apps read far more than they write, so spread reads across several servers.
- **Isolate heavy queries:** reports and analytics run on a replica and don't slow down the primary.
- **High availability:** if the primary fails, a replica can be **promoted** to be the new primary.

#### The catch: replication lag
- Replication is usually **asynchronous**, so a replica can be **a few milliseconds to a few seconds behind** the primary.
- **Classic bug:** a user updates their profile (write → primary), the page reloads (read → replica), and the **old** profile is shown.
- **Fix: "read your own writes."** Right after a user writes something, send that user's reads to the primary for a short time.
- **Synchronous** replication avoids lag, but every write waits for the replica to confirm, which makes writes slower.

### Summary

| Technique | What it saves | Freshness | Who manages it |
|---|---|---|---|
| Buffer pool / `shared_buffers` | Disk reads | Always current | The database, automatically |
| Plan cache | Query planning CPU | Always current | The database, automatically |
| Materialized / indexed view | Re-running an expensive query | PG: stale until refresh; SQL Server: current | You define it |
| Redis / Memcached | The whole database trip | Stale until TTL or invalidation | Your app code |
| Read replica | Load on the primary | Slightly behind (lag) | Database config / infrastructure |

### Practice
For a food-delivery app, which technique would you use for each of these? Think about **how stale each one can safely be**.
1. A restaurant's menu
2. A live order's status
3. The monthly revenue report
