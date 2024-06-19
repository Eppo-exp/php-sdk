# Eppo SDK for PHP

EppoClient is a client sdk for `eppo.cloud` randomization API.
It is used to retrieve the experiments data and put it to in-memory cache, and then get assignments information.

## Getting Started

Refer to our [SDK documentation](https://docs.geteppo.com/feature-flags/sdks/php) for how to install and use the SDK.

## Supported PHP Versions
This version of the SDK is compatible with PHP 7.3 and above.

## Install

```
composer require eppo/php-sdk
```

## Example

```php
<?php

use Eppo\EppoClient;

require __DIR__ . '/vendor/autoload.php';

$eppoClient = EppoClient::init(
   '<your_api_key>',
   '<base_url>', // optional, default https://fscdn.eppo.cloud/api
   $assignmentLogger, // optional, must be an instance of Eppo\Logger\LoggerInterface
   $cache // optional, must be an instance of PSR-16 SimpleCache\CacheInterface. If not passed, FileSystem cache will be used
   $httpClient // optional, must be an instance of PSR-18 ClientInterface. If not passed, Discovery will be used to find a suitable implementation
   $requestFactory // optional, must be an instance of PSR-17 Factory. If not passed, Discovery will be used to find a suitable implementation
);

$subjectAttributes = [ 'tier' => 2 ];
$assignment = $eppoClient->getStringAssignment('experimentalBackground', 'user123', $subjectAttributes, 'defaultValue');

if ($assignment !== 'defaultValue') {
    // do something
}

```

To make the experience of using the library faster, there is an option to start a background polling for randomization params.
This background job will start calling the Eppo API, updating the config in the cache.

For this, create a file, e.g. `eppo-poller.php` with the contents:

```php
$eppoClient = EppoClient::init(
   '<your_api_key>',
   '<base_url>', // optional, default https://fscdn.eppo.cloud/api
   $assignmentLogger, // optional, must be an instance of Eppo\LoggerInterface
   $cache // optional, must be an instance of PSR-16 SimpleInterface. If not passed, FileSystem cache will be used
   $httpClient // optional, must be an instance of PSR-18 ClientInterface. If not passed, Discovery will be used to find a suitable implementation
   $requestFactory // optional, must be an instance of PSR-17 Factory. If not passed, Discovery will be used to find a suitable implementation
);

$eppoClient->startPolling();
```

after this, run this script by:

`php eppo-poller.php`

This will start an indefinite process of polling the Eppo-api.

## Troubleshooting
### HTTP
This package uses the `php-http/discovery` package to automatically locate implementations of the various HTTP related 
PSR interfaces (ex: `ClientInterface`, `RequstFactory`, etc.). If your project does not depend on any library which can 
fulfill this need, you may see an exception such as follows.
>Fatal error: Uncaught Http\Discovery\Exception\DiscoveryFailedException: Could not find resource using any discovery strategy.

To solve this, simply require a suitable package, such as _guzzle_
>composer require guzzlehttp/guzzle:^7.0