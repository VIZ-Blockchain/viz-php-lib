<?php
/**
 * VIZ Passwordless Authentication - Session Management Example
 *
 * This script demonstrates a complete workflow:
 * 1. Generate authentication signature (client-side)
 * 2. Send to server and create session
 * 3. Use session for subsequent requests
 *
 * For cloud operations and free-speech-project integration.
 *
 * Usage:
 *   php session_example.php [account] [private_key_wif] [server_url]
 */

include(__DIR__ . '/../class/autoloader.php');

// ========== CONFIGURATION ==========

$account = $argv[1] ?? 'test_account';
$private_key_wif = $argv[2] ?? '5KcfoRuDfkhrLCxVcE9x51J6KN9aM9fpb78tLrvvFckxVV6FyFW';
$server_url = $argv[3] ?? 'http://localhost/auth_server.php';
$domain = parse_url($server_url, PHP_URL_HOST) ?? 'localhost';

echo "=== VIZ Session Management Example ===\n\n";
echo "Server: $server_url\n";
echo "Domain: $domain\n";
echo "Account: $account\n\n";

// ========== HELPER FUNCTIONS ==========

function send_request($url, $data) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => true, 'message' => $error];
    }
    return json_decode($response, true);
}

// ========== STEP 1: GENERATE AUTH SIGNATURE ==========

echo "--- Step 1: Generate Authentication Signature ---\n";

try {
    $private_key = new VIZ\Key($private_key_wif);
    echo "Private key loaded\n";
} catch (Exception $e) {
    die("Error: Invalid private key - " . $e->getMessage() . "\n");
}

// Generate auth data
list($data, $signature) = $private_key->auth($account, $domain, 'auth', 'regular');
echo "Auth data: $data\n";
echo "Signature: " . substr($signature, 0, 32) . "...\n\n";

// ========== STEP 2: CREATE SESSION ==========

echo "--- Step 2: Create Session ---\n";

$auth_request = [
    'data' => $data,
    'signature' => $signature,
    'action' => 'session'
];

echo "Sending authentication request...\n";
$result = send_request($server_url, $auth_request);

if (isset($result['error']) && $result['error']) {
    die("Authentication failed: " . ($result['message'] ?? json_encode($result)) . "\n");
}

if (!isset($result['auth']) || !$result['auth']) {
    die("Authentication rejected by server\n");
}

$session_id = $result['session'] ?? null;
$expire = $result['expire'] ?? 0;

if (!$session_id) {
    die("No session ID received\n");
}

echo "Session created!\n";
echo "Session ID: $session_id\n";
echo "Expires: " . date('Y-m-d H:i:s', $expire) . "\n\n";

// ========== STEP 3: USE SESSION ==========

echo "--- Step 3: Use Session for Requests ---\n";

// Example: Ping request using session
$session_request = [
    'session' => $session_id,
    'action' => 'ping'
];

echo "Sending ping request with session...\n";
$result = send_request($server_url, $session_request);

if (isset($result['auth']) && $result['auth']) {
    echo "Session validated!\n";
    echo "Server response: " . json_encode($result['result'] ?? [], JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "Session invalid or expired\n\n";
}

// Example: Verify request using session
$verify_request = [
    'session' => $session_id,
    'action' => 'verify'
];

echo "Sending verify request with session...\n";
$result = send_request($server_url, $verify_request);

if (isset($result['auth']) && $result['auth']) {
    echo "Verification successful!\n";
    echo "Account: " . ($result['result']['account'] ?? 'unknown') . "\n\n";
} else {
    echo "Verification failed\n\n";
}

// ========== SESSION STORAGE EXAMPLE ==========

echo "--- Session Storage Pattern ---\n\n";

echo "// Store session after authentication\n";
echo "\$session = [\n";
echo "    'id' => '$session_id',\n";
echo "    'account' => '$account',\n";
echo "    'expire' => $expire\n";
echo "];\n";
echo "file_put_contents('session.json', json_encode(\$session));\n\n";

echo "// Load session for subsequent requests\n";
echo "\$session = json_decode(file_get_contents('session.json'), true);\n";
echo "if (\$session['expire'] > time()) {\n";
echo "    // Session is valid, use \$session['id'] for requests\n";
echo "} else {\n";
echo "    // Session expired, re-authenticate\n";
echo "}\n\n";

// ========== CLOUD OPERATIONS PATTERN ==========

echo "--- Cloud Operations Pattern ---\n\n";

echo <<<'PATTERN'
// Pattern for cloud operations (e.g., free-speech-project)

class VIZCloudAuth {
    private $server_url;
    private $domain;
    private $session_file;
    private $private_key;
    private $account;

    public function __construct($server_url, $private_key_wif, $account) {
        $this->server_url = $server_url;
        $this->domain = parse_url($server_url, PHP_URL_HOST);
        $this->session_file = sys_get_temp_dir() . '/viz_session_' . $account . '.json';
        $this->private_key = new VIZ\Key($private_key_wif);
        $this->account = $account;
    }

    public function getSession() {
        // Check cached session
        if (file_exists($this->session_file)) {
            $session = json_decode(file_get_contents($this->session_file), true);
            if ($session && $session['expire'] > time() + 60) {
                return $session['id'];
            }
        }

        // Create new session
        list($data, $signature) = $this->private_key->auth(
            $this->account,
            $this->domain,
            'auth',
            'regular'
        );

        $result = $this->request([
            'data' => $data,
            'signature' => $signature,
            'action' => 'session'
        ]);

        if (isset($result['session'])) {
            file_put_contents($this->session_file, json_encode([
                'id' => $result['session'],
                'expire' => $result['expire']
            ]));
            return $result['session'];
        }

        return false;
    }

    public function execute($action, $params = []) {
        $session = $this->getSession();
        if (!$session) {
            return ['error' => 'Authentication failed'];
        }

        return $this->request(array_merge([
            'session' => $session,
            'action' => $action
        ], $params));
    }

    private function request($data) {
        $ch = curl_init($this->server_url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
}

// Usage:
// $auth = new VIZCloudAuth('https://api.example.com/auth', $wif, $account);
// $result = $auth->execute('get_updates', ['activity' => $last_activity]);

PATTERN;

echo "\n\n=== Example Complete ===\n";
