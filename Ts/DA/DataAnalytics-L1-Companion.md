# Data Analytics - L1 Companion (Q&A)

Companion notes for questions arising while reading [DataAnalytics-L1.md](./DataAnalytics-L1.md).

## Q&A

**Q: Why are there two "orthogonal cut" in the Data types note?**

"Orthogonal cut" = a classification axis that's independent of the main Categorical/Numerical split — it doesn't sit as a sub-branch of Nominal/Ordinal/Discrete/Continuous, but cuts across them from a different angle.

There are two because they're two separate, independent lenses:
1. **Interval vs Ratio** — whether the numeric scale has a true zero and equal spacing (applies within Numerical data: Celsius is Interval, weight is Ratio).
2. **Structured vs Semi-structured vs Unstructured** — how the data is organized/stored (tables vs JSON vs free text/images), regardless of whether values are categorical or numerical.

Both are orthogonal to the main Categorical/Numerical hierarchy — and orthogonal to each other too, since each answers a different question about the same dataset.

