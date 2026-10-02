from llm import writer_llm
from models import InterviewQuestions
from state import JDState


def prepare_interview(state: JDState) -> dict:
    interview_questions = writer_llm.with_structured_output(InterviewQuestions).invoke(
        "Write exactly 5 interview questions this candidate is likely to be asked for this job: "
        "3 technical questions on the job's key skills and 2 behavioural questions. "
        "Focus especially on the skills the candidate is missing. For each, write a strong answer "
        "in the candidate's own voice (first person), based only on their real resume. "
        "Where they lack experience, answer honestly and show how they'd approach it.\n\n"
        f"JOB:\n{state['job_info'].model_dump_json(indent=2)}\n\n"
        f"MISSING SKILLS: {', '.join(state['skill_match'].missing_skills)}\n\n"
        f"RESUME:\n{state['resume_text']}"
    )
    return {"interview_questions": interview_questions}
