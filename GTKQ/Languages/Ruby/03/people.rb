# People Directory - class, arrays, and 'unless'

class Person
  attr_accessor :name, :age, :country

  def initialize(name, age, country)
    @name = name
    @age = age
    @country = country
  end

  def to_s
    "#{name}, #{age}, #{country}"
  end
end

people = []

loop do
  print "\nEnter Name (or X to stop): "
  name = gets.chomp.strip
  break if name.empty? || name.upcase == "X"

  print "Enter Age: "
  age = gets.chomp.to_i

  print "Enter Country: "
  country = gets.chomp.strip

  people << Person.new(name, age, country)
end

# 'unless' is the opposite of 'if' - the block runs only when the condition is false
unless people.any?
  puts "\nNo data entered."
else
  loop do
    print "\nSort by Name, Age, or Country (or X to exit): "
    choice = gets.chomp.strip
    break if choice.empty? || choice.upcase == "X"

    sorted = case choice.downcase
             when "name", "n"
               people.sort_by { |p| p.name.downcase }
             when "age", "a"
               people.sort_by { |p| p.age }
             when "country", "c"
               people.sort_by { |p| p.country.downcase }
             end

    # another 'unless' - used as a guard clause when sorting didn't match a known field
    unless sorted
      puts "Unknown sort field. Please choose Name, Age, or Country."
      next
    end

    puts "\nSorted by #{choice.capitalize}:"
    sorted.each { |p| puts p }
  end
end

puts "\nGoodbye!"
