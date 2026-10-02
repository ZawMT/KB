## AI
### logprobs

#### [Back to AI contents](../_Contents.md)

*What are logprobs in an LLM API, how are they read, and when are they useful?*

Short answer: **`logprobs` (log probabilities) tell you, for each token in the model's answer, how probable the model thought that token was. They're a per-token confidence signal: off by default, useful for analysing output, and useful inside apps when the answer is short and structured (yes/no, a label, a choice).**

### Background: tokens and probabilities

An LLM writes text one **token** at a time (a token is a word or part of a word). At each step it gives every possible next token a **probability**, then picks one. With `logprobs` turned on, the API also returns **the probability of each token it picked**, and optionally the most likely alternatives.

### Why "log"?

The value is the **natural logarithm** of the probability, not the probability itself.

- The probability of a whole text is the probabilities of its tokens **multiplied** together, which quickly becomes a number too small for a computer to store accurately.
- Logs turn that multiplication into simple **addition**: log(a × b) = log(a) + log(b).

### Reading the values

A logprob is always **0 or negative**. Closer to 0 = more confident.

| logprob | Probability |
|---|---|
| 0 | 100% |
| −0.1 | ~90% |
| −0.69 | 50% |
| −2.3 | 10% |
| −4.6 | 1% |

Convert back with **probability = e^logprob** (Python: `math.exp(logprob)`).

### Parameters (OpenAI)

| Parameter | Effect |
|---|---|
| `logprobs: true` | Return the logprob of each output token |
| `top_logprobs: n` (0–20) | Also return the **n most likely alternatives** at each position |

In LangChain: `ChatOpenAI(model=..., logprobs=True)`; the values appear in the response's `response_metadata["logprobs"]`.

### Per token, not per call

Logprobs are returned **for each token**, not as one figure for the whole call. A one-word answer `Yes` (OpenAI Chat Completions format):

```json
"logprobs": {
  "content": [
    {
      "token": "Yes",
      "logprob": -0.02,
      "top_logprobs": [
        { "token": "Yes", "logprob": -0.02 },
        { "token": "No",  "logprob": -3.9 }
      ]
    }
  ]
}
```

- `Yes`: e^−0.02 ≈ **98%**
- `No`: e^−3.9 ≈ **2%**

Because the answer is a single token, **that token's probability is the answer's confidence**.

### Longer answers: combine the tokens yourself

| Token | logprob | Probability |
|---|---|---|
| `Not` | −0.30 | 74% |
| ` spam` | −0.01 | 99% |

| Method | Calculation | Meaning |
|---|---|---|
| **Sum** | −0.30 + (−0.01) = −0.31 → e^−0.31 ≈ **73%** | Probability of this *exact* sequence of tokens |
| **Average** | (−0.30 − 0.01) ÷ 2 = −0.155 → e^−0.155 ≈ **86%** | Typical per-token confidence; fairer when comparing answers of different lengths |

Adding logprobs = multiplying probabilities (0.74 × 0.99 ≈ 0.73). The sum always shrinks as an answer gets longer, so a long answer looks "unlikely" even when every token is confident. That's why the average is used to compare answers of different lengths.

### When to use them

**Usually off.** Most apps (chatbots, writing helpers, summarisers, agents) only need the **text**, which is also the API's default. Turning logprobs on makes every response **larger**, adds data the code has to ignore, and isn't supported by every model (e.g. many reasoning models).

**1. Analysing or debugging output**

- Evaluating a prompt: where is the model unsure?
- Comparing models or prompts
- Investigating why a model gave a strange answer

**2. Inside an application, as a decision signal**

When the answer is short and structured, the logprob works as a **confidence score** the code can act on:

| Use | How it works |
|---|---|
| **Classification with a threshold** | "Is this email spam?" → `Yes` at 98% means auto-handle it; `Yes` at 55% means send it to a human |
| **Routing** | Choose which agent or tool handles a request; fall back to asking the user when confidence is low |
| **Ranking options** | Compare how likely the model finds each candidate answer |
| **Autocomplete** | Use `top_logprobs` to show the next few likely words as suggestions |
| **Flagging possible hallucinations** | Highlight low-confidence spans for review |

Rule of thumb: **long free text → rarely useful; short, structured answers → potentially very useful.**

### Setting up a confidence score

1. Tell the model to **answer with exactly one word** (e.g. "Answer only Yes or No", or labels like `A`/`B`/`C`).
2. Read the **first token's** logprob, plus the `top_logprobs` alternatives.
3. Compare it against a **threshold** in code.

Tokenisation detail: tokens aren't always whole words. `Yes` and ` Yes` (with a leading space) are different tokens, and a long word may be split into pieces. When looking up an answer in `top_logprobs`, compare after trimming spaces and ignoring case.

### Remember

- A high logprob means the model is **confident**, not that it's **correct**. Models can be confidently wrong; for anything important, treat it as one signal among others.
- Not available for every model or API; check the model's page.

### Think about it

If `top_logprobs` shows `Yes` at 50% and `No` at 45% for "Is this email spam?", what does that say about the email? And what makes up the remaining 5%?
