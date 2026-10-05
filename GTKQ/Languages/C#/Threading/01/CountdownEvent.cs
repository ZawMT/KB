using System;
using System.Threading;

class Program
{
    static void Main()
    {
        int workers = 3;
        using var countdown = new CountdownEvent(workers);

        for (int i = 1; i <= workers; i++)
        {
            int id = i;
            new Thread(() =>
            {
                Console.WriteLine($"Worker {id} starting");
                Thread.Sleep(id * 500);           // simulate work
                Console.WriteLine($"Worker {id} done");
                countdown.Signal();               // decrement count by 1
            }).Start();
        }

        countdown.Wait();                         // blocks until count == 0
        Console.WriteLine("All workers finished.");
    }
}