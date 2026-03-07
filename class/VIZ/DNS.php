<?php
namespace VIZ;

/**
 * VIZ DNS Nameserver Helpers
 *
 * Provides utilities for working with NS records stored in VIZ account metadata.
 * Supports A records (IPv4), TXT records (SSL hashes), and SSL certificate verification.
 *
 * @see .qoder\docs\spec\viz-dns-nameserver-spec.md
 */
class DNS {
	/** Default TTL in seconds (8 hours) */
	const DEFAULT_TTL = 28800;

	/** Record type constants */
	const RECORD_A = 'A';
	const RECORD_TXT = 'TXT';

	/** SSL hash prefix in TXT records */
	const SSL_PREFIX = 'ssl=';

	/** Maximum TXT record length per NS standard */
	const MAX_TXT_LENGTH = 256;

	/**
	 * Parse NS data from account json_metadata
	 *
	 * @param string|array $metadata Raw json_metadata string or decoded array
	 * @return array|false Parsed NS data ['ns' => [...], 'ttl' => int] or false if not found
	 */
	static function parse_ns_data($metadata) {
		if (is_string($metadata)) {
			$metadata = json_decode($metadata, true);
			if ($metadata === null) {
				return false;
			}
		}

		if (!is_array($metadata) || !isset($metadata['ns'])) {
			return false;
		}

		return [
			'ns' => $metadata['ns'],
			'ttl' => $metadata['ttl'] ?? self::DEFAULT_TTL
		];
	}

	/**
	 * Build NS metadata structure
	 *
	 * @param array $records Array of records [['A', '192.168.1.1'], ['TXT', 'ssl=hash...']]
	 * @param int $ttl Time-to-live in seconds
	 * @return array NS metadata structure ready for json_encode
	 */
	static function build_ns_data(array $records, int $ttl = self::DEFAULT_TTL): array {
		return [
			'ns' => $records,
			'ttl' => $ttl
		];
	}

	/**
	 * Merge NS data into existing metadata
	 *
	 * @param string|array $existing_metadata Existing json_metadata
	 * @param array $records NS records to set
	 * @param int $ttl TTL value
	 * @return array Updated metadata array
	 */
	static function merge_ns_into_metadata($existing_metadata, array $records, int $ttl = self::DEFAULT_TTL): array {
		if (is_string($existing_metadata)) {
			$metadata = json_decode($existing_metadata, true);
			if ($metadata === null) {
				$metadata = [];
			}
		} else {
			$metadata = $existing_metadata ?? [];
		}

		$metadata['ns'] = $records;
		$metadata['ttl'] = $ttl;

		return $metadata;
	}

	/**
	 * Remove NS data from metadata
	 *
	 * @param string|array $existing_metadata Existing json_metadata
	 * @return array Metadata array without NS fields
	 */
	static function remove_ns_from_metadata($existing_metadata): array {
		if (is_string($existing_metadata)) {
			$metadata = json_decode($existing_metadata, true);
			if ($metadata === null) {
				$metadata = [];
			}
		} else {
			$metadata = $existing_metadata ?? [];
		}

		unset($metadata['ns']);
		unset($metadata['ttl']);

		return $metadata;
	}

	/**
	 * Extract all A records (IPv4 addresses) from NS data
	 *
	 * @param array $ns_data NS data from parse_ns_data()
	 * @return array List of IPv4 addresses
	 */
	static function get_a_records(array $ns_data): array {
		$records = [];
		if (!isset($ns_data['ns'])) {
			return $records;
		}

		foreach ($ns_data['ns'] as $record) {
			if ($record[0] === self::RECORD_A) {
				$records[] = $record[1];
			}
		}

		return $records;
	}

	/**
	 * Extract SSL hash from TXT records
	 *
	 * @param array $ns_data NS data from parse_ns_data()
	 * @return string|null SSL hash or null if not found
	 */
	static function get_ssl_hash(array $ns_data): ?string {
		if (!isset($ns_data['ns'])) {
			return null;
		}

		foreach ($ns_data['ns'] as $record) {
			if ($record[0] === self::RECORD_TXT) {
				$parts = explode('=', $record[1], 2);
				if ($parts[0] === 'ssl' && isset($parts[1])) {
					return $parts[1];
				}
			}
		}

		return null;
	}

	/**
	 * Extract all TXT records from NS data
	 *
	 * @param array $ns_data NS data from parse_ns_data()
	 * @return array List of TXT record values
	 */
	static function get_txt_records(array $ns_data): array {
		$records = [];
		if (!isset($ns_data['ns'])) {
			return $records;
		}

		foreach ($ns_data['ns'] as $record) {
			if ($record[0] === self::RECORD_TXT) {
				$records[] = $record[1];
			}
		}

		return $records;
	}

	/**
	 * Create an A record tuple
	 *
	 * @param string $ipv4 IPv4 address
	 * @return array Record tuple ['A', 'ip']
	 */
	static function create_a_record(string $ipv4): array {
		return [self::RECORD_A, $ipv4];
	}

