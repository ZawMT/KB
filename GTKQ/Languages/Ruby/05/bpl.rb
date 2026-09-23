# Blocks vs Procs vs Lambdas

# --- Block ---
# Not an object - just syntax attached to a method call. A method
# receives it implicitly and runs it with 'yield'.
def run_with_block
  yield("from a block")
end

puts run_with_block { |msg| "Block says: #{msg}" }

# A block can also be captured explicitly as a Proc via '&name'.
def run_with_captured_block(&blk)
  blk.call("captured block")
end

puts run_with_captured_block { |msg| "Captured says: #{msg}" }

# --- Proc ---
# An actual object, stored in a variable, callable later.
say_proc = Proc.new { |name| "Proc says hi to #{name}" }
puts say_proc.call("Alice")
puts say_proc.("Bob")   # shorthand for .call
puts say_proc["Cleo"]   # another shorthand for .call

# Procs are lenient about argument count - missing args become nil,
# extra args are ignored.
lenient = Proc.new { |a, b| "a=#{a.inspect}, b=#{b.inspect}" }
puts lenient.call(1)       # b is nil, no error
puts lenient.call(1, 2, 3) # 3 is ignored, no error

# --- Lambda ---
# Also a Proc under the hood (lambda.is_a?(Proc) is true), but stricter.
say_lambda = lambda { |name| "Lambda says hi to #{name}" }
# or the literal arrow syntax:
say_lambda2 = ->(name) { "Arrow lambda says hi to #{name}" }

puts say_lambda.call("Dave")
puts say_lambda2.call("Eve")

# Lambdas enforce arity - wrong argument count raises ArgumentError.
begin
  say_lambda.call
rescue ArgumentError => e
  puts "Lambda arity check: #{e.message}"
end

# --- return behavior: the key practical difference ---

def proc_return_demo
  my_proc = Proc.new { return "returned from the PROC's return" }
  my_proc.call
  "this line is never reached - proc's return exits the whole method"
end

def lambda_return_demo
  my_lambda = lambda { return "returned from the LAMBDA's return" }
  result = my_lambda.call
  "lambda's return only exited the lambda - method continues, got: #{result}"
end

puts proc_return_demo
puts lambda_return_demo

puts "\nsay_proc.is_a?(Proc):   #{say_proc.is_a?(Proc)}"
puts "say_lambda.is_a?(Proc): #{say_lambda.is_a?(Proc)}"
puts "say_lambda.lambda?:     #{say_lambda.lambda?}"
puts "say_proc.lambda?:       #{say_proc.lambda?}"

# --- Wrapping a named method (def...end) as an object ---
# A regular 'def' method is NOT an object by itself - you can't store it
# in a variable or pass it around. 'method(:name)' wraps it into a Method
# object, which behaves a lot like a Proc/lambda from here on.

def square(n)
  n * n
end

squarer = method(:square)   # wraps the def into a Method object
puts squarer.call(5)        # => 25
puts squarer.(6)            # => 36, same shorthand call syntax as Proc

# Method objects can be converted to a Proc with .to_proc, and passed
# anywhere a block is expected using '&'.
puts [1, 2, 3].map(&squarer) # => [1, 4, 9]

# squarer.is_a?(Proc) is false - Method is its own class, distinct from
# Proc/lambda, until you explicitly convert it.
puts "squarer.is_a?(Proc):        #{squarer.is_a?(Proc)}"
puts "squarer.to_proc.is_a?(Proc): #{squarer.to_proc.is_a?(Proc)}"
