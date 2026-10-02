## Concurrency
### Contents

#### [Back to main contents](/Contents.md)

Here are some key points to take note about concurrency:
[Key points](_KeyPoints.md)

Concurrency vs multi-threading vs parallelism, and why the difference matters:
[Overview](_Notes/Concurrency_01_Overview.md)

The other approaches besides multi-threading: processes, async, green threads, actors, channels and more:
[Approaches](_Notes/Concurrency_02_Approaches.md)

Task vs Thread vs Process, umbrella terms, other similar entities, and what happens to threads during an await:
[Entities](_Notes/Concurrency_03_Entities.md)

Synchronisation: why it's needed, the approaches (locks, semaphores, signals, atomics...) and how languages support it:
[Synchronisation](_Notes/Synchronisation_01_Overview.md)

### Samples

Basics of creating, starting, joining and synchronising threads in C#:
[Thread (C#)](_Samples/Thread_C%23.md)

The thread pool: reusable worker threads, what runs on it, and thread-pool starvation in C#:
[ThreadPool (C#)](_Samples/ThreadPool_C%23.md)

Running work with Task, async/await, WhenAll/WhenAny, exceptions and cancellation in C#:
[Task (C#)](_Samples/Task_C%23.md)

Starting, monitoring and controlling other processes in C#:
[Process (C#)](_Samples/Process_C%23.md)
