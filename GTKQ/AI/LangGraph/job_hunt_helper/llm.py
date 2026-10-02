from dotenv import load_dotenv
from langchain_openai import ChatOpenAI

load_dotenv()

MODEL = "gpt-4o-mini"

# Extraction, analysis, critique: we want consistent answers.
llm = ChatOpenAI(model=MODEL, temperature=0)

# Cover letter and interview answers: a bit more natural wording.
writer_llm = ChatOpenAI(model=MODEL, temperature=0.7)

# Same model plus OpenAI's built-in web search tool (Responses API),
# so YouTube links come from real search results instead of memory.
search_llm = ChatOpenAI(model=MODEL, use_responses_api=True).bind_tools(
    [{"type": "web_search_preview"}]
)
