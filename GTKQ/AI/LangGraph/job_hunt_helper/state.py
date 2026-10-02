from typing import TypedDict

from models import (
    CandidateInfo,
    Critique,
    Decision,
    ExperienceCheck,
    InterviewQuestions,
    JobInfo,
    Recommendations,
    RedFlags,
    SkillMatch,
)


# total=False: every key is optional, because each node only fills in its own part.
# Note: LangGraph doesn't allow a node to have the same name as a state key.
class JDState(TypedDict, total=False):
    # inputs
    jd_path: str
    resume_path: str

    # nodes_extract
    jd_text: str
    resume_text: str
    job_info: JobInfo
    candidate_info: CandidateInfo

    # nodes_analyze
    skill_match: SkillMatch
    red_flags: RedFlags
    experience_check: ExperienceCheck
    decision: Decision

    # nodes_cover_letter
    cover_letter: str
    critique: Critique
    revision_count: int

    # nodes_recommend / nodes_interview
    recommendations: Recommendations
    interview_questions: InterviewQuestions

    # nodes_output
    output_files: list[str]
