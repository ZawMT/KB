# LangGraph — JD "Should I apply?" checker

Follow-up to `LangChain/jd_checker`. That script was a straight line: load PDF → extract `JobInfo` → write JSON. Each step ran once, in order. LangGraph is for flows that **aren't** a straight line.

## LangGraph ≠ "just parallel"

Parallel execution is one thing LangGraph can do, but it isn't the main point. The core ideas are **shared state**, **branching**, and **loops**.

| Concept | What it means |
|---|---|
| **State** | One shared object (usually a `TypedDict` or Pydantic model) that every step reads from and writes to |
| **Nodes** | Plain functions: take the state, return the fields they update |
| **Edges** | Which node runs next |
| **Conditional edges** | Pick the next node based on the state (`if match_score > 70 → X else → Y`) |
| **Cycles / loops** | Go back to an earlier node, e.g. "draft → critique → redraft until good". This is the part a plain chain can't do. |
| **Parallel fan-out** | Several nodes run from the same point, and their results are merged back into the state |

## The example: "Should I apply, and if so, draft a cover letter"

Keep the JD extraction from the LangChain version, then add a decision on top.

```
   load_jd ──► extract_job_info ──┐
                                  │
   load_resume ─► extract_resume ─┤        (parallel: JD and resume are independent)
                                  ▼
                 ┌────────────────┼────────────────┐   (parallel fan-out)
                 ▼                ▼                ▼
            skill_match       red_flags        seniority
         (JobInfo vs CV)   (on-call, vague   (junior/senior?)
                                pay)
                 └────────────────┼────────────────┘   (fan-in)
                                  ▼
                             ┌─────────┐
                             │ decide  │ ── conditional edge
                             └────┬────┘
                       not a fit  │  good fit
                         ▼        ▼
                      report   draft_cover_letter ◄──┐
                                  ▼                  │  loop (max N times)
                               critique ─────────────┘
                                  │ good enough
                                  ▼
                                 END
```

Each LangGraph idea shows up here:

- **State**: e.g. `JDState` holding `jd_text`, `job_info: JobInfo`, `resume_text`, `candidate_info: CandidateInfo`, `match_score`, `red_flags`, `draft`, `feedback`, `revision_count`.
- **Parallel**: the JD and resume branches don't depend on each other, and neither do the three analysis nodes.
- **Conditional edge**: `decide` sends the flow to either `report` or `draft_cover_letter`.
- **Loop**: `critique` sends the draft back until it passes, or until `revision_count` hits a limit. **Always add a limit** so it can't loop forever.

`JobInfo` from the LangChain version can be reused as-is. It becomes one field in the graph state.

## Input: what the app needs to know about me

To decide "should I apply", `skill_match` and `decide` need something to compare the job against.

| Option | Pros | Cons |
|---|---|---|
| **Resume PDF** | Realistic, and `load_pdf` can be reused | Resume text is messy, and it gets sent to OpenAI on every run |
| **Hand-written profile** (`me.json`: skills, years, preferences) | Clean and cheap. Can include things a resume doesn't say ("no on-call", "min salary", "remote only") | Not "real" input |

**Chosen approach:** use the resume PDF, and treat it just like the JD. Extract it into a structured model (`CandidateInfo` with `skills`, `years_experience`, `past_roles`) the same way `JobInfo` was extracted. Then `skill_match` compares two **structured objects** instead of two blobs of text, which is more reliable and easier to debug.

