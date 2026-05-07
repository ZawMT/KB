# Node.js Basic Example — Calculator

Demonstrates the most fundamental Node.js concepts:
- Creating an HTTP server using the built-in `http` module (no frameworks, no dependencies)
- Parsing URL query parameters
- Returning JSON responses
- A plain HTML/JS frontend calling the backend via `fetch`

## Structure

```
01/
├── backend/
│   └── server.js      # Node.js HTTP server (calculator API)
└── frontend/
    └── index.html     # Browser UI that calls the backend
```

## How to Run

### 1. Start the backend

```bash
cd backend
node server.js
```

The server starts at `http://localhost:3000`.

### 2. Open the frontend

Open `frontend/index.html` directly in your browser (double-click or drag it in). No web server needed for the frontend.

## API

**Endpoint:** `GET /calculate`

| Param | Description              |
|-------|--------------------------|
| `op`  | `add`, `subtract`, `multiply`, `divide` |
| `a`   | First number             |
| `b`   | Second number            |

**Examples:**

```
GET http://localhost:3000/calculate?op=add&a=10&b=5
→ { "op": "add", "a": 10, "b": 5, "result": 15 }

GET http://localhost:3000/calculate?op=divide&a=10&b=0
→ { "error": "Division by zero" }
```

## Key Node.js Concepts Illustrated

| Concept | Where |
|---------|-------|
| `require()` — importing built-in modules | `server.js` lines 4-5 |
| `http.createServer()` — creating an HTTP server | `server.js` line 22 |
| Request/response object (`req`, `res`) | `server.js` line 22 callback |
| `url.parse()` — reading query parameters | `server.js` line 27 |
| `res.writeHead()` / `res.end()` — sending a response | throughout `server.js` |
| `server.listen()` — binding to a port | `server.js` last line |
