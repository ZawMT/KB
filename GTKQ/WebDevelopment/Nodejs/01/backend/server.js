// Basic Node.js HTTP server - no external dependencies
// Uses only the built-in 'http' module

const http = require('http');
const url = require('url');

const PORT = 3000;

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

const server = http.createServer((req, res) => {
  // Allow requests from the frontend (CORS)
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Content-Type', 'application/json');

  const parsed = url.parse(req.url, true);

  if (parsed.pathname !== '/calculate') {
    res.writeHead(404);
    res.end(JSON.stringify({ error: 'Not found. Use GET /calculate?op=add&a=5&b=3' }));
    return;
  }

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
});

server.listen(PORT, () => {
  console.log(`Calculator backend running at http://localhost:${PORT}`);
  console.log('Example: http://localhost:3000/calculate?op=add&a=5&b=3');
});
