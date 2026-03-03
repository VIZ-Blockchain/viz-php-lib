# VIZ PHP Library — Operations Implementation Status

**Status Date:** 03.03.2026
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
| Witness Operations | 5 | 5 | 0 | 0 |
| **TOTAL** | **42** | **39** | **0** | **3** |

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
| **Witness Operations** |||||
| 6 | `witness_update_operation` | `build_witness_update()` | ✅ Implemented | |
| 7 | `account_witness_vote_operation` | `build_account_witness_vote()` | ✅ Implemented | |
| 8 | `account_witness_proxy_operation` | `build_account_witness_proxy()` | ✅ Implemented | |
| 25 | `chain_properties_update_operation` | `build_chain_properties_update()` | ✅ Implemented | Added 03.03.2026 |
| 46 | `versioned_chain_properties_update_operation` | `build_versioned_chain_properties_update()` | ✅ Implemented | |

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Fully implemented |
| ⚠️ | Deprecated (not implementing by design) |
| ❌ | Missing (needs implementation) |

---

## Recently Added (03.03.2026)

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

**Status Date:** 03.03.2026
**Reference:** viz-cpp-node plugins documentation

---

## API Summary

| Plugin | Total Methods | Implemented | Status |
|--------|---------------|-------------|--------|
| database_api | 31 | 31 | ✅ Complete |
| network_broadcast_api | 4 | 4 | ✅ Complete |
| witness_api | 8 | 8 | ✅ Complete |
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
| auth_util | 1 | 0 | ⚠️ Deprecated |
| debug_node | 8 | 0 | 🔧 Dev only |
| **TOTAL (non-deprecated)** | **63** | **63** | **100%** |

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
|--------|--------|
| ✅ | Fully implemented |
| ⚠️ | Deprecated (not implementing by design) |
| 🔧 | Development/testing only |
