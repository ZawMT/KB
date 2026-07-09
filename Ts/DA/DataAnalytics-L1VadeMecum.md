# Data Analytics - L1 Companion (Q&A)

Companion notes for questions arising while reading [DataAnalytics-L1.md](./DataAnalytics-L1.md).

## Q&A
[Q1: Why are there two "orthogonal cut" in the Data types note?](#orthogonal-cuts-in-the-note)
[Q2: For Interval vs Ratio, what does "true zero" actually mean? Does 0°C being "no true zero" mean it has no meaning? What does age=0 or weight=0 mean for Ratio?](#interval-vs-ratio)
[Q3: What is IQR? Formula? Why is it more robust than Range?](#iqr-vs-range)
[Q4: What are skewness, kurtosis, shapes and trends?](#skewness-kurtosis-shapes-and-trends)
[Q5: What is Z-Score?](#z-score)
[Q6: What is Percentiles and Quantiles?](#percentiles-and-quantiles)
[Q7: What are the formula for mean, median, and mode?](#mean-median-mode)
[Q8: What is variance and standard deviation?](#variance-and-standard-deviation)
---

### Orthogonal cuts in the note

"Orthogonal cut" = a classification axis that's independent of the main Categorical/Numerical split — it doesn't sit as a sub-branch of Nominal/Ordinal/Discrete/Continuous, but cuts across them from a different angle.

There are two because they're two separate, independent lenses:
1. **Interval vs Ratio** — whether the numeric scale has a true zero and equal spacing (applies within Numerical data: Celsius is Interval, weight is Ratio).
2. **Structured vs Semi-structured vs Unstructured** — how the data is organized/stored (tables vs JSON vs free text/images), regardless of whether values are categorical or numerical.

Both are orthogonal to the main Categorical/Numerical hierarchy — and orthogonal to each other too, since each answers a different question about the same dataset.
---

### Interval vs Ratio

True zero = zero represents the *total absence* of the quantity being measured.

- **Celsius (Interval):** 0°C isn't "no temperature" — it's just the freezing point of water, an arbitrary reference point. There's still thermal energy at 0°C, and negative values are meaningful (temperature can go below it). Because zero doesn't mean "none," ratios break: 20°C is not twice as hot as 10°C — convert to Fahrenheit (50°F vs 68°F) and the 2x ratio disappears. A real ratio would survive unit conversion; this doesn't, because the zero point is just a convention (different scales put zero in different places).
- **Age/Weight (Ratio):** age=0 means literally no time has elapsed since birth (absence of age); weight=0 means absence of mass. Negative age/weight isn't meaningful — unlike temperature. Because zero really means "none," ratios hold: 40 years old is truly twice 20 years old, and this stays true whether measured in years or days.

Practical test: **can you double the value and have "twice as much" mean something physically real, independent of the unit chosen?** Yes → Ratio. No (zero is arbitrary) → Interval.
---

### IQR vs Range
IQR (Interquartile Range) = spread of the middle 50% of the data.

Formula: **IQR = Q3 − Q1**
- Q1 (25th percentile) — value below which 25% of data falls
- Q3 (75th percentile) — value below which 75% of data falls

Robustness vs Range: Range = Max − Min, so it depends entirely on the two most extreme values — one outlier (data-entry error, a billionaire in an income dataset) can blow it up even if the rest of the data is tightly clustered. IQR ignores the top 25% and bottom 25% entirely, so outliers fall outside the Q1–Q3 window and don't affect it at all — only the "middle" of the data matters, which barely shifts even with a few extreme points.

Side note: this is also the basis for outlier detection — values below Q1 − 1.5×IQR or above Q3 + 1.5×IQR are commonly flagged as outliers (box-plot whiskers).
---

### Skewness, Kurtosis, Shapes, and Trends

**Skewness** — asymmetry of a distribution around its mean.
- Symmetric (≈0): mirrors both sides, Mean ≈ Median ≈ Mode (e.g., normal distribution).
- Right/Positive skew (>0): long tail to the right, a few large outliers pull Mean above Median (e.g., income, house prices).
- Left/Negative skew (<0): long tail to the left, Mean below Median (e.g., retirement age).

Formula: Skewness = E[(X − μ)³] / σ³ — cubing preserves the sign of deviations, so a few large deviations on one side dominate and pull the value in that direction.

**Kurtosis** — "tailedness"/peakedness relative to normal; how much data sits in the tails (extremes) vs the center.

Formula: Kurtosis = E[(X − μ)⁴] / σ⁴

- Mesokurtic (≈3, excess ≈0): normal-like tails.
- Leptokurtic (>3, excess >0): heavier tails, sharper peak, more extreme outliers than normal (e.g., stock returns — "fat tails").
- Platykurtic (<3, excess <0): thinner tails, flatter peak, fewer extreme outliers than normal.

"Excess kurtosis" = Kurtosis − 3 (centers normal at 0; what most software reports).

Why both matter: two datasets can share the same mean/std-dev but look totally different in shape — one symmetric/normal-tailed, another skewed with fat tails. Many statistical methods (t-tests, linear regression) assume normality, so high skew/kurtosis flags that assumption may not hold.

![DataTrends](./images/DataTrends.png)

**Shape** — how a *single distribution's curve* is formed at one point in time: is it symmetric or lopsided (skewness), is it peaked with fat tails or flat with thin tails (kurtosis)? Shape describes a snapshot — feed it a batch of data (income, test scores, heights) and it tells you how that batch is distributed around its center. This is exactly the "Shape" bucket in the original note (`Shape — Skewness, Kurtosis`), and it's what the image above visualizes.

**Trend** — how a value *changes across an ordered sequence*, usually time (e.g., monthly revenue, daily active users, temperature over a year). A trend is directional: upward, downward, flat, cyclical/seasonal. Trend analysis lives in time-series analysis, not in the Shape category — a distribution's skewness doesn't tell you whether values are rising or falling over time, and a trending time series doesn't need to be skewed at all.

Why the distinction matters: skewness/kurtosis answer "what does the data look like right now, all together?" while trend answers "which way is the data moving as time (or sequence) progresses?" They're independent — a dataset can be highly skewed but flat over time, or symmetric (normal-shaped) but trending sharply upward. So "shape" and "trend" are two different lenses on data, not interchangeable terms — skewness/kurtosis are shape stats, not trend stats.
---

### Z-Score

**Plain idea:** Z-score = "how many std-lengths away from the mean is this value?" — same as asking "how many times does the std fit into the gap between this value and the mean?"

**Example:** std = 2, value is 6 away from the mean → 2 fits into 6 exactly 3 times → Z = 3. That's it — just a division.

Formula: **Z = (X − μ) / σ**
- top = distance from the mean (X − μ)
- bottom = the size of one "std unit" (σ)
- dividing tells you how many of those units fit in the distance

Interpretation:
- Z = 0 → value equals the mean
- Z = +1 → exactly 1 std above the mean
- Z = −2 → exactly 2 std below the mean
- Sign (+/−) = which side of the mean; number = how many std-lengths

Why it's useful (one line each):
1. **Standardization** — turns any unit (dollars, cm, test score) into "# of std away," so values from different scales become comparable.
2. **Outlier detection** — |Z| > 3 is usually flagged as an extreme value.
3. **Probability lookup** — for normal data, Z maps to a percentile (Z = 1.96 → 97.5th percentile).

A *Position* statistic (`Position — Percentiles, Quantiles, Z-score` in the original note). Also the building block behind [Skewness, Kurtosis](#skewness-kurtosis-shapes-and-trends) — those formulas are just "average of Z-scores cubed/to-the-4th-power."
---

### Percentiles and Quantiles

**Percentile** — the value below which a given % of the data falls. Pth percentile → P% of data sits below it, (100−P)% sits above.

Example: 90th percentile on a test → 90% of test-takers scored below you.

**Quantile** — the umbrella term for cut-points that divide sorted data into equal-sized groups. Percentile is just one *flavor* of quantile (100 groups). Others:
- **Quartiles** — 4 groups (Q1, Q2, Q3) → same Q1/Q3 as in [IQR](#iqr-vs-range): Q1 = 25th percentile, Q2 = 50th percentile = **median**, Q3 = 75th percentile.
- **Deciles** — 10 groups (D1...D9)
- **Percentiles** — 100 groups (P1...P99)

So "quantile" = umbrella word; "percentile/quartile/decile" = specific flavors, just slicing the sorted data into a different number of equal parts.

How to find one: sort the data, walk P% of the way through the sorted list — the value you land on is that percentile.

Why useful: unlike mean/std, percentiles/quantiles don't assume any distribution shape — they just describe position within the actual sorted data, so they're robust to outliers/skew (same robustness idea behind [IQR](#iqr-vs-range) = Q3 − Q1).
---

### Mean, Median, Mode

Sample data: 4, 8, 6, 5, 3, 8, 9

**Mean** — arithmetic average.
Formula: **x̄ = (Σx) / n**
Sum = 4+8+6+5+3+8+9 = 43, n = 7 → Mean = 43/7 = **6.14**

**Median** — middle value when sorted (splits data 50/50).
- n odd → value at position (n+1)/2
- n even → average of the two middle values

Sorted: 3, 4, 5, 6, 8, 8, 9 (n=7, odd) → middle position = (7+1)/2 = 4th value → **6**
If we add 10: 3, 4, 5, 6, 8, 8, 9, 10 (n=8, even) → middle two = 6, 8 → Median = (6+8)/2 = **7**

**Mode** — most frequent value(s). No formula, just count frequency.
Same data: 3, 4, 5, 6, 8, 8, 9 → 8 appears twice, rest appear once → Mode = **8**
(Can have no mode, one mode, or multiple — e.g., 1,1,2,2,3 is bimodal: modes 1 and 2.)
---

### Variance and Standard Deviation

**Variance** — average of squared deviations from the mean (squaring avoids +/− deviations canceling out).
**Standard Deviation** — √variance (brings the unit back to the original scale, e.g., dollars instead of dollars²).

Formulas:
- Population variance: **σ² = Σ(x − μ)² / N**
- Sample variance: **s² = Σ(x − x̄)² / (n − 1)**
- Std dev: **σ = √σ²** (population), **s = √s²** (sample)

Sample variance divides by n−1, not n ("Bessel's correction") — a sample's own mean is pulled slightly toward its own data, so deviations from it slightly underestimate true population spread; n−1 corrects for that.

Worked example — same data as [Mean/Median/Mode](#mean-median-mode): 4, 8, 6, 5, 3, 8, 9 (mean = 6.14)

| x | x − mean | (x − mean)² |
|---|----------|-------------|
| 4 | −2.14 | 4.59 |
| 8 | 1.86 | 3.45 |
| 6 | −0.14 | 0.02 |
| 5 | −1.14 | 1.31 |
| 3 | −3.14 | 9.88 |
| 8 | 1.86 | 3.45 |
| 9 | 2.86 | 8.16 |

Sum of squared deviations ≈ 30.86

- Population variance = 30.86/7 ≈ **4.41**
- Sample variance = 30.86/6 ≈ **5.14**
- Population std dev = √4.41 ≈ **2.10**
- Sample std dev = √5.14 ≈ **2.27**

These 7 values are a sample (not the full population), so **s ≈ 2.27** is the one you'd normally report.

