<?php

header('Content-Type: application/json');

$ufcFile = getenv('UFC');
$banditFile = __DIR__ . '/../data/ufc/bandit-models-v1.json';

if (strpos($_SERVER["REQUEST_URI"], '/flag-config/v1/config') !== false) {
    echo file_get_contents($ufcFile);
    return;
} elseif (strpos($_SERVER["REQUEST_URI"], '/flag-config/v1/bandits') !== false) {
    echo file_get_contents($banditFile);
    return;
} else {
    return 'Not Found';
}
