## LangChain
### What is it?

#### [Back to LangChain contents](_Contents.md)

LangChain is a framework for building LLM applications by composing smaller building blocks — prompts, models, output parsers, retrievers, tools — into a pipeline.

Core pieces:
- **Prompt templates**: reusable prompts with variable placeholders.
- **Chat models**: a common wrapper around different LLM providers (OpenAI, Anthropic, etc.) so the calling code looks the same regardless of provider.
- **Output parsers**: turn raw model text into structured data (e.g. JSON, a Pydantic object).
- **Retrievers / vector stores**: fetch relevant chunks of text for RAG (retrieval-augmented generation).
- **Tools**: functions the model can be given so it can call them (e.g. a search function, a calculator).
- **Runnables & LCEL** (LangChain Expression Language): the `|` pipe operator used to compose the above into a pipeline, e.g. `prompt | model | output_parser`. Every piece implements a common `Runnable` interface (`invoke`, `batch`, `stream`, and async variants).

Chains built this way are typically **linear/DAG** — data flows one direction through the pipe. There's no built-in looping; for multi-step agents that need to loop (think → act → observe → think again), that's what LangGraph is for, and it's built on top of the same `Runnable`/message concepts.

### Prerequisites
- **Python**: functions, classes; decorators and type hints are helpful but not essential to start.
- **What an LLM call looks like**: messages with roles (system/user/assistant).
- **pip/venv basics**: to install `langchain` plus a provider package (e.g. `langchain-openai` or `langchain-anthropic`) and manage an API key.
- No LangGraph knowledge needed — this is the more foundational layer.

### Common use cases
- **Prompt templating**: reusable, parameterised prompts instead of hand-built strings.
- **Structured output extraction**: pull structured data (JSON, specific fields) out of free-text model responses.
- **Simple RAG pipelines**: retrieve relevant document chunks → stuff into a prompt → generate an answer.
- **Single-shot tool calling**: let the model call one function/tool as part of a pipeline (not a looping agent — that's LangGraph territory).
- **Straightforward text pipelines**: summarisation, translation, classification, etc. where the flow is linear.
