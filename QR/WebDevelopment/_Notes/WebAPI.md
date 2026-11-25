# "Web API": two meanings

The term **Web API** is used for two different things.

## Meaning 1: an API a server offers over HTTP

An endpoint that other programs call across the network.

- Examples: GitHub API, a weather API, or `/calculate` in this folder's `Calculator.java`
- Often also called a "REST API" or "web service"

## Meaning 2: features built into the browser

The objects and functions the **browser** provides to JavaScript, which are **not** part of the JavaScript language itself.

JavaScript running in a page has access to two layers:

| Layer | Defined by | Examples |
|---|---|---|
| **Core language (ECMAScript)** | ECMAScript specification (TC39) | `let`, `if`, `function`, `Array`, `Map`, `JSON`, `Promise`, `Math` |
| **Host environment APIs** | Whatever is *running* the JavaScript | Browser: `document`, `fetch`, `URLSearchParams`, `localStorage`, `setTimeout`, `console` |

- The browser APIs are standardised by **WHATWG** and **W3C**, so Chrome, Firefox and Safari behave the same
- MDN documents them under the heading **"Web APIs"**, which is where this meaning of the name comes from

## Same language, different host: Node.js

Node.js runs the **same core JavaScript**, but it is a different host environment:

- No `document`, because there is no page
- Adds its own APIs, such as `fs` (files) and `process`
- Has adopted some browser APIs, such as `fetch`, `URL`, `URLSearchParams` and `console`, so the same code can run in both

## Java comparison

| | Java | JavaScript in a browser |
|---|---|---|
| The language | `class`, `if`, `switch` | `function`, `if`, `let` |
| Built-in library from the runtime | The JDK: `java.util`, `java.nio.file`, `com.sun.net.httpserver` | Browser APIs: `fetch`, `document`, `URLSearchParams` |

`HttpServer` comes from the JDK, not the Java language. In the same way, `URLSearchParams` comes from the browser, not the JavaScript language.

## Example: `URLSearchParams`

`URLSearchParams` is a browser Web API (also available in Node.js). It builds a URL query string:

- Joins key/value pairs with `&` and `=`
- Encodes special characters (a space becomes `+`, an `&` inside a value becomes `%26`) so values can't break the URL

```js
new URLSearchParams({ a: "12", op: "add", b: "7" }).toString()
// "a=12&op=add&b=7"
```

You can try this in the browser console (F12 → Console).
