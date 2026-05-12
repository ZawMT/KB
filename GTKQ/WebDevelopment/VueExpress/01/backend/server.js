const express = require('express')
const cors = require('cors')

const app = express()

app.use(cors())
app.use(express.json())

app.post('/calculate', (req, res) => {
  const { num1, num2, operation } = req.body

  let result
  if (operation === 'add')      result = num1 + num2
  else if (operation === 'sub') result = num1 - num2
  else if (operation === 'mul') result = num1 * num2
  else if (operation === 'div') result = num2 !== 0 ? num1 / num2 : 'Error: divide by zero'
  else return res.status(400).json({ error: 'Invalid operation' })

  res.json({ result })
})

app.listen(3001, () => {
  console.log('Backend running on http://localhost:3001')
})
