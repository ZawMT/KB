#### [Back to Python contents](_Contents.md)

| Key Point | Note |
|-----------|------|
| Pydantic object | An instance of a class inheriting from Pydantic's `BaseModel`. Fields are declared with type hints; Pydantic validates and parses input against those types at creation, raising a `ValidationError` on mismatch. Commonly used for structured data such as API request/response bodies or structured LLM output. |