	/**
	 * Create a TXT record tuple for SSL hash
	 *
	 * @param string $hash SHA256 hash of SSL public key
	 * @return array Record tuple ['TXT', 'ssl=hash']
	 */
	static function create_ssl_record(string $hash): array {
		return [self::RECORD_TXT, self::SSL_PREFIX . $hash];
	}

	/**
	 * Create a generic TXT record tuple
	 *
	 * @param string $value TXT record value
	 * @return array Record tuple ['TXT', 'value']
	 */
	static function create_txt_record(string $value): array {
		return [self::RECORD_TXT, $value];
	}

	/**
	 * Validate IPv4 address format
	 *
	 * @param string $ip IP address to validate
	 * @return bool True if valid IPv4
	 */
	static function validate_ipv4(string $ip): bool {
		return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
	}

	/**
	 * Validate SSL hash format (64 hex characters for SHA256)
	 *
	 * @param string $hash Hash to validate
	 * @return bool True if valid SHA256 hash
	 */
	static function validate_ssl_hash(string $hash): bool {
		return preg_match('/^[a-f0-9]{64}$/i', $hash) === 1;
	}

	/**
	 * Validate TXT record length
	 *
	 * @param string $value TXT record value
	 * @return bool True if within length limit
	 */
	static function validate_txt_length(string $value): bool {
		return strlen($value) <= self::MAX_TXT_LENGTH;
	}

	/**
	 * Validate NS records array
	 *
	 * @param array $records Records to validate
	 * @return array ['valid' => bool, 'errors' => [...]]
	 */
	static function validate_records(array $records): array {
		$errors = [];

		foreach ($records as $index => $record) {
			if (!is_array($record) || count($record) !== 2) {
				$errors[] = "Record $index: Invalid format, expected [type, value]";
				continue;
			}

			$type = $record[0];
			$value = $record[1];

			if ($type === self::RECORD_A) {
				if (!self::validate_ipv4($value)) {
					$errors[] = "Record $index: Invalid IPv4 address '$value'";
				}
			} elseif ($type === self::RECORD_TXT) {
				if (!self::validate_txt_length($value)) {
					$errors[] = "Record $index: TXT record exceeds " . self::MAX_TXT_LENGTH . " characters";
				}
				// Validate SSL hash if it's an SSL record
				if (strpos($value, self::SSL_PREFIX) === 0) {
					$hash = substr($value, strlen(self::SSL_PREFIX));
					if (!self::validate_ssl_hash($hash)) {
						$errors[] = "Record $index: Invalid SSL hash format";
					}
				}
			} else {
				$errors[] = "Record $index: Unsupported record type '$type'";
			}
		}

		return [
			'valid' => empty($errors),
			'errors' => $errors
		];
	}

	/**
	 * Get SSL public key hash from a remote server
	 *
	 * @param string $domain Domain name (used for SNI)
	 * @param string|null $ipv4 IP address to connect to (optional, resolves domain if null)
	 * @param int $port HTTPS port (default 443)
	 * @param int $timeout Connection timeout in seconds
	 * @return array ['error' => string|false, 'result' => ['ipv4' => string, 'hash' => string]]
	 */
	static function get_ssl_hash_from_server(string $domain, ?string $ipv4 = null, int $port = 443, int $timeout = 3): array {
		// Resolve IP if not provided
		if ($ipv4 === null) {
			$ipv4 = gethostbyname($domain);
			if ($ipv4 === $domain) {
				return ['error' => 'dns_resolution_failed', 'result' => null];
			}
		}

		// Create SSL context
		$streamContext = stream_context_create([
			'ssl' => [
				'peer_name' => $domain,
				'verify_peer' => false,
				'verify_peer_name' => false,
				'capture_peer_cert' => true,
			],
		]);

		// Connect to server
		$errorNumber = 0;
		$errorDescription = '';
		$client = @stream_socket_client(
			'ssl://' . $ipv4 . ':' . $port,
			$errorNumber,
			$errorDescription,
			$timeout,
			STREAM_CLIENT_CONNECT,
			$streamContext
		);

		if ($client === false || $errorNumber !== 0) {
			return [
				'error' => 'connection_failed: ' . $errorDescription,
				'result' => null
			];
		}

		$response = stream_context_get_params($client);

		if (!isset($response['options']['ssl']['peer_certificate'])) {
			fclose($client);
			return ['error' => 'no_certificate', 'result' => null];
		}

		// Extract public key and compute hash
		$public_key = openssl_pkey_get_public($response['options']['ssl']['peer_certificate']);
		if ($public_key === false) {
			fclose($client);
			return ['error' => 'invalid_certificate', 'result' => null];
		}

		$public_key_data = openssl_pkey_get_details($public_key);
		if ($public_key_data === false) {
			fclose($client);
			return ['error' => 'cannot_extract_public_key', 'result' => null];
		}

		// Hash the full PEM-encoded public key (including headers)
		$hash = hash('sha256', $public_key_data['key'], false);

		fclose($client);

		return [
			'error' => false,
			'result' => [
				'ipv4' => $ipv4,
				'hash' => $hash
			]
		];
	}

