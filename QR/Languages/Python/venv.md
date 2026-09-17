## Python
### venv

#### [Back to Python contents](_Contents.md)

`venv` is Python's built-in tool for creating an isolated environment: its own `site-packages` folder, separate from the global/system Python packages.

### Creating and activating

```
python -m venv .venv
source .venv/bin/activate
```

- `-m venv` runs the built-in `venv` module — this part is fixed syntax.
- `.venv` (the second one) is just an argument: the folder name/path to create. It can be any name (`venv`, `.venv`, `myenv`, ...) — `.venv` is a common convention since the leading dot hides it from a plain `ls`.
- After activation, `python` / `pip` resolve to the copies inside `.venv/bin`, so installs land there instead of globally.

Verify it worked:
```
which python
```
Should print a path ending in `<project>/.venv/bin/python`.

### venv does not give you a different Python version

Creating a venv gives an isolated interpreter *copy/symlink* and package folder, but **not a new version** — it inherits whatever `python`/`python3` was active when you ran `python -m venv .venv`. Check the version with `python --version`.

To use a genuinely different version, you'd need to point `venv` at a specific interpreter (e.g. `python3.11 -m venv .venv`) or use a version-manager tool like `pyenv`.

### venv vs pyenv vs conda

| Tool | Isolates packages (per project) | Manages Python versions |
|------|----------------------------------|--------------------------|
| `venv` | Yes | No — inherits whichever interpreter created it |
| `pyenv` | No (needs the `pyenv-virtualenv` plugin for this) | Yes — installs and switches between Python versions |
| `conda` | Yes | Yes — each conda environment can have its own Python version |

So `venv` is roughly "the isolation half" of what conda does, and `pyenv` is roughly "the version-switching half." Conda does both at once, which is why it can feel like it's doing double duty.

### Note on conda + venv together

If conda's `base` environment auto-activates in your shell (via `conda init` in `.zshrc`), activating a `venv` on top of it stacks: the prompt shows both, e.g. `(.venv) (base)`. This is generally fine — `venv` activation prepends `.venv/bin` to `PATH`, so it takes priority — but it's worth confirming with `which python` rather than assuming.
