from pathlib import Path

from dotenv import load_dotenv
from langchain_openai import ChatOpenAI
from pydantic import BaseModel
from pypdf import PdfReader

load_dotenv()


class JobInfo(BaseModel):
    organisation: str
    position: str
    programming_languages: list[str]
    databases: list[str]
    other_skills: list[str]


def load_pdf(file_path):
    reader = PdfReader(file_path)
    return [page.extract_text() for page in reader.pages]


def extract_job_info(text):
    model = ChatOpenAI(model="gpt-4o-mini")
    structured_model = model.with_structured_output(JobInfo)
    return structured_model.invoke(
        "Extract the organisation, position, and required programming languages, "
        f"databases, and other skills from this job description:\n\n{text}"
    )


def main():
    pdf_filename = input("Enter PDF filename: ")

    pages = load_pdf(pdf_filename)
    full_text = "\n".join(pages)

    job_info = extract_job_info(full_text)

    json_filename = Path(pdf_filename).with_suffix(".json")
    json_filename.write_text(job_info.model_dump_json(indent=2))
    print(f"Wrote {json_filename}")


if __name__ == "__main__":
    main()
