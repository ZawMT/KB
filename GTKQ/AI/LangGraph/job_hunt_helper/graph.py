from langgraph.graph import END, START, StateGraph

from nodes_analyze import analyze_red_flags, analyze_skills, check_min_experience, decide
from nodes_cover_letter import (
    MAX_REVISIONS,
    cover_letter_done,
    critique_cover_letter,
    draft_cover_letter,
)
from nodes_extract import extract_job, extract_resume, load_jd, load_resume
from nodes_interview import prepare_interview
from nodes_output import write_outputs
from nodes_recommend import recommend
from state import JDState

ANALYZE_NODES = ["analyze_skills", "analyze_red_flags", "check_min_experience"]
APPLY_NODES = ["draft_cover_letter", "recommend", "prepare_interview"]


# Routing functions: read the state, return the name(s) of the next node(s).


def route_after_decide(state: JDState):
    if state["decision"].should_apply:
        return APPLY_NODES  # a list = fan out, run all three in parallel
    return "write_outputs"


def route_after_critique(state: JDState):
    if state["critique"].approved or state["revision_count"] >= MAX_REVISIONS:
        return "cover_letter_done"
    return "draft_cover_letter"  # loop back


def build_graph():
    graph = StateGraph(JDState)

    graph.add_node("load_jd", load_jd)
    graph.add_node("load_resume", load_resume)
    graph.add_node("extract_job", extract_job)
    graph.add_node("extract_resume", extract_resume)
    graph.add_node("analyze_skills", analyze_skills)
    graph.add_node("analyze_red_flags", analyze_red_flags)
    graph.add_node("check_min_experience", check_min_experience)
    graph.add_node("decide", decide)
    graph.add_node("draft_cover_letter", draft_cover_letter)
    graph.add_node("critique_cover_letter", critique_cover_letter)
    graph.add_node("cover_letter_done", cover_letter_done)
    graph.add_node("recommend", recommend)
    graph.add_node("prepare_interview", prepare_interview)
    graph.add_node("write_outputs", write_outputs)

    # Two edges from START: the JD and resume branches run in parallel.
    graph.add_edge(START, "load_jd")
    graph.add_edge(START, "load_resume")
    graph.add_edge("load_jd", "extract_job")
    graph.add_edge("load_resume", "extract_resume")

    # A list of sources = "wait for ALL of these" (fan-in / join).
    for node in ANALYZE_NODES:
        graph.add_edge(["extract_job", "extract_resume"], node)
    graph.add_edge(ANALYZE_NODES, "decide")

    # Conditional edge: skip straight to writing the JSON, or fan out.
    graph.add_conditional_edges("decide", route_after_decide, APPLY_NODES + ["write_outputs"])

    # The loop: draft -> critique -> (draft again | done).
    graph.add_edge("draft_cover_letter", "critique_cover_letter")
    graph.add_conditional_edges(
        "critique_cover_letter", route_after_critique, ["draft_cover_letter", "cover_letter_done"]
    )

    # Wait for all three "apply" branches before writing files.
    graph.add_edge(["cover_letter_done", "recommend", "prepare_interview"], "write_outputs")
    graph.add_edge("write_outputs", END)

    return graph.compile()
