'use client';

import { useState } from 'react';

const SYMBOLS: Record<string, string> = {
  add: '+',
  subtract: '-',
  multiply: '×',
  divide: '÷',
};

export default function Home() {
  const [a, setA]       = useState('10');
  const [b, setB]       = useState('5');
  const [op, setOp]     = useState('add');
  const [result, setResult] = useState('Result will appear here');
  const [isError, setIsError] = useState(false);

  async function calculate() {
    setResult('Calculating...');
    setIsError(false);

    try {
      const res  = await fetch(`/api/calculate?op=${op}&a=${a}&b=${b}`);
      const data = await res.json();

      if (!res.ok) {
        setIsError(true);
        setResult('Error: ' + data.error);
      } else {
        setResult(`${data.a} ${SYMBOLS[op]} ${data.b} = ${data.result}`);
      }
    } catch {
      setIsError(true);
      setResult('Request failed');
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-100">
      <div className="bg-white rounded-xl shadow-md p-8 w-80">
        <h1 className="text-xl font-semibold mb-1">Basic Calculator</h1>
        <p className="text-sm text-gray-400 mb-6">Next.js — frontend and API on the same server</p>

        <label className="block text-sm text-gray-500 mb-1">Number A</label>
        <input
          type="number"
          value={a}
          onChange={e => setA(e.target.value)}
          className="w-full border border-gray-300 rounded px-3 py-2 mb-4 text-base"
        />

        <label className="block text-sm text-gray-500 mb-1">Operation</label>
        <select
          value={op}
          onChange={e => setOp(e.target.value)}
          className="w-full border border-gray-300 rounded px-3 py-2 mb-4 text-base"
        >
          <option value="add">Add (+)</option>
          <option value="subtract">Subtract (−)</option>
          <option value="multiply">Multiply (×)</option>
          <option value="divide">Divide (÷)</option>
        </select>

        <label className="block text-sm text-gray-500 mb-1">Number B</label>
        <input
          type="number"
          value={b}
          onChange={e => setB(e.target.value)}
          className="w-full border border-gray-300 rounded px-3 py-2 mb-4 text-base"
        />

        <button
          onClick={calculate}
          className="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded transition-colors"
        >
          Calculate
        </button>

        <div className={`mt-4 p-3 bg-gray-50 rounded text-center text-base ${isError ? 'text-red-600' : 'text-gray-700'}`}>
          {result}
        </div>
      </div>
    </div>
  );
}
