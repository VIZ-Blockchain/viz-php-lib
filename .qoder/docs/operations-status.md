# VIZ PHP Library — Operations Implementation Status

**Status Date:** 02.07.2026
**Reference:** viz-cpp-node documentation + prediction-market-library-integration-spec (HF14, Onix)

---

## Summary

| Category | Total | Implemented | Missing | Deprecated |
|----------|-------|-------------|---------|------------|
| Account Operations | 3 | 3 | 0 | 0 |
| Account Market Operations | 4 | 4 | 0 | 0 |
| Award Operations | 2 | 2 | 0 | 0 |
| Committee Operations | 3 | 3 | 0 | 0 |
| Content Operations | 4 | 1 | 0 | 3 |
| Escrow Operations | 4 | 4 | 0 | 0 |
| Invite Operations | 4 | 4 | 0 | 0 |
| Proposal Operations | 3 | 3 | 0 | 0 |
| Recovery Operations | 3 | 3 | 0 | 0 |
| Subscription Operations | 2 | 2 | 0 | 0 |
| Transfer & Vesting Operations | 5 | 5 | 0 | 0 |
| Witness / Validator Operations | 6 | 6 | 0 | 0 |
| Prediction Market Operations (HF14) | 23 | 23 | 0 | 0 |
| **TOTAL** | **66** | **63** | **0** | **3** |

**Coverage:** 100% of non-deprecated operations implemented

