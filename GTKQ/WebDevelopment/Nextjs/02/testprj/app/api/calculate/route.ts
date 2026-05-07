import { NextRequest, NextResponse } from 'next/server';

function calculate(op: string, a: number, b: number): number {
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

export async function GET(request: NextRequest) {
  const { searchParams } = new URL(request.url);

  const op = searchParams.get('op');
  const rawA = searchParams.get('a');
  const rawB = searchParams.get('b');

  if (!op || rawA === null || rawB === null) {
    return NextResponse.json(
      { error: 'Missing params. Required: op, a, b' },
      { status: 400 }
    );
  }

  const a = parseFloat(rawA);
  const b = parseFloat(rawB);

  if (isNaN(a) || isNaN(b)) {
    return NextResponse.json(
      { error: 'a and b must be numbers' },
      { status: 400 }
    );
  }

  try {
    const result = calculate(op, a, b);
    return NextResponse.json({ op, a, b, result });
  } catch (err) {
    return NextResponse.json(
      { error: (err as Error).message },
      { status: 400 }
    );
  }
}