Optional: a small `preferences.json` for things only I know (salary floor, remote/on-site, things I won't do). `red_flags` and `decide` can use it.

### Practical notes

- **Git:** add the resume PDF (and any extracted resume JSON) to `.gitignore` before the first commit, so personal details stay out of the history.
- **Cost:** the resume doesn't change, so the extracted `CandidateInfo` could be cached to a JSON file and not re-extracted every run. Do this later; keep step 1 simple.

## Build order

Add one concept at a time:

1. **Linear graph only:** `load_jd → extract_job_info → END`. This is the LangChain script rewritten as a graph. Learn `StateGraph`, `add_node`, `add_edge`, `compile`, `invoke`.
2. **Add the resume branch and the parallel fan-out:** the three analysis nodes plus a join. Look up how parallel writes to the same state key are merged: **reducers**, e.g. `Annotated[list, operator.add]`.
3. **Add `decide`** with `add_conditional_edges`.
4. **Add the draft ⇄ critique loop**, with a max-revisions guard.
5. **Bonus:** `graph.get_graph().draw_mermaid()` to see the graph drawn out.

## Implementation

### Files

| File | What's in it |
|---|---|
| `main.py` | CLI: asks for the JD filename, runs the graph, prints the result. `--graph` prints the Mermaid diagram |
| `graph.py` | Builds the `StateGraph`: nodes, edges, joins, conditional edges, the loop |
| `state.py` | `JDState`, the shared state every node reads and writes |
| `models.py` | Pydantic models for every structured LLM output |
| `llm.py` | Shared `ChatOpenAI` instances: `llm` (temp 0), `writer_llm` (temp 0.7), `search_llm` (web search) |
| `pdf_utils.py` | `load_pdf_text` |
| `nodes_extract.py` | `load_jd`, `load_resume`, `extract_job`, `extract_resume` |
| `nodes_analyze.py` | `analyze_skills`, `analyze_red_flags`, `analyze_seniority`, `decide` |
| `nodes_cover_letter.py` | `draft_cover_letter`, `critique_cover_letter`, `cover_letter_done`, `MAX_REVISIONS` |
| `nodes_recommend.py` | `recommend` (web search, then structured extraction) |
| `nodes_interview.py` | `prepare_interview` |
| `nodes_output.py` | `write_outputs`: the JSON always, and the 3 Markdown files only if applying |

Node files use `_` rather than `-` (`nodes_extract.py`) because Python can't `import` a module with a hyphen in its name.

### Output (for `JDs/JD1.pdf`)

- `JDs/JD1.json`: always. Job info plus skill match, red flags, seniority and the decision
- `JDs/JD1-CoverLetter.md`: only if applying
- `JDs/JD1-Recommendations.md`: only if applying. 1 YouTube video and 1 book
- `JDs/JD1-Interviews.md`: only if applying. 5 questions first, then the answers

### LangGraph details worth noticing

- **Nodes return only what they change** (`return {"job_info": ...}`). LangGraph merges that into the state.
- **Fan-out:** two edges from `START`, or a routing function that returns a *list* of node names (`route_after_decide`).
- **Fan-in / join:** `add_edge(["a", "b"], "c")` means `c` waits until both `a` and `b` have finished.
- **No reducers needed here:** parallel nodes each write *different* keys. If two parallel nodes wrote the *same* key, you'd need a reducer such as `Annotated[list, operator.add]`.
- **Loop exit node:** `cover_letter_done` does nothing. It gives the draft/critique loop one fixed exit that the `write_outputs` join can wait on.
- **Node names can't match state keys.** That's why the node is `prepare_interview` and the key is `interview_questions`.
- **Web search** (`nodes_recommend.py`): `ChatOpenAI(use_responses_api=True).bind_tools([{"type": "web_search_preview"}])`. The search answer is free text, so a second plain call extracts it into `Recommendations`. Still click the link once to check it.

### Run

```bash
cd GTKQ/AI/LangGraph
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
# put your key in .env: OPENAI_API_KEY=...
python main.py            # then enter e.g. JDs/JD1.pdf
python main.py --graph    # print the graph diagram
```

`.gitignore` excludes `.env`, `*.pdf` and `JDs/`, so the resume and generated files stay out of git.

## Understanding the implementation

### Adding nodes

```python
graph.add_node("decide", decide)
graph.add_node("draft_cover_letter", draft_cover_letter)
```

Does this mean the cover letter is drafted after `decide`, whether or not `should_apply` is true? **No.**

**`add_node` only registers a node. It does not set the run order.** The `add_node` lines are a list of available steps: "there's a step called `decide`, there's a step called `draft_cover_letter`". Putting them next to each other doesn't mean one runs after the other. **Edges** set the order.

The `should_apply` check is the conditional edge:

```python
graph.add_conditional_edges("decide", route_after_decide, APPLY_NODES + ["write_outputs"])
```

After `decide` finishes, LangGraph calls `route_after_decide(state)` and goes wherever it returns:

```python
def route_after_decide(state: JDState):
    if state["decision"].should_apply:
        return APPLY_NODES        # draft_cover_letter, recommend, prepare_interview (in parallel)
    return "write_outputs"        # skip straight to the end
```

`decide` doesn't return `should_apply` directly. It writes `{"decision": decision}` into the shared **state**, where `decision` is a `Decision` object with a `should_apply` field. The routing function then reads it from the state: `state["decision"].should_apply`.

| `should_apply` | What runs after `decide` |
|---|---|
| `True` | `draft_cover_letter`, `recommend` and `prepare_interview` in parallel, then `write_outputs` |
| `False` | straight to `write_outputs`; no cover letter is drafted |

To see this, run `python main.py --graph` and paste the output into mermaid.live. The dotted arrows out of `decide` are the conditional branches.

### Nodes vs Edges

Same idea as points and lines in a diagram, but LangGraph gives them a meaning:

| | In a diagram | In LangGraph | Example |
|---|---|---|---|
| **Node** | a point | a **step that does work**: a function that takes the state and returns the fields it changed | `decide` calls the LLM and writes `decision` |
| **Edge** | a line | **what runs next**. It does no work itself | `add_edge("draft_cover_letter", "critique_cover_letter")` |
| **Conditional edge** | a line that forks | a routing function reads the state and picks which line to follow | `route_after_decide` |

The **state** is neither. It's the shared data that moves along the edges; every node reads from it and writes to it.

**How to choose when designing a graph:**

- It **does** something (calls an LLM, reads a file, computes a score, writes output) → **node**. Usually named with a verb: `load_jd`, `analyze_skills`, `write_outputs`.
- It **decides where to go** or **in what order** ("after X, do Y", "wait for A and B", "if score is high go here, else there") → **edge**.

Common mistake: making "check if `should_apply`" a node. A node only updates state; it doesn't pick the next step. The choice belongs on a conditional edge.

**Question to think about:** `cover_letter_done` is a node that does no work. Why is it a node and not just an edge? (Hint: "LangGraph details worth noticing", the loop exit node.)
