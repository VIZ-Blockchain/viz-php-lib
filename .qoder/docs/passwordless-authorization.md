# Passwordless Authorization

## Overview

VIZ blockchain provides a passwordless authentication mechanism using cryptographic signatures. Instead of transmitting passwords, users sign a challenge message with their private key, and servers verify the signature against the public key stored in the blockchain account.

## How It Works

### Authentication Flow

```
┌─────────┐                    ┌─────────┐                    ┌─────────────┐
│  Client │                    │  Server │                    │  VIZ Node   │
└────┬────┘                    └────┬────┘                    └──────┬──────┘
     │                              │                                │
     │  1. Generate data+signature  │                                │
     │  (domain:action:account:     │                                │
     │   authority:unixtime:nonce)  │                                │
     │                              │                                │
     │──────────────────────────────>                                │
     │  2. Send data + signature    │                                │
     │                              │                                │
     │                              │  3. Recover public key         │
     │                              │     from signature             │
     │                              │                                │
     │                              │──────────────────────────────> │
     │                              │  4. Fetch account authorities  │
     │                              │                                │
     │                              │<────────────────────────────── │
     │                              │  5. Return account data        │
     │                              │                                │
     │                              │  6. Verify key matches         │
     │                              │     authority threshold        │
     │                              │                                │
     │<──────────────────────────────                                │
     │  7. Return auth result       │                                │
     │                              │                                │
```

### Data Format

The authentication data string follows this format:
```
domain:action:account:authority:unixtime:nonce
```

| Field | Description | Example |
|-------|-------------|---------|
| domain | Your application domain | `readdle.me` |
| action | Authentication action type | `auth` |
| account | VIZ blockchain account name | `invite` |
| authority | Authority level to check | `regular`, `active`, `master` |
| unixtime | Current Unix timestamp (UTC) | `1709500000` |
| nonce | Random value for uniqueness | `1` |

### Authority Levels

- **regular** - Default authority for everyday operations
- **active** - Higher authority for important operations
- **master** - Highest authority for account recovery

## Client-Side Implementation

### Generate Authentication Signature

```php
<?php
include('./class/autoloader.php');

$account = 'your_account';
$domain = 'your-domain.com';
$action = 'auth';
$authority = 'regular';

$private_key = new VIZ\Key('5YOUR_PRIVATE_KEY_WIF');
list($data, $signature) = $private_key->auth($account, $domain, $action, $authority);

// Send to server
$payload = [
    'data' => $data,
    'signature' => $signature,
    'action' => 'session'
];
```

## Server-Side Implementation

### Initialize Auth Verifier

```php
<?php
include('./class/autoloader.php');

$node = 'https://node.viz.plus/';
$domain = 'your-domain.com';
$action = 'auth';
$authority = 'regular';
$time_range = 60; // ±60 seconds tolerance

$viz_auth = new VIZ\Auth($node, $domain, $action, $authority, $time_range);
```

### Verify Signature

```php
$auth_status = $viz_auth->check($request['data'], $request['signature']);

if ($auth_status) {
    // Extract account from data
    $data_parts = explode(':', $request['data']);
    $account = $data_parts[2];

    // User is authenticated
    // Create session or process request
}
```

## Session Management

For web applications, you can create sessions after successful authentication:

```php
if ($auth_status) {
    $session_id = md5($data . $signature);
    $expire_time = time() + 600; // 10 minutes

    // Store session in database
    // Return session ID to client
}
```

Client can then use session ID for subsequent requests instead of signing each time.

## Security Considerations

1. **Time Window**: The `range` parameter (default 60 seconds) defines how much clock skew is tolerated
2. **Timezone**: Set `$viz_auth->fix_server_timezone = true` if server timezone differs from UTC
3. **HTTPS**: Always use HTTPS in production
4. **Session Expiry**: Implement proper session expiration and cleanup

## Configuration Options

### VIZ\Auth Constructor

```php
new VIZ\Auth($node, $domain, $action, $authority, $range)
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| $node | string | required | VIZ node URL |
| $domain | string | required | Your domain |
| $action | string | `'auth'` | Action identifier |
| $authority | string | `'regular'` | Authority level |
| $range | int | `60` | Time tolerance in seconds |

### Properties

- `$viz_auth->fix_server_timezone` - Set to `true` to adjust for server timezone offset

## Integration Examples

### REST API Authentication

See `scripts/auth_server.php` for a complete server implementation.

### Client Library Usage

See `scripts/auth_client.php` for client-side examples.

### Session-Based Operations

See `scripts/session_example.php` for session management patterns.
