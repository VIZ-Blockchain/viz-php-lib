# Prediction Markets — end-to-end example (viz-php-lib)

> Onix / HF14. A single market walked **from creation to payout**: oracle →
> market → bet → resolve → read positions. Copy-paste runnable (swap in your node
> and keys). For the full per-operation reference (all 23 builders, 29 read methods,
> commit-reveal) see the **Prediction Markets (Onix, HF14)** section of [`README.md`](./README.md).

Conventions:
- **object ids** (`market_id`, `bet_id`, `liquidity_id`, `position_id`, `commit_id`)
  are the bare integer instance of the on-chain object.
- **percent** fields are basis points (`10000 = 100%`) unless a builder note says otherwise.
- **assets** are VIZ strings (`"10.000 VIZ"`); `min_tokens` / `share_type` are raw
  milli-VIZ integers (VIZ×1000).
- `extensions` is appended automatically; the no-prefix method builds **and signs** a
  standalone transaction (`build_` prefix = queue/propose without signing).
- signer authority is **active** for all ops **except** `pm_dispute_vote` (**regular** key).

## Scenario

A binary (CPMM) market "Will X happen by 2026?", the creator is its own oracle
(self-oracle), seeded with 100 VIZ liquidity. One account bets 10 VIZ on "Yes";
after betting closes the oracle resolves the outcome and the bettor's payout is settled.

Lifecycle: `pending` → (oracle accepts) `active` → (betting expires) `resolving` →
(oracle resolves) `resolved` → payouts.

```php
<?php
include('./class/autoloader.php');

$node='https://api.viz.world/';
$oracle='alice';  $oracle_key='5K...'; // active
$bettor='bob';    $bettor_key='5J...'; // active

// 1. Register the oracle.
$tx=new VIZ\Transaction($node,$oracle_key);
$reg=$tx->pm_oracle_register(
  $oracle,'50.000 VIZ'/*insurance*/,300/*fee bp*/,'0.000 VIZ'/*fixed_fee*/,
  'https://rules.example/oracle'/*rules_url*/,
  true/*auto_accept_creator*/,true/*auto_accept_resolver*/,true/*auto_accept*/
);
$tx->execute($reg['json']);

// 2. Create a binary (CPMM) market, seeded with 100 VIZ.
$mk=$tx->pm_create_market(
  $oracle/*creator*/,$oracle/*oracle*/,0/*binary*/,['Yes','No'],
  'Will X happen by 2026?',
  300/*oracle_fee bp*/,'0.000 VIZ'/*oracle_fixed_fee*/,
  100/*creator_fee bp*/,200/*lp_fee bp*/,
  '100.000 VIZ'/*liquidity*/,0/*lmsr_b, 0 for binary*/,
  '2026-08-01T00:00:00'/*betting_expiration*/,
  '2026-08-02T00:00:00'/*result_expiration*/
  // remaining fields (time_penalty, allow_*, dispute_*, metadata) use defaults
);
$tx->execute($mk['json']);

// Manual accept, if auto_accept is off:
// $acc=$tx->pm_oracle_accept_market($oracle,5/*market_id*/,true,300,'0.000 VIZ');
// $tx->execute($acc['json']);

// 3. Bet 10 VIZ on side 0 (Yes). binary -> outcome_index -1.
$tx_bet=new VIZ\Transaction($node,$bettor_key);
$bet=$tx_bet->pm_place_bet($bettor,5/*market_id*/,0/*side*/,-1/*outcome_index*/,'10.000 VIZ');
$tx_bet->execute($bet['json']);

// 4. Resolve after betting closes (winning_outcome 0 = Yes).
$res=$tx->pm_resolve_market($oracle,5/*market_id*/,0/*winning_outcome*/,
  'https://proof.example'/*decision_url*/,'X happened'/*decision_reason*/);
$tx->execute($res['json']);

// 5. Read the result.
$api=new VIZ\JsonRPC($node);
$full=$api->execute_method('get_market_full',[5,$bettor]); // status, outcome, position
$pos =$api->execute_method('get_account_positions',[$bettor,5,0,100]);
```

Read methods (`prediction_market_api`, pagination `(...key, from, limit)`, `limit<=1000`):

```php
$market=$api->execute_method('get_market',[5]);
$active=$api->execute_method('list_markets',[1/*status active*/,0/*from*/,100/*limit*/]);
$props =$api->execute_method('get_pm_chain_properties'); // live median governance params
$kline =$api->execute_method('get_market_kline',[5,0,1000]);
```

## Beyond the happy path

- **commit–reveal** betting: `$tx->pm_commitment(...)` builds the byte-exact SHA-256
  commitment the node re-checks on reveal (a wrong preimage forfeits the escrow), then
  `pm_commit_bet` → `pm_reveal_bet` with the same values + salt.
- **liquidity**: `pm_add_liquidity` / `pm_withdraw_liquidity`, lazy pool
  (`pm_lazy_deposit` / `pm_lazy_withdraw`).
- **disputes**: `pm_dispute_create` → `pm_dispute_vote` (**regular** key!) /
  `pm_dispute_resolve`, oracle rebuttal `pm_dispute_oracle_respond`, `pm_unban`.
- **leverage** (gated by `pm_leverage_enabled`): `pm_leverage_open/close/convert`.
- **multi-outcome (LMSR)** market: market_type `1`, `outcomes=['A','B','C']`,
  `outcome_index` 0..N-1, `lmsr_b>0`, `side=-1`.

See the **Prediction Markets** section of [`README.md`](./README.md) for the full
signatures of each of these.
