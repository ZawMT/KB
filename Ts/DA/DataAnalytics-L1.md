# Data Analytics 

## Foundational disciplines
DA itself is an application of these other fields:
- Mathematics & Statistics — probability theory, statistical inference, linear algebra. Etc.
- Computer Science / Programming — programming languages (Python, R, SQL), databases, algorithms & data structures. Etc.
- Domain/Business knowledge — subject-matter expertise needed to ask the right questions and interpret results meaningfully. Etc.

## Basic topics
When we study Data Analytics, conceptually, we have to know these:
- Data types
  - Categorical (Qualitative)
    - Nominal — no inherent order (color, gender, blood type)
    - Ordinal — ordered, no fixed interval (rating: low/medium/high, education level)
  - Numerical (Quantitative)
    - Discrete — countable (number of kids, cars owned)
    - Continuous — measurable, any value in a range (height, weight, temperature)
    - (orthogonal cut) Interval vs Ratio — equal spacing either way; Interval has no true zero (Celsius, calendar year), Ratio does (weight, income, age)
  - (orthogonal cut) Structured vs Semi-structured vs Unstructured — Structured (tables, relational rows), Semi-structured (JSON, XML), Unstructured (text, images, video)
- Population vs Sample — the full group of interest vs the subset actually observed; the distinction that sample statistics (e.g. sample variance dividing by n-1) and all of statistical inference are built on
- Data collection
  - Primary — collected firsthand for the purpose at hand: surveys, interviews, observations, experiments (A/B tests), sensors/IoT. Etc.
  - Secondary — pre-existing, collected by someone/something else: databases, logs, web scraping, APIs/third-party feeds, public/open datasets. Etc.
- Basic descriptive statistics
  - Central Tendency — Mean, Median, Mode
  - Dispersion/Spread — Range, Variance, Standard Deviation, IQR. Etc.
  - Shape — Skewness, Kurtosis. Etc.
  - Position — Percentiles, Quantiles, Z-score. Etc.

## Next topics
Then, as the next step, we have to know things like:
- Data structures for analysis — tables, data frames, arrays/matrices, hierarchical (JSON/XML), graphs, time series. Etc.
- Data preprocessing (the standard pipeline: Cleaning, Integration, Transformation, Reduction)
  - Data cleaning — missing values, duplicates, outliers, inconsistent formatting/invalid entries. Etc.
  - Data integration — merging/joining multiple sources, schema/entity resolution. Etc.
  - Data transformation — aggregation, grouping, normalization, binning, encoding, feature engineering. Etc.
  - Data reduction — sampling, dimensionality reduction, feature selection. Etc.

## Further topics
The higher we go, the deeper and detailed knowledge such as:
- Probability — distributions, random variables, expected value. Etc.
- Statistical inference — confidence intervals, hypothesis testing, p-values, significance. Etc.
- Relationships between variables — correlation vs causation, confounders, regression. Etc.

Beyond this: the analytics maturity model (Descriptive → Diagnostic → Predictive → Prescriptive) — DA covers Descriptive/Diagnostic and reaches into Predictive via things like regression; Predictive/Prescriptive proper is Machine Learning, a separate field built on these foundations.

## Application topics
But data analytics becomes part of a business/application when we learn the things like
- Communication & Consumption — visualization, storytelling, BI/dashboards, reporting. Etc.
- Infrastructure — data pipeline and architecture (ETL/ELT, data warehouse/lake). Etc.
- Decision-making & Strategy — KPIs, A/B testing, data-driven culture. Etc.
- Governance & Ethics — privacy, security, regulatory compliance, data quality standards, bias/fairness. Etc.
