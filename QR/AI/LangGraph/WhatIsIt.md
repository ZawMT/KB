## LangGraph
### What is it?

#### [Back to LangGraph contents](_Contents.md)

LangGraph (by the LangChain team) is a library for building LLM applications as a **graph of steps**, instead of a straight-line chain.

Two core ideas:
- **State**: a shared object (usually a `dict` or `TypedDict`) that flows through the graph. Each node reads it and returns updates to it.
- **Nodes and edges**: nodes are units of work (an LLM call, a tool call, a bit of Python logic). Edges wire nodes together, and can be **conditional**, routing to different nodes depending on what's in the state.

The key thing that sets it apart from a plain LangChain chain: **graphs can loop**. A chain is basically a straight pipeline (A → B → C). LangGraph lets you go A → B → A → B → C, which is exactly what an "agent" needs: think → act (call a tool) → observe the result → think again → ... until it decides it's done.

It also gives checkpointing (save/resume state) and human-in-the-loop interrupts (pause the graph mid-run for a human to approve or edit something) largely for free.

### Prerequisites
- **Python**: comfortable with functions, classes, dicts, and type hints (`TypedDict`, `Annotated`) — LangGraph leans on these for defining state.
- **What an LLM call looks like**: messages with roles (system/user/assistant), and the idea of "tool calling" (the model asks to invoke a function, you run it, you feed the result back).
- **Basic graph/state-machine intuition**: nodes and edges, conditional branching.
- LangChain basics are helpful but not required — LangGraph can be used standalone.
- Async/await in Python is common in LangGraph code, but not required to start.

### Common use cases
- **Agents that loop**: ReAct-style agents that reason, call a tool, look at the result, and decide whether to call another tool or respond — needs cycles, which plain chains can't do cleanly.
- **Multi-agent systems**: a "supervisor" node routes work to specialist agent nodes and combines results.
- **Stateful chatbots**: conversation memory that persists and evolves across many turns, not just one prompt→response.
- **Human-in-the-loop workflows**: e.g. an agent drafts an email, execution pauses, a human approves/edits, then it continues.
- **Self-correcting RAG**: retrieve → grade the retrieval → if bad, rewrite the query and retry → generate answer.
