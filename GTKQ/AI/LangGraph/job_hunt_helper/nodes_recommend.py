from llm import llm, search_llm
from models import Recommendations
from state import JDState


def _text(message) -> str:
    # The Responses API returns content as a list of blocks (text, search calls, ...).
    if isinstance(message.content, str):
        return message.content
    return "".join(
        block.get("text", "")
        for block in message.content
        if isinstance(block, dict) and block.get("type") == "text"
    )


def recommend(state: JDState) -> dict:
    job = state["job_info"]
    skills = job.programming_languages + job.databases + job.other_skills

    # Step 1: search the web (free-form text answer with real links).
    found = _text(
        search_llm.invoke(
            "Search the web and recommend:\n"
            "1. One YouTube video, and\n"
            "2. One book (title and author)\n"
            f"that would best help someone prepare for this role: {job.position} at "
            f"{job.organisation}.\n"
            f"Key skills: {', '.join(skills)}.\n"
            f"Skills the candidate is missing: {', '.join(state['skill_match'].missing_skills)}.\n"
            "For the video, give its exact title and the full youtube.com URL taken from the "
            "search results. Briefly say why each one helps."
        )
    )

    # Step 2: turn that text into our structured model.
    recommendations = llm.with_structured_output(Recommendations).invoke(
        "Extract the YouTube video and the book from this text. Copy the URL exactly as written.\n\n"
        + found
    )
    return {"recommendations": recommendations}
