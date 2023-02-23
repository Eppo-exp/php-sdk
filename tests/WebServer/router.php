<?php

header('Content-Type: application/json');

if ($_SERVER["REQUEST_URI"] === '/randomized_assignment/v2/config') {
    echo file_get_contents(__DIR__ . '/../data/rac-experiments-v2.json');
    return;
} else {
    return 'Not Found';
}
