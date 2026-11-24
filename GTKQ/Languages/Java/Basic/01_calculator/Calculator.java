import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpServer;

import java.io.IOException;
import java.io.OutputStream;
import java.math.BigDecimal;
import java.math.RoundingMode;
import java.net.InetSocketAddress;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.HashMap;
import java.util.Map;

/**
 * A tiny web calculator using only the JDK's built-in HTTP server (no Maven, no dependencies).
 *
 * Run (from the 01_calculator folder):  java Calculator.java
 * Then open in the browser:             http://localhost:8080
 *
 *   GET /            -> serves index.html (the calculator page)
 *   GET /calculate   -> e.g. /calculate?a=12&op=add&b=7  returns the answer as plain text
 */
public class Calculator {

    private static final int PORT = 8080;
    private static final Path PAGE = Path.of("index.html");   // relative to the folder you run java from
    private static final Path STYLE = Path.of("style.css");

    public static void main(String[] args) throws IOException {
        if (!Files.exists(PAGE)) {
            System.err.println("index.html not found in " + Path.of("").toAbsolutePath());
            System.err.println("Run this from the 01_calculator folder.");
            return;
        }

        HttpServer server = HttpServer.create(new InetSocketAddress(PORT), 0);
        server.createContext("/", Calculator::handlePage);
        server.createContext("/style.css", Calculator::handleStyle);
        server.createContext("/calculate", Calculator::handleCalculate);
        server.start();

        System.out.println("Calculator running at http://localhost:" + PORT + "  (Ctrl+C to stop)");
    }

    // GET /  -> the calculator page
    private static void handlePage(HttpExchange exchange) throws IOException {
        // The "/" context also catches every path no other context claims (e.g. /favicon.ico)
        if (!exchange.getRequestURI().getPath().equals("/")) {
            send(exchange, 404, "text/plain", "Not found");
            return;
        }
        // Read on every request: edit index.html, refresh the browser, no server restart needed
        String html = Files.readString(PAGE);
        send(exchange, 200, "text/html", html);
    }

    // GET /style.css  -> the stylesheet linked from index.html
    // Content-Type must be text/css, or the browser refuses to apply it
    private static void handleStyle(HttpExchange exchange) throws IOException {
        String css = Files.readString(STYLE);
        send(exchange, 200, "text/css", css);
    }

    // GET /calculate?a=..&op=..&b=..  -> the answer (200) or an error message (400)
    private static void handleCalculate(HttpExchange exchange) throws IOException {
        try {
            Map<String, String> params = parseQuery(exchange.getRequestURI().getRawQuery());
            BigDecimal a = new BigDecimal(params.getOrDefault("a", ""));
            BigDecimal b = new BigDecimal(params.getOrDefault("b", ""));
            String op = params.getOrDefault("op", "");

            // Up to 2 decimal places, rounding halves up (1.005 -> 1.01).
            // Needed for "mul" too: an answer carried forward (e.g. 3.33) can be an input, and 3.33 x 3.33 = 11.0889
            BigDecimal result = calculate(a, op, b).setScale(2, RoundingMode.HALF_UP);
            // stripTrailingZeros: 19.00 -> 19, 3.50 -> 3.5 ; toPlainString: avoids "1E+2" for 100
            send(exchange, 200, "text/plain", result.stripTrailingZeros().toPlainString());
        } catch (NumberFormatException e) {          // must come before IllegalArgumentException (it is a subclass)
            send(exchange, 400, "text/plain", "Invalid number");
        } catch (IllegalArgumentException | ArithmeticException e) {
            send(exchange, 400, "text/plain", e.getMessage());
        }
    }

    // BigDecimal instead of int/double: no overflow, and no 0.1 + 0.2 = 0.30000000000000004 surprises
    private static BigDecimal calculate(BigDecimal a, String op, BigDecimal b) {
        return switch (op) {
            case "add" -> a.add(b);
            case "sub" -> a.subtract(b);
            case "mul" -> a.multiply(b);
            case "div" -> {
                if (b.signum() == 0) {
                    throw new ArithmeticException("Cannot divide by 0");
                }
                // Round straight to 2 decimal places; without a scale, 10 / 3 would throw
                // (BigDecimal refuses to produce an endless 3.333...)
                yield a.divide(b, 2, RoundingMode.HALF_UP);
            }
            default -> throw new IllegalArgumentException("Unknown operator: " + op);
        };
    }

    // "a=12&op=add&b=7" -> {a=12, op=add, b=7}
    private static Map<String, String> parseQuery(String rawQuery) {
        Map<String, String> params = new HashMap<>();
        if (rawQuery == null || rawQuery.isEmpty()) {
            return params;
        }
        for (String pair : rawQuery.split("&")) {
            String[] keyValue = pair.split("=", 2);
            String key = URLDecoder.decode(keyValue[0], StandardCharsets.UTF_8);
            String value = keyValue.length > 1 ? URLDecoder.decode(keyValue[1], StandardCharsets.UTF_8) : "";
            params.put(key, value);
        }
        return params;
    }

    private static void send(HttpExchange exchange, int status, String contentType, String body) throws IOException {
        byte[] bytes = body.getBytes(StandardCharsets.UTF_8);
        exchange.getResponseHeaders().set("Content-Type", contentType + "; charset=utf-8");
        exchange.sendResponseHeaders(status, bytes.length);   // headers must be sent before the body
        try (OutputStream out = exchange.getResponseBody()) {
            out.write(bytes);
        }
    }
}
