extern crate rand;

use std::io;
use std::cmp::Ordering;

fn main() {
    let secret_number = rand::random_range(1..=100);
    println!("I have a number between 1 and 100. Guess what it is.");
    loop {
        println!("Please input your guess:");
        let mut guess = String::new();
        io::stdin()
            .read_line(&mut guess)
            .expect("Failed to read line");
        let guess: u32 = match guess.trim().parse() {
            Ok(num) => num,
            Err(_) => {
                println!("Please enter a valid number.");
                continue;
            }
        };
        match guess.cmp(&secret_number) {
            Ordering::Less => println!("Too small!"),
            Ordering::Greater => println!("Too big!"),
            Ordering::Equal => {
                println!("Correct!!!");
                break;
            }
        }
    }
}
