// Single Node.js server that serves both the frontend HTML and the calculator API.
// No external dependencies — uses only built-in 'http', 'fs', and 'url' modules.

const http = require('http');
const fs   = require('fs');
const path = require('path');
const url  = require('url');

const PORT          = 3000;
const FRONTEND_FILE = path.join(__dirname, 'frontend', 'index.html');

// --- Calculator logic ---

function calculate(op, a, b) {
  switch (op) {
    case 'add':      return a + b;
    case 'subtract': return a - b;
    case 'multiply': return a * b;
    case 'divide':
      if (b === 0) throw new Error('Division by zero');
      return a / b;
    default:
      throw new Error(`Unknown operation: ${op}`);
  }
}

// --- Request handler ---

const server = http.createServer((req, res) => {
  const parsed = url.parse(req.url, true);

  // Route: GET / → serve index.html
  if (parsed.pathname === '/') {
    fs.readFile(FRONTEND_FILE, (err, data) => {
      if (err) {
        res.writeHead(500, { 'Content-Type': 'text/plain' });
        res.end('Could not load frontend/index.html');
        return;
      }
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end(data);
    });
    return;
  }

  // Route: GET /calculate → calculator API
  if (parsed.pathname === '/calculate') {
    res.setHeader('Content-Type', 'application/json');

    const { op, a, b } = parsed.query;

    if (!op || a === undefined || b === undefined) {
      res.writeHead(400);
      res.end(JSON.stringify({ error: 'Missing params. Required: op, a, b' }));
      return;
    }

    const numA = parseFloat(a);
    const numB = parseFloat(b);

    if (isNaN(numA) || isNaN(numB)) {
      res.writeHead(400);
      res.end(JSON.stringify({ error: 'a and b must be numbers' }));
      return;
    }

    try {
      const result = calculate(op, numA, numB);
      res.writeHead(200);
      res.end(JSON.stringify({ op, a: numA, b: numB, result }));
    } catch (err) {
      res.writeHead(400);
      res.end(JSON.stringify({ error: err.message }));
    }
    return;
  }

  // Catch-all: 404
  res.writeHead(404, { 'Content-Type': 'text/plain' });
  res.end('Not found');
});

server.listen(PORT, () => {
  console.log(`Server running at http://localhost:${PORT}`);
  console.log(`  Frontend : http://localhost:${PORT}/`);
  console.log(`  API      : http://localhost:${PORT}/calculate?op=add&a=10&b=5`);
});
