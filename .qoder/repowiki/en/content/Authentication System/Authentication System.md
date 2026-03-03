# Authentication System

<cite>
**Referenced Files in This Document**
- [Auth.php](file://class/VIZ/Auth.php)
- [Key.php](file://class/VIZ/Key.php)
- [JsonRPC.php](file://class/VIZ/JsonRPC.php)
- [Transaction.php](file://class/VIZ/Transaction.php)
- [EC.php](file://class/Elliptic/EC.php)
- [EC/Signature.php](file://class/Elliptic/EC/Signature.php)
- [EdDSA.php](file://class/Elliptic/EdDSA.php)
- [EdDSA/Signature.php](file://class/Elliptic/EdDSA/Signature.php)
- [Utils.php](file://class/VIZ/Utils.php)
- [README.md](file://README.md)
- [autoloader.php](file://class/autoloader.php)
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
This document explains the Authentication System implemented in the VIZ PHP Library. It focuses on passwordless authentication using cryptographic signatures, including domain-scoped authentication, time-based validation, authority verification, and multi-signature support. It also covers authority structures, weight thresholds, permission checks, and recovery mechanisms. Practical examples, integration patterns for web applications and APIs, and security considerations are included to help developers implement robust authentication flows.

## Project Structure
The authentication system spans several core classes:
- VIZ\Auth: orchestrates passwordless authentication checks against the blockchain.
- VIZ\Key: manages cryptographic keys, signing, verification, and public key recovery.
- VIZ\JsonRPC: provides JSON-RPC client access to the blockchain node.
- VIZ\Transaction: demonstrates multi-signature workflows and authority structures.
- Elliptic EC and EdDSA: underlying elliptic curve cryptography for signatures and recovery.
- VIZ\Utils: utilities for encoding, encryption, and interoperability helpers.
- README.md: includes working examples for passwordless authentication.

```mermaid
graph TB
subgraph "Application Layer"
APP["Your Application"]
end
subgraph "Authentication Layer"
AUTH["VIZ\\Auth"]
KEY["VIZ\\Key"]
RPC["VIZ\\JsonRPC"]
end
subgraph "Blockchain Layer"
NODE["VIZ Node (JSON-RPC)"]
end
subgraph "Crypto Layer"
ECG["Elliptic\\EC"]
EDS["Elliptic\\EdDSA"]
end
APP --> AUTH
AUTH --> KEY
AUTH --> RPC
KEY --> ECG
KEY --> EDS
RPC --> NODE
```

**Diagram sources**
- [Auth.php](file://class/VIZ/Auth.php#L9-L24)
- [Key.php](file://class/VIZ/Key.php#L9-L32)
- [JsonRPC.php](file://class/VIZ/JsonRPC.php#L17-L22)
- [EC.php](file://class/Elliptic/EC.php#L9-L40)
- [EdDSA.php](file://class/Elliptic/EdDSA.php#L7-L27)

**Section sources**
- [README.md](file://README.md#L207-L222)
- [autoloader.php](file://class/autoloader.php#L1-L14)

## Core Components
- VIZ\Auth
  - Validates passwordless authentication requests by reconstructing the public key from a signature, verifying domain/action/authority/time constraints, and confirming account authority weights against the blockchain.
  - Provides configurable time window range and timezone handling.
- VIZ\Key
  - Implements ECDSA signing and verification using secp256k1.
  - Supports compact signature recovery to derive the public key from a signature and data.
  - Generates domain-scoped authentication data and produces signatures suitable for VIZ\Auth.
- VIZ\JsonRPC
  - Executes blockchain queries such as retrieving account details and authority structures.
- VIZ\Transaction
  - Demonstrates multi-signature workflows and authority structures (master, active, regular) used during authentication and permission checks.

**Section sources**
- [Auth.php](file://class/VIZ/Auth.php#L9-L24)
- [Key.php](file://class/VIZ/Key.php#L339-L352)
- [JsonRPC.php](file://class/VIZ/JsonRPC.php#L311-L353)
- [Transaction.php](file://class/VIZ/Transaction.php#L191-L350)

## Architecture Overview
The authentication flow is a three-part process:
1. Data Generation: The client constructs a domain-scoped authentication string with account, authority, timestamp, and nonce.
2. Signature Creation: The client signs the data with their private key to produce a compact signature.
3. Verification: The verifier reconstructs the public key from the signature and data, validates domain/action/authority/time constraints, fetches the account’s authority structure from the blockchain, sums weighted keys, and compares against the configured threshold.

```mermaid
sequenceDiagram
participant Client as "Client App"
participant Signer as "VIZ\\Key"
participant Verifier as "VIZ\\Auth"
participant RPC as "VIZ\\JsonRPC"
participant Chain as "Blockchain Node"
Client->>Signer : "auth(account, domain, action, authority)"
Signer-->>Client : "data, signature"
Client->>Verifier : "check(data, signature)"
Verifier->>Signer : "recover_public_key(data, signature)"
Signer-->>Verifier : "public_key"
Verifier->>RPC : "get_account(account)"
RPC->>Chain : "JSON-RPC call"
Chain-->>RPC : "Account data"
RPC-->>Verifier : "Account data"
Verifier->>Verifier : "Validate domain/action/authority/time"
Verifier->>Verifier : "Sum weights for matching keys"
Verifier-->>Client : "Auth result (pass/fail)"
```

**Diagram sources**
- [Key.php](file://class/VIZ/Key.php#L339-L352)
- [Auth.php](file://class/VIZ/Auth.php#L25-L69)
- [JsonRPC.php](file://class/VIZ/JsonRPC.php#L311-L353)

## Detailed Component Analysis

### Passwordless Authentication Mechanism
- Domain-specific authentication
  - The authentication string includes a domain, action, account, authority, timestamp, and nonce. The verifier enforces exact matches for domain and action and supports configurable authority scopes.
- Time-based validation
  - A configurable time window around the current server time ensures freshness. The verifier rejects timestamps outside the allowed range.
- Authority verification
  - The verifier fetches the account’s authority structure from the blockchain and checks whether the recovered public key exists in the specified authority’s key_auths list.
- Weight threshold validation
  - The verifier sums the weights of matched keys and compares against the authority’s weight_threshold. Only if the sum meets or exceeds the threshold is the authentication considered valid.

```mermaid
flowchart TD
Start(["Start check(data, signature)"]) --> Recover["Recover public key from signature"]
Recover --> DomainCheck{"Domain matches?"}
DomainCheck --> |No| Fail["Fail"]
DomainCheck --> |Yes| ActionCheck{"Action matches?"}
ActionCheck --> |No| Fail
ActionCheck --> |Yes| AuthorityCheck{"Authority matches?"}
AuthorityCheck --> |No| Fail
AuthorityCheck --> |Yes| TimeWindow{"Timestamp in range?"}
TimeWindow --> |No| Fail
TimeWindow --> |Yes| FetchAccount["Fetch account from blockchain"]
FetchAccount --> AccountFound{"Account found?"}
AccountFound --> |No| Fail
AccountFound --> |Yes| SumWeights["Sum weights for matching keys"]
SumWeights --> Threshold{"Sum >= weight_threshold?"}
Threshold --> |No| Fail
Threshold --> |Yes| Pass["Pass"]
```

**Diagram sources**
- [Auth.php](file://class/VIZ/Auth.php#L25-L69)

**Section sources**
- [Auth.php](file://class/VIZ/Auth.php#L25-L69)

### Data Generation and Signature Creation
- Data generation
  - The signer composes a colon-separated string containing domain, action, account, authority, timestamp, and nonce. The timestamp is adjusted for server timezone if enabled.
- Signature creation
  - The signer hashes the data and generates a canonical ECDSA signature using secp256k1. The resulting compact signature encodes the recovery parameter and signature components.

```mermaid
sequenceDiagram
participant Signer as "VIZ\\Key"
participant EC as "Elliptic\\EC"
participant Data as "Auth Data"
Signer->>Data : "Compose domain : action : account : authority : timestamp : nonce"
Signer->>EC : "sign(hash(data), private_key)"
EC-->>Signer : "Signature (compact)"
Signer-->>Caller : "data, signature"
```

**Diagram sources**
- [Key.php](file://class/VIZ/Key.php#L339-L352)
- [EC.php](file://class/Elliptic/EC.php#L89-L177)

**Section sources**
- [Key.php](file://class/VIZ/Key.php#L339-L352)
- [EC.php](file://class/Elliptic/EC.php#L89-L177)

### Public Key Recovery and Verification
- Public key recovery
  - Using the signature and data, the system recovers the public key. This enables verification without requiring the original public key to be transmitted.
- Verification
  - The recovered public key is compared against the account’s authority key list. The verifier aggregates weights and enforces the threshold.

```mermaid
sequenceDiagram
participant Verifier as "VIZ\\Auth"
participant Signer as "VIZ\\Key"
participant EC as "Elliptic\\EC"
Verifier->>Signer : "recover_public_key(data, signature)"
Signer->>EC : "recoverPubKey(hash(data), signature, recovery_param)"
EC-->>Signer : "Recovered public key"
Signer-->>Verifier : "Public key"
Verifier->>Verifier : "Match against account authority key_auths"
Verifier->>Verifier : "Sum weights and compare threshold"
```

**Diagram sources**
- [Auth.php](file://class/VIZ/Auth.php#L35-L67)
- [Key.php](file://class/VIZ/Key.php#L323-L338)
- [EC.php](file://class/Elliptic/EC.php#L221-L249)

**Section sources**
- [Key.php](file://class/VIZ/Key.php#L323-L338)
- [EC.php](file://class/Elliptic/EC.php#L221-L249)

### Authority Structures and Multi-Signature Support
- Authority structures
  - Accounts define authorities (master, active, regular) with weight thresholds and key_auths. The authentication system verifies against the selected authority.
- Multi-signature support
  - Transactions demonstrate multi-signature workflows and authority composition. Multiple private keys can contribute signatures to satisfy thresholds.

```mermaid
classDiagram
class Transaction {
+build_account_create(...)
+build_account_update(...)
+add_private_key(private_key)
+signatures
}
class Key {
+sign(data) string
+verify(data, signature) bool
+recover_public_key(data, signature) string
}
Transaction --> Key : "uses for signing"
```

**Diagram sources**
- [Transaction.php](file://class/VIZ/Transaction.php#L191-L350)
- [Key.php](file://class/VIZ/Key.php#L302-L322)

**Section sources**
- [Transaction.php](file://class/VIZ/Transaction.php#L191-L350)
- [README.md](file://README.md#L241-L283)

### Permission Checking and Recovery Mechanisms
- Permission checking
  - The verifier ensures the recovered public key belongs to the specified authority and that the aggregated weight meets or exceeds the threshold.
- Recovery mechanisms
  - The library supports account recovery operations via transaction builders, enabling recovery authority workflows when needed.

**Section sources**
- [Auth.php](file://class/VIZ/Auth.php#L47-L59)
- [Transaction.php](file://class/VIZ/Transaction.php#L503-L561)

## Dependency Analysis
The authentication system relies on:
- VIZ\Auth depends on VIZ\Key for signature recovery and VIZ\JsonRPC for account retrieval.
- VIZ\Key depends on Elliptic EC for signing and recovery.
- VIZ\Transaction demonstrates authority structures and multi-signature workflows.

```mermaid
graph LR
AUTH["VIZ\\Auth"] --> KEY["VIZ\\Key"]
AUTH --> RPC["VIZ\\JsonRPC"]
KEY --> ECG["Elliptic\\EC"]
KEY --> EDS["Elliptic\\EdDSA"]
TX["VIZ\\Transaction"] --> KEY
TX --> RPC
```

**Diagram sources**
- [Auth.php](file://class/VIZ/Auth.php#L9-L24)
- [Key.php](file://class/VIZ/Key.php#L9-L32)
- [JsonRPC.php](file://class/VIZ/JsonRPC.php#L17-L22)
- [EC.php](file://class/Elliptic/EC.php#L9-L40)
- [EdDSA.php](file://class/Elliptic/EdDSA.php#L7-L27)
- [Transaction.php](file://class/VIZ/Transaction.php#L21-L41)

**Section sources**
- [Auth.php](file://class/VIZ/Auth.php#L9-L24)
- [Key.php](file://class/VIZ/Key.php#L9-L32)
- [JsonRPC.php](file://class/VIZ/JsonRPC.php#L17-L22)
- [EC.php](file://class/Elliptic/EC.php#L9-L40)
- [EdDSA.php](file://class/Elliptic/EdDSA.php#L7-L27)
- [Transaction.php](file://class/VIZ/Transaction.php#L21-L41)

## Performance Considerations
- Time window sizing
  - Adjust the range parameter to balance usability and security. A larger range increases tolerance for clock skew but broadens the validation window.
- Signature generation retries
  - The signer attempts to produce a canonical signature; in rare cases, it may increment the nonce until a valid signature is found.
- Network latency
  - JsonRPC calls introduce latency. Consider caching account authority data when appropriate and batching requests where feasible.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
Common issues and resolutions:
- Authentication fails due to time mismatch
  - Ensure server timezone handling is configured correctly. The verifier can adjust for local timezone offsets.
- Invalid signature or public key mismatch
  - Verify that the signature was produced using the same data and private key. Confirm the correct curve (secp256k1) and canonical signature format.
- Authority threshold not met
  - Confirm the account’s authority structure and ensure the recovered public key is present in the authority’s key_auths with sufficient weight.
- Network errors
  - Check endpoint availability and SSL settings. Review JsonRPC debug output for request/response details.

**Section sources**
- [Auth.php](file://class/VIZ/Auth.php#L29-L31)
- [Key.php](file://class/VIZ/Key.php#L302-L322)
- [JsonRPC.php](file://class/VIZ/JsonRPC.php#L311-L353)

## Conclusion
The VIZ PHP Library provides a robust, domain-scoped, time-bound, and authority-aware passwordless authentication mechanism. By leveraging ECDSA signatures, public key recovery, and blockchain-backed authority structures, it enables secure and flexible authentication flows. Developers can integrate these components into web applications and APIs to authenticate users without traditional passwords, while maintaining strong security guarantees through weight thresholds and multi-signature support.

[No sources needed since this section summarizes without analyzing specific files]

## Appendices

### Practical Implementation Examples
- Passwordless authentication example
  - See the example in the repository that generates data and signature, then verifies it using VIZ\Auth.
- Multi-signature transaction example
  - The library includes examples of building transactions with multiple operations and adding additional signatures.

**Section sources**
- [README.md](file://README.md#L207-L222)
- [README.md](file://README.md#L113-L135)

### Security Considerations
- Use canonical signatures to avoid ambiguity.
- Protect private keys and ensure secure storage.
- Validate domains and actions to prevent cross-domain replay attacks.
- Monitor and rotate keys regularly; leverage recovery mechanisms when necessary.
- Keep the time window reasonable to minimize exposure windows.

[No sources needed since this section provides general guidance]