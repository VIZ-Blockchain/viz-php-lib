<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include('./class/autoloader.php');

/**
 * Offline tests for the HF15 agent branch of VIZ\Auth (domain auth).
 *
 * `check()` itself needs a real signature, so these drive `check_agent()` — the decision that
 * differs — with a stubbed node. The stub is a plain object, not a JsonRPC: the class only ever
 * calls execute_method() on it, so duck typing keeps this honest without a mock library.
 */
final class TestAuth extends TestCase
{
    const KEY = 'VIZ7iiYUk6hFuieveBJcTVTV33aNuC2vZauXuYc9Hki3moxsm9WXe';

    private function row(array $overrides = []): array
    {
        return array_merge([
            'account' => 'alice',
            'agent_name' => 'shop',
            'agent_key' => self::KEY,
            'operations' => ['transfer'],
            'expiration' => '1970-01-01T00:00:00',
            'addons' => ['wallet'],
            'expired' => false,
        ], $overrides);
    }

    /** A VIZ\Auth whose agent lookups come back from $rows (false = a node that has no HF15 at all). */
    private function auth($rows, $authority = 'regular', $addon = 'wallet'): VIZ\Auth
    {
        $auth = new VIZ\Auth('http://node.invalid', 'wallet.viz.world', 'auth', $authority, 60, $addon);
        $auth->agent->jsonrpc = new class($rows) {
            private $rows;
            public function __construct($rows) { $this->rows = $rows; }
            public function execute_method($method, $params)
            {
                return $method === 'get_agent_permissions' ? $this->rows : false;
            }
        };
        return $auth;
    }

    public function testLiveAgentScopedToOurAddonIsAccepted(): void
    {
        $this->assertTrue($this->auth([$this->row()])->check_agent('alice', self::KEY));
    }

    public function testAgentScopedToAnotherServiceIsRefused(): void
    {
        //the addon is the grant: an agent of the same principal but a different service must not pass
        $rows = [$this->row(['addons' => ['exchange']])];
        $this->assertFalse($this->auth($rows)->check_agent('alice', self::KEY));
    }

    public function testAddonMatchIsCaseSensitive(): void
    {
        //addons are opaque strings the chain does not normalise; matching loosely would widen the grant
        $rows = [$this->row(['addons' => ['Wallet']])];
        $this->assertFalse($this->auth($rows)->check_agent('alice', self::KEY));
    }

    public function testNoAddonConfiguredKeepsTheOldBehaviour(): void
    {
        $this->assertFalse($this->auth([$this->row()], 'regular', null)->check_agent('alice', self::KEY));
        $this->assertFalse($this->auth([$this->row()], 'regular', '')->check_agent('alice', self::KEY));
    }

    public function testMasterIsNeverAnsweredByAnAgent(): void
    {
        $this->assertFalse($this->auth([$this->row()], 'master')->check_agent('alice', self::KEY));
    }

    public function testExpiredAgentIsRefused(): void
    {
        $this->assertFalse($this->auth([$this->row(['expired' => true])])->check_agent('alice', self::KEY));
    }

    public function testPoisonedAgentIsRefusedEvenThoughItsAddonMatches(): void
    {
        //the node refuses such a row whole, so it must not carry identity either
        $rows = [$this->row(['operations' => ['transfer', 'account_update']])];
        $this->assertFalse($this->auth($rows)->check_agent('alice', self::KEY));
    }

    public function testKeyThatIsNotAnAgentIsRefused(): void
    {
        $rows = [$this->row(['agent_key' => 'VIZ7FzBqZJmTdve9iuHXWvB3Ncu7VeJWKwpA1FpbLi4X4ZdsVAYjC'])];
        $this->assertFalse($this->auth($rows)->check_agent('alice', self::KEY));
    }

    public function testNodeWithoutHf15RefusesQuietly(): void
    {
        //unknown method -> not an array -> "no agents", never an exception
        $this->assertFalse($this->auth(false)->check_agent('alice', self::KEY));
    }
}
