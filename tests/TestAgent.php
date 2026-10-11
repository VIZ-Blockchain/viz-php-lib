<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include('./class/autoloader.php');

/**
 * Offline tests for the HF15 agent helpers. The decision rules are pure functions on purpose:
 * they mirror the node's agent_evaluator.cpp::usable_agent_row and must be testable without a node.
 */
final class TestAgent extends TestCase
{
    private function row(array $overrides = []): array
    {
        return array_merge([
            'account' => 'alice',
            'agent_name' => 'shop',
            'agent_key' => 'VIZ7iiYUk6hFuieveBJcTVTV33aNuC2vZauXuYc9Hki3moxsm9WXe',
            'operations' => ['transfer'],
            'expiration' => '1970-01-01T00:00:00',
            'addons' => ['wallet'],
            'expired' => false,
        ], $overrides);
    }

    public function testEpochIsPerpetualNotExpired(): void
    {
        $this->assertFalse(VIZ\Agent::is_expired($this->row()));
    }

    public function testPastExpirationIsExpiredWhenNodeSentNoFlag(): void
    {
        //the timestamp path is the fallback for a node that does not compute `expired`
        $this->assertTrue(VIZ\Agent::is_expired($this->row(['expiration' => '2020-01-01T00:00:00', 'expired' => null])));
    }

    public function testFutureExpirationIsLive(): void
    {
        $this->assertFalse(VIZ\Agent::is_expired($this->row(['expiration' => '2099-01-01T00:00:00'])));
    }

    public function testNodeExpiredFlagWins(): void
    {
        //the node computes the flag at head block time; it outranks the client clock
        $this->assertTrue(VIZ\Agent::is_expired($this->row(['expiration' => '2099-01-01T00:00:00', 'expired' => true])));
    }

    public function testGrantsOnlyListedOperations(): void
    {
        $row = $this->row();
        $this->assertTrue(VIZ\Agent::grants($row, 'transfer'));
        $this->assertFalse(VIZ\Agent::grants($row, 'pm_place_bet'));
    }

    public function testRowHoldingNeverDelegableNameGrantsNothing(): void
    {
        //a row poisoned with a never-delegable name is unusable whole, not just for that name
        $row = $this->row(['operations' => ['transfer', 'account_update']]);
        $this->assertFalse(VIZ\Agent::grants($row, 'transfer'));
    }

    public function testAuthorityWeightMeetsThreshold(): void
    {
        $key = 'VIZ7iiYUk6hFuieveBJcTVTV33aNuC2vZauXuYc9Hki3moxsm9WXe';
        //this key alone meets the threshold
        $authority = [
            'weight_threshold' => 2,
            'key_auths' => [
                [$key, 2],
                ['VIZ7FzBqZJmTdve9iuHXWvB3Ncu7VeJWKwpA1FpbLi4X4ZdsVAYjC', 1],
            ],
        ];
        $this->assertTrue(VIZ\Agent::in_authority($authority, $key));
        //one key of weight 1 does not reach a threshold of 2 — a member is not "in" by mere presence
        $this->assertFalse(VIZ\Agent::in_authority(['weight_threshold' => 2, 'key_auths' => [[$key, 1]]], $key));
        $this->assertFalse(VIZ\Agent::in_authority(false, $key));
    }

    public function testKeyEqualsToleratesSpelling(): void
    {
        $key = 'VIZ7iiYUk6hFuieveBJcTVTV33aNuC2vZauXuYc9Hki3moxsm9WXe';
        $this->assertTrue(VIZ\Agent::key_equals($key, ' '.$key.' '));
        $this->assertTrue(VIZ\Agent::key_equals($key, substr($key, 3)));
        $this->assertFalse(VIZ\Agent::key_equals($key, 'VIZ7FzBqZJmTdve9iuHXWvB3Ncu7VeJWKwpA1FpbLi4X4ZdsVAYjC'));
    }

    public function testNormalizeKeyReturnsCanonicalForm(): void
    {
        $key = 'VIZ7iiYUk6hFuieveBJcTVTV33aNuC2vZauXuYc9Hki3moxsm9WXe';
        $this->assertSame($key, VIZ\Agent::normalize_key(substr($key, 3)));
        $this->assertSame($key, VIZ\Agent::normalize_key($key));
        //an unparsable value is returned trimmed, not dropped: the caller can still compare it verbatim
        $this->assertSame('not-a-key', VIZ\Agent::normalize_key(' not-a-key '));
    }
}