> Virtual operations (chain-generated, decode-only — never built/signed) are not counted above.
> See [Virtual Operations](#virtual-operations-decode-only) for the HF14 PM vops and `stakeholder_reward`.

---

## Detailed Status Table

| ID | Operation Name | PHP Method | Status | Notes |
|----|----------------|------------|--------|-------|
| **Account Operations** |||||
| 20 | `account_create_operation` | `build_account_create()` | ✅ Implemented | |
| 5 | `account_update_operation` | `build_account_update()` | ✅ Implemented | |
| 21 | `account_metadata_operation` | `build_account_metadata()` | ✅ Implemented | |
| **Account Market Operations** |||||
| 54 | `set_account_price_operation` | `build_set_account_price()` | ✅ Implemented | |
| 55 | `set_subaccount_price_operation` | `build_set_subaccount_price()` | ✅ Implemented | |
| 56 | `buy_account_operation` | `build_buy_account()` | ✅ Implemented | |
| 61 | `target_account_sale_operation` | `build_target_account_sale()` | ✅ Implemented | HF11 |
| **Award Operations** |||||
| 47 | `award_operation` | `build_award()` | ✅ Implemented | |
| 60 | `fixed_award_operation` | `build_fixed_award()` | ✅ Implemented | HF11 |
| **Committee Operations** |||||
| 35 | `committee_worker_create_request_operation` | `build_committee_worker_create_request()` | ✅ Implemented | |
| 36 | `committee_worker_cancel_request_operation` | `build_committee_worker_cancel_request()` | ✅ Implemented | |
| 37 | `committee_vote_request_operation` | `build_committee_vote_request()` | ✅ Implemented | |
| **Content & Custom Operations** |||||
| 0 | `vote_operation` | — | ⚠️ Deprecated | Not implementing |
| 1 | `content_operation` | — | ⚠️ Deprecated | Not implementing |
| 9 | `delete_content_operation` | — | ⚠️ Deprecated | Not implementing |
| 10 | `custom_operation` | `build_custom()` | ✅ Implemented | |
| **Escrow Operations** |||||
| 15 | `escrow_transfer_operation` | `build_escrow_transfer()` | ✅ Implemented | |
| 16 | `escrow_dispute_operation` | `build_escrow_dispute()` | ✅ Implemented | |
| 17 | `escrow_release_operation` | `build_escrow_release()` | ✅ Implemented | |
| 18 | `escrow_approve_operation` | `build_escrow_approve()` | ✅ Implemented | |
| **Invite Operations** |||||
| 43 | `create_invite_operation` | `build_create_invite()` | ✅ Implemented | |
| 44 | `claim_invite_balance_operation` | `build_claim_invite_balance()` | ✅ Implemented | |
| 45 | `invite_registration_operation` | `build_invite_registration()` | ✅ Implemented | |
| 58 | `use_invite_balance_operation` | `build_use_invite_balance()` | ✅ Implemented | |
| **Proposal Operations** |||||
| 22 | `proposal_create_operation` | `build_proposal_create()` | ✅ Implemented | |
| 23 | `proposal_update_operation` | `build_proposal_update()` | ✅ Implemented | |
| 24 | `proposal_delete_operation` | `build_proposal_delete()` | ✅ Implemented | |
| **Recovery Operations** |||||
| 12 | `request_account_recovery_operation` | `build_request_account_recovery()` | ✅ Implemented | |
| 13 | `recover_account_operation` | `build_recover_account()` | ✅ Implemented | |
| 14 | `change_recovery_account_operation` | `build_change_recovery_account()` | ✅ Implemented | |
| **Subscription Operations** |||||
| 50 | `set_paid_subscription_operation` | `build_set_paid_subscription()` | ✅ Implemented | |
| 51 | `paid_subscribe_operation` | `build_paid_subscribe()` | ✅ Implemented | |
| **Transfer & Vesting Operations** |||||
| 2 | `transfer_operation` | `build_transfer()` | ✅ Implemented | |
| 3 | `transfer_to_vesting_operation` | `build_transfer_to_vesting()` | ✅ Implemented | |
| 4 | `withdraw_vesting_operation` | `build_withdraw_vesting()` | ✅ Implemented | |
| 11 | `set_withdraw_vesting_route_operation` | `build_set_withdraw_vesting_route()` | ✅ Implemented | |
| 19 | `delegate_vesting_shares_operation` | `build_delegate_vesting_shares()` | ✅ Implemented | |
| **Witness / Validator Operations** |||||
| 6 | `witness_update_operation` | `build_witness_update()` | ✅ Implemented | Alias: `build_validator_update()` emits `validator_update` |
| 6 | `validator_update_operation` | `build_validator_update()` | ✅ Implemented | New name for type 6, same binary format |
| 7 | `account_witness_vote_operation` | `build_account_witness_vote()` | ✅ Implemented | Alias: `build_account_validator_vote()` emits `account_validator_vote` |
| 7 | `account_validator_vote_operation` | `build_account_validator_vote()` | ✅ Implemented | New name for type 7, same binary format |
| 8 | `account_witness_proxy_operation` | `build_account_witness_proxy()` | ✅ Implemented | Alias: `build_account_validator_proxy()` emits `account_validator_proxy` |
| 8 | `account_validator_proxy_operation` | `build_account_validator_proxy()` | ✅ Implemented | New name for type 8, same binary format |
| 25 | `chain_properties_update_operation` | `build_chain_properties_update()` | ✅ Implemented | Added 03.03.2026 |
| 46 | `versioned_chain_properties_update_operation` | `build_versioned_chain_properties_update()` | ✅ Implemented | Updated to v4 (HF13) |
| 64 | `set_reward_sharing_operation` | `build_set_reward_sharing()` | ✅ Implemented | HF13 |
| **Prediction Market Operations (HF14, Onix)** |||||
| 66 | `pm_oracle_register_operation` | `build_pm_oracle_register()` | ✅ Implemented | active(owner) |
| 67 | `pm_oracle_update_operation` | `build_pm_oracle_update()` | ✅ Implemented | active(owner); optional fields = `null` |
| 68 | `pm_create_market_operation` | `build_pm_create_market()` | ✅ Implemented | active(creator) |
| 69 | `pm_oracle_accept_market_operation` | `build_pm_oracle_accept_market()` | ✅ Implemented | active(oracle) |
| 70 | `pm_place_bet_operation` | `build_pm_place_bet()` | ✅ Implemented | active(account) |
| 71 | `pm_commit_bet_operation` | `build_pm_commit_bet()` | ✅ Implemented | active(account); see `pm_commitment()` |
| 72 | `pm_reveal_bet_operation` | `build_pm_reveal_bet()` | ✅ Implemented | active(account) |
| 73 | `pm_cancel_bet_operation` | `build_pm_cancel_bet()` | ✅ Implemented | active(account) |
| 74 | `pm_add_liquidity_operation` | `build_pm_add_liquidity()` | ✅ Implemented | active(provider) |
| 75 | `pm_withdraw_liquidity_operation` | `build_pm_withdraw_liquidity()` | ✅ Implemented | active(provider) |
| 76 | `pm_resolve_market_operation` | `build_pm_resolve_market()` | ✅ Implemented | active(oracle) |
| 77 | `pm_no_contest_operation` | `build_pm_no_contest()` | ✅ Implemented | active(oracle) |
| 78 | `pm_dispute_create_operation` | `build_pm_dispute_create()` | ✅ Implemented | active(disputer) |
| 79 | `pm_dispute_vote_operation` | `build_pm_dispute_vote()` | ✅ Implemented | **regular**(voter) |
| 80 | `pm_dispute_resolve_operation` | `build_pm_dispute_resolve()` | ✅ Implemented | active(resolver) |
| 81 | `pm_transfer_position_operation` | `build_pm_transfer_position()` | ✅ Implemented | active(from) |
| 82 | `pm_lazy_deposit_operation` | `build_pm_lazy_deposit()` | ✅ Implemented | active(account) |
| 83 | `pm_lazy_withdraw_operation` | `build_pm_lazy_withdraw()` | ✅ Implemented | active(account) |
| 91 | `pm_leverage_open_operation` | `build_pm_leverage_open()` | ✅ Implemented | active(account); gated by `pm_leverage_enabled` |
| 92 | `pm_leverage_close_operation` | `build_pm_leverage_close()` | ✅ Implemented | active(account) |
| 93 | `pm_leverage_convert_operation` | `build_pm_leverage_convert()` | ✅ Implemented | active(account) |
| 98 | `pm_dispute_oracle_respond_operation` | `build_pm_dispute_oracle_respond()` | ✅ Implemented | active(oracle) |
| 99 | `pm_unban_operation` | `build_pm_unban()` | ✅ Implemented | active(resolver) |

> PM op-ids are **not contiguous** — 84–90, 94–97 and 100 are virtual ops interleaved in the
> `operation` variant (see below). Every PM user op ends with an empty `extensions` vector and is
> serialized by JSON name (`["pm_place_bet", {...}]`); percent fields are basis points unless noted.

---

## Virtual Operations (decode-only)

Virtual operations are emitted by the node (block/account history) and **cannot be built or signed** —
the library only decodes them from `get_account_history` / `get_ops_in_block` (returned already-named
in JSON, no builder or decoder registry needed).

| ID | Operation Name | Emitted when |
|----|----------------|--------------|
| 65 | `stakeholder_reward` | HF13 epoch payout: validator with non-zero `sharing_rate` pays a voter their `shares` (SHARES asset, precision 6) |
| 84 | `pm_batch_settle` | Epoch boundary: queued/revealed bets settle at a uniform price |
| 85 | `pm_commit_forfeit` | An unrevealed commitment forfeits its penalty into `forfeit_pool` |
| 86 | `pm_auto_payout` | Deferred market-level payout marker after the dispute grace elapses |
| 87 | `pm_dispute_finalize` | Committee-mode tally finalized at `voting_end_time` |
| 88 | `pm_dispute_auto_close` | Anti-freeze: dispute unresolved at `auto_close_time` → full refund |
| 89 | `pm_oracle_missed_penalty` | Oracle missed `result_expiration` → insurance slashed, refund all |
| 90 | `pm_lazy_recall` | Lazy-pool graduated recall step (idle allocation returned) |
| 94 | `pm_leverage_liquidate` | A leveraged position force-closed (opposing bet / cancel / expiration) |
| 95 | `pm_leverage_resolve` | A leveraged position settled at market resolution |
| 96 | `pm_market_accepted` | Oracle accepted (or self-oracle auto-accepted); terms frozen |
| 97 | `pm_payout` | Per-bettor parimutuel settlement (one per active bet inside settle) |
| 100 | `pm_ban_expired` | A temporary oracle/creator ban lapsed at `banned_until`; cron cleared it |
| 101 | `pm_market_expired` | Pending market not accepted within `pm_oracle_accept_window_sec`; cron voids it, refunds the creator's seed liquidity (**creation fee forfeited**). Fields: `oracle`, `creator`, `market_id` (int64), `refunded_liquidity` (asset) |

> Also chain-generated (pre-HF14): `shutdown_validator` (30), `validator_reward` (42).
>
> A market **rejected** by a signed `pm_oracle_accept_market accept=false` emits **no** vop —
> detect it via `status = -1`. Only the accept-window timeout emits `pm_market_expired`.

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Fully implemented |
| ⚠️ | Deprecated (not implementing by design) |
| ❌ | Missing (needs implementation) |

---

## Recently Added (02.07.2026)

- **PM spec delta (2026-07)** — decode/read-only, no signed op added or changed:
  - New virtual op `pm_market_expired` (op-id 101) — oracle-accept-window timeout; documented in the
    Virtual Operations table (decode from history, no builder).
  - Two new `get_pm_chain_properties` fields: `pm_oracle_accept_window_sec` (uint32 sec) and
    `pm_lazy_min_liquidity_fee_percent` (uint16 bp), plus the read-only `pm_market_object.accept_deadline`
    field. Relayed dynamically — no library code change required.
- **Prediction Markets (HF14, Onix)** — all 23 signed PM operations (IDs 66–83, 91–93, 98–99):
  `build_pm_oracle_register()` … `build_pm_unban()`. See the PM section of the detailed table.
- `pm_commitment()` helper — builds the byte-exact SHA-256 commit-reveal commitment (spec §3.6.1),
  verified against the spec golden test vector.
- New binary codecs on `Transaction`: `encode_int8`, `encode_int64` (LE), `encode_sha256`,
  `encode_optional` (fc `optional<T>`), `json_string`.
- 12 PM virtual ops + `stakeholder_reward` (ID 65) documented as decode-only.

## Recently Added (16.05.2026)

- `set_reward_sharing_operation` (ID 64) — HF13 validator stakeholder reward sharing rate
- `versioned_chain_properties_update_operation` updated to v4 (HF13) — adds `distribution_epoch_length`

## Added (03.03.2026)

- `chain_properties_update_operation` (ID 25) — basic chain properties update for witnesses

---

## Deprecated Operations (Not Implemented)

These operations are marked as deprecated in viz-cpp-node and should not be used in new code:

1. **vote_operation** (ID 0) — Legacy content voting
2. **content_operation** (ID 1) — Legacy content creation
3. **delete_content_operation** (ID 9) — Legacy content deletion

---

## Usage Examples

### Using chain_properties_update

```php
$tx = new \VIZ\Transaction('https://node.viz.cx', $private_key);
$result = $tx->chain_properties_update(
    'witness_account',
    [
        'account_creation_fee' => '1.000 VIZ',
        'maximum_block_size' => 65536,
        'min_delegation' => '1.000 VIZ',
    ]
);
$tx->execute($result['json']);
```

### Using versioned_chain_properties_update (recommended)

```php
$tx = new \VIZ\Transaction('https://node.viz.cx', $private_key);
$result = $tx->versioned_chain_properties_update(
    'witness_account',
    [
        'account_creation_fee' => '1.000 VIZ',
        'maximum_block_size' => 65536,
        'withdraw_intervals' => 28,
    ]
);
$tx->execute($result['json']);
```

---

## Notes

- All operations support queue mode via `start_queue()` / `end_queue()`
- Transaction signing supports multiple private keys via `add_private_key()`
- The library uses the default VIZ mainnet chain_id

---

# JSON-RPC API Coverage

**Status Date:** 02.07.2026
**Reference:** viz-cpp-node plugins documentation + prediction_market_api (HF14, Onix)

---

## API Summary

| plugin | Total Methods | Implemented | Status |
|--------|---------------|-------------|--------|
| database_api | 31 | 31 | ✅ Complete |
| network_broadcast_api | 4 | 4 | ✅ Complete |
| validator_api / witness_api | 16 | 16 | ✅ Complete |
| account_by_key | 1 | 1 | ✅ Complete |
| account_history | 1 | 1 | ✅ Complete |
| operation_history | 2 | 2 | ✅ Complete |
| committee_api | 3 | 3 | ✅ Complete |
| invite_api | 3 | 3 | ✅ Complete |
| paid_subscription_api | 5 | 5 | ✅ Complete |
| custom_protocol_api | 1 | 1 | ✅ Complete |
| block_info | 2 | 2 | ✅ Complete |
| raw_block | 1 | 1 | ✅ Complete |
| auth_util | 1 | 1 | ✅ Complete |
| prediction_market_api (HF14) | 29 | 29 | ✅ Complete |
| follow | 9 | 0 | ⚠️ Deprecated |
| tags | 15 | 0 | ⚠️ Deprecated |
| social_network | 12 | 0 | ⚠️ Deprecated |
| private_message | 2 | 0 | ⚠️ Deprecated |
| debug_node | 8 | 0 | 🔧 Dev only |
| **TOTAL (non-deprecated)** | **108** | **108** | **100%** |

---

## Detailed API Status

### database_api (31 methods) ✅

| Method | Status |
|--------|--------|
| `get_block_header` | ✅ |
| `get_block` | ✅ |
| `get_irreversible_block_header` | ✅ |
| `get_irreversible_block` | ✅ |
| `set_block_applied_callback` | ✅ |
| `get_config` | ✅ |
| `get_dynamic_global_properties` | ✅ |
| `get_chain_properties` | ✅ |
| `get_hardfork_version` | ✅ |
| `get_next_scheduled_hardfork` | ✅ |
| `get_accounts` | ✅ |
| `lookup_account_names` | ✅ |
| `lookup_accounts` | ✅ |
| `get_account_count` | ✅ |
| `get_master_history` | ✅ Added 03.03.2026 |
| `get_owner_history` | ✅ |
| `get_recovery_request` | ✅ |
| `get_escrow` | ✅ |
| `get_withdraw_routes` | ✅ |
| `get_vesting_delegations` | ✅ |
| `get_expiring_vesting_delegations` | ✅ |
| `get_transaction_hex` | ✅ |
| `get_required_signatures` | ✅ |
| `get_potential_signatures` | ✅ |
| `verify_authority` | ✅ |
| `verify_account_authority` | ✅ |
| `get_database_info` | ✅ |
| `get_proposed_transactions` | ✅ |
| `get_proposed_transaction` | ✅ |
| `get_accounts_on_sale` | ✅ |
| `get_accounts_on_auction` | ✅ |
| `get_subaccounts_on_sale` | ✅ |

### network_broadcast_api (4 methods) ✅

| Method | Status |
|--------|--------|
| `broadcast_transaction` | ✅ |
| `broadcast_transaction_synchronous` | ✅ |
| `broadcast_transaction_with_callback` | ✅ |
| `broadcast_block` | ✅ |

### validator_api / witness_api (16 methods) ✅

All validator and witness method names route to the `validator_api` plugin on new nodes. When the `validator_api` plugin is unavailable (older nodes), the library automatically falls back to the `witness_api` plugin with the corresponding old method name.

| Method | Plugin Sent | Fallback |
|--------|-------------|----------|
| `get_active_validators` | `validator_api` | → `witness_api`.`get_active_witnesses` |
| `get_active_witnesses` | `validator_api` | → `witness_api`.`get_active_witnesses` |
| `get_validator_by_account` | `validator_api` | → `witness_api`.`get_witness_by_account` |
| `get_witness_by_account` | `validator_api` | → `witness_api`.`get_witness_by_account` |
| `get_validator_count` | `validator_api` | → `witness_api`.`get_witness_count` |
| `get_witness_count` | `validator_api` | → `witness_api`.`get_witness_count` |
| `get_validator_schedule` | `validator_api` | → `witness_api`.`get_witness_schedule` |
| `get_witness_schedule` | `validator_api` | → `witness_api`.`get_witness_schedule` |
| `get_validators` | `validator_api` | → `witness_api`.`get_witnesses` |
| `get_witnesses` | `validator_api` | → `witness_api`.`get_witnesses` |
| `get_validators_by_counted_vote` | `validator_api` | → `witness_api`.`get_witnesses_by_counted_vote` |
| `get_witnesses_by_counted_vote` | `validator_api` | → `witness_api`.`get_witnesses_by_counted_vote` |
| `get_validators_by_vote` | `validator_api` | → `witness_api`.`get_witnesses_by_vote` |
| `get_witnesses_by_vote` | `validator_api` | → `witness_api`.`get_witnesses_by_vote` |
| `lookup_validator_accounts` | `validator_api` | → `witness_api`.`lookup_witness_accounts` |
| `lookup_witness_accounts` | `validator_api` | → `witness_api`.`lookup_witness_accounts` |

### account_by_key (1 method) ✅

| Method | Status |
|--------|--------|
| `get_key_references` | ✅ |

### account_history (1 method) ✅

| Method | Status |
|--------|--------|
| `get_account_history` | ✅ |

### operation_history (2 methods) ✅

| Method | Status |
|--------|--------|
| `get_ops_in_block` | ✅ |
| `get_transaction` | ✅ |

### committee_api (3 methods) ✅

| Method | Status |
|--------|--------|
| `get_committee_request` | ✅ |
| `get_committee_request_votes` | ✅ |
| `get_committee_requests_list` | ✅ |

### invite_api (3 methods) ✅

| Method | Status |
|--------|--------|
| `get_invites_list` | ✅ |
| `get_invite_by_id` | ✅ |
| `get_invite_by_key` | ✅ |

### paid_subscription_api (5 methods) ✅

| Method | Status |
|--------|--------|
| `get_paid_subscriptions` | ✅ |
| `get_paid_subscription_options` | ✅ |
| `get_paid_subscription_status` | ✅ |
| `get_active_paid_subscriptions` | ✅ |
| `get_inactive_paid_subscriptions` | ✅ |

### custom_protocol_api (1 method) ✅

| Method | Status |
|--------|--------|
| `get_account` | ✅ |

### block_info (2 methods) ✅

| Method | Status |
|--------|--------|
| `get_block_info` | ✅ Added 03.03.2026 |
| `get_blocks_with_info` | ✅ Added 03.03.2026 |

### raw_block (1 method) ✅

| Method | Status |
|--------|--------|
| `get_raw_block` | ✅ Added 03.03.2026 |

---

### auth_util (1 method) ✅

| Method | Status |
|--------|--------|
| `check_authority_signature` | ✅ Added 03.03.2026 |

---

### prediction_market_api (29 methods) ✅

All PM read methods route to the `prediction_market_api` plugin. Pagination is uniformly
`(…key…, from, limit)` with `limit ≤ 1000`. Call via `execute_method('<method>', [args])`.

| Method | Status | Returns |
|--------|--------|---------|
| `get_market` | ✅ Added 02.07.2026 | `pm_market_object` |
| `list_markets` | ✅ Added 02.07.2026 | `pm_market_object[]` (`status, from, limit, [show_risky]`) |
| `list_markets_by_oracle` | ✅ Added 02.07.2026 | `pm_market_object[]` |
| `list_markets_by_creator` | ✅ Added 02.07.2026 | `pm_market_object[]` |
| `get_market_outcomes` | ✅ Added 02.07.2026 | `pm_outcome_object[]` |
| `get_market_weight_sums` | ✅ Added 02.07.2026 | `pm_market_weight_sums_api_object` |
| `get_market_bets` | ✅ Added 02.07.2026 | `pm_bet_object[]` |
| `get_account_positions` | ✅ Added 02.07.2026 | `pm_position_api_object[]` |
| `get_market_liquidity` | ✅ Added 02.07.2026 | `pm_liquidity_object[]` |
| `get_market_full` | ✅ Added 02.07.2026 | `pm_market_full_api_object` (`market_id, [account]`) |
| `get_account_leverage_positions` | ✅ Added 02.07.2026 | `pm_leverage_position_object[]` |
| `get_market_leverage_positions` | ✅ Added 02.07.2026 | `pm_leverage_position_object[]` |
| `get_creator_ban` | ✅ Added 02.07.2026 | `pm_creator_ban_object` |
| `get_leverage_quote` | ✅ Added 02.07.2026 | `pm_leverage_quote_api_object` |
| `get_leverage_close_preview` | ✅ Added 02.07.2026 | `pm_leverage_close_preview_api_object` |
| `get_leverage_convert_preview` | ✅ Added 02.07.2026 | `pm_leverage_convert_preview_api_object` |
| `get_oracle` | ✅ Added 02.07.2026 | `pm_oracle_api_object` |
| `list_oracles` | ✅ Added 02.07.2026 | `pm_oracle_object[]` |
| `get_dispute` | ✅ Added 02.07.2026 | `pm_dispute_object` |
| `get_dispute_votes` | ✅ Added 02.07.2026 | `pm_dispute_votes_api_object` |
| `get_lazy_pool` | ✅ Added 02.07.2026 | `pm_lazy_pool_object` (no args) |
| `get_lazy_deposit` | ✅ Added 02.07.2026 | `pm_lazy_deposit_object` |
| `get_lazy_allocations` | ✅ Added 02.07.2026 | `pm_lazy_allocation_object[]` |
| `get_market_lazy_allocation` | ✅ Added 02.07.2026 | `pm_lazy_allocation_object` |
| `get_pm_chain_properties` | ✅ Added 02.07.2026 | `chain_properties_pm` (median governance params, no args) |

> **Governance params are read dynamically** — the library relays whatever `chain_properties_pm`
> fields the node returns, so new median-voted knobs need no client code change. The 2026-07 delta
> adds two: `pm_oracle_accept_window_sec` (uint32 sec, default `3600`) — the window in which a named
> oracle must accept/reject a pending market before the cron voids it (emitting `pm_market_expired`,
> op 101) and refunds the creator's seed liquidity; and `pm_lazy_min_liquidity_fee_percent` (uint16
> bp, default `200` = 2%) — the lazy pool refuses to co-provide depth to a market whose
> `liquidity_fee_percent` is below this floor. Pending markets also carry a new read-only field
> `pm_market_object.accept_deadline` (= `created_time + pm_oracle_accept_window_sec`; `0` when active
> at creation). **Read live thresholds; never hard-code the defaults.**
| `get_market_meta` | ✅ Added 02.07.2026 | `pm_market_meta_object` |
| `list_markets_by_category` | ✅ Added 02.07.2026 | `pm_market_meta_object[]` |
| `get_market_categories` | ✅ Added 02.07.2026 | `pm_market_categories_api_object` (no args) |
| `get_market_kline` | ✅ Added 02.07.2026 | `pm_kline_api_object[]` (offset-from-newest paging) |

