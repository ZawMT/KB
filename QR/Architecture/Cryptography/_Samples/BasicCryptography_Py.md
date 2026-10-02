## Cryptography
### Basic Cryptography in Python

#### [Back to Cryptography contents](../_Contents.md)

*How do you do basic hashing, message authentication and encryption in Python?*

Short answer: **Use `hashlib` to fingerprint data, `hmac` to prove data wasn't tampered with (both in the standard library), and the `cryptography` package's `AESGCM` to encrypt it.**

> Each sample is a complete script (Python 3.9+). Save it as a `.py` file and run it with `python file.py`.
>
> Samples 1 and 2 use only the standard library. Sample 3 needs: `pip install cryptography`

The three approaches, and the security goal each one covers:

| # | Approach | Needs a key? | Reversible? | Protects |
|---|---|---|---|---|
| 1 | Hashing (SHA-256) | ❌ | ❌ | **Integrity** (detects changes) |
| 2 | HMAC (HMAC-SHA256) | ✅ secret key | ❌ | **Integrity + authenticity** (detects tampering by anyone without the key) |
| 3 | Encryption (AES-GCM) | ✅ secret key | ✅ | **Confidentiality** (+ integrity, because GCM is authenticated) |

### 1. Hashing: SHA-256

A hash is a fixed-size **fingerprint** of data. The same input always gives the same hash; change one character and the hash changes completely.

```python
import hashlib


def hash_text(text: str) -> str:
    data = text.encode("utf-8")
    return hashlib.sha256(data).hexdigest()   # 32 bytes → 64 hex characters


print(hash_text("Hello"))
print(hash_text("Hello"))    # same input → same hash
print(hash_text("Hello!"))   # tiny change → completely different hash
```

Hashing a large file in chunks, so it doesn't all have to fit in memory:

```python
import hashlib


def hash_file(path: str) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)   # feed the data in pieces
    return h.hexdigest()


print(hash_file(__file__))   # hash this script itself
```

Typical uses: checking a downloaded file matches its published checksum, detecting changed data, content-based IDs.

> ⚠️ A plain hash has **no key**. Anyone who changes the data can simply recompute the hash too. To detect deliberate tampering, use an HMAC (next).

### 2. HMAC: hash + secret key

An HMAC mixes a **secret key** into the hash. Only someone with the key can produce a valid HMAC, so an attacker can't change the message and fix up the tag.

```python
import hashlib
import hmac
import secrets

key = secrets.token_bytes(32)   # secret key, shared by sender and receiver


def make_tag(key: bytes, message: bytes) -> str:
    return hmac.new(key, message, hashlib.sha256).hexdigest()


def verify(key: bytes, message: bytes, tag: str) -> bool:
    expected = make_tag(key, message)
    return hmac.compare_digest(expected, tag)   # ✅ constant-time compare


# Sender: compute a tag for the message
message = b"amount=100&to=alice"
tag = make_tag(key, message)
print(f"Tag: {tag}")

# Receiver: recompute the tag and compare
print(f"Original valid? {verify(key, message, tag)}")                  # True
print(f"Tampered valid? {verify(key, b'amount=900&to=alice', tag)}")   # False
```

Typical uses: signing webhooks and API requests, JWT tokens (HS256), tamper-proof cookies.

> Compare tags with `hmac.compare_digest`, not `==`. A normal comparison stops at the first different character, and the timing difference can leak information to an attacker.

### 3. Symmetric encryption: AES-GCM

Encryption **hides** the data; only someone with the key can turn it back. AES-GCM is *authenticated* encryption: it also adds a **tag**, so tampering is detected when decrypting.

```python
# pip install cryptography
import os

from cryptography.exceptions import InvalidTag
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

key = AESGCM.generate_key(bit_length=256)   # 256-bit AES key: keep it secret
aes = AESGCM(key)

# --- Encrypt ---
plaintext = b"My secret message"
nonce = os.urandom(12)                                  # 12 bytes; NEW for every encryption
ciphertext = aes.encrypt(nonce, plaintext, None)        # the 16-byte tag is appended to the ciphertext
print(f"Ciphertext: {ciphertext.hex()}")

# --- Decrypt --- (needs the same key, plus the nonce stored with the ciphertext)
decrypted = aes.decrypt(nonce, ciphertext, None)
print(f"Decrypted:  {decrypted.decode()}")

# --- Tampering is detected ---
tampered = bytes([ciphertext[0] ^ 0xFF]) + ciphertext[1:]   # flip some bits
try:
    aes.decrypt(nonce, tampered, None)
except InvalidTag:
    print("Tampering detected: decryption refused")
```

What you store or send: **nonce + ciphertext** (the tag is already included). The nonce isn't secret; only the **key** is.

> ⚠️ **Never reuse a nonce with the same key.** With GCM, reusing one can expose the plaintext and allow forgeries. Generating a fresh random 12-byte nonce for each encryption is the simple, safe habit.

> 💡 Want something even simpler? `cryptography.fernet.Fernet` handles the nonce, tag and encoding for you: `Fernet(key).encrypt(data)` / `.decrypt(token)`.

Typical uses: encrypting files, database fields, or secrets at rest.

### Key takeaways

- **Hash** to detect changes; **HMAC** when you also need to stop someone changing the data and the hash; **encrypt** when the data must stay secret.
- Generate keys, nonces and tokens with `secrets` or `os.urandom`, never the `random` module.
- Keys don't belong in source code: load them from a secret store or environment variables.
- Don't build your own scheme: these libraries with these settings are the safe defaults.

See the [Overview](../_Notes/Cryptography_01_Overview.md) for where these fit among the other cryptographic techniques.
