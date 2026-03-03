<?php
/**
 * VIZ Passwordless Authentication - Server Example
 *
 * This script demonstrates a complete server-side implementation for
 * passwordless authentication using VIZ blockchain.
 *
 * Designed for cloud operations and free-speech-project integration.
 *
 * Features:
 * - CORS support for cross-origin requests
 * - Signature-based authentication
 * - Session-based authentication
 * - Multiple action handlers
 *
 * Deploy: Place in web-accessible directory with PHP support
 */

// Disable error display in production
error_reporting(0);

// Handle CORS preflight
if ('OPTIONS' == $_SERVER['REQUEST_METHOD']) {
    http_response_code(200);
    header('Access-Control-Allow-Headers: DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Expose-Headers: Content-Length');
    exit;
}

// Include VIZ library
include(__DIR__ . '/../class/autoloader.php');

// ========== CONFIGURATION ==========

// VIZ node and domain settings
$config = [
    'node' => 'https://node.viz.plus/',
    'domain' => 'your-domain.com',     // Change to your domain
    'action' => 'auth',
    'authority' => 'regular',
    'time_range' => 60,                 // ±60 seconds tolerance
    'session_ttl' => 600,               // 10 minutes
];

// Initialize authenticator
$viz_auth = new VIZ\Auth(
    $config['node'],
    $config['domain'],
    $config['action'],
    $config['authority'],
    $config['time_range']
);

// ========== SESSION STORAGE ==========
// In production, use database (MySQL, Redis, etc.)
// This example uses file-based storage for simplicity

$sessions_file = sys_get_temp_dir() . '/viz_sessions.json';

function load_sessions($file) {
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        // Clean expired sessions
        $now = time();
        foreach ($data as $id => $session) {
            if ($session['expire'] < $now) {
                unset($data[$id]);
            }
        }
        return $data;
    }
    return [];
}

function save_sessions($file, $sessions) {
    file_put_contents($file, json_encode($sessions), LOCK_EX);
}

function create_session($file, $account, $ttl) {
    $sessions = load_sessions($file);
    $session_id = bin2hex(random_bytes(16));
    $sessions[$session_id] = [
        'account' => $account,
        'expire' => time() + $ttl,
        'created' => time()
    ];
    save_sessions($file, $sessions);
    return [$session_id, $sessions[$session_id]['expire']];
}

function verify_session($file, $session_id) {
    $sessions = load_sessions($file);
    if (isset($sessions[$session_id]) && $sessions[$session_id]['expire'] > time()) {
        return $sessions[$session_id]['account'];
    }
    return false;
}

// ========== REQUEST HANDLING ==========

$request = file_get_contents('php://input');
$request_arr = json_decode($request, true) ?: [];

$response = [];
$auth_status = false;
$account = false;

// Method 1: Session-based authentication
if (isset($request_arr['session'])) {
    $account = verify_session($sessions_file, $request_arr['session']);
    if ($account) {
        $auth_status = true;
    } else {
        $response['session'] = '';
        $response['expire'] = -1;
        $response['error'] = true;
        $response['result'] = false;
    }
}

// Method 2: Signature-based authentication
if (isset($request_arr['signature']) && isset($request_arr['data'])) {
    $auth_status = $viz_auth->check($request_arr['data'], $request_arr['signature']);
    if ($auth_status) {
        $data_parts = explode(':', $request_arr['data']);
        $account = $data_parts[2];
    }
}

$response['auth'] = $auth_status;

// ========== ACTION HANDLERS ==========

if ($auth_status) {
    $action = $request_arr['action'] ?? '';
    $error = false;

    switch ($action) {
        case 'session':
            // Create new session after successful signature auth
            list($session_id, $expire) = create_session(
                $sessions_file,
                $account,
                $config['session_ttl']
            );
            $response['session'] = $session_id;
            $response['expire'] = $expire;
            $response['account'] = $account;
            break;

        case 'ping':
            // Simple auth check - returns account info
            $response['result'] = [
                'account' => $account,
                'time' => time()
            ];
            break;

        case 'verify':
            // Verify authentication status
            $response['result'] = [
                'authenticated' => true,
                'account' => $account
            ];
            break;

        default:
            // Unknown action
            if ($action) {
                $error = true;
                $response['error_message'] = 'Unknown action: ' . $action;
            } else {
                // No action specified - just return auth status
                $response['account'] = $account;
            }
            break;
    }

    if ($error) {
        $response['error'] = true;
        $response['result'] = false;
    }
}

// Include original request in response (for debugging)
$response['request'] = $request_arr;

// ========== SEND RESPONSE ==========

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT);
