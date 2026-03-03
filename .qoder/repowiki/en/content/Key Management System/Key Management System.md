# Key Management System

<cite>
**Referenced Files in This Document**
- [Key.php](file://class/VIZ/Key.php)
- [Utils.php](file://class/VIZ/Utils.php)
- [Auth.php](file://class/VIZ/Auth.php)
- [EC.php](file://class/Elliptic/EC.php)
- [EC/KeyPair.php](file://class/Elliptic/EC/KeyPair.php)
- [EC/Signature.php](file://class/Elliptic/EC/Signature.php)
- [EdDSA.php](file://class/Elliptic/EdDSA.php)
- [EdDSA/KeyPair.php](file://class/Elliptic/EdDSA/KeyPair.php)
- [EdDSA/Signature.php](file://class/Elliptic/EdDSA/Signature.php)
- [BigInteger.php](file://class/BI/BigInteger.php)
- [BN.php](file://class/BN/BN.php)
- [TestKeys.php](file://tests/TestKeys.php)
- [README.md](file://README.md)
</cite>

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Detailed Component Analysis](#detailed-component-analysis)
6. [Dependency Analysis](#dependency-analysis)
7. [Performance Considerations](#performance-considerations)
8. [Troubleshooting Guide](#troubleshooting-guide)
9. [Conclusion](#conclusion)
10. [Appendices](#appendices)

## Introduction
This document describes the Key Management System of the VIZ PHP Library, focusing on the complete key lifecycle: generation, import/export, encoding/decoding, and cryptographic operations. It explains supported key formats (WIF, hex, compressed/uncompressed), encoding standards, and security considerations. It covers ECDSA signature creation and verification, public key recovery, shared key derivation for memo encryption, and integration with external tools. Practical examples, best practices, and troubleshooting guidance are included for each key operation.

## Project Structure
The Key Management System spans several core modules:
- VIZ namespace: Key, Utils, Auth, and Transaction classes
- Elliptic namespace: EC and EdDSA implementations with KeyPair and Signature classes
- BigInteger and BN wrappers for big integer arithmetic

```mermaid
graph TB
subgraph "VIZ Layer"
K["Key.php"]
U["Utils.php"]
A["Auth.php"]
end
subgraph "Elliptic Layer"
EC["EC.php"]
ECKP["EC/KeyPair.php"]
ECS["EC/Signature.php"]
ED["EdDSA.php"]
EDKP["EdDSA/KeyPair.php"]
EDS["EdDSA/Signature.php"]
end
subgraph "BigNum Layer"
BI["BigInteger.php"]
BN["BN.php"]
end
K --> EC
K --> U
A --> K
EC --> ECKP
EC --> ECS
ED --> EDKP
ED --> EDS
ECKP --> BN
ECS --> BN
EDKP --> BI
EDS --> BI
BN --> BI
```

**Diagram sources**
- [Key.php](file://class/VIZ/Key.php#L1-L353)
- [Utils.php](file://class/VIZ/Utils.php#L1-L413)
- [Auth.php](file://class/VIZ/Auth.php#L1-L70)
- [EC.php](file://class/Elliptic/EC.php#L1-L272)
- [EC/KeyPair.php](file://class/Elliptic/EC/KeyPair.php#L1-L138)
- [EC/Signature.php](file://class/Elliptic/EC/Signature.php#L1-L208)
- [EdDSA.php](file://class/Elliptic/EdDSA.php#L1-L122)
- [EdDSA/KeyPair.php](file://class/Elliptic/EdDSA/KeyPair.php#L1-L126)
- [EdDSA/Signature.php](file://class/Elliptic/EdDSA/Signature.php#L1-L82)
- [BigInteger.php](file://class/BI/BigInteger.php#L1-L200)
- [BN.php](file://class/BN/BN.php#L1-L200)

**Section sources**
- [README.md](file://README.md#L1-L455)

## Core Components
- Key: central class managing EC private/public keys, WIF import/export, public key derivation, ECDSA signing/verification, public key recovery, shared key derivation, and memo encryption/decryption.
- EC: elliptic curve cryptography provider for ECDSA, key generation, signing, verification, and public key recovery.
- EC KeyPair and Signature: low-level EC key pair and signature handling with DER/compact encodings.
- EdDSA and EdDSA KeyPair/Signature: Ed25519 implementation for signatures.
- Utils: encoding/decoding utilities (Base58), AES-256-CBC, VLQ helpers, and cross-chain address helpers.
- Auth: passwordless authentication verifier using recovered public keys and account authorities.

**Section sources**
- [Key.php](file://class/VIZ/Key.php#L1-L353)
- [EC.php](file://class/Elliptic/EC.php#L1-L272)
- [EC/KeyPair.php](file://class/Elliptic/EC/KeyPair.php#L1-L138)
- [EC/Signature.php](file://class/Elliptic/EC/Signature.php#L1-L208)
- [EdDSA.php](file://class/Elliptic/EdDSA.php#L1-L122)
- [EdDSA/KeyPair.php](file://class/Elliptic/EdDSA/KeyPair.php#L1-L126)
- [EdDSA/Signature.php](file://class/Elliptic/EdDSA/Signature.php#L1-L82)
- [Utils.php](file://class/VIZ/Utils.php#L1-L413)
- [Auth.php](file://class/VIZ/Auth.php#L1-L70)

## Architecture Overview
The Key Management System integrates VIZ-specific key handling with the Elliptic library for ECDSA and EdDSA operations. It provides:
- Key import from WIF, hex, and raw binary
- Public key derivation (compressed and uncompressed)
- ECDSA signing with canonical compact format
- Signature verification and public key recovery
- Shared key derivation via ECDH for memo encryption
- Memo encryption/decryption compatible with the JavaScript library
- Cross-format utilities for Bitcoin, Litecoin, Ethereum, Tron addresses

```mermaid
sequenceDiagram
participant App as "Application"
participant Key as "VIZ\\Key"
participant EC as "Elliptic\\EC"
participant Utils as "VIZ\\Utils"
App->>Key : new Key(seed_or_wif_or_hex)
Key->>EC : keyFromPrivate(hex, 'hex', compressed)
EC-->>Key : KeyPair
App->>Key : sign(data)
Key->>EC : key.sign(hash(data), 'hex', {canonical : true})
EC-->>Key : Signature
Key-->>App : compact signature
App->>Key : verify(data, signature)
Key->>EC : key.verify(hash(data), signature)
EC-->>Key : boolean
Key-->>App : verification result
```

**Diagram sources**
- [Key.php](file://class/VIZ/Key.php#L302-L322)
- [EC.php](file://class/Elliptic/EC.php#L89-L177)
- [EC/Signature.php](file://class/Elliptic/EC/Signature.php#L188-L207)

## Detailed Component Analysis

### Key Lifecycle and Formats
- Import formats:
  - WIF: Base58-decoded with version and checksum validation; marks key as private.
  - Hex: raw private key hex string.
  - Binary: raw private key bytes.
  - Public import: Base58-decoded with RIPEMD160 checksum validation; marks key as public.
- Export formats:
  - WIF for private keys (version byte, private key, double SHA-256 checksum).
  - Encoded public keys with prefix and Base58 with RIPEMD160 checksum.
- Key representations:
  - Compressed (33-byte): x02/x03 + x
  - Uncompressed (65-byte): x04 + x + y

```mermaid
flowchart TD
Start(["Import Input"]) --> Detect["Detect Format<br/>WIF? Hex? Bin? Public?"]
Detect --> |WIF| DecodeWIF["Base58 decode<br/>Verify version and checksum"]
Detect --> |Hex| ImportHex["Store hex and bin"]
Detect --> |Bin| ImportBin["Store bin and hex"]
Detect --> |Public| DecodePub["Base58 decode<br/>Verify RIPEMD160 checksum"]
DecodeWIF --> SetPriv["Set private=true"]
DecodePub --> SetPub["Set private=false"]
ImportHex --> Done(["Ready"])
ImportBin --> Done
SetPriv --> Done
SetPub --> Done
```

**Diagram sources**
- [Key.php](file://class/VIZ/Key.php#L14-L32)
- [Key.php](file://class/VIZ/Key.php#L219-L260)

**Section sources**
- [Key.php](file://class/VIZ/Key.php#L14-L32)
- [Key.php](file://class/VIZ/Key.php#L211-L260)
- [Utils.php](file://class/VIZ/Utils.php#L209-L290)

### Encoding Standards and Cross-Chain Utilities
- Base58 encoding/decoding with custom alphabet and leading zero handling.
- Address generation helpers for Bitcoin, Litecoin, Ethereum, and Tron using keccak hashing and checksums.
- AES-256-CBC encryption/decryption with IV handling.
- Variable-length quantity (VLQ) encoding for memo framing.

```mermaid
classDiagram
class VIZ_Utils {
+base58_encode(string) string
+base58_decode(base58) string
+aes_256_cbc_encrypt(data, key, iv) mixed
+aes_256_cbc_decrypt(data, key, iv) string|false
+vlq_create(data) string
+vlq_extract(data, as_bytes) array
+vlq_calculate(digits, as_bytes) int
+privkey_hex_to_btc_wif(hex, compressed) string
+full_pubkey_hex_to_btc_address(hex, network_id) string
+full_pubkey_hex_to_eth_address(hex) string
+full_pubkey_hex_to_trx_address(hex) string
}
```

**Diagram sources**
- [Utils.php](file://class/VIZ/Utils.php#L209-L413)

**Section sources**
- [Utils.php](file://class/VIZ/Utils.php#L209-L413)

### ECDSA Operations
- Signing: SHA-256 hash of data, deterministic nonce generation, canonical signature enforcement, compact encoding.
- Verification: SHA-256 hash of data, signature parsing, public key validation, point arithmetic.
- Public key recovery: from signature recovery parameter and message hash.

```mermaid
sequenceDiagram
participant App as "Application"
participant Key as "VIZ\\Key"
participant EC as "Elliptic\\EC"
participant Sig as "EC\\Signature"
App->>Key : sign(data)
Key->>Key : sha256(data)
Key->>EC : keyFromPrivate(hex, 'hex', compressed)
EC->>EC : sign(hash, keyPair, 'hex', {canonical : true})
EC-->>Key : Signature
Key->>Sig : toCompact('hex')
Sig-->>Key : compact signature
Key-->>App : signature
App->>Key : verify(data, signature)
Key->>Key : sha256(data)
Key->>EC : keyFromPublic(publicHex, 'hex', compressed)
EC->>EC : verify(hash, signature, keyPair)
EC-->>Key : boolean
Key-->>App : verification result
```

**Diagram sources**
- [Key.php](file://class/VIZ/Key.php#L302-L322)
- [EC.php](file://class/Elliptic/EC.php#L89-L177)
- [EC/Signature.php](file://class/Elliptic/EC/Signature.php#L188-L207)

**Section sources**
- [Key.php](file://class/VIZ/Key.php#L302-L322)
- [EC.php](file://class/Elliptic/EC.php#L89-L177)
- [EC/Signature.php](file://class/Elliptic/EC/Signature.php#L1-L208)

### Public Key Recovery
- Extracts recovery parameter from compact signature header, recovers candidate public key, and returns encoded public key string.

```mermaid
flowchart TD
Start(["Recover Public Key"]) --> Hash["SHA-256(data)"]
Hash --> Parse["Parse compact signature<br/>extract r, s, recovery param"]
Parse --> Recover["EC.recoverPubKey(hash, signature, j)"]
Recover --> Encode["Encode recovered point<br/>compressed hex"]
Encode --> Return(["Return encoded public key"])
```

**Diagram sources**
- [Key.php](file://class/VIZ/Key.php#L323-L338)
- [EC.php](file://class/Elliptic/EC.php#L221-L249)

**Section sources**
- [Key.php](file://class/VIZ/Key.php#L323-L338)
- [EC.php](file://class/Elliptic/EC.php#L221-L249)

### Shared Key Derivation and Memo Encryption
- ECDH shared secret derived from private key and peer public key.
- Memo encryption:
  - Uses shared key to derive encryption key via SHA-512, splits into AES key and IV.
  - Prepends sender/receiver public keys, random nonce, and 4-byte checksum.
  - VLQ-encodes payload length and data, encrypts with AES-256-CBC, Base58-encodes result.
- Memo decryption mirrors encryption steps, validates checksum, and decrypts payload.

```mermaid
sequenceDiagram
participant P1 as "Party 1 (Private)"
participant P2 as "Party 2 (Private/Public)"
participant Utils as "VIZ\\Utils"
participant EC as "Elliptic\\EC"
P1->>P1 : get_shared_key(P2_public)
P2->>P2 : get_shared_key(P1_public)
P1->>P1 : encode_memo(P2_public, memo)
P1->>EC : keyFromPrivate(hex, 'hex', compressed)
EC-->>P1 : KeyPair
P1->>EC : keyFromPublic(P2_hex, 'hex', compressed)
EC-->>P1 : PublicKey
P1->>EC : derive(shared_point)
EC-->>P1 : shared_point
P1->>P1 : hash(sha512, shared_point.toString(16))
P1->>Utils : aes_256_cbc_encrypt(vlq(memo+memo)+memo, key, iv)
Utils-->>P1 : encrypted data
P1->>Utils : base58_encode(prepend(from,to,nonce,checksum,encrypted))
P1-->>P2 : memo_ciphertext
P2->>P2 : decode_memo(memo_ciphertext)
P2->>Utils : base58_decode(ciphertext)
P2->>P2 : extract from/to, nonce, checksum, encrypted
P2->>P2 : get_shared_key(P1_public)
P2->>Utils : aes_256_cbc_decrypt(encrypted, key, iv)
Utils-->>P2 : decrypted
P2-->>P2 : verify checksum and VLQ length
```

**Diagram sources**
- [Key.php](file://class/VIZ/Key.php#L33-L44)
- [Key.php](file://class/VIZ/Key.php#L45-L86)
- [Key.php](file://class/VIZ/Key.php#L87-L176)
- [Utils.php](file://class/VIZ/Utils.php#L291-L320)
- [Utils.php](file://class/VIZ/Utils.php#L322-L383)

**Section sources**
- [Key.php](file://class/VIZ/Key.php#L33-L86)
- [Key.php](file://class/VIZ/Key.php#L87-L176)
- [Utils.php](file://class/VIZ/Utils.php#L291-L383)

### Passwordless Authentication Integration
- Generates domain-action-account-authority-time-nonce data string.
- Signs with private key; server-side verifier recovers public key, checks domain/action/authority/time window, and validates against account’s active authority threshold.

```mermaid
sequenceDiagram
participant Client as "Client"
participant Key as "VIZ\\Key"
participant Auth as "VIZ\\Auth"
participant Node as "Blockchain Node"
Client->>Key : auth(account, domain, action, authority)
Key-->>Client : [data, signature]
Client->>Auth : check(data, signature)
Auth->>Key : recover_public_key(data, signature)
Key-->>Auth : recovered_public_key
Auth->>Node : get_account(account, authority)
Node-->>Auth : account authorities
Auth->>Auth : verify domain/action/authority/time window
Auth->>Auth : sum weights match threshold
Auth-->>Client : auth_status
```

**Diagram sources**
- [Key.php](file://class/VIZ/Key.php#L339-L352)
- [Auth.php](file://class/VIZ/Auth.php#L25-L69)

**Section sources**
- [Key.php](file://class/VIZ/Key.php#L339-L352)
- [Auth.php](file://class/VIZ/Auth.php#L1-L70)

### EdDSA (Ed25519) Support
- Provides EdDSA key pairs, signatures, and encoding/decoding helpers.
- Used for Ed25519 signatures alongside ECDSA.

```mermaid
classDiagram
class EdDSA {
+sign(message, secret) Signature
+verify(message, sig, pub) bool
+encodePoint(point) bytes
+decodePoint(bytes) point
+encodeInt(num) bytes
+decodeInt(bytes) num
}
class EdDSA_KeyPair {
+fromPublic(eddsa, pub) KeyPair
+fromSecret(eddsa, secret) KeyPair
+pubBytes() bytes
+privBytes() bytes
+sign(message) Signature
+verify(message, sig) bool
}
class EdDSA_Signature {
+toBytes() bytes
+toHex() string
}
EdDSA --> EdDSA_KeyPair : "creates"
EdDSA --> EdDSA_Signature : "creates"
```

**Diagram sources**
- [EdDSA.php](file://class/Elliptic/EdDSA.php#L1-L122)
- [EdDSA/KeyPair.php](file://class/Elliptic/EdDSA/KeyPair.php#L1-L126)
- [EdDSA/Signature.php](file://class/Elliptic/EdDSA/Signature.php#L1-L82)

**Section sources**
- [EdDSA.php](file://class/Elliptic/EdDSA.php#L1-L122)
- [EdDSA/KeyPair.php](file://class/Elliptic/EdDSA/KeyPair.php#L1-L126)
- [EdDSA/Signature.php](file://class/Elliptic/EdDSA/Signature.php#L1-L82)

## Dependency Analysis
- VIZ Key depends on Elliptic EC for ECDSA operations and on VIZ Utils for Base58 and AES utilities.
- EC KeyPair and Signature depend on BN and BigInteger for big integer arithmetic.
- EdDSA depends on Elliptic curves and uses BigInteger for hashing and encoding.

```mermaid
graph LR
VIZ_Key["VIZ\\Key"] --> Ell_EC["Elliptic\\EC"]
VIZ_Key --> VIZ_Utils["VIZ\\Utils"]
Ell_EC --> BN
BN --> BI["BI\\BigInteger"]
EdDSA["Elliptic\\EdDSA"] --> BI
EdDSA_KP["EdDSA\\KeyPair"] --> BI
EdDSA_Sig["EdDSA\\Signature"] --> BI
```

**Diagram sources**
- [Key.php](file://class/VIZ/Key.php#L1-L353)
- [EC.php](file://class/Elliptic/EC.php#L1-L272)
- [EdDSA.php](file://class/Elliptic/EdDSA.php#L1-L122)
- [BigInteger.php](file://class/BI/BigInteger.php#L1-L200)
- [BN.php](file://class/BN/BN.php#L1-L200)

**Section sources**
- [Key.php](file://class/VIZ/Key.php#L1-L353)
- [EC.php](file://class/Elliptic/EC.php#L1-L272)
- [EdDSA.php](file://class/Elliptic/EdDSA.php#L1-L122)
- [BigInteger.php](file://class/BI/BigInteger.php#L1-L200)
- [BN.php](file://class/BN/BN.php#L1-L200)

## Performance Considerations
- Prefer compressed public keys for reduced bandwidth and storage.
- Use canonical signatures to minimize ambiguity and improve interoperability.
- Reuse shared keys for memo encryption sessions to avoid repeated ECDH computations.
- Ensure sufficient entropy for nonce generation and random bytes for AES IVs.
- Validate inputs early to avoid unnecessary cryptographic operations.

## Troubleshooting Guide
Common issues and resolutions:
- WIF import fails:
  - Verify Base58 validity and checksum; ensure version byte matches expected format.
  - Check that the decoded key length and checksum match expectations.
- Public key import fails:
  - Confirm RIPEMD160 checksum matches the decoded public key.
- Signature verification fails:
  - Ensure SHA-256 hash of the original data is used.
  - Verify signature format and that the public key corresponds to the private key used for signing.
- Public key recovery returns false:
  - Validate the compact signature header and recovery parameter.
  - Confirm the message hash and signature are correct.
- Memo encryption/decryption errors:
  - Verify shared key derivation from correct parties.
  - Ensure nonce and checksum are preserved and validated.
  - Confirm AES key and IV extraction from SHA-512 of shared key.
- Authentication failures:
  - Check time window and ensure server timezone adjustments are considered.
  - Verify domain, action, authority, and account existence.
  - Confirm authority weights meet threshold.

**Section sources**
- [Key.php](file://class/VIZ/Key.php#L219-L260)
- [Key.php](file://class/VIZ/Key.php#L302-L322)
- [Key.php](file://class/VIZ/Key.php#L323-L338)
- [Key.php](file://class/VIZ/Key.php#L45-L176)
- [Auth.php](file://class/VIZ/Auth.php#L25-L69)

## Conclusion
The VIZ PHP Library provides a robust Key Management System with comprehensive support for ECDSA and EdDSA operations, secure key import/export, encoding/decoding, and memo encryption compatible with external tools. By following the best practices and troubleshooting guidance herein, developers can implement secure and reliable cryptographic workflows for VIZ applications.

## Appendices

### Practical Examples Index
- Initialize key from hex and encode to WIF, derive public key, sign and verify data, recover public key from signature.
- Generate keys with seeds and salts, encode WIF and public keys, and execute transactions.
- Derive shared keys and perform AES-256-CBC encryption/decryption for memo exchange.
- Create and verify passwordless authentication data for domain actions.

**Section sources**
- [README.md](file://README.md#L36-L222)
- [TestKeys.php](file://tests/TestKeys.php#L1-L29)