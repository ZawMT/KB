## Cryptography
### Overview

#### [Back to Cryptography contents](../_Contents.md)

*Are hashing, encryption and similar techniques all called "cryptography"?*

Short answer: **Mostly yes. Cryptography is the umbrella term for techniques that protect information: encryption, cryptographic hashing, MACs, digital signatures, key exchange and more. But not every "hash" is cryptographic, and encoding (e.g. Base64) isn't cryptography at all.**

### What falls under cryptography

| Technique | What it does | Reversible? | Examples |
|---|---|---|---|
| **Symmetric encryption** | Hides data using one shared key | ✅ (with the key) | AES, ChaCha20 |
| **Asymmetric encryption** | Hides data using a public/private key pair | ✅ (with the private key) | RSA, ECC |
| **Cryptographic hashing** | Makes a fixed-size "fingerprint" of data | ❌ one-way | SHA-256, SHA-3, BLAKE3 |
| **MAC** (Message Authentication Code) | A hash combined with a secret key; proves the data wasn't changed by someone without the key | ❌ | HMAC-SHA256 |
| **Digital signatures** | Proves *who* sent the data and that it's unchanged | ❌ (verify only) | RSA, ECDSA, Ed25519 |
| **Key exchange** | Lets two parties agree on a secret over an open channel | — | Diffie-Hellman, ECDH |
| **Password hashing / KDFs** | Deliberately *slow* hashing for passwords, or deriving keys from them | ❌ | bcrypt, scrypt, Argon2, PBKDF2 |
| **Secure random numbers** | Unpredictable randomness for keys, tokens and salts | — | `RandomNumberGenerator` (C#), `secrets` (Python) |

### Building blocks combine into protocols

Real systems combine several of these pieces. **TLS (HTTPS)**, for example:

```
1. Key exchange (ECDH)          → client and server agree on a shared secret
2. Digital signature + certificate → client verifies it's really the server
3. Symmetric encryption (AES-GCM) → the data itself is encrypted
4. MAC / authenticated encryption → any tampering is detected
```

Asymmetric crypto is slow, so it's used to **set up** a shared key. Symmetric crypto is fast, so it's used for the **bulk data**.

### What each piece protects

| Security goal | Question it answers | Provided by |
|---|---|---|
| **Confidentiality** | Can others read it? | Encryption |
| **Integrity** | Was it changed? | Hashes, MACs, signatures |
| **Authenticity** | Who sent it? | MACs, signatures |
| **Non-repudiation** | Can the sender deny it later? | Signatures only (a MAC's key is shared, so either side could have made it) |

### Watch out: lookalikes that aren't cryptography

| Term | What it is | Why it's not crypto |
|---|---|---|
| **Non-cryptographic hashing** | Hash tables (`GetHashCode`), MurmurHash, xxHash | Built for speed, not security; collisions are easy to create on purpose |
| **Checksums** | CRC32, Adler-32 | Detect *accidental* corruption, not deliberate tampering |
| **Encoding** | Base64, URL encoding, hex | Changes the format only; anyone can decode it without a key |
| **Obfuscation** | Minifying or scrambling code | Harder to read, but there's no secret key |
| **Compression** | gzip, zip | Makes data smaller, not secret |

"Hashing" is broader than its cryptographic use. A hash table's hash and SHA-256 share the name, but only SHA-256 is a cryptographic tool.

### Related terms

- **Cryptology** = **cryptography** (building the systems) + **cryptanalysis** (breaking them).
- **Information security** is broader still: access control, network security, auditing and more. Cryptography is one of its tools.

### Golden rule

**Never invent your own cryptography.** Use well-reviewed algorithms through your platform's standard libraries (e.g. .NET `System.Security.Cryptography`, Python `cryptography` / `hashlib`), with recommended settings.

### Think about it

Hashing and encryption both turn data into unreadable output. Why is it wrong to "encrypt" passwords for storage, and why is plain SHA-256 *also* a poor choice for them?

### Acronyms

| Short | Long form |
|---|---|
| **AES** | Advanced Encryption Standard |
| **CRC** | Cyclic Redundancy Check (CRC32 = 32-bit) |
| **ECC** | Elliptic Curve Cryptography |
| **ECDH** | Elliptic Curve Diffie-Hellman |
| **ECDSA** | Elliptic Curve Digital Signature Algorithm |
| **GCM** | Galois/Counter Mode (as in AES-GCM) |
| **HMAC** | Hash-based Message Authentication Code |
| **HTTPS** | Hypertext Transfer Protocol Secure |
| **KDF** | Key Derivation Function |
| **MAC** | Message Authentication Code |
| **PBKDF2** | Password-Based Key Derivation Function 2 |
| **RSA** | Rivest–Shamir–Adleman (the surnames of its three inventors) |
| **SHA** | Secure Hash Algorithm (SHA-256 = SHA-2 family, 256-bit output) |
| **TLS** | Transport Layer Security |
| **URL** | Uniform Resource Locator |
