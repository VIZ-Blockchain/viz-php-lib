<?php
/**
 * VIZ Passwordless Authentication - Client Example
 *
 * This script demonstrates how to generate authentication data and signature
 * on the client side for passwordless login to VIZ-based services.
 *
 * Usage:
 *   php auth_client.php [account] [private_key_wif] [domain] [action] [authority]
 *
 * Example:
 *   php auth_client.php invite 5KcfoRuDfkhrLCxVcE9x51J6KN9aM9fpb78tLrvvFckxVV6FyFW my-domain.com auth regular
 */

include(__DIR__ . '/../class/autoloader.php');

// Configuration (can be passed as CLI arguments or set here)
$account = $argv[1] ?? 'your_account';
$private_key_wif = $argv[2] ?? '5YOUR_PRIVATE_KEY_WIF';
$domain = $argv[3] ?? 'example.com';
$action = $argv[4] ?? 'auth';
$authority = $argv[5] ?? 'regular';

echo "=== VIZ Passwordless Authentication - Client ===\n\n";

// Initialize private key
try {
    $private_key = new VIZ\Key($private_key_wif);
    echo "Private key loaded successfully\n";
    echo "Public key: " . $private_key->get_public_key()->encode() . "\n\n";
} catch (Exception $e) {
    die("Error: Invalid private key\n");
}

// Generate authentication data and signature
list($data, $signature) = $private_key->auth($account, $domain, $action, $authority);

echo "Authentication Parameters:\n";
echo "  Account:   $account\n";
echo "  Domain:    $domain\n";
echo "  Action:    $action\n";
echo "  Authority: $authority\n\n";

echo "Generated Authentication:\n";
echo "  Data:      $data\n";
echo "  Signature: $signature\n\n";

// Parse data for display
$data_parts = explode(':', $data);
echo "Data breakdown:\n";
echo "  [0] Domain:    {$data_parts[0]}\n";
echo "  [1] Action:    {$data_parts[1]}\n";
echo "  [2] Account:   {$data_parts[2]}\n";
echo "  [3] Authority: {$data_parts[3]}\n";
echo "  [4] Timestamp: {$data_parts[4]} (" . date('Y-m-d H:i:s', $data_parts[4]) . " UTC)\n";
echo "  [5] Nonce:     {$data_parts[5]}\n\n";

// Prepare payload for server
$payload = [
    'data' => $data,
    'signature' => $signature,
    'action' => 'session'  // Request a session after auth
];

echo "JSON payload for server:\n";
echo json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

// Example: Send to server using cURL
echo "=== Example cURL command ===\n";
$json_payload = json_encode($payload);
echo "curl -X POST -H \"Content-Type: application/json\" \\\n";
echo "     -d '$json_payload' \\\n";
echo "     https://your-server.com/api/auth\n\n";

// Self-verification test
echo "=== Self-verification test ===\n";
$recovered_key = $private_key->recover_public_key($data, $signature);
$expected_key = $private_key->get_public_key()->encode();
echo "Recovered public key: $recovered_key\n";
echo "Expected public key:  $expected_key\n";
echo "Keys match: " . ($recovered_key === $expected_key ? "YES ✓" : "NO ✗") . "\n";
