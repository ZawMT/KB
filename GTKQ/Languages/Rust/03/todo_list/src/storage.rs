use crate::task::Task;
use std::fs;

const FILE_PATH: &str = "tasks.json";

pub fn load_tasks() -> Vec<Task> {
    match fs::read_to_string(FILE_PATH) {
        Ok(contents) => serde_json::from_str(&contents).unwrap_or_else(|_| Vec::new()),
        Err(_) => Vec::new(),
    }
}

pub fn save_tasks(tasks: &Vec<Task>) {
    let json = serde_json::to_string_pretty(tasks).expect("Failed to serialize tasks");
    fs::write(FILE_PATH, json).expect("Failed to write tasks file");
}
