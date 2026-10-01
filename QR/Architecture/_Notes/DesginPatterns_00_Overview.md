
# Design Patterns

## Creational

| Name | Remark |
| --- | --- |
| Singleton pattern | Ensures a class has only one instance and gives a global access point to it (e.g. config, logger). |
| Factory Method pattern | Lets subclasses decide which concrete class to instantiate; callers depend on an interface, not `new`. |
| Abstract Factory pattern | Creates families of related objects (e.g. Windows vs. macOS UI widgets) without naming concrete classes. |
| Builder pattern | Constructs a complex object step by step; avoids huge constructors with many optional params. |
| Prototype pattern | Creates new objects by cloning an existing instance instead of building from scratch. |

## Structural

| Name | Remark |
| --- | --- |
| Adapter pattern | Wraps an incompatible interface so it matches the one the client expects (plug converter). |
| Decorator pattern | Adds behavior to an object dynamically by wrapping it, without changing its class (e.g. logging, caching). |
| Facade pattern | Provides one simple interface over a complex subsystem. |
| Proxy pattern | A stand-in object that controls access to the real one (lazy loading, access control, remote calls). |
| Composite pattern | Treats individual objects and groups of objects uniformly via a tree structure (e.g. files & folders). |

## Behavioral

| Name | Remark |
| --- | --- |
| Strategy pattern | Encapsulates interchangeable algorithms behind one interface; swap them at runtime. |
| Observer pattern | When one object changes state, all its subscribers are notified automatically (pub/sub, events). |
| Command pattern | Turns a request into an object, enabling queueing, logging, and undo/redo. |
| Template Method pattern | Base class defines an algorithm's skeleton; subclasses fill in specific steps. |
| Iterator pattern | Traverses a collection without exposing its internal structure. |
| State pattern | An object changes its behavior when its internal state changes (looks like it changed class). |
| Chain of Responsibility pattern | Passes a request along a chain of handlers until one handles it (e.g. middleware). |

## Architectural / Enterprise

| Name | Remark |
| --- | --- |
| Dependency Injection | Objects receive their dependencies from outside instead of creating them; improves testability and loose coupling. |
| Repository pattern | Abstracts data access behind a collection-like interface, hiding the DB/ORM details from business logic. |
| MVC (Model-View-Controller) | Separates data (Model), UI (View), and input handling (Controller). |
