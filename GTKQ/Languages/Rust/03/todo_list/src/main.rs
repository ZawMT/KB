mod storage;
mod task;

use std::io;
use task::Task;

fn main() {
    let mut tasks: Vec<Task> = storage::load_tasks();

    loop {
        println!("Add TODO (A), List TODOs (L), Mark as completed (C), Delete TODO (D), Exit (X):");
        println!("Choose the task:");

        let mut input = String::new();
        io::stdin()
            .read_line(&mut input)
            .expect("Failed to read line");

        match input.trim() {
            "A" | "a" => {
                println!("Enter task description:");
                let mut description = String::new();
                io::stdin()
                    .read_line(&mut description)
                    .expect("Failed to read line");

                tasks.push(Task {
                    id: tasks.len() as u32 + 1,
                    description: description.trim().to_string(),
                    completed: false,
                });
                println!("Task added.");
            }
            "L" | "l" => {
                for task in &tasks {
                    println!("{:?}", task);
                }
            }
            "C" | "c" => {
                println!("Enter task number to complete:");
                let mut number_input = String::new();
                io::stdin()
                    .read_line(&mut number_input)
                    .expect("Failed to read line");
                let number: u32 = number_input.trim().parse().expect("Please enter a number");

                if number == 0 || number > tasks.len() as u32 {
                    println!("Error: no task with that number.");
                } else {
                    tasks[(number - 1) as usize].completed = true;
                    println!("Task marked as completed.");
                }
            }
            "D" | "d" => {
                println!("Enter task number to delete:");
                let mut number_input = String::new();
                io::stdin()
                    .read_line(&mut number_input)
                    .expect("Failed to read line");
                let number: u32 = number_input.trim().parse().expect("Please enter a number");

                if number == 0 || number > tasks.len() as u32 {
                    println!("Error: no task with that number.");
                } else {
                    tasks.remove((number - 1) as usize);
                    println!("Task deleted.");
                }
            }
            "X" | "x" => {
                storage::save_tasks(&tasks);
                println!("Goodbye!");
                break;
            }
            _ => {
                println!("Invalid choice, try again.");
            }
        }
    }
}