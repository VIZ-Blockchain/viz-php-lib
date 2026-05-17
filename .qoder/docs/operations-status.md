# VIZ PHP Library — Operations Implementation Status

**Status Date:** 16.05.2026
**Reference:** viz-cpp-node documentation

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
| **TOTAL** | **43** | **40** | **0** | **3** |

**Coverage:** 100% of non-deprecated operations implemented

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

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Fully implemented |
| ⚠️ | Deprecated (not implementing by design) |
| ❌ | Missing (needs implementation) |

---

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

**Status Date:** 16.05.2026
**Reference:** viz-cpp-node plugins documentation

---

## API Summary

| plugin | Total Methods | Implemented | Status |
|--------|---------------|-------------|--------|
| database_api | 31 | 31 | ✅ Complete |
| network_broadcast_api | 4 | 4 | ✅ Complete |
| witness_api | 8 | 8 | ✅ Complete |
| validator_api | 8 | 8 | ✅ Complete |
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
| follow | 9 | 0 | ⚠️ Deprecated |
| tags | 15 | 0 | ⚠️ Deprecated |
| social_network | 12 | 0 | ⚠️ Deprecated |
| private_message | 2 | 0 | ⚠️ Deprecated |
| debug_node | 8 | 0 | 🔧 Dev only |
| **TOTAL (non-deprecated)** | **71** | **71** | **100%** |

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

### witness_api (8 methods) ✅

| Method | Status |
|--------|--------|
| `get_active_witnesses` | ✅ |
| `get_witness_schedule` | ✅ |
| `get_witnesses` | ✅ |
| `get_witness_by_account` | ✅ |
| `get_witnesses_by_vote` | ✅ |
| `get_witnesses_by_counted_vote` | ✅ |
| `get_witness_count` | ✅ |
| `lookup_witness_accounts` | ✅ |

### validator_api (8 methods) ✅

Renamed from `witness_api`. Uses `validator_api` plugin name on new nodes; automatically falls back to `witness_api` methods on older nodes.

| Method | Status | Fallback |
|--------|--------|----------|
| `get_active_validators` | ✅ | → `get_active_witnesses` |
| `get_validator_schedule` | ✅ | → `get_witness_schedule` |
| `get_validators` | ✅ | → `get_witnesses` |
| `get_validator_by_account` | ✅ | → `get_witness_by_account` |
| `get_validators_by_vote` | ✅ | → `get_witnesses_by_vote` |
| `get_validators_by_counted_vote` | ✅ | → `get_witnesses_by_counted_vote` |
| `get_validator_count` | ✅ | → `get_witness_count` |
| `lookup_validator_accounts` | ✅ | → `lookup_witness_accounts` |

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

### API Method Fallback

New `validator_api` method names are tried first; falls back to `witness_api` on older nodes automatically.

```php
$result = $rpc->execute_method('get_active_validators');  // tries validator_api, falls back to witness_api
$result = $rpc->execute_method('get_active_witnesses');   // direct old method (still works)
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
