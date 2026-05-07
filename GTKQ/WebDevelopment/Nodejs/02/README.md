# Node.js Basic Example — Calculator (Hosted Frontend)

Extends example `01` by serving the frontend over HTTP instead of opening it as a local file.

A single Node.js server handles two routes:

| Route                              | What it does                             |
|------------------------------------|------------------------------------------|
| `GET /`                            | Serves `frontend/index.html` to the browser |
| `GET /calculate?op=&a=&b=` | Runs the calculation, returns JSON result |

Because both the page and the API come from the same server (same origin), no CORS headers are needed and the frontend uses a **relative URL** (`/calculate`) instead of an absolute one.

## Structure

```
02/
├── server.js           # Single server: serves frontend + API
└── frontend/
    └── index.html      # HTML page (fetched from the server, not opened as a file)
```

## How to Run

```bash
cd 02
node server.js
```

Then open your browser at:

```
http://localhost:3000
```

## Key Differences from Example 01

| | 01 | 02 |
|---|---|---|
| Frontend delivery | Opened as a local `file://` | Served over HTTP by Node.js |
| `fetch` URL | Absolute `http://localhost:3000/calculate` | Relative `/calculate` |
| CORS header needed | Yes (different origins) | No (same origin) |
| Servers | 1 (backend only) | 1 (serves both frontend and API) |
| New module used | — | `fs` (to read and serve the HTML file) |

## Key Node.js Concepts Illustrated

| Concept | Where |
|---------|-------|
| `fs.readFile()` — reading a file asynchronously | `server.js` line 30 |
| `path.join(__dirname, ...)` — portable file paths | `server.js` line 11 |
| Routing by `pathname` | `server.js` lines 26, 44 |
| Serving different content types (`text/html` vs `application/json`) | `server.js` lines 35, 46 |
