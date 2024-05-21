<?php

header('Content-Type: application/json');

if (strpos($_SERVER["REQUEST_URI"], '/flag-config/v1/config') !== false) {
    echo file_get_contents(__DIR__ . '/../data/ufc/flags-v1.json');
    return;
} else {
    return 'Not Found';
}
