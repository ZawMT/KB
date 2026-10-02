from llm import llm
from models import CandidateInfo, JobInfo
from pdf_utils import load_pdf_text
from state import JDState

# Each node takes the whole state and returns only the keys it updates.


def load_jd(state: JDState) -> dict:
    return {"jd_text": load_pdf_text(state["jd_path"])}


def load_resume(state: JDState) -> dict:
    return {"resume_text": load_pdf_text(state["resume_path"])}


def extract_job(state: JDState) -> dict:
    job_info = llm.with_structured_output(JobInfo).invoke(
        "Extract the organisation, position, required minimum years of experience, required programming languages, "
        f"databases, other skills, and main responsibilities from this job description:\n\n"
        f"{state['jd_text']}"
    )
    return {"job_info": job_info}


def extract_resume(state: JDState) -> dict:
    candidate_info = llm.with_structured_output(CandidateInfo).invoke(
        "Extract the candidate's name, current title, years of experience, programming languages, "
        f"databases, other skills, and past roles from this resume:\n\n{state['resume_text']}"
    )
    return {"candidate_info": candidate_info}
