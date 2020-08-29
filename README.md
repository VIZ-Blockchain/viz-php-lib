# viz-php-lib

<p align="center">
<img height="200" src="logo-viz-php-lib.svg" alt="VIZ PHP Library">
</p>

Native PHP class for VIZ Keys, Transaction, JsonRPC

## Features
- JsonRPC — native socket usage (all API methods, hostname cache, ssl flag, full result flag)
- Keys — private (sign), public (verify), shared, encoded keys support (wif, public encoded)
- Transaction — easy workflow, multi-signature support, multi-operations support, execute by JsonRPC, support 5 most usable operations: transfer, transfer_to_vesting, withdraw_vesting, award, create_invite (other operations will be implemented later)
- Classes support PSR-4
- Contains modificated classes for best fit to VIZ Blockchain (all-in-one)
- Native code without additional installations (sry composer, but we need other changes in third-party classes)
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

## Examples

Some examples have placeholders for private keys or accounts. Change them for successfull test.

Keys: init from hex, encode to wif, get public key from private, get encoded version, verify signature.

```php
<?php
include('./class/autoloader.php');

$private_key=new VIZ\Key('b9f3c242e5872ac828cf2ef411f4c7b2a710bd9643544d735cc115ee939b3aae');
print 'Private key from hex: '.$private_key->hex.PHP_EOL;
print 'Private key WIF: '.$private_key->encode().PHP_EOL;
$data='Hello VIZ.World!';
print 'Data for signing: '.$data.PHP_EOL;
$signature=$private_key->sign($data);
print 'Signature: '.$signature.PHP_EOL;
$public_key=$private_key->get_public_key();
if($public_key){
	print 'Public key from private: '.$public_key->encode().PHP_EOL;
	print 'Verify signature status for same data: '.var_export($public_key->verify($data,$signature),true).PHP_EOL;
	print 'Verify signature status for other data: '.var_export($public_key->verify('Bye VIZ.World!',$signature),true).PHP_EOL;
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
if(false===$account){
	print PHP_EOL.'Account '.$account_login.' not founded';
}
```

Transaction: init with endpoint and private key in wif, activate queue mode and add 2 operations, end queue and get result array. Execute transaction json from array. Add additional signature for multi-sig example (can be false if canonical signature was not found).

```php
<?php
include('./class/autoloader.php');

$tx=new VIZ\Transaction('https://node.viz.plus/','5Jaw8HtYbPDWRDhoH3eojmwquvsNZ8Z9HTWCsXJ2nAMrSxNPZ4F');
$tx->api->return_only_result=false;
$tx->start_queue();
$initiator='some.account';
$tx->award($initiator,'committee',1000,0,'testing viz-php-lib multi-operations');
$tx->award($initiator,'committee',2000,0,'testing viz-php-lib multi-operations 2');
$tx_data=$tx->end_queue();
var_dump($tx_data);

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

Find shared key from two sides. Will be good for AES-256-CBC.

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
print '$shared_key1: '.$shared_key1->encode().PHP_EOL;

$shared_key2=$private_key2->get_shared_key($public_key1->encode());
print '$shared_key2: '.$shared_key2->encode().PHP_EOL;
```

May VIZ be with you.