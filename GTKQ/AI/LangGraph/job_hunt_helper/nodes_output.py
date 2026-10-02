import json
from pathlib import Path

from models import InterviewQuestions, JobInfo, Recommendations
from state import JDState


def _recommendations_md(job: JobInfo, rec: Recommendations) -> str:
    return (
        f"# Recommendations: {job.position} at {job.organisation}\n\n"
        "## YouTube\n\n"
        f"[{rec.youtube_title}]({rec.youtube_url})\n\n"
        f"{rec.youtube_why}\n\n"
        "## Book\n\n"
        f"**{rec.book_title}** by {rec.book_author}\n\n"
        f"{rec.book_why}\n"
    )


def _interviews_md(job: JobInfo, qa: InterviewQuestions) -> str:
    items = qa.items[:10]
    questions = "\n".join(f"{i}. {item.question}" for i,
                          item in enumerate(items, 1))
    answers = "\n\n".join(
        f"### {i}. {item.question}\n\n{item.answer}" for i, item in enumerate(items, 1)
    )
    return (
        f"# Interview Practice: {job.position} at {job.organisation}\n\n"
        "## Questions\n\n"
        "Try answering these yourself first, then scroll down to check.\n\n"
        f"{questions}\n\n"
        "---\n\n"
        "## Answers\n\n"
        f"{answers}\n"
    )


def write_outputs(state: JDState) -> dict:
    jd_path = Path(state["jd_path"])
    name = jd_path.stem  # "JDs/JD1.pdf" -> "JD1"
    out_dir = jd_path.parent

    written = []

    def write(filename: str, content: str):
        path = out_dir / filename
        path.write_text(content)
        written.append(str(path))

    # Always: the extracted job info plus the analysis and decision.
    summary = {
        key: state[key].model_dump()
        for key in ("job_info", "skill_match", "red_flags", "experience_check", "decision")
    }
    write(f"{name}.json", json.dumps(summary, indent=2))

    if state["decision"].should_apply:
        job = state["job_info"]
        write(f"{name}-CoverLetter.md", state["cover_letter"])
        write(f"{name}-Recommendations.md",
              _recommendations_md(job, state["recommendations"]))
        write(f"{name}-Interviews.md",
              _interviews_md(job, state["interview_questions"]))

    return {"output_files": written}
