## Database
### Key points

#### [Back to Database contents](_Contents.md)

| Point | Note |
|---|---|
| COALESCE function | Returns the **first non-`NULL` value** from its arguments, checked left to right. Returns `NULL` if all of them are `NULL`. Mostly used to **replace `NULL` with a default**, e.g. `COALESCE(SUM(x.amount), 0)` shows `0` for days with no sales after a `LEFT JOIN`, and `COALESCE(nickname, first_name, 'Guest')` uses the first name that's filled in. Standard SQL, so it works in PostgreSQL, SQL Server, Oracle and MySQL. Similar functions with only 2 arguments: `ISNULL` (SQL Server), `NVL` (Oracle), `IFNULL` (MySQL). ⚠️ **All arguments must share one type** (or be convertible to it). A table column always has one type, but `COALESCE` creates a new **calculated** column whose values can come from **any** argument, so they must agree. In `COALESCE(int_column, 'N/A')` the column is fine, but the text `'N/A'` you mixed in can't become an integer, so it fails. `COALESCE(int_column, '0')` works because `'0'` can be converted. Fix: cast to text first, `COALESCE(int_column::text, 'N/A')` (SQL Server: `CAST(int_column AS varchar(20))`), but then the column is no longer a number, so prefer `0` (or keep `NULL` and show 'N/A' in the app). Same rule for `CASE` branches and `UNION` columns. |