> Requires the node to run the `prediction_market_api` plugin (needs `chain` + `json_rpc`).
> The three leverage preview methods are **non-consensus quotes** — always send on-chain slippage
> guards (`min_tokens` / `min_return` / `conversion_profit_cost`) when broadcasting.

---

## Deprecated Plugins (Not Implementing)

These plugins are deprecated or content-related (VIZ moved away from content model):

### follow (9 methods) ⚠️ Deprecated
- `get_followers`, `get_following`, `get_follow_count`
- `get_feed_entries`, `get_feed`, `get_blog_entries`, `get_blog`
- `get_reblogged_by`, `get_blog_authors`

### tags (15 methods) ⚠️ Deprecated
- `get_trending_tags`, `get_tags_used_by_author`
- `get_discussions_by_*` (trending, created, active, cashout, payout, votes, children, hot, feed, blog, contents, author_before_date)
- `get_languages`

### social_network (12 methods) ⚠️ Deprecated
- `get_content`, `get_content_replies`, `get_all_content_replies`
- `get_account_votes`, `get_active_votes`, `get_replies_by_last_update`
- Duplicate methods from committee_api and invite_api

### private_message (2 methods) ⚠️ Deprecated
- `get_inbox`, `get_outbox`

### debug_node (8 methods) 🔧 Dev Only
- Development/testing utilities, not for production use

