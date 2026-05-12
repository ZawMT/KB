<template>
  <div class="container">
    <h1>Simple Math App</h1>

    <div class="calculator">
      <input v-model.number="num1" type="number" placeholder="Number 1" />

      <select v-model="operation">
        <option value="add">+ (Add)</option>
        <option value="sub">- (Subtract)</option>
        <option value="mul">x (Multiply)</option>
        <option value="div">/ (Divide)</option>
      </select>

      <input v-model.number="num2" type="number" placeholder="Number 2" />

      <button @click="calculate">Calculate</button>

      <div v-if="result !== null" class="result">
        Result: <strong>{{ result }}</strong>
      </div>

      <div v-if="error" class="error">{{ error }}</div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      num1: null,
      num2: null,
      operation: 'add',
      result: null,
      error: null
    }
  },
  methods: {
    async calculate() {
      this.result = null
      this.error = null

      try {
        const response = await fetch('http://localhost:3001/calculate', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            num1: this.num1,
            num2: this.num2,
            operation: this.operation
          })
        })

        const data = await response.json()
        this.result = data.result
      } catch (err) {
        this.error = 'Could not connect to the server. Is the backend running?'
      }
    }
  }
}
</script>

<style>
body {
  font-family: sans-serif;
  background: #f4f4f4;
  margin: 0;
}

.container {
  max-width: 400px;
  margin: 60px auto;
  background: white;
  padding: 32px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

h1 {
  margin: 0 0 24px;
  font-size: 1.5rem;
}

.calculator {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

input, select {
  padding: 10px;
  font-size: 1rem;
  border: 1px solid #ccc;
  border-radius: 4px;
}

button {
  padding: 12px;
  font-size: 1rem;
  background: #333;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button:hover {
  background: #555;
}

.result {
  font-size: 1.1rem;
  padding: 12px;
  background: #eef;
  border-radius: 4px;
}

.error {
  color: red;
  font-size: 0.9rem;
}
</style>
