# Eppo PHP SDK

[![Test and lint SDK](https://github.com/Eppo-exp/php-sdk/actions/workflows/run-tests.yml/badge.svg)](https://github.com/Eppo-exp/php-sdk/actions/workflows/run-tests.yml)

[Eppo](https://www.geteppo.com/) is a modular flagging and experimentation analysis tool. Eppo's PHP SDK is built to make assignments in multi-user server side contexts, compatible with PHP 7.3 and above. Before proceeding you'll need an Eppo account.

## Features

- Feature gates
- Kill switches
- Progressive rollouts
- A/B/n experiments
- Mutually exclusive experiments (Layers)
- Dynamic configuration

## Installation

```shell
composer require eppo/php-sdk
```

## Quick start

Begin by initializing a singleton instance of Eppo's client. Once initialized, the client can be used to make assignments anywhere in your app.

#### Initialize once

```php
<?php

use Eppo\EppoClient;

require __DIR__ . '/vendor/autoload.php';

$eppoClient = EppoClient::init("SDK-KEY-FROM-DASHBOARD");
```


#### Assign anywhere

```php
$assignment = $eppoClient->getStringAssignment(
    'new-user-onboarding', 
    $user->id, 
    ['country' => $user->country], 
    'control'
);
```

## Assignment functions

Every Eppo flag has a return type that is set once on creation in the dashboard. Once a flag is created, assignments in code should be made using the corresponding typed function: 

```php
getBooleanAssignment(...)
getNumericAssignment(...)
getIntegerAssignment(...)
getStringAssignment(...)
getJSONAssignment(...)
```

Each function has the same signature, but returns the type in the function name. For booleans use `getBooleanAssignment`, which has the following signature:

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

| Option | Type | Description | Default |
| ------ | ----- | ----- | ----- | 
| **`cache`**  | Instance of PSD-16 SimpleInterface | Cache used to store flag configuration. If not passed, FileSystem cache will be used | `null` |


## Assignment logger 

To use the Eppo SDK for experiments that require analysis, pass in a callback logging function to the `init` function on SDK initialization. The SDK invokes the callback to capture assignment data whenever a variation is assigned. The assignment data is needed in the warehouse to perform analysis.

The code below illustrates an example implementation of a logging callback using [Segment](https://segment.com/), but you can use any system you'd like. The only requirement is that the SDK receives a `logAssignment` callback function. Here we define an implementation of the Eppo `IAssignmentLogger` interface containing a single function named `logAssignment`:

```php
<?php

use Eppo\Logger\LoggerInterface;

class Logger implements LoggerInterface {
  public function logAssignment(
    string $experiment,
    string $variation,
    string $subject,
    string $timestamp,
    array $subjectAttributes = []
  ) {
    var_dump(
      json_encode([
        'experiment' => $experiment,
        'variation' => $variation,
        'subject' => $subject,
        'timestamp' => $timestamp,
      ]);
    );
  }
}
```

## Philosophy

Eppo's SDKs are built for simplicity, speed and reliability. Flag configurations are compressed and distributed over a global CDN (Fastly), typically reaching your servers in under 15ms. Server SDKs continue polling Eppo’s API at 30-second intervals. Configurations are then cached locally, ensuring that each assignment is made instantly. Evaluation logic within each SDK consists of a few lines of simple numeric and string comparisons. The typed functions listed above are all developers need to understand, abstracting away the complexity of the Eppo's underlying (and expanding) feature set.
