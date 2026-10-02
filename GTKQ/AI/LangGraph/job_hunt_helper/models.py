from typing import Literal

from pydantic import BaseModel, Field


class JobInfo(BaseModel):
    organisation: str
    position: str
    seniority: str = Field(description="e.g. junior, mid, senior, lead. 'unspecified' if not stated")
    programming_languages: list[str]
    databases: list[str]
    other_skills: list[str]
    responsibilities: list[str] = Field(description="Main duties of the role, as short phrases")


class CandidateInfo(BaseModel):
    name: str
    current_title: str
    years_experience: float = Field(description="Total years of professional experience")
    programming_languages: list[str]
    databases: list[str]
    other_skills: list[str]
    past_roles: list[str] = Field(description="Each as 'Title at Company', most recent first")


class SkillMatch(BaseModel):
    match_score: int = Field(description="0-100: how well the candidate's skills cover the job's requirements")
    matched_skills: list[str]
    missing_skills: list[str]
    summary: str


class RedFlags(BaseModel):
    red_flags: list[str] = Field(description="Concerning things in the job description. Empty if none")
    summary: str


class SeniorityFit(BaseModel):
    fit: Literal["under-qualified", "good fit", "over-qualified"]
    summary: str


class Decision(BaseModel):
    should_apply: bool
    reasons: list[str]


class Critique(BaseModel):
    approved: bool = Field(description="True if the cover letter is ready to send as-is")
    feedback: str = Field(description="Concrete changes to make. Empty if approved")


class Recommendations(BaseModel):
    youtube_title: str
    youtube_url: str
    youtube_why: str = Field(description="One sentence: why this video helps for the job")
    book_title: str
    book_author: str
    book_why: str = Field(description="One sentence: why this book helps for the job")


class InterviewQuestion(BaseModel):
    question: str
    answer: str = Field(description="A strong answer in the candidate's own voice, based on their resume")


class InterviewQuestions(BaseModel):
    items: list[InterviewQuestion] = Field(description="Exactly 5 questions")
