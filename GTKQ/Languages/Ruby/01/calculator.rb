# Simple Calculator

def factorial(n)
  return 1 if n <= 1
  n * factorial(n - 1)
end

loop do
  puts "\nChoose an operation: Add, Subtract, Multiply, Divide, Factorial"
  print "Operator (or X to exit): "
  operator = gets.chomp # 'gets' reads input from the user and 'chomp' removes the trailing newline character

  break if operator.nil? || operator.strip.empty? || operator.strip.upcase == "X"

  case operator.strip.downcase
  when "add", "subtract", "multiply", "divide", "a", "s", "m", "d"
    print "Enter first number: "
    num1 = gets.chomp.to_f

    print "Enter second number: "
    num2 = gets.chomp.to_f

    case operator.strip.downcase
    when "add", "a"
      puts "Result: #{num1 + num2}"
    when "subtract", "s"
      puts "Result: #{num1 - num2}"
    when "multiply", "m"
      puts "Result: #{num1 * num2}"
    when "divide", "d"
      if num2 == 0
        puts "Error: Cannot divide by zero"
      else
        puts "Result: #{num1 / num2}"
      end
    end

  when "factorial", "f"
    print "Enter a number: "
    num = gets.chomp.to_i

    if num < 0
      puts "Error: Factorial is not defined for negative numbers"
    elsif num == 0
      puts "Result: 1"
    else
      puts "Result: #{factorial(num)}"
    end

  else
    puts "Unknown operator. Please choose Add, Subtract, Multiply, Divide, or Factorial."
  end
end

puts "Goodbye!"
