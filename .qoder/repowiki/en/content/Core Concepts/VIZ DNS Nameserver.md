# VIZ DNS Nameserver

<cite>
**Referenced Files in This Document**
- [README.md](file://README.md)
- [composer.json](file://composer.json)
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php)
- [class/VIZ/JsonRPC.php](file://class/VIZ/JsonRPC.php)
- [class/VIZ/Transaction.php](file://class/VIZ/Transaction.php)
- [class/VIZ/Key.php](file://class/VIZ/Key.php)
- [class/VIZ/Auth.php](file://class/VIZ/Auth.php)
- [class/VIZ/Utils.php](file://class/VIZ/Utils.php)
- [class/autoloader.php](file://class/autoloader.php)
- [scripts/dns_example.php](file://scripts/dns_example.php)
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

## Introduction

The VIZ DNS Nameserver is a specialized PHP library designed to provide DNS-like functionality for the VIZ blockchain ecosystem. This library enables domain owners to publish and manage DNS records directly on the blockchain, creating a decentralized and tamper-proof naming system that integrates seamlessly with VIZ account metadata.

The library focuses on three primary DNS record types:
- **A records**: IPv4 address resolution for domains
- **TXT records**: Text-based data storage including SSL certificate hashes
- **SSL verification**: Cryptographically secure certificate validation against blockchain-stored hashes

Built on top of the VIZ blockchain's account metadata system, this library provides a comprehensive solution for decentralized domain management, enabling secure and verifiable DNS resolution without traditional centralized DNS infrastructure.

## Project Structure

The VIZ DNS Nameserver library follows a modular architecture organized around core blockchain interaction components and specialized DNS functionality:

```mermaid
graph TB
subgraph "Core Library Structure"
A[class/VIZ/] --> B[DNS.php]
A --> C[JsonRPC.php]
A --> D[Transaction.php]
A --> E[Key.php]
A --> F[Auth.php]
A --> G[Utils.php]
H[class/] --> I[autoloader.php]
H --> J[Elliptic/]
H --> K[BI/]
H --> L[BN/]
M[scripts/] --> N[dns_example.php]
O[tests/] --> P[TestKeys.php]
end
subgraph "External Dependencies"
Q[GMP/Bcmath Extensions]
R[OpenSSL Extension]
S[PHP Streams]
end
B --> Q
C --> S
D --> Q
E --> R
F --> Q
G --> Q
```

**Diagram sources**
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L1-L511)
- [class/VIZ/JsonRPC.php](file://class/VIZ/JsonRPC.php#L1-L368)
- [class/VIZ/Transaction.php](file://class/VIZ/Transaction.php#L1-L800)

**Section sources**
- [composer.json](file://composer.json#L19-L29)
- [class/autoloader.php](file://class/autoloader.php#L1-L14)

The project is structured with clear separation of concerns:
- **VIZ namespace classes**: Core blockchain integration and cryptographic operations
- **Elliptic curve cryptography**: Secure key generation and signature verification
- **BigInteger arithmetic**: High-precision mathematical operations for cryptographic functions
- **Example scripts**: Demonstration of practical usage scenarios
- **Unit tests**: Comprehensive testing framework for cryptographic operations

## Core Components

The VIZ DNS Nameserver library consists of several interconnected components that work together to provide comprehensive DNS functionality on the blockchain:

### DNS Management System

The central `DNS` class provides a complete toolkit for managing DNS records within VIZ blockchain accounts:

```mermaid
classDiagram
class DNS {
+DEFAULT_TTL : int
+RECORD_A : string
+RECORD_TXT : string
+SSL_PREFIX : string
+MAX_TXT_LENGTH : int
+parse_ns_data(metadata) : array|false
+build_ns_data(records, ttl) : array
+merge_ns_into_metadata(existing, records, ttl) : array
+remove_ns_from_metadata(existing) : array
+get_a_records(ns_data) : array
+get_ssl_hash(ns_data) : string|null
+get_txt_records(ns_data) : array
+create_a_record(ipv4) : array
+create_ssl_record(hash) : array
+create_txt_record(value) : array
+validate_ipv4(ip) : bool
+validate_ssl_hash(hash) : bool
+validate_txt_length(value) : bool
+validate_records(records) : array
+get_ssl_hash_from_server(domain, ipv4, port, timeout) : array
+verify_ssl(api, account, ipv4) : array
+get_account_ns(api, account) : array|false
+prepare_metadata_json(records, ttl, existing) : string
+build_simple_ns(ipv4, ssl_hash, ttl) : array
+build_round_robin_ns(ipv4_list, ssl_hash, ttl) : array
}
```

**Diagram sources**
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L12-L511)

### Blockchain Interaction Layer

The library integrates with the VIZ blockchain through specialized classes that handle transaction building, signing, and API communication:

```mermaid
classDiagram
class JsonRPC {
+endpoint : string
+debug : bool
+request_arr : array
+result_arr : array
+post_num : int
+header_arr : array
+host_ip : array
+check_ssl : bool
+return_only_result : bool
+execute_method(method, params, debug) : mixed
+build_method(method, params) : string
+raw_method(method, params) : string
+get_url(url, post, debug) : string|false
}
class Transaction {
+api : JsonRPC
+chain_id : string
+queue : bool
+queue_arr : array
+private_keys_count : int
+private_keys : array
+signatures : array
+build_account_metadata(account, json_metadata) : array
+execute(transaction_json, synchronous) : mixed
+build(operations_json, operations_data, operations_count) : array
+add_signature(json, data, private_key) : array|false
}
class Key {
+ec : EC
+private : bool
+bin : string
+hex : string
+sign(data) : string|false
+verify(data, signature) : bool
+get_public_key() : Key
+encode(prefix) : string
+get_shared_key(public_key_encoded) : string
+auth(account, domain, action, authority) : array
}
```

**Diagram sources**
- [class/VIZ/JsonRPC.php](file://class/VIZ/JsonRPC.php#L4-L368)
- [class/VIZ/Transaction.php](file://class/VIZ/Transaction.php#L10-L800)
- [class/VIZ/Key.php](file://class/VIZ/Key.php#L9-L353)

**Section sources**
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L12-L511)
- [class/VIZ/JsonRPC.php](file://class/VIZ/JsonRPC.php#L4-L368)
- [class/VIZ/Transaction.php](file://class/VIZ/Transaction.php#L10-L800)
- [class/VIZ/Key.php](file://class/VIZ/Key.php#L9-L353)

## Architecture Overview

The VIZ DNS Nameserver implements a layered architecture that separates blockchain interaction from DNS record management:

```mermaid
graph TB
subgraph "Application Layer"
A[DNS Manager]
B[SSL Verifier]
C[Metadata Builder]
end
subgraph "Transaction Layer"
D[Transaction Builder]
E[Signature Generator]
F[Network Broadcast]
end
subgraph "Blockchain Layer"
G[VIZ Account Metadata]
H[DNS Records Storage]
I[SSL Hash Verification]
end
subgraph "Cryptographic Layer"
J[Elliptic Curve Crypto]
K[SHA-256 Hashing]
L[Base58 Encoding]
end
A --> D
B --> G
C --> D
D --> E
E --> J
E --> K
D --> F
F --> G
G --> H
G --> I
H --> J
I --> K
I --> L
```

**Diagram sources**
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L368-L435)
- [class/VIZ/Transaction.php](file://class/VIZ/Transaction.php#L61-L157)
- [class/VIZ/Key.php](file://class/VIZ/Key.php#L302-L352)

The architecture follows these key principles:

1. **Separation of Concerns**: DNS management is isolated from blockchain operations
2. **Layered Security**: Cryptographic operations are handled at the lowest level
3. **Transaction Abstraction**: Complex blockchain operations are simplified through high-level APIs
4. **Extensible Design**: New record types and validation rules can be easily added

## Detailed Component Analysis

### DNS Record Management System

The DNS component provides comprehensive functionality for managing various DNS record types within VIZ blockchain accounts:

#### Record Types and Validation

The system supports three primary record types with strict validation mechanisms:

```mermaid
flowchart TD
A[DNS Record Input] --> B{Record Type?}
B --> |A Record| C[Validate IPv4 Address]
B --> |TXT Record| D[Validate TXT Length]
B --> |SSL Record| E[Validate SHA-256 Hash]
C --> F{Valid IPv4?}
D --> G{Valid TXT Length?}
E --> H{Valid Hash Format?}
F --> |Yes| I[Accept Record]
F --> |No| J[Reject with Error]
G --> |Yes| K[Accept Record]
G --> |No| J
H --> |Yes| L[Accept SSL Record]
H --> |No| J
I --> M[Store in NS Data]
K --> M
L --> M
J --> N[Return Validation Errors]
```

**Diagram sources**
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L241-L277)
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L211-L233)

#### SSL Certificate Verification Workflow

The SSL verification process ensures cryptographic integrity between blockchain-stored certificate hashes and actual server certificates:

```mermaid
sequenceDiagram
participant App as Application
participant DNS as DNS Manager
participant API as VIZ API
participant Server as Web Server
App->>DNS : verify_ssl(account, domain)
DNS->>API : get_accounts([account])
API-->>DNS : Account with json_metadata
DNS->>DNS : parse_ns_data(json_metadata)
DNS->>DNS : get_ssl_hash(ns_data)
DNS->>DNS : get_a_records(ns_data)
DNS->>Server : get_ssl_hash_from_server(domain, ipv4)
Server-->>DNS : Actual SSL Hash
DNS->>DNS : Compare hashes (timing-safe)
DNS-->>App : Verification Result
Note over DNS,Server : Uses hash_equals() for timing-safe comparison
```

**Diagram sources**
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L368-L435)
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L288-L358)

#### Metadata Management Operations

The library provides sophisticated metadata manipulation capabilities:

| Operation | Purpose | Implementation |
|-----------|---------|----------------|
| `parse_ns_data()` | Extract DNS data from account metadata | JSON parsing with fallback handling |
| `build_ns_data()` | Create DNS metadata structure | Array construction with TTL |
| `merge_ns_into_metadata()` | Combine DNS with existing metadata | Safe merging with validation |
| `remove_ns_from_metadata()` | Clean DNS data from metadata | Array cleanup |

**Section sources**
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L32-L108)
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L116-L203)

### Transaction Building and Execution

The transaction system handles the complex process of creating and broadcasting DNS-related operations to the VIZ blockchain:

#### Transaction Construction Process

```mermaid
flowchart TD
A[Build DNS Records] --> B[Prepare Metadata JSON]
B --> C[Create Transaction Object]
C --> D[Fetch Dynamic Global Properties]
D --> E[Get Tapos Block Information]
E --> F[Encode Transaction Data]
F --> G[Sign with Private Keys]
G --> H[Build Final Transaction JSON]
H --> I[Execute on Blockchain]
I --> J[Transaction Result]
```

**Diagram sources**
- [class/VIZ/Transaction.php](file://class/VIZ/Transaction.php#L61-L157)
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L461-L469)

#### Multi-Signature Support

The transaction system supports advanced multi-signature configurations:

| Authority Level | Weight Threshold | Key Authentication |
|----------------|------------------|-------------------|
| Master | Typically 1 | Primary account keys |
| Active | Configurable | Secondary account keys |
| Regular | Configurable | General operations keys |

**Section sources**
- [class/VIZ/Transaction.php](file://class/VIZ/Transaction.php#L191-L350)
- [class/VIZ/Transaction.php](file://class/VIZ/Transaction.php#L351-L502)

### Cryptographic Foundation

The library leverages robust cryptographic primitives for secure operations:

#### Elliptic Curve Cryptography

The implementation uses the secp256k1 curve for all cryptographic operations:

```mermaid
classDiagram
class EC {
+curve : Curve
+n : BigInteger
+nh : BigInteger
+g : Point
+hash : HashFunction
+keyPair(options) : KeyPair
+keyFromPrivate(priv, enc) : KeyPair
+keyFromPublic(pub, enc) : KeyPair
+genKeyPair(options) : KeyPair
+sign(msg, key, enc, options) : Signature
+verify(msg, signature, key, enc) : bool
+recoverPubKey(msg, signature, recId, enc) : Point
}
class KeyPair {
+ec : EC
+priv : BigInteger
+pub : Point
+getPrivate() : BigInteger
+getPublic(compressed, enc) : string
+sign(msg, options) : Signature
}
class Signature {
+r : BigInteger
+s : BigInteger
+recoveryParam : int
+toCompact(enc) : string
+toDER(enc) : string
}
EC --> KeyPair : creates
KeyPair --> Signature : produces
```

**Diagram sources**
- [class/Elliptic/EC.php](file://class/Elliptic/EC.php#L9-L200)
- [class/VIZ/Key.php](file://class/VIZ/Key.php#L9-L353)

**Section sources**
- [class/VIZ/Key.php](file://class/VIZ/Key.php#L14-L32)
- [class/Elliptic/EC.php](file://class/Elliptic/EC.php#L46-L75)

## Dependency Analysis

The VIZ DNS Nameserver library has a carefully managed dependency structure that balances functionality with minimal external requirements:

```mermaid
graph TB
subgraph "Internal Dependencies"
A[VIZ/DNS] --> B[VIZ/JsonRPC]
A --> C[VIZ/Key]
A --> D[VIZ/Utils]
B --> E[VIZ/Transaction]
C --> F[Elliptic/EC]
C --> G[BI/BigInteger]
D --> G
D --> H[kornrunner/Keccak]
end
subgraph "PHP Extensions"
I[gmp]
J[bcmath]
K[openssl]
L[ctype]
M[filter]
N[hash]
O[json]
P[pcntl]
end
subgraph "External Libraries"
Q[simplito/elliptic-php]
R[simplito/bn-php]
S[simplito/bigint-wrapper-php]
T[kornrunner/php-keccak]
end
A --> I
A --> J
A --> K
A --> L
A --> M
A --> N
A --> O
A --> P
F --> Q
F --> R
F --> S
D --> T
```

**Diagram sources**
- [composer.json](file://composer.json#L19-L29)
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L1-L12)

### External Dependencies and Requirements

The library requires specific PHP extensions for optimal performance:

| Extension | Purpose | Alternative |
|-----------|---------|-------------|
| GMP | High-precision arithmetic | BCMath |
| BCMath | Mathematical operations | GMP |
| OpenSSL | SSL/TLS operations | None |
| Filter | Input validation | None |
| Hash | Cryptographic hashing | None |
| JSON | Data serialization | None |

**Section sources**
- [README.md](file://README.md#L20-L28)
- [composer.json](file://composer.json#L19-L29)

## Performance Considerations

The VIZ DNS Nameserver library is optimized for both security and performance:

### Cryptographic Performance

- **Elliptic Curve Operations**: Optimized using the secp256k1 curve for efficient signature generation and verification
- **BigInteger Arithmetic**: Automatic selection between GMP and BCMath based on availability
- **Memory Management**: Efficient handling of large cryptographic values and signatures

### Network Optimization

- **Connection Pooling**: Reuse of network connections for API requests
- **Response Caching**: Temporary caching of frequently accessed blockchain data
- **Timeout Management**: Configurable timeouts for reliable operation

### Transaction Efficiency

- **Batch Operations**: Support for multiple DNS record updates in single transactions
- **Queue System**: Asynchronous processing of multiple operations
- **Signature Optimization**: Efficient multi-signature aggregation

## Troubleshooting Guide

Common issues and their solutions when working with the VIZ DNS Nameserver:

### SSL Verification Issues

**Problem**: SSL verification fails despite correct configuration
**Solution**: 
1. Verify DNS records are properly published to blockchain
2. Check network connectivity to VIZ nodes
3. Confirm SSL certificate matches blockchain-stored hash

**Section sources**
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L368-L435)

### Transaction Broadcasting Problems

**Problem**: Transactions fail to broadcast or confirm
**Solution**:
1. Verify sufficient VIZ balance for fees
2. Check transaction expiration settings
3. Ensure proper private key formatting

**Section sources**
- [class/VIZ/Transaction.php](file://class/VIZ/Transaction.php#L53-L60)

### Metadata Encoding Errors

**Problem**: Metadata fails to encode properly for blockchain storage
**Solution**:
1. Validate JSON structure compliance
2. Check character encoding issues
3. Verify escape sequence handling

**Section sources**
- [class/VIZ/DNS.php](file://class/VIZ/DNS.php#L461-L469)

## Conclusion

The VIZ DNS Nameserver represents a sophisticated solution for decentralized domain management, combining blockchain technology with familiar DNS concepts. The library provides:

- **Complete DNS Functionality**: Support for A records, TXT records, and SSL verification
- **Robust Security**: Cryptographically secure operations using proven elliptic curve cryptography
- **Flexible Integration**: Easy-to-use APIs that integrate seamlessly with existing applications
- **Production Ready**: Comprehensive error handling, validation, and performance optimization

The modular architecture ensures maintainability and extensibility, while the comprehensive example scripts demonstrate practical usage patterns. The library serves as a foundation for building decentralized applications that require reliable, tamper-proof domain resolution services.

Future enhancements could include support for additional DNS record types, improved caching mechanisms, and expanded SSL certificate validation features. The solid architectural foundation makes such extensions straightforward to implement while maintaining backward compatibility.