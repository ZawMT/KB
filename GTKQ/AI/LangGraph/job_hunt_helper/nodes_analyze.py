from llm import llm
from models import Decision, RedFlags, SeniorityFit, SkillMatch
from state import JDState


def _job_and_candidate(state: JDState) -> str:
    return (
        f"JOB:\n{state['job_info'].model_dump_json(indent=2)}\n\n"
        f"CANDIDATE:\n{state['candidate_info'].model_dump_json(indent=2)}"
    )


# The three analyze_* nodes run in parallel: none of them needs the others' output.


def analyze_skills(state: JDState) -> dict:
    skill_match = llm.with_structured_output(SkillMatch).invoke(
        "Compare the candidate's skills with the job's requirements. Treat close equivalents "
        "as a match (e.g. PostgreSQL experience counts for 'SQL databases').\n\n"
        + _job_and_candidate(state)
    )
    return {"skill_match": skill_match}


def analyze_red_flags(state: JDState) -> dict:
    red_flags = llm.with_structured_output(RedFlags).invoke(
        "List red flags in this job description from a candidate's point of view, e.g. "
        "unrealistic requirements, no salary information, heavy on-call, unpaid overtime, "
        "vague responsibilities, 'rockstar/ninja' culture. Only list what is actually there.\n\n"
        + state["jd_text"]
    )
    return {"red_flags": red_flags}


def analyze_seniority(state: JDState) -> dict:
    seniority = llm.with_structured_output(SeniorityFit).invoke(
        "Is the candidate under-qualified, a good fit, or over-qualified for the level of "
        "this role? Consider years of experience and past roles.\n\n" + _job_and_candidate(state)
    )
    return {"seniority": seniority}


def decide(state: JDState) -> dict:
    decision = llm.with_structured_output(Decision).invoke(
        "Based on this analysis, should the candidate apply? A candidate doesn't need to match "
        "100% of the requirements. Around 60-70% plus a reasonable seniority fit is usually "
        "worth applying. Serious red flags can outweigh a good match.\n\n"
        f"SKILL MATCH:\n{state['skill_match'].model_dump_json(indent=2)}\n\n"
        f"RED FLAGS:\n{state['red_flags'].model_dump_json(indent=2)}\n\n"
        f"SENIORITY:\n{state['seniority'].model_dump_json(indent=2)}"
    )
    return {"decision": decision}
