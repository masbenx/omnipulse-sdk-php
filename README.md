# omnipulse/php-sdk - Official PHP SDK

The official PHP SDK for [OmniPulse](https://omnipulse.cloud) - a unified monitoring platform for Server Monitoring, APM, and Centralized Logs.

## Installation

```bash
composer require omnipulse/php-sdk
```

## Quick Start

```php
<?php
require_once 'vendor/autoload.php';

use OmniPulse\Client;
use OmniPulse\Logger;

// Initialize SDK
Client::init([
    'apiKey' => 'YOUR_X_INGEST_KEY',
    'serviceName' => 'my-php-app',
    'endpoint' => 'https://api.omnipulse.cloud'
]);

// Logging
Logger::info('Application started');
Logger::error('Something went wrong', ['userId' => 123]);

// Logs are automatically flushed on script shutdown
```

## Configuration Options

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `apiKey` | string | Yes | Your `X-Ingest-Key` from OmniPulse |
| `serviceName` | string | Yes | Name of your application |
| `endpoint` | string | Yes | OmniPulse backend URL |

## Features

- **Logging**: Send structured logs with metadata.
- **Buffering**: Logs are buffered and sent in batches for efficiency.
- **Fail-Safe**: Network errors are silently handled to prevent application crashes.
- **Async-ish**: Uses `fastcgi_finish_request()` when available to flush logs after response.

## License

MIT
