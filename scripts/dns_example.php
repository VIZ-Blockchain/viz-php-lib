<?php
/**
 * VIZ DNS Helpers - Usage Examples
 *
 * Demonstrates how to use the VIZ\DNS class for working with NS records
 * stored in VIZ blockchain account metadata.
 */

require_once __DIR__ . '/../class/autoloader.php';

use VIZ\DNS;
use VIZ\JsonRPC;
use VIZ\Transaction;

// =============================================================================
// Example 1: Building NS Records
// =============================================================================

echo "=== Building NS Records ===\n\n";

// Simple NS configuration with one A record
$ns_simple = DNS::build_simple_ns('188.120.231.153');
echo "Simple NS:\n" . json_encode($ns_simple, JSON_PRETTY_PRINT) . "\n\n";

// NS with SSL hash
$ns_with_ssl = DNS::build_simple_ns(
    '188.120.231.153',
    '4a4613daef37cbc5c4a5156cd7b24ea2e6ee2e5f1e7461262a2df2b63cbf17e2'
);
echo "NS with SSL:\n" . json_encode($ns_with_ssl, JSON_PRETTY_PRINT) . "\n\n";

// Round Robin configuration (multiple IPs)
$ns_round_robin = DNS::build_round_robin_ns(
    ['188.120.231.153', '192.168.1.100', '10.0.0.50'],
    '4a4613daef37cbc5c4a5156cd7b24ea2e6ee2e5f1e7461262a2df2b63cbf17e2',
    3600 // Custom TTL: 1 hour
);
echo "Round Robin NS:\n" . json_encode($ns_round_robin, JSON_PRETTY_PRINT) . "\n\n";

// Manual record creation
$records = [
    DNS::create_a_record('188.120.231.153'),
    DNS::create_a_record('192.168.1.100'),
    DNS::create_ssl_record('4a4613daef37cbc5c4a5156cd7b24ea2e6ee2e5f1e7461262a2df2b63cbf17e2'),
    DNS::create_txt_record('custom=data'),
];
$ns_manual = DNS::build_ns_data($records, DNS::DEFAULT_TTL);
echo "Manual NS:\n" . json_encode($ns_manual, JSON_PRETTY_PRINT) . "\n\n";

// =============================================================================
// Example 2: Parsing NS Data
// =============================================================================

echo "=== Parsing NS Data ===\n\n";

// Parse from JSON string (as received from blockchain)
$json_metadata = '{"ns":[["A","188.120.231.153"],["TXT","ssl=4a4613daef37cbc5c4a5156cd7b24ea2e6ee2e5f1e7461262a2df2b63cbf17e2"]],"ttl":28800}';
$ns_data = DNS::parse_ns_data($json_metadata);

if ($ns_data !== false) {
    echo "Parsed NS Data:\n";

    // Get A records
    $a_records = DNS::get_a_records($ns_data);
    echo "  A Records: " . implode(', ', $a_records) . "\n";

    // Get SSL hash
    $ssl_hash = DNS::get_ssl_hash($ns_data);
    echo "  SSL Hash: " . ($ssl_hash ?? 'not found') . "\n";

    // Get TTL
    echo "  TTL: " . $ns_data['ttl'] . " seconds\n\n";
}

// =============================================================================
// Example 3: Validating Records
// =============================================================================

echo "=== Validating Records ===\n\n";

// Valid records
$valid_records = [
    ['A', '192.168.1.1'],
    ['TXT', 'ssl=4a4613daef37cbc5c4a5156cd7b24ea2e6ee2e5f1e7461262a2df2b63cbf17e2'],
];
$result = DNS::validate_records($valid_records);
echo "Valid records: " . ($result['valid'] ? 'YES' : 'NO') . "\n";

// Invalid records
$invalid_records = [
    ['A', 'not-an-ip'],
    ['TXT', 'ssl=invalid-hash'],
    ['UNKNOWN', 'value'],
];
$result = DNS::validate_records($invalid_records);
echo "Invalid records: " . ($result['valid'] ? 'YES' : 'NO') . "\n";
echo "Errors:\n";
foreach ($result['errors'] as $error) {
    echo "  - $error\n";
}
echo "\n";

