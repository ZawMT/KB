from llm import llm, writer_llm
from models import Critique
from state import JDState

MAX_REVISIONS = 3  # hard stop for the draft -> critique loop


def draft_cover_letter(state: JDState) -> dict:
    job = state["job_info"]
    prompt = (
        f"Write a cover letter for the position of {job.position} at {job.organisation}.\n"
        "- Use only facts from the resume. Never invent experience or skills.\n"
        "- Connect 2-3 concrete achievements from the resume to the job's needs.\n"
        "- Under 350 words, addressed to 'Dear Hiring Manager', signed with the candidate's name.\n"
        "- Output only the letter in Markdown, no commentary.\n\n"
        f"JOB:\n{job.model_dump_json(indent=2)}\n\n"
        f"RESUME:\n{state['resume_text']}"
    )

    # On a second+ pass, include the previous draft and the critic's feedback.
    if critique := state.get("critique"):
        prompt += (
            f"\n\nPREVIOUS DRAFT:\n{state['cover_letter']}\n\n"
            f"Revise the previous draft using this feedback:\n{critique.feedback}"
        )

    letter = writer_llm.invoke(prompt).content
    return {"cover_letter": letter, "revision_count": state.get("revision_count", 0) + 1}


def critique_cover_letter(state: JDState) -> dict:
    critique = llm.with_structured_output(Critique).invoke(
        "You are a strict hiring manager. Review this cover letter against the job and resume. "
        "Reject it if it is generic, claims anything not in the resume, is over 350 words, or "
        "doesn't address the job's key requirements. Otherwise approve it.\n\n"
        f"JOB:\n{state['job_info'].model_dump_json(indent=2)}\n\n"
        f"RESUME:\n{state['resume_text']}\n\n"
        f"COVER LETTER:\n{state['cover_letter']}"
    )
    return {"critique": critique}


def cover_letter_done(state: JDState) -> dict:
    # Does nothing. It exists so the loop has a single exit point that
    # write_outputs can wait on (see graph.py).
    return {}
