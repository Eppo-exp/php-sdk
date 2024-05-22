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
   "<your_api_key>",
   "<base_url>", // optional, default https://fscdn.eppo.cloud/api
   $assignmentLogger, // optional, must be an instance of Eppo\Logger\LoggerInterface
   $cache // optional, must be an instance of PSR-16 CacheInterface. If not passed, FileSystem cache will be used
);

$subjectAttributes = [];
$assignment = $eppoClient->getAssignment('subject-1', 'experiment_5', $subjectAttributes);

if ($assignment === 'control') {
    // do something
}

```

To make the experience of using the library faster, there is an option to start a background polling for randomization params.
This way background job will start calling the Eppo api, updating the config in the cache.

For this, create a file, e.g. `eppo-poller.php` with the contents:

```php
$eppoClient = EppoClient::init(
   "<your_api_key>",
   "<base_url>", // optional, default https://fscdn.eppo.cloud/api
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