// Individual validations
echo "IP validation:\n";
echo "  188.120.231.153: " . (DNS::validate_ipv4('188.120.231.153') ? 'valid' : 'invalid') . "\n";
echo "  999.999.999.999: " . (DNS::validate_ipv4('999.999.999.999') ? 'valid' : 'invalid') . "\n";
echo "  not-an-ip: " . (DNS::validate_ipv4('not-an-ip') ? 'valid' : 'invalid') . "\n\n";

// =============================================================================
// Example 4: Merging with Existing Metadata
// =============================================================================

echo "=== Merging with Existing Metadata ===\n\n";

$existing = '{"profile":{"name":"John"},"website":"https://example.com"}';
$new_records = [
    DNS::create_a_record('188.120.231.153'),
    DNS::create_ssl_record('4a4613daef37cbc5c4a5156cd7b24ea2e6ee2e5f1e7461262a2df2b63cbf17e2'),
];

$merged = DNS::merge_ns_into_metadata($existing, $new_records);
echo "Merged metadata:\n" . json_encode($merged, JSON_PRETTY_PRINT) . "\n\n";

// Remove NS from metadata
$without_ns = DNS::remove_ns_from_metadata($merged);
echo "After removing NS:\n" . json_encode($without_ns, JSON_PRETTY_PRINT) . "\n\n";

// =============================================================================
// Example 5: Preparing for Transaction
// =============================================================================

echo "=== Preparing for Transaction ===\n\n";

$records = [
    DNS::create_a_record('188.120.231.153'),
    DNS::create_ssl_record('4a4613daef37cbc5c4a5156cd7b24ea2e6ee2e5f1e7461262a2df2b63cbf17e2'),
];

// Prepare escaped JSON for account_metadata operation
$metadata_json = DNS::prepare_metadata_json($records);
echo "Escaped JSON for transaction:\n$metadata_json\n\n";

// With existing metadata
$metadata_json_merged = DNS::prepare_metadata_json($records, DNS::DEFAULT_TTL, $existing);
echo "Escaped JSON (merged):\n$metadata_json_merged\n\n";

// =============================================================================
// Example 6: Full Transaction Example (requires endpoint and key)
// =============================================================================

echo "=== Transaction Example (pseudo-code) ===\n\n";

echo <<<'CODE'
// Set NS records on blockchain
$endpoint = 'https://node.viz.cx/';
$private_key = '5K...';  // Regular private key
$account = 'myaccount';

$tx = new Transaction($endpoint, $private_key);

// Get existing metadata
$account_data = $tx->api->execute_method('get_accounts', [[$account]]);
$existing_metadata = $account_data[0]['json_metadata'] ?? '{}';

// Build new NS records
$records = [
    DNS::create_a_record('188.120.231.153'),
    DNS::create_ssl_record('your-ssl-hash-here'),
];

// Prepare and execute
$metadata_json = DNS::prepare_metadata_json($records, DNS::DEFAULT_TTL, $existing_metadata);
$tx_data = $tx->account_metadata($account, $metadata_json);
$result = $tx->execute($tx_data['json']);

CODE;
echo "\n\n";

// =============================================================================
// Example 7: SSL Verification (requires network access)
// =============================================================================

echo "=== SSL Verification Example ===\n\n";

echo "Note: Uncomment below to test SSL verification (requires network)\n\n";

/*
$api = new JsonRPC('https://node.viz.cx/');

// Get SSL hash from a server
$ssl_result = DNS::get_ssl_hash_from_server('example.com', null, 443, 5);
if ($ssl_result['error'] === false) {
    echo "Server SSL Hash: " . $ssl_result['result']['hash'] . "\n";
    echo "Server IP: " . $ssl_result['result']['ipv4'] . "\n";
}

// Verify SSL against blockchain records
$verify_result = DNS::verify_ssl($api, 'on1x');
if ($verify_result['valid']) {
    echo "SSL verification PASSED\n";
} else {
    echo "SSL verification FAILED: " . $verify_result['error'] . "\n";
    echo "Expected: " . $verify_result['expected'] . "\n";
    echo "Actual: " . $verify_result['actual'] . "\n";
}
*/

echo "=== Examples Complete ===\n";
