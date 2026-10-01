## Database
### Contents

#### [Back to main contents](/Contents.md)

Here are some key points to take note about databases:
[Key points](_KeyPoints.md)

Normalisation vs denormalisation, OLTP vs OLAP, and star schemas with fact and dimension tables:
[Normalisation](_Notes/Normalisation.md)

How databases cache data (buffer pool, plan cache, materialized views, Redis) and what read replicas are:
[Caching](_Notes/Caching.md)

What indexes are, how B-trees work, index types, pros and cons, and how to verify and review indexes:
[Indexing](_Notes/Indexing.md)

Hands-on EXPLAIN (ANALYZE, BUFFERS) experiments to run in pgAdmin:
[Indexing Demo](_Notes/IndexingDemo.md)

JOIN vs subquery vs CTE: what each is for, recursive CTEs, and performance differences:
[JOIN, Subquery and CTE](_Notes/JoinSubqueryCTE_0_Overview.md)

JOIN in depth: join types, ON vs WHERE, row multiplication, and how the database runs joins:
[JOIN](_Notes/JoinSubqueryCTE_1_Join.md)

Subquery in depth: kinds of subquery, common traps, ANY and ALL, and performance:
[Subquery](_Notes/JoinSubqueryCTE_2_Subquery.md)

CTE in depth: what the name means, recursive CTEs step by step, and when a CTE is essential:
[CTE](_Notes/JoinSubqueryCTE_3_CTE.md)
