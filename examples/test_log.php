<?php

// Manually require files if autoloader is missing
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/OmniPulse.php';

use OmniPulse\OmniPulse;

// Config - REPLACE WITH YOUR REAL KEY
$config = [
    'server_url' => 'http://localhost:8080',
    'token' => 'YOUR_TEST_INGEST_KEY', // <--- REPLACE ME
    'service_name' => 'php-sdk-test',
    'env' => 'development'
];

echo "Initializing OmniPulse...\n";
OmniPulse::init($config);

echo "Sending logs...\n";
OmniPulse::logger()->info('Hello from PHP SDK!', ['user_id' => 123]);
OmniPulse::logger()->warning('This is a warning', ['memory' => 'high']);
OmniPulse::logger()->error('Something went wrong', ['exception' => 'NullPointer']);
OmniPulse::logger()->debug('Debug info', ['var' => 'dump']);

echo "Logs buffered. Script ending (should flush now)...\n";

// Force sleep to allow curl to finish (since it's async-ish via timeout)
// minimal sleep just in case
usleep(100000); 
