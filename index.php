<?php

use Eppo\EppoClient;

require __DIR__ . '/vendor/autoload.php';

$eppoClient = EppoClient::init('vMp7wOWhwGt6hTEqlXDGtF5MgldH4CyeA6aH-nCnh5g');

$assignment = $eppoClient->getAssignment('one','evans-dog-food');

var_dump($assignment);
