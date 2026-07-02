# viz-php-lib

<p align="center">
<img height="200" src="logo-viz-php-lib.svg" alt="VIZ PHP Library">
</p>

Native PHP class for VIZ Keys, Transaction, JsonRPC

## Features
- JsonRPC — native socket usage (all API methods, hostname cache, ssl flag, full result flag)
- Keys — private (sign), public (verify), shared, encoded keys support (wif, public encoded, compressed/uncompressed public key in hex representation)
- Transaction — easy workflow, multi-signature support, multi-operations support, execute by JsonRPC, support all 40 operations: transfer, transfer_to_vesting, withdraw_vesting, award, create_invite, validator_update, etc...
- Classes support PSR-4
- Contains modificated classes for best fit to VIZ Blockchain (all-in-one)
- Native code without additional installations (sry composer, but we need other changes in third-party classes)
- Utils for keys compatibility with Ethereum, Bitcoin addresses
- Utilities with (Voice protocol)[https://github.com/VIZ-Blockchain/Free-Speech-Project/blob/master/specification.md] support: voice_text, voice_publication
- MIT License

## Dependencies

One PHP extension from the list:

- GMP (GNU Multiple Precision) — `sudo apt-get install libgmp-dev php-gmp`
- BCMath — `sudo apt-get install php-bcmath`

Most hosting providers have it turned on already, but if not found, check your control panel.

Thanks to third-party class developers:

- https://github.com/simplito/bigint-wrapper-php
- https://github.com/simplito/bn-php
- https://github.com/simplito/elliptic-php
- https://github.com/kornrunner/php-keccak

## Examples

Some examples have placeholders for private keys or accounts. Change them for successfull test.

Keys: init from hex, encode to wif, get public key from private, get encoded version, verify signature.

```php
<?php
include('./class/autoloader.php');

$private_key=new VIZ\Key('b9f3c242e5872ac828cf2ef411f4c7b2a710bd9643544d735cc115ee939b3aae');
print 'Private key from hex: '.$private_key->hex.PHP_EOL;
print 'Private key WIF: '.$private_key->encode().PHP_EOL;
$data='Hello VIZ.World! '.date('d.m.Y H:i:s');
print 'Data for signing: '.$data.PHP_EOL;
$signature=$private_key->sign($data);
if(false===$signature){
	print 'Canonical signature was not found, please try again.';
}
else{
	print 'Signature: '.$signature.PHP_EOL;
	$public_key=$private_key->get_public_key();
	$recovered_public_key=$public_key->recover_public_key($data,$signature);
	print 'Recovered public key from signature: '.$public_key->encode().PHP_EOL;

	if($public_key){
		print 'Public key from private: '.$public_key->encode().PHP_EOL;
		print 'Verify signature status for same data: '.var_export($public_key->verify($data,$signature),true).PHP_EOL;
		print 'Verify signature status for other data: '.var_export($public_key->verify('Bye VIZ.World!',$signature),true).PHP_EOL;
	}
}
```

JsonRPC: init with endpoint, api method without parameters, change endpoint, get account, turn on all json result, request a non-existent account.

```php
<?php
include('./class/autoloader.php');

$api=new VIZ\JsonRPC('https://node.viz.plus/');
$dgp=$api->execute_method('get_dynamic_global_properties');
var_dump($dgp);

$api->endpoint='https://node.viz.media/';

$account_login='on1x';
$account=$api->execute_method('get_account',[$account_login,'']);
if(false!==$account){
	print PHP_EOL.'Account '.$account_login.' was founded:';
	var_dump($account);
}

$api->return_only_result=false;

$account_login='strange.viz';
$account=$api->execute_method('get_account',[$account_login,'']);
if(isset($account['error'])){
	print PHP_EOL.$account['error']['message'];
}
```

Transaction: init with endpoint and private key in wif, build simple transaction with award operation and execute it.

```php
<?php
include('./class/autoloader.php');
$initiator='account';
$initiator_private_key='5Jaw8HtYbPDWRDhoH3eojmwquvsNZ8Z9HTWCsXJ2nAMrSxNPZ4F';

$tx=new VIZ\Transaction('https://node.viz.plus/',$initiator_private_key);
$tx_data=$tx->award($initiator,'committee',1000,0,'testing viz-php-lib award operation');
var_dump($tx_data);

$tx_status=$tx->execute($tx_data['json']);
var_dump($tx_status);
```

Init with endpoint and private key in wif, activate queue mode and add 2 operations, end queue and get result array. Execute transaction json from array. Add additional signature for multi-sig example (can be false if canonical signature was not found).

```php
<?php
include('./class/autoloader.php');
$initiator='some.account';
$initiator_private_key='5Jaw8HtYbPDWRDhoH3eojmwquvsNZ8Z9HTWCsXJ2nAMrSxNPZ4F';

$tx=new VIZ\Transaction('https://node.viz.plus/',$initiator_private_key);
$tx->start_queue();
$tx->award($initiator,'committee',1000,0,'testing viz-php-lib multi-operations');
$tx->award($initiator,'committee',2000,0,'testing viz-php-lib multi-operations 2');
$tx_data=$tx->end_queue();
var_dump($tx_data);

//turn api flag to return all result (including error state)
$tx->api->return_only_result=false;
$tx_status=$tx->execute($tx_data['json']);
var_dump($tx_status);

$tx2_data=$tx->add_signature($tx_data['json'],$tx_data['data'],'5HrmLC83FybxVgJ5jXQN5dUHxXZfHVc27sYpjdnoTviRqppPhPN');
var_dump($tx2_data);
```

Create new private key, get public key from it, execute transaction with create_invite operation.

```php
<?php
include('./class/autoloader.php');

$key=new VIZ\Key();
$key_data=$key->gen('some seed, salt will be input','there');
print 'Key seed with inputed salt: '.$key_data[0].PHP_EOL;

$key_data=$key->gen('some seed, salt will be generated');
print 'Key seed with random salt: '.$key_data[0].PHP_EOL;

print 'Private key (wif): '.$key_data[1].PHP_EOL;
print 'Private key (wif) from object: '.$key->encode().PHP_EOL;
print 'Public key (encoded): '.$key_data[2].PHP_EOL;
print 'Public key (encoded) from object: '.$key_data[3]->encode().PHP_EOL;

$tx=new VIZ\Transaction('https://node.viz.plus/','5JWm...');

$tx_data=$tx->create_invite('on1x','50.005 VIZ',$key_data[2]);
var_dump($tx_data);

$tx_status=$tx->execute($tx_data['json']);
var_dump($tx_status);
```

Find shared key from two sides. Simple string encrypt and decrypt with AES-256-CBC. Encode and decode memo with `viz-js-lib` compability structure for encrypted memo.

```php
<?php
include('./class/autoloader.php');

$private_key1=new VIZ\Key();
$private_key1->gen();
$public_key1=$private_key1->get_public_key();
print '$public_key1: '.$public_key1->encode().PHP_EOL;

$private_key2=new VIZ\Key();
$private_key2->gen();
$public_key2=$private_key2->get_public_key();
print '$public_key2: '.$public_key2->encode().PHP_EOL;

$shared_key1=$private_key1->get_shared_key($public_key2->encode());
print '$shared_key1: '.$shared_key1.PHP_EOL;

$shared_key2=$private_key2->get_shared_key($public_key1->encode());
print '$shared_key2: '.$shared_key2.PHP_EOL;

$string='Hello VIZ World! 🤘';

$encrypted=VIZ\Utils::aes_256_cbc_encrypt($string,hex2bin($shared_key1));
$decrypted=VIZ\Utils::aes_256_cbc_decrypt(hex2bin($encrypted['data']),hex2bin($shared_key2),hex2bin($encrypted['iv']));

print PHP_EOL.'Simple encrypted AES-256-cbc with $shared_key1: '.var_export($encrypted,true).PHP_EOL;
print PHP_EOL.'Simple decrypted AES-256-cbc with $shared_key2: '.var_export($decrypted,true).PHP_EOL;

$crypted=$private_key1->encode_memo($public_key2->encode(),$string);
print PHP_EOL.'Crypted memo by AES-256-cbc with shared key between private_key1 and public_key2: '.var_export($crypted,true).PHP_EOL;

$result=$private_key2->decode_memo($crypted);
print PHP_EOL.'Decrypted memo by AES-256-cbc with shared key between private_key2 and public_key1: '.var_export($result,true).PHP_EOL;

$result=$private_key1->decode_memo($crypted);
print PHP_EOL.'Decrypted memo by AES-256-cbc with shared key between private_key1 and public_key2: '.var_export($result,true).PHP_EOL;

$result=$private_key3->decode_memo($crypted);
print PHP_EOL.'Decrypted memo by AES-256-cbc with shared key between private_key3 and public_key1: '.var_export($result,true).PHP_EOL;
```

Generate data and signature for passwordless authentication and check it for domain auth action with active authority.

```php
<?php
include('./class/autoloader.php');
$account='invite';
$private_key=new VIZ\Key('5KcfoRuDfkhrLCxVcE9x51J6KN9aM9fpb78tLrvvFckxVV6FyFW');
print 'Private key WIF: '.$private_key->encode().PHP_EOL;
list($data,$signature)=$private_key->auth($account,'domain.com','auth','active');
print 'Data for auth: '.$data.PHP_EOL;
print 'Signature: '.$signature.PHP_EOL;

$viz_auth=new VIZ\Auth('https://node.viz.plus/','domain.com','auth','active');
$auth_status=$viz_auth->check($data,$signature);
print 'Passwordless authentication: '.var_export($auth_status,true);
```

Make transaction with custom operation.

```php
<?php
include('./class/autoloader.php');
$account='test';
$private_key='5K...';//regular
$tx=new VIZ\Transaction('https://node.viz.plus/',$private_key);
$tx_data=$tx->custom([],[$account],'test','{"msg":"testing viz-php-lib custom operation"}');
var_dump($tx_data);

$tx->api->return_only_result=false;
//second attribute is synchronous, if setted then return block num where transaction was witnessed
$tx_status=$tx->execute($tx_data['json'],true);
var_dump($tx_status);
```

Make transaction with account create operation.

```php
<?php
include('./class/autoloader.php');
$account='test';
$private_key='5K...';//regular
$tx=new VIZ\Transaction('https://api.viz.world/',$private_key);

$fee='0.000 VIZ';
$delegation='10.000000 SHARES';

//you can set any authority as simple string with encoded public key
$master='VIZ7RXhpaw8SbedSp84EqMGGzeBZgAPLEn7D6kQhJu8bMMvUKtuxk';//5JRd4Toy8cmDr15qEtZieqAgbg3qQMU6n8cPC1Las2hBah46tr1
$active='VIZ7H8S8rHkKQkX8bSUpDtBAwy8tTphq2NH7ZRv1dcCvk1Cjz38nK';//5JvgdGsA5M8rZ9oY2p7qcKkexG1kWqN2jQcQ6afEPCqQXrKTsbS

//or make full authority struct (if you need more flexibility)
$regular=[
	//'weight_threshold'=>1,//can be empty if weight_threshold=1
	'account_auths'=>[
		['on1x',1]
	],
	'key_auths'=>[
		['VIZ5bGNeJPjoDdZTEK3LSMUfP21gcBH34AdMPHpvQymuYMk2YMbsB',1],//5HuHKQhiiAAAp7zMCgRpCCyv8hramEmzAzDUagrhMkTWDhyVtK6
		['VIZ5jWA94PYBanGSPhTyWrwvT8RAJcnB7onXGvK5DnzbxB6874yap',1]//5KESchwZvs67C4Xz5SQQ1ea4N9rZR67NEvi6yemWpSYoKo9eTrM
	]
];
//you need manually check public keys sorting in key_auths or node will refuse the transaction

$memo_key='VIZ1111111111111111111111111111111114T1Anm';//can be empty key

$json_metadata='';
$referrer='';

$new_account_name='test-lib';

$tx_data=$tx->account_create($fee,$delegation,$account,$new_account_name,$master,$actove,$regular,$memo_key,$json_metadata,$referrer);
var_dump($tx_data);

$tx->api->return_only_result=false;
$tx_status=$tx->execute($tx_data['json']);
var_dump($tx_status);
```

Make transaction with proposal operations as array (need to use `build_` prefix). Approve that proposal with other account active key.

```php
<?php
include('./class/autoloader.php');
$account='test';
$private_key='5K...';//regular
$account2='test2';
$private_key2='5K...';//active

$tx=new VIZ\Transaction('https://api.viz.world/',$private_key);
$tx_data=$tx->proposal_create($account,'test','test proposal operation builder','2021-03-03T12:00:01',[$tx->build_transfer($account2,'committee','1.000 VIZ','proposed operation 1'),$tx->build_transfer($account2,'committee','2.000 VIZ','proposed operation 2')],false);
var_dump($tx_data);

$tx_status=$tx->execute($tx_data['json']);
var_dump($tx_status);

$tx2=new VIZ\Transaction('https://api.viz.world/',$private_key2);
$tx2_data=$tx2->proposal_update($account,'test',[$account2]);
var_dump($tx2_data);

$tx2_status=$tx->execute($tx2_data['json']);
var_dump($tx2_status);
```

Utils method for post text object to Voice protocol. Method attributes (* - is optional):

Attribute | Description
------------ | -------------
text | Simple text note.
reply* | Link to replied context in `viz://` url scheme.
share* | Link to shared context in any url scheme.
beneficiaries* | Array of objects `[]` contains `["account"=>"committee","weight"=>100]` for awarding beneficiaries details.
loop* | Block num. Ability to make loop for previous objects, exclude from personal activity feed.

```php
<?php
include('./class/autoloader.php');
$account='test';
$private_key='5K...';//regular
//$endpoint,$key,$account,$text,$reply=false,$share=false,$beneficiaries=false,$loop=false
$status=VIZ\Utils::voice_text('https://api.viz.world/',$private_key,$account,'Test from viz-php-lib');
var_dump($status);
```

Utils method for post publication object to Voice protocol. Method attributes (* - is optional):

Attribute | Description
------------ | -------------
title | Publication title.
markdown | Publication text with voice markdown.
description* | Publication short description for preview.
image* | Link to publication image for preview thumbnail.
reply* | Link to replied context in `viz://` url scheme.
share* | Link to shared context in any url scheme.
beneficiaries* | Array of objects `[]` contains `["account"=>"committee","weight"=>100]` for awarding beneficiaries details.
loop* | Block num. Ability to make loop for previous objects, exclude from personal activity feed.

```php
<?php
include('./class/autoloader.php');
$account='test';
$private_key='5K...';//regular
$markdown='Well, Voice protocol markdown have **bold**, __italic__, ~~stroke~~ and `code`

## Headers 2

### And 3

Also we got:

> Quotes and

>> Second style for citation

Support lists:

* Unordered
* as ordinary
* items

And ordered, ofc:

*n Yes
*n it is!

After all, simple images:
![Alt text for image](https://viz.world/ubi-circle-300.jpg)

Paragraph
with
multiline

...and #en #example tags support :)';
//$endpoint,$key,$account,$title,$markdown,$description,$image,$reply=false,$share=false,$beneficiaries=false,$loop=false,$synchronous=false
$status=VIZ\Utils::voice_publication('https://api.viz.world/',$private_key,$account,'Test publication from viz-php-lib',$markdown);
var_dump($status);
```

Create Voice text object, hide it with Voice Event.

```php
<?php
include('./class/autoloader.php');
$account='test';
$private_key='5K...';
$text='Test text and event from viz-php-lib';

//create Voice object with text type (last attribute is synchronous that returns block num where is transaction was witnessed)
$object_block_num=VIZ\Utils::voice_text('https://api.viz.world/',$private_key,$account,$text,false,false,false,false,true);
print 'Object: viz://@'.$account.'/'.$object_block_num.'/';

//hide event
$event_block_num=VIZ\Utils::voice_event('https://api.viz.world/',$private_key,$account,'h',false,$object_block_num,false,false,true);
print 'Hide event for this object: viz://@'.$account.'/'.$object_block_num.'/?event='.$event_block_num;
```

Create Voice text object, check transaction size, split it for `add` Voice Event.

```php
<?php
include('./class/autoloader.php');
$account='test';
$private_key='5K...';
$max_size_limit=65280;
$part_size=1024*10;//10Kb

$long_text='...';//long text more that $max_size_limit bytes

//create Voice object with text type (last attribute is synchronous that returns block num where is transaction was witnessed)
$tx_prepare=VIZ\Utils::voice_text('https://api.viz.world/',$private_key,$account,$long_text,false/*reply*/,false/*share*/,false/*beneficiaries*/,false/*loop*/,true/*synchronous*/,true/*raw tx*/);
$tx_length=strlen($tx_prepare['data'])/2;
if($tx_length<$max_size_limit){
	$object_block_num=VIZ\Utils::voice_text('https://api.viz.world/',$private_key,$account,$long_text,false/*reply*/,false/*share*/,false/*beneficiaries*/,false/*loop*/,true/*synchronous*/,false/*raw tx*/);
	if($object_block_num){
		print 'Object: viz://@'.$account.'/'.$object_block_num.'/';
	}
	else{
		print 'Object error';
	}
}
else{
	$tx_diff=$tx_length-$max_size_limit;
	$parts_count=ceil($tx_diff / $part_size);

	$markdown_length=mb_strlen($long_text);
	$main_part_length=$markdown_length-($parts_count*$part_size);
	if($main_part_length<0){
		$main_part_length=$part_size;
	}
	$main_part=mb_substr($long_text,0,$main_part_length);
	$secondary_part=mb_substr($long_text,$main_part_length);
	$object_block_num=VIZ\Utils::voice_text('https://api.viz.world/',$private_key,$account,$main_part,false/*reply*/,false/*share*/,false/*beneficiaries*/,false/*loop*/,true/*synchronous*/,false/*raw tx*/);
	if($object_block_num){
		print 'Main object: viz://@'.$account.'/'.$object_block_num.'/'.PHP_EOL;
		//add event
		$event_block_num=VIZ\Utils::voice_event('https://api.viz.world/',$private_key,$account,'a',false,$object_block_num,false/*data_type*/,['t'=>$secondary_part]/*data*/,true/*synchronous*/,false/*raw tx*/);
		if($event_block_num){
			print 'Add event for this object: viz://@'.$account.'/'.$object_block_num.'/?event='.$event_block_num;
		}
		else{
			print 'Event error';
		}
	}
	else{
		print 'Object error';
	}
}
```

## Prediction Markets (Onix, HF14)

The library ships all 23 signed prediction-market operations and all 29 `prediction_market_api`
read methods. Operation builders follow the usual convention (`build_` prefix for queued/proposed
operations, no prefix to build-and-sign a standalone transaction) and every op takes an empty
`extensions` vector automatically. Object ids (`market_id`, `bet_id`, `liquidity_id`,
`position_id`, `commit_id`) are the bare integer instance of the on-chain object.

Percent fields are basis points (`10000 = 100%`) unless a builder note says otherwise. Assets are
VIZ strings (`"10.000 VIZ"`); `share_type`/`min_tokens` args are raw milli-VIZ integers (VIZ×1000).

### Operation builders

`pm_oracle_register` · `pm_oracle_update` · `pm_create_market` · `pm_oracle_accept_market` ·
`pm_place_bet` · `pm_commit_bet` · `pm_reveal_bet` · `pm_cancel_bet` · `pm_add_liquidity` ·
`pm_withdraw_liquidity` · `pm_resolve_market` · `pm_no_contest` · `pm_dispute_create` ·
`pm_dispute_vote` (regular auth) · `pm_dispute_resolve` · `pm_transfer_position` · `pm_lazy_deposit` ·
`pm_lazy_withdraw` · `pm_leverage_open` · `pm_leverage_close` · `pm_leverage_convert` ·
`pm_dispute_oracle_respond` · `pm_unban`.

```php
<?php
include('./class/autoloader.php');
$creator='test';
$active_key='5K...';//active

$tx=new VIZ\Transaction('https://api.viz.world/',$active_key);

//create a binary (CPMM) market, self-oracle, seeded with 100 VIZ liquidity
$tx_data=$tx->pm_create_market(
	$creator,$creator,0/*binary*/,['Yes','No'],'Will X happen by 2026?',
	300/*oracle_fee bp*/,'0.000 VIZ',100/*creator_fee bp*/,200/*lp_fee bp*/,
	'100.000 VIZ',0/*lmsr_b, 0 for binary*/,
	'2026-08-01T00:00:00'/*betting_expiration*/,'2026-08-02T00:00:00'/*result_expiration*/
);
$tx_status=$tx->execute($tx_data['json']);

//place an instant bet on side 0 (binary uses outcome_index -1)
$bet=$tx->pm_place_bet($creator,5/*market_id*/,0/*side*/,-1/*outcome_index*/,'10.000 VIZ');
$tx->execute($bet['json']);
```

Optional fields on `pm_oracle_update` accept `null` to leave a field unchanged
(`insurance_delta` is a signed asset — `"-5.000 VIZ"` to withdraw):

```php
$upd=$tx->pm_oracle_update('oracle_acc',null/*insurance_delta*/,500/*fee_percent bp*/);
```

### Commit–reveal betting

`pm_commitment(...)` builds the byte-exact SHA-256 commitment the node re-checks on reveal (a wrong
preimage forfeits the escrow). `amount`/`min_tokens` are milli-VIZ integers:

```php
$salt='cafe1234';
$commitment=$tx->pm_commitment(5/*market_id*/,'alice',0/*side*/,-1/*outcome_index*/,10000/*amount*/,0/*min_tokens*/,$salt);
//no_reveal_fee_percent MUST equal median(pm_commit_no_reveal_penalty_percent)
$commit=$tx->pm_commit_bet('alice',5,$commitment,'10.000 VIZ',2000);
$tx->execute($commit['json']);
//...later, within the reveal window, re-supply the same values + salt:
$reveal=$tx->pm_reveal_bet('alice',$commit_id,0,-1,'10.000 VIZ',$salt,0);
$tx->execute($reveal['json']);
```

### Read methods (`prediction_market_api`)

Called via `execute_method` like any other API. Pagination is `(...key..., from, limit)` with
`limit <= 1000`.

```php
<?php
include('./class/autoloader.php');
$api=new VIZ\JsonRPC('https://api.viz.world/');

$market=$api->execute_method('get_market',[5]);
$active=$api->execute_method('list_markets',[1/*status active*/,0/*from*/,100/*limit*/]);
$full=$api->execute_method('get_market_full',[5,'alice']);//one-call market detail view
$props=$api->execute_method('get_pm_chain_properties');//live median governance params
$kline=$api->execute_method('get_market_kline',[5,0,1000]);//chart series (offset-from-newest)
```

Full method set: `get_market`, `list_markets`, `list_markets_by_oracle`, `list_markets_by_creator`,
`get_market_outcomes`, `get_market_weight_sums`, `get_market_bets`, `get_account_positions`,
`get_market_liquidity`, `get_market_full`, `get_account_leverage_positions`,
`get_market_leverage_positions`, `get_creator_ban`, `get_leverage_quote`,
`get_leverage_close_preview`, `get_leverage_convert_preview`, `get_oracle`, `list_oracles`,
`get_dispute`, `get_dispute_votes`, `get_lazy_pool`, `get_lazy_deposit`, `get_lazy_allocations`,
`get_market_lazy_allocation`, `get_pm_chain_properties`, `get_market_meta`,
`list_markets_by_category`, `get_market_categories`, `get_market_kline`.

May VIZ be with you.