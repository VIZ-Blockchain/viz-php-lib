# viz-php-lib

Native PHP class for VIZ Keys, Transaction, JsonRPC

## Features
- JsonRPC — native socket usage (all API methods, hostname cache, ssl flag, full result flag)
- Keys — private (sign), public (verify), shared, encoded keys support (wif, public encoded)
- Transaction — easy workflow, multi-signature support, multi-operations support, execute by JsonRPC
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

Look into test directory.

May VIZ be with you.