---

## Recently Added (03.03.2026)

### Operations
- `chain_properties_update_operation` (ID 25)

### API Methods
- `get_master_history` (database_api)
- `get_block_info` (block_info)
- `get_blocks_with_info` (block_info)
- `get_raw_block` (raw_block)
- `check_authority_signature` (auth_util)

---

## API Legend

| Symbol | Meaning |
|--------|----------|
| ✅ | Fully implemented |
| ⚠️ | Deprecated (not implementing by design) |
| 🔧 | Development/testing only |

---

## Witness → Validator Migration (Phase B — Node Upgraded)

**Added:** 17.05.2026
**Updated:** 17.05.2026 — node upgrade complete, all renames live

The VIZ blockchain has completed renaming "witness" → "validator" across the entire stack. This library (Phase B) sends new names and keeps backward-compat receivers for old names in operation history.

### Operation Builders

Both old and new method names are supported. New names emit new JSON; old names emit old JSON for historical compatibility.

| Old Method | New Method | JSON Name Emitted | Type ID |
|------------|------------|-------------------|---------|
| `build_witness_update()` | `build_validator_update()` | `validator_update` | 6 |
| `build_account_witness_vote()` | `build_account_validator_vote()` | `account_validator_vote` | 7 |
| `build_account_witness_proxy()` | `build_account_validator_proxy()` | `account_validator_proxy` | 8 |

