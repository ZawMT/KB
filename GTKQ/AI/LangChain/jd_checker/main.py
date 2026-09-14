from pydantic import BaseModel
from pypdf import PdfReader


class JobInfo(BaseModel):
    organisation: str
    position: str
    programming_languages: list[str]
    databases: list[str]
    other_skills: list[str]


def load_pdf(file_path):
    reader = PdfReader(file_path)
    return [page.extract_text() for page in reader.pages]


def main():
    pages = load_pdf("JD1.pdf")

    for i, text in enumerate(pages):
        print(f"--- Page {i + 1} ---")
        print(text)


if __name__ == "__main__":
    main()
