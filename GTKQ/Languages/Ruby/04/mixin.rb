# Mixins - sharing behavior across classes via modules, alongside inheritance

# A module holds behavior that isn't a class on its own - it can't be
# instantiated. Including it in a class "mixes in" its methods.
module Swimmable
  def swim
    "#{name} is swimming"
  end
end

module Flyable
  def fly
    "#{name} is flying"
  end
end

# Inheritance: Animal is the shared base class. Every animal has a name
# and can speak, but "speak" is meant to be overridden by subclasses.
class Animal
  attr_reader :name

  def initialize(name)
    @name = name
  end

  def speak
    "#{name} makes a sound"
  end

  def to_s
    "#{self.class}(#{name})"
  end
end

# Duck inherits from Animal (is-a Animal) AND mixes in two modules
# (can-swim, can-fly). Ruby only allows one superclass, but any number
# of included modules.
class Duck < Animal
  include Swimmable
  include Flyable

  def speak
    "#{name} says Quack!"
  end
end

# Fish inherits the same base but only mixes in Swimmable - it can't fly.
class Fish < Animal
  include Swimmable

  def speak
    "#{name} blows bubbles"
  end
end

# Dog inherits from Animal but uses no mixins at all - plain inheritance.
class Dog < Animal
  def speak
    "#{name} says Woof!"
  end
end

animals = [Duck.new("Donald"), Fish.new("Nemo"), Dog.new("Rex")]

animals.each do |animal|
  puts animal.speak
  puts animal.swim if animal.respond_to?(:swim)
  puts animal.fly if animal.respond_to?(:fly)
  puts "--"
end

# Ancestors show where mixed-in modules sit in the method lookup chain,
# between the class itself and its superclass.
puts "\nDuck ancestors: #{Duck.ancestors}"
puts "Fish ancestors: #{Fish.ancestors}"
puts "Dog ancestors:  #{Dog.ancestors}"