Usage via `__call` magic method:
```php
$tx->validator_update($owner, $url, $key);          // new name (recommended)
$tx->account_validator_vote($account, $validator);  // new name (recommended)
$tx->account_validator_proxy($account, $proxy);     // new name (recommended)
// Old names still work for backward compat:
$tx->witness_update($owner, $url, $key);
$tx->account_witness_vote($account, $witness);
$tx->account_witness_proxy($account, $proxy);
```

### Field Rename in `account_validator_vote` (type 7)

`build_account_validator_vote($account, $validator, $approve)` emits field `"validator"`. The old `build_account_witness_vote($account, $witness, $approve)` still emits `"witness"`. The node accepts both.

### Chain Properties Field Renames

`build_versioned_chain_properties_update()` uses new field names:

| Old Field | New Field | Struct |
|-----------|-----------|--------|
| `inflation_witness_percent` | `inflation_validator_percent` | `chain_properties_hf4` |
| `witness_miss_penalty_percent` | `validator_miss_penalty_percent` | `chain_properties_hf6` |
| `witness_miss_penalty_duration` | `validator_miss_penalty_duration` | `chain_properties_hf6` |
| `witness_declaration_fee` | `validator_declaration_fee` | `chain_properties_hf9` |

### API Method Routing

All 16 method names (8 old `witness_*` + 8 new `validator_*`) route to the `validator_api` plugin. Old method names are aliases. When the `validator_api` plugin is unavailable (older nodes), the library automatically falls back to the `witness_api` plugin with the corresponding old method name via `execute_witness_fallback()`.

