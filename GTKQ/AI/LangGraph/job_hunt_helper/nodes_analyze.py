from llm import llm
from models import Decision, ExperienceCheck, RedFlags, SkillMatch
from state import JDState


def _job_and_candidate(state: JDState) -> str:
    return (
        f"JOB:\n{state['job_info'].model_dump_json(indent=2)}\n\n"
        f"CANDIDATE:\n{state['candidate_info'].model_dump_json(indent=2)}"
    )


# analyze_skills, analyze_red_flags and check_min_experience run in parallel:
# none of them needs the others' output.


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


def check_min_experience(state: JDState) -> dict:
    # Plain Python, no LLM: comparing two numbers doesn't need a model,
    # and this way the result is always the same for the same input.
    required = state["job_info"].min_experience
    years = state["candidate_info"].years_experience

    if required is None:
        meets = None
        summary = "The job doesn't state a minimum years of experience."
    else:
        meets = years >= required
        summary = (
            f"The job requires at least {required:g} years; the candidate has {years:g}. "
            + ("Meets the minimum." if meets else "Below the minimum.")
        )

    check = ExperienceCheck(
        min_required=required, candidate_years=years, meets_minimum=meets, summary=summary
    )
    return {"experience_check": check}


def decide(state: JDState) -> dict:
    decision = llm.with_structured_output(Decision).invoke(
        "Based on this analysis, should the candidate apply? A candidate doesn't need to match "
        "100% of the requirements. Around 60-70% is usually worth applying, as long as the "
        "candidate meets the minimum experience when one is stated. Having more experience "
        "than the minimum is never a reason against applying. Serious red flags can outweigh "
        "a good match.\n\n"
        f"SKILL MATCH:\n{state['skill_match'].model_dump_json(indent=2)}\n\n"
        f"RED FLAGS:\n{state['red_flags'].model_dump_json(indent=2)}\n\n"
        f"EXPERIENCE:\n{state['experience_check'].model_dump_json(indent=2)}"
    )
    return {"decision": decision}
