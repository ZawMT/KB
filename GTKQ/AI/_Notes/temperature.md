## AI
### Sampling temperature

#### [Back to AI contents](../_Contents.md)

*What is "temperature" in an LLM API, and what value should be used?*

Short answer: **Temperature controls how random the model's word choices are. Low values (around 0) make it pick the most likely words, so output is focused and repeatable. Higher values (around 0.7–1) give more variety and more natural-sounding text. In the OpenAI API the range is 0–2, and the default is 1.**

### Background: tokens and sampling

An LLM writes text one **token** at a time (a token is a word or part of a word). At each step:

1. The model gives **every possible next token a probability**.
2. One token is **picked** from those probabilities.
3. The picked token is added to the text, and the process repeats.

Picking at random *according to* those probabilities is called **sampling**. A token with 60% probability is picked about 60% of the time, one with 5% about 5% of the time.

### What temperature does

Temperature **reshapes the probabilities before the pick**.

Example: the next token after *"The cat sat on the"*:

| Token | T = 1 (original) | T = 0.5 (low) | T = 2 (high) |
|---|---|---|---|
| mat | 60% | 83% | 43% |
| sofa | 25% | 14% | 28% |
| roof | 10% | 2% | 17% |
| moon | 5% | 1% | 12% |

- **Low temperature** sharpens the distribution: likely tokens become even more likely.
- **High temperature** flattens it: unlikely tokens get a real chance.
- **T = 1** leaves the model's probabilities unchanged.

### How it works (the maths, briefly)

The model first produces a raw score for each token, called a **logit**. The scores are turned into probabilities with the **softmax** function, and temperature `T` divides the scores first:

```
probability(token i) = e^(logit_i / T) / Σ e^(logit_j / T)
```

- Dividing by a **small** `T` makes the differences between scores **bigger**, so the top token dominates.
- Dividing by a **large** `T` makes the differences **smaller**, so the probabilities even out.
- As `T` approaches 0, the top token gets ~100%. This is called **greedy decoding**: always pick the most likely token.

(Equivalently: each original probability is raised to the power 1/T, then all are rescaled to add up to 100%. That's how the table above was calculated.)

### Choosing a value

Values in the OpenAI API: **0 to 2**, default **1**.

| Temperature | Behaviour | Good for |
|---|---|---|
| **0** | (Almost) always the most likely token; focused and repeatable | Extraction, classification, structured output |
| **0–0.3** | Very focused, slight variation | Code, factual answers, analysis |
| **0.7–1** | Varied, natural wording | Writing, rephrasing, brainstorming |
| **above ~1.3** | Increasingly random | Rarely useful; output can drift into nonsense |

A common pattern is to use **separate model instances** for different jobs, e.g. in LangChain:

```python
from langchain_openai import ChatOpenAI

analyst = ChatOpenAI(model="gpt-4o-mini", temperature=0)    # consistent extraction and analysis
writer  = ChatOpenAI(model="gpt-4o-mini", temperature=0.7)  # more natural wording
```

### Temperature 0 is not fully deterministic

Even at 0, the same prompt can occasionally give a different answer:

- Two tokens can have almost identical scores, and tiny differences decide between them.
- Calculations on GPUs can vary slightly between runs (e.g. depending on how requests are batched together).

So 0 means "**as consistent as possible**", not "guaranteed identical". If exact repeatability matters, store the output rather than regenerating it.

### Temperature vs `top_p`

`top_p` (*nucleus sampling*) is another way to control randomness. Instead of reshaping the probabilities, it **cuts off the unlikely tail**: it keeps the smallest set of top tokens whose probabilities add up to at least `p`, then samples only from those.

Example with `top_p = 0.9`: mat 60% + sofa 25% = 85% (not enough), + roof 10% = 95% (enough). So **moon is excluded**, and the pick is between mat, sofa and roof.

| | Temperature | `top_p` |
|---|---|---|
| How | Reshapes all probabilities | Removes the unlikely tail |
| Range (OpenAI) | 0–2, default 1 | 0–1, default 1 |

**Change one or the other, not both.** They both control randomness, and combining them makes the effect hard to predict.

(Some other providers also offer `top_k`: keep only the k most likely tokens.)

### Things to watch

- **Model support:** some models don't accept `temperature`. Many reasoning models, for example, fix it internally and use other settings (such as reasoning effort) instead. Check the model's page.
- **Temperature doesn't change what the model knows.** Low temperature doesn't make answers *correct*, only *consistent*; a model can be consistently wrong.
- **High temperature isn't "more creative" in a useful sense beyond a point:** it mostly adds randomness.

### Think about it

For a step that asks the model "Does this job description match the CV? Answer Yes or No", which temperature makes sense, and why? What changes if the same step should instead write a short paragraph explaining the match?
