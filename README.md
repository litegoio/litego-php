# litego-php

Simple Litego API wrapper class, in PHP.
Litego API documentation can be found here https://litego.io/documentation/

## Installation

Preferred way to install is with <a href="https://getcomposer.org/" rel="nofollow">Composer</a> as external
websocket client library is used.

Just add
```
"require": {
  "litegoio/litego-php": "1.1.*"
}
```
in your projects composer.json.

## Examples

Start by use-ing the class and creating an instance with test/live mode
```php
require('vendor/autoload.php');

use Litego\Litego;

$litego = new Litego();
```
If you want to use test mode
```php
$litego = new Litego('test');
```
After registration on https://litego.io and getting secret key and merchant ID values try to authenticate (get auth token for other requests)
Two ways how to get auth token:
- secret key and merchant ID
- refresh token (if exists). 
```php
$result = $litego->authenticate($merchantId, $secretKey);
```
or
```php
$result = $litego->reauthenticate($refreshToken $merchantId, $secretKey);
```

You will get auth token and refresh token values. Auth token will be used then for other API requests. Refresh token should be saved for reauthentication when auth token is expired.

Create charge
```php
$result = $litego->createCharge($authToken, $description, $amount_satoshi);
print_r($result);
```

Charges list 
```php
$result = $litego->chargesList($authToken, array(
'page' => 1,
'pageSize' => 5,
'paidOnly' => true,
));
print_r($result);
```
Get charge
```php
$result = $litego->getCharge($authToken, $chargeId);
print_r($result);
```



