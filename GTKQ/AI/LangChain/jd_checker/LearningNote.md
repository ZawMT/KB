# LangChain vs raw OpenAI — `jd_checker`

Two versions of the same script, to compare directly:

- **`main_langchain.py`** — uses `langchain_openai.ChatOpenAI` + `.with_structured_output(JobInfo)`.
- **`main_openai.py`** — uses the raw `openai` SDK's `client.chat.completions.parse(..., response_format=JobInfo)`.

Both read a PDF job description and write the same structured `JobInfo` JSON. Same result, same underlying OpenAI API call.

## What LangChain is actually doing here

`ChatOpenAI` is a thin wrapper around the OpenAI SDK — it still calls OpenAI's chat completions API underneath. What it adds:

- A **standardized interface** (`Runnable`: `.invoke()`, `.stream()`, `|` piping) that's the same across providers. Swap `ChatOpenAI` for `ChatAnthropic` and the rest of the script doesn't change.
- **`with_structured_output()`** — converts a Pydantic model to a schema, wires it into the provider's structured-output feature, and parses the response back into a validated instance, with retry/error handling built in.

## The tradeoff

| | `main_langchain.py` | `main_openai.py` |
|---|---|---|
| Dependency | `langchain_openai` (+ langchain-core) | `openai` only |
| Portable across providers | Yes — same code shape for other models | No — tied to OpenAI's API |
| Structured output | `.with_structured_output(JobInfo)` | `client.chat.completions.parse(response_format=JobInfo)` |
| Abstraction | Extra layer to learn | Closer to the wire |

For a single-provider script like this, LangChain isn't buying much — the raw SDK is arguably simpler. LangChain starts to pay off once you're chaining multiple calls, swapping models, or adding tools/agents/retrieval — places where the standardized interface and prebuilt utilities save real work.

## How the result gets mapped into `JobInfo`

Both `with_structured_output(JobInfo)` and `response_format=JobInfo` work the same way under the hood:

1. The Pydantic model is converted into a JSON Schema, including each field's **name** and **type** (`str`, `list[str]`, etc.).
2. That schema is sent to OpenAI alongside the prompt. The API then constrains the model's output so it can only emit JSON matching that schema — this is enforced mechanically, so the shape (keys present, list vs. string) is guaranteed.
3. The **mapping of content to the right field is not mechanical** — it relies on the model reading the field names as semantic hints, the same way it reads any other instruction. `programming_languages` only ends up with programming languages because the model understands what that name means combined with the job description text. Vague field names (`field1`, `field2`) would give it much less to go on.
4. The returned JSON is parsed and validated back into a real `JobInfo` instance.

So structure is guaranteed by the API; correct content-to-field mapping still depends on how well the schema communicates intent.

## `Field(description=...)` for steering accuracy

Pydantic's `Field` lets you attach a description per attribute, which also gets included in the schema sent to the model — a way to disambiguate beyond just the field name:

```python
from pydantic import BaseModel, Field

class JobInfo(BaseModel):
    programming_languages: list[str] = Field(
        description="Languages explicitly required, not nice-to-haves"
    )
```

Useful when a field name alone is ambiguous, or when you want to nudge the model toward a specific interpretation (e.g. required vs. preferred skills).

## When LangChain is actually worth it

Not just "it's a framework so use it" — concrete cases where it earns its cost over the raw SDK:

1. **Multi-provider / model-swapping** — running the same pipeline against OpenAI, Anthropic, a local model, etc. One `Runnable` interface beats maintaining a separate SDK call pattern per provider.
2. **Chains of multiple LLM calls** — e.g. cheap model to classify/route, then a stronger model to generate, then another to critique. `|` piping and `Runnable` composition make this legible instead of hand-rolled glue code.
3. **Structured output on providers without native schema support** — OpenAI has first-class structured outputs, but many providers don't. `with_structured_output` falls back to tool-calling or prompt+parser tricks so the calling code doesn't change per provider.
4. **Agents / tool-calling loops** — an LLM deciding which tool to call, executing it, feeding the result back, repeating. This is actually **LangGraph's** job, not plain LangChain (see below) — LangGraph provides the loop, state, and tool-schema wiring instead of hand-rolling control flow.
5. **Retrieval (RAG)** — standardized interfaces across vector stores/retrievers/document loaders (Chroma, Pinecone, FAISS, ...) so swapping the backend doesn't touch the rest of the pipeline.
6. **Memory / conversation state** — utilities for trimming, summarizing, or persisting chat history across turns instead of managing message lists by hand.

Common thread: LangChain pays off when there are **multiple moving pieces that need to compose or swap** — providers, steps, or backends. For a single call to a single provider, like this script, the raw SDK is simpler and nothing is missing by skipping LangChain.

## LangChain (chains) vs LangGraph (loops)

Plain LangChain — `Runnable`/LCEL, the `A | B | C` style composition — only builds a **straight pipeline or DAG**. No cycles, no "go back and retry a step based on a decision." Agent loops (call a tool → get a result → decide → maybe call another tool → repeat) need cycles, which LCEL can't express — that's specifically why **LangGraph** exists.

So plenty of real use cases are legitimately loop-free, because the number of steps is fixed ahead of time — plain LangChain chains are enough, no LangGraph needed:

- **Basic RAG** — retrieve documents → stuff into prompt → generate answer. One pass, no re-visiting.
- **`jd_checker`-style extraction** — load doc → LLM call → structured output (this repo's example).
- **Map-reduce summarization** — summarize each chunk → combine the summaries → summarize the combination.
- **Multi-step transformation pipelines** — e.g. translate → summarize → extract entities. Fixed order, never loops back.
- **Classify-then-route** — LCEL's `RunnableBranch` handles "if classified as X, run chain A, else chain B." That's a one-time fork, not a loop, so it fits fine within plain LangChain.

The deciding question: **does the number of steps depend on something the LLM decides at runtime** (keep calling tools until it says "done")? If yes, that's a loop → reach for LangGraph. If the steps are fixed in advance — even with a one-time branch — plain LangChain chains are the simpler, correct tool.
