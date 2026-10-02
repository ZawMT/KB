## Cryptography
### Basic Cryptography in C#

#### [Back to Cryptography contents](../_Contents.md)

*How do you do basic hashing, message authentication and encryption in C#?*

Short answer: **Use the built-in `System.Security.Cryptography` namespace: `SHA256` to fingerprint data, `HMACSHA256` to prove data wasn't tampered with, and `AesGcm` to encrypt it.**

> Each sample is a complete console program (top-level statements, **.NET 8+**). Paste one into `Program.cs` and run it with `dotnet run`. No NuGet packages needed.

The three approaches, and the security goal each one covers:

| # | Approach | Needs a key? | Reversible? | Protects |
|---|---|---|---|---|
| 1 | Hashing (SHA-256) | ❌ | ❌ | **Integrity** (detects changes) |
| 2 | HMAC (HMAC-SHA256) | ✅ secret key | ❌ | **Integrity + authenticity** (detects tampering by anyone without the key) |
| 3 | Encryption (AES-GCM) | ✅ secret key | ✅ | **Confidentiality** (+ integrity, because GCM is authenticated) |

### 1. Hashing: SHA-256

A hash is a fixed-size **fingerprint** of data. The same input always gives the same hash; change one character and the hash changes completely.

```csharp
using System.Security.Cryptography;
using System.Text;

string Hash(string text)
{
    byte[] bytes = Encoding.UTF8.GetBytes(text);
    byte[] hash = SHA256.HashData(bytes);    // always 32 bytes (256 bits)
    return Convert.ToHexString(hash);        // 64 hex characters
}

Console.WriteLine(Hash("Hello"));
Console.WriteLine(Hash("Hello"));    // same input → same hash
Console.WriteLine(Hash("Hello!"));   // tiny change → completely different hash
```

Typical uses: checking a downloaded file matches its published checksum, detecting changed data, content-based IDs.

> ⚠️ A plain hash has **no key**. Anyone who changes the data can simply recompute the hash too. To detect deliberate tampering, use an HMAC (next).

### 2. HMAC: hash + secret key

An HMAC mixes a **secret key** into the hash. Only someone with the key can produce a valid HMAC, so an attacker can't change the message and fix up the tag.

```csharp
using System.Security.Cryptography;
using System.Text;

byte[] key = RandomNumberGenerator.GetBytes(32);   // secret key, shared by sender and receiver

// Sender: compute a tag for the message
byte[] message = Encoding.UTF8.GetBytes("amount=100&to=alice");
byte[] tag = HMACSHA256.HashData(key, message);
Console.WriteLine($"Tag: {Convert.ToHexString(tag)}");

// Receiver: recompute the tag and compare
Console.WriteLine($"Original valid? {Verify(key, message, tag)}");   // True

byte[] tampered = Encoding.UTF8.GetBytes("amount=900&to=alice");
Console.WriteLine($"Tampered valid? {Verify(key, tampered, tag)}");  // False

static bool Verify(byte[] key, byte[] message, byte[] tag)
{
    byte[] expected = HMACSHA256.HashData(key, message);
    return CryptographicOperations.FixedTimeEquals(expected, tag);   // ✅ constant-time compare
}
```

Typical uses: signing webhooks and API requests, JWT tokens (HS256), tamper-proof cookies.

> Compare tags with `CryptographicOperations.FixedTimeEquals`, not `==` or `SequenceEqual`. Those stop at the first different byte, and the timing difference can leak information to an attacker.

### 3. Symmetric encryption: AES-GCM

Encryption **hides** the data; only someone with the key can turn it back. AES-GCM is *authenticated* encryption: it also produces a **tag**, so tampering is detected when decrypting.

```csharp
using System.Security.Cryptography;
using System.Text;

byte[] key = RandomNumberGenerator.GetBytes(32);   // 256-bit AES key: keep it secret

// --- Encrypt ---
byte[] plaintext = Encoding.UTF8.GetBytes("My secret message");
byte[] nonce = RandomNumberGenerator.GetBytes(AesGcm.NonceByteSizes.MaxSize);   // 12 bytes; NEW for every encryption
byte[] ciphertext = new byte[plaintext.Length];
byte[] tag = new byte[AesGcm.TagByteSizes.MaxSize];                            // 16 bytes

using (var aes = new AesGcm(key, tag.Length))
{
    aes.Encrypt(nonce, plaintext, ciphertext, tag);
}
Console.WriteLine($"Ciphertext: {Convert.ToHexString(ciphertext)}");

// --- Decrypt --- (needs the same key, plus the nonce and tag stored with the ciphertext)
byte[] decrypted = new byte[ciphertext.Length];
using (var aes = new AesGcm(key, tag.Length))
{
    aes.Decrypt(nonce, ciphertext, tag, decrypted);
}
Console.WriteLine($"Decrypted:  {Encoding.UTF8.GetString(decrypted)}");

// --- Tampering is detected ---
ciphertext[0] ^= 0xFF;   // flip some bits
try
{
    using var aes = new AesGcm(key, tag.Length);
    aes.Decrypt(nonce, ciphertext, tag, decrypted);
}
catch (AuthenticationTagMismatchException)
{
    Console.WriteLine("Tampering detected: decryption refused");
}
```

What you store or send: **nonce + ciphertext + tag**. The nonce and tag aren't secret; only the **key** is.

> ⚠️ **Never reuse a nonce with the same key.** With GCM, reusing one can expose the plaintext and allow forgeries. Generating a fresh random 12-byte nonce for each encryption is the simple, safe habit.

Typical uses: encrypting files, database fields, or secrets at rest.

### Key takeaways

- **Hash** to detect changes; **HMAC** when you also need to stop someone changing the data and the hash; **encrypt** when the data must stay secret.
- Generate keys, nonces and tokens with `RandomNumberGenerator`, never `System.Random`.
- Keys don't belong in source code: load them from a secret store (e.g. Azure Key Vault, environment variables, user secrets in development).
- Don't build your own scheme: these three built-in APIs with these settings are the safe defaults.

See the [Overview](../_Notes/Cryptography_01_Overview.md) for where these fit among the other cryptographic techniques.
