import sys
from pathlib import Path

from graph import build_graph

RESUME_PATH = Path(__file__).parent / "Resume-ZawMinTun.pdf"


def main():
    app = build_graph()

    # python main.py --graph  -> print the graph as Mermaid (paste into mermaid.live)
    if "--graph" in sys.argv:
        print(app.get_graph().draw_mermaid())
        return

    jd_path = input("Enter JD PDF filename: ")

    result = app.invoke({"jd_path": jd_path, "resume_path": str(RESUME_PATH)})

    decision = result["decision"]
    print(f"\nMatch score: {result['skill_match'].match_score}/100")
    print("Recommendation:", "APPLY" if decision.should_apply else "SKIP")
    for reason in decision.reasons:
        print(f"  - {reason}")

    if decision.should_apply:
        print(f"Cover letter revisions: {result['revision_count']}")

    print("\nWrote:")
    for path in result["output_files"]:
        print(f"  {path}")


if __name__ == "__main__":
    main()
