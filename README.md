# viz-php-lib

<p align="center">
<img height="200" src="logo-viz-php-lib.svg" alt="VIZ PHP Library">
</p>

Native PHP class for VIZ Keys, Transaction, JsonRPC

## Features
- JsonRPC — native socket usage (all API methods, hostname cache, ssl flag, full result flag)
- Keys — private (sign), public (verify), shared, encoded keys support (wif, public encoded, compressed/uncompressed public key in hex representation)
- Transaction — easy workflow, multi-signature support, multi-operations support, execute by JsonRPC, support 5 most usable operations: transfer, transfer_to_vesting, withdraw_vesting, award, create_invite (other operations will be implemented later)
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
$tx_status=$tx->execute($tx_data['json']);
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
------------ | ------------ | -------------
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
------------ | ------------ | -------------
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
//$endpoint,$key,$account,$title,$markdown,$description,$image,$reply=false,$share=false,$beneficiaries=false,$loop=false
$status=VIZ\Utils::voice_publication('https://api.viz.world/',$private_key,$account,'Test publication from viz-php-lib',$markdown);
var_dump($status);
```

May VIZ be with you.