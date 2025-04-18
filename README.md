# Eppo PHP SDK

[![Run Tests](https://github.com/Eppo-exp/php-sdk/actions/workflows/run-tests.yml/badge.svg)](https://github.com/Eppo-exp/php-sdk/actions/workflows/run-tests.yml)
[![Package Integration Testing](https://github.com/Eppo-exp/php-sdk/actions/workflows/test-package.yml/badge.svg)](https://github.com/Eppo-exp/php-sdk/actions/workflows/test-package.yml)

[Eppo](https://www.geteppo.com/) is a modular flagging and experimentation analysis tool. Eppo's PHP SDK is built to
make assignments in multi-user server side contexts, compatible with PHP 7.3 and above. Before proceeding you'll need an
Eppo account.

## Features

- Feature gates
- Kill switches
- Progressive rollouts
- A/B/n experiments
- Mutually exclusive experiments (Layers)
- Dynamic configuration
- Multi-armed Contextual Bandits

## Installation

```shell
composer require eppo/php-sdk
```

## Quick start

Begin by initializing a singleton instance of Eppo's client. Once initialized, the client can be used to make
assignments anywhere in your app.

### Initialize once

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
```

### Assign anywhere

```php
$subjectAttributes = [ 'tier' => 2 ];
$assignment = $eppoClient->getStringAssignment('experimentalBackground', 'user123', $subjectAttributes, 'defaultValue');

if ($assignment !== 'defaultValue') {
    // do something
}
```

### Select a Bandit Action

This SDK supports [Multi-armed Contextual Bandits](https://docs.geteppo.com/contextual-bandits/).

```php
$subjectContext = [
    'age' => 30, // Gets interpreted as a Numeric Attribute
    'country' => 'uk', // Categorical Attribute
    'pricingTier' => '1'  // NOTE: Deliberately setting to string causes this to be treated as a Categorical Attribute
];

$actionContexts = [
    'nike' => [
        'brandLoyalty' => 0.4,
        'from' => 'usa'
    ],
    'adidas' => [
        'brandLoyalty' => 2,
        'from' => 'germany'
    ]
];

$result = $client->getBanditAction(
    'flagKey',
    'subjectKey',
    $subjectContext,
    $actionContexts,
    'defaultValue'
);

if ($result->action != null) {
    // Follow the Bandit action
    doAction($result->action);
} else {
    doSomething($result->variation);
}
```

## Assignment functions

Every Eppo flag has a return type that is set once on creation in the dashboard. Once a flag is created, assignments in
code should be made using the corresponding typed function:

```php
getBooleanAssignment(...)
getNumericAssignment(...)
getIntegerAssignment(...)
getStringAssignment(...)
getJSONAssignment(...)
```

Each function has the same signature, but returns the type in the function name. For booleans
use `getBooleanAssignment`, which has the following signature:

```php
function getBooleanAssignment(
    string $flagKey,
    string $subjectKey,
    array $subjectAttributes,
    bool $defaultValue
): bool
```

## Initialization options

The `init` function accepts the following optional configuration arguments.

| Option                 | Type                               | Description                                                                                         | Default |
|------------------------|------------------------------------|-----------------------------------------------------------------------------------------------------|---------| 
| **`cache`**            | Instance of PSD-16 SimpleInterface | Cache used to store flag configuration. If not passed, FileSystem cache will be used                | `null`  |
| **`assignmentLogger`** | AssignmentLogger/IBanditLogger     | Logs assignment events back to data warehoouse                                                      | `null`  |
| **`httpClient`**       | ClientInterface                    | For making HTTP requests. If not passed, Discovery will attempt to autoload an applicable pacakge   | `null`  |
| **`requestFactory`**   | RequestFactoryInterface            | Instance of PSR-17 Factory. If not passed, Discovery will be used to find a suitable implementation | null    |

## Assignment logger

To use the Eppo SDK for experiments that require analysis, pass in an implementation of the `LoggerInterface` to
the `init` function on SDK initialization. The SDK invokes the callback to capture assignment data whenever a variation
is assigned. The assignment data is needed in the warehouse to perform analysis.

The code below illustrates an example implementation of a logging callback using [Segment](https://segment.com/), but
you can use any system you'd like. The only requirement is that the SDK receives a `logAssignment` callback function.
Here we define an implementation of the Eppo `AssignmentLogger` interface containing a single function
named `logAssignment`:

```php
<?php

use Eppo\Logger\LoggerInterface;


use Eppo\Logger\AssignmentEvent;
use Eppo\Logger\LoggerInterface;

class SegmentLogger implements LoggerInterface
{
    public function logAssignment(AssignmentEvent $assignmentEvent): void
    {
        Segment::track([
            'event' => 'Flag Assignment for ' . $assignmentEvent->featureFlag,
            'userId' => $assignmentEvent->subject,
            'properties' => $assignmentEvent->toArray()
        ]);
    }
}
```

### Bandit Action Logging

When using Bandits, a different logging method is called. Your logging class must
implement [`IBanditLogger`](https://github.com/Eppo-exp/php-sdk/blob/main/src/Logger/IBanditLogger.php) instead
of `LoggerInterface`.

```php
<?php

use Eppo\Logger\AssignmentEvent;
use Eppo\Logger\BanditActionEvent;
use Eppo\Logger\IBanditLogger;

class SegmentLogger implements IBanditLogger
{
    public function logAssignment(AssignmentEvent $assignmentEvent): void
    {
        Segment::track([
            'event' => 'Flag Assignment for ' . $assignmentEvent->featureFlag,
            'userId' => $assignmentEvent->subject,
            'properties' => $assignmentEvent->toArray()
        ]);
    }

    public function logBanditAction(BanditActionEvent $banditActionEvent): void
    {
        Segment::track([
            'event' => 'Bandit Action Selected',
            'userId' => $banditActionEvent->subjectKey,
            'properties' => $banditActionEvent->toArray()
        ]);
    }
}
```

## Background Polling

To make the experience of using the library faster, there is an option to start a background polling for randomization
params.
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

```shell
php eppo-poller.php
```

This will start an indefinite process of polling the Eppo-api.

## Troubleshooting

### HTTP

This package uses the `php-http/discovery` package to automatically locate implementations of the various HTTP related
PSR interfaces (ex: `ClientInterface`, `RequstFactory`, etc.). If your project does not depend on any library which can
fulfill this need, you may see an exception such as follows.
> Fatal error: Uncaught Http\Discovery\Exception\DiscoveryFailedException: Could not find resource using any discovery
> strategy.

To solve this, simply require a suitable package, such as _guzzle_
> composer require guzzlehttp/guzzle:^7.0

## Philosophy

Eppo's SDKs are built for simplicity, speed and reliability. Flag configurations are compressed and distributed over a
global CDN (Fastly), typically reaching your servers in under 15ms. Server SDKs continue polling Eppo’s API at 30-second
intervals. Configurations are then cached locally, ensuring that each assignment is made instantly. Evaluation logic
within each SDK consists of a few lines of simple numeric and string comparisons. The typed functions listed above are
all developers need to understand, abstracting away the complexity of the Eppo's underlying (and expanding) feature set.