```php
$result = $rpc->execute_method('get_active_validators');  // validator_api on new, falls back to witness_api.get_active_witnesses on old
$result = $rpc->execute_method('get_active_witnesses');   // validator_api on new (alias), falls back to witness_api.get_active_witnesses on old
```

### Node Response Field Renames (library relays raw JSON — no code changes needed)

After node upgrade the following response field names changed. Library users must update field access in their application code:

**Block header** (`get_block`, `get_block_header`):

| Old Field | New Field |
|-----------|-----------|
| `witness` | `validator` |
| `witness_signature` | `validator_signature` |

**Dynamic global properties** (`get_dynamic_global_properties`):

| Old Field | New Field |
|-----------|-----------|
| `current_witness` | `current_validator` |

**Account object** (`get_accounts`):

| Old Field | New Field |
|-----------|-----------|
| `witnesses_voted_for` | `validators_voted_for` |
| `witnesses_vote_weight` | `validators_vote_weight` |
| `witness_votes` | `validator_votes` |

**Validator schedule object** (`get_validator_schedule`):

| Old Field | New Field |
|-----------|-----------|
| `current_shuffled_witnesses` | `current_shuffled_validators` |
| `num_scheduled_witnesses` | `num_scheduled_validators` |

**`get_config` response keys**:

| Old Key | New Key |
|---------|---------|
| `CHAIN_HARDFORK_REQUIRED_WITNESSES` | `CHAIN_HARDFORK_REQUIRED_VALIDATORS` |
| `CHAIN_MAX_ACCOUNT_WITNESS_VOTES` | `CHAIN_MAX_ACCOUNT_VALIDATOR_VOTES` |
| `CHAIN_MAX_WITNESSES` | `CHAIN_MAX_VALIDATORS` |
| `CHAIN_MAX_SUPPORT_WITNESSES` | `CHAIN_MAX_SUPPORT_VALIDATORS` |
| `CHAIN_MAX_TOP_WITNESSES` | `CHAIN_MAX_TOP_VALIDATORS` |
| `CHAIN_MAX_WITNESS_URL_LENGTH` | `CHAIN_MAX_VALIDATOR_URL_LENGTH` |

### What Never Changes

- Integer type IDs (6, 7, 8, 30, 42) — binary wire format uses integer indices
- Binary serialization — struct field order preserved, names not written to binary
- Field names `block_signing_key`, `url`, `approve`, `proxy`, `shares`, `owner`
- Virtual operations (`shutdown_validator` type 30, `validator_reward` type 42) — chain-generated, not submitted by users