	/**
	 * Verify SSL certificate against VIZ blockchain records
	 *
	 * @param JsonRPC $api JsonRPC API instance
	 * @param string $account VIZ account name (domain)
	 * @param string|null $ipv4 Server IP address (optional, uses A record if null)
	 * @return array ['valid' => bool, 'error' => string|null, 'expected' => string|null, 'actual' => string|null]
	 */
	static function verify_ssl(JsonRPC $api, string $account, ?string $ipv4 = null): array {
		// Get account metadata from blockchain
		$accounts = $api->execute_method('get_accounts', [[$account]]);
		if (!$accounts || count($accounts) === 0) {
			return [
				'valid' => false,
				'error' => 'account_not_found',
				'expected' => null,
				'actual' => null
			];
		}

		$ns_data = self::parse_ns_data($accounts[0]['json_metadata']);
		if ($ns_data === false) {
			return [
				'valid' => false,
				'error' => 'no_ns_data',
				'expected' => null,
				'actual' => null
			];
		}

		// Get expected SSL hash from TXT record
		$expected_hash = self::get_ssl_hash($ns_data);
		if ($expected_hash === null) {
			return [
				'valid' => false,
				'error' => 'no_ssl_record',
				'expected' => null,
				'actual' => null
			];
		}

		// Get IP from A record if not provided
		if ($ipv4 === null) {
			$a_records = self::get_a_records($ns_data);
			if (empty($a_records)) {
				return [
					'valid' => false,
					'error' => 'no_a_record',
					'expected' => $expected_hash,
					'actual' => null
				];
			}
			$ipv4 = $a_records[0];
		}

		// Get actual SSL hash from server
		$ssl_result = self::get_ssl_hash_from_server($account, $ipv4);
		if ($ssl_result['error'] !== false) {
			return [
				'valid' => false,
				'error' => $ssl_result['error'],
				'expected' => $expected_hash,
				'actual' => null
			];
		}

		$actual_hash = $ssl_result['result']['hash'];

		// Compare hashes (timing-safe comparison)
		return [
			'valid' => hash_equals($expected_hash, $actual_hash),
			'error' => null,
			'expected' => $expected_hash,
			'actual' => $actual_hash
		];
	}

	/**
	 * Get NS records from a VIZ account
	 *
	 * @param JsonRPC $api JsonRPC API instance
	 * @param string $account VIZ account name
	 * @return array|false NS data or false if not found
	 */
	static function get_account_ns(JsonRPC $api, string $account) {
		$accounts = $api->execute_method('get_accounts', [[$account]]);
		if (!$accounts || count($accounts) === 0) {
			return false;
		}

		return self::parse_ns_data($accounts[0]['json_metadata']);
	}

	/**
	 * Prepare metadata JSON string for account_metadata operation
	 *
	 * @param array $records NS records array
	 * @param int $ttl TTL value
	 * @param string|array|null $existing_metadata Existing metadata to merge with
	 * @return string Escaped JSON string for account_metadata operation
	 */
	static function prepare_metadata_json(array $records, int $ttl = self::DEFAULT_TTL, $existing_metadata = null): string {
		if ($existing_metadata !== null) {
			$metadata = self::merge_ns_into_metadata($existing_metadata, $records, $ttl);
		} else {
			$metadata = self::build_ns_data($records, $ttl);
		}

		return addslashes(json_encode($metadata));
	}

	/**
	 * Build simple NS configuration with single A record and optional SSL
	 *
	 * @param string $ipv4 IPv4 address
	 * @param string|null $ssl_hash Optional SSL hash
	 * @param int $ttl TTL value
	 * @return array NS data structure
	 */
	static function build_simple_ns(string $ipv4, ?string $ssl_hash = null, int $ttl = self::DEFAULT_TTL): array {
		$records = [self::create_a_record($ipv4)];

		if ($ssl_hash !== null) {
			$records[] = self::create_ssl_record($ssl_hash);
		}

		return self::build_ns_data($records, $ttl);
	}

	/**
	 * Build Round Robin NS configuration with multiple A records
	 *
	 * @param array $ipv4_list Array of IPv4 addresses
	 * @param string|null $ssl_hash Optional SSL hash (shared by all servers)
	 * @param int $ttl TTL value
	 * @return array NS data structure
	 */
	static function build_round_robin_ns(array $ipv4_list, ?string $ssl_hash = null, int $ttl = self::DEFAULT_TTL): array {
		$records = [];

		foreach ($ipv4_list as $ipv4) {
			$records[] = self::create_a_record($ipv4);
		}

		if ($ssl_hash !== null) {
			$records[] = self::create_ssl_record($ssl_hash);
		}

		return self::build_ns_data($records, $ttl);
	}
}
