<?php
declare(strict_types=1);

/**
 * Application Configuration
 * Loads environment variables from .env file
 */

// Load from .env.local first, then .env
$envFile = __DIR__ . '/.env.local';
if (!file_exists($envFile)) {
    $envFile = __DIR__ . '/.env';
}

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || strpos(trim($line), '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, ' "'"'"'');
        if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            putenv("$key=$value");
        }
    }
}

// Helper function to get env var with default
function envWithDefault(string $key, string $default = ''): string {
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}

// Application Configuration
define('APP_URL', envWithDefault('APP_URL', 'http://localhost:8080/transfert_paypal_momo'));
define('APP_DEBUG', (int) envWithDefault('APP_DEBUG', '0') === 1);

// PayPal Configuration
define('PAYPAL_CLIENT_ID', envWithDefault('PAYPAL_CLIENT_ID', ''));
define('PAYPAL_CLIENT_SECRET', envWithDefault('PAYPAL_CLIENT_SECRET', ''));
define('PAYPAL_MODE', envWithDefault('PAYPAL_MODE', 'sandbox'));

// MTN Configuration
define('MTN_SUBSCRIPTION_KEY', envWithDefault('MTN_SUBSCRIPTION_KEY', ''));
define('MTN_API_USER_ID', envWithDefault('MTN_API_USER_ID', ''));
define('MTN_API_KEY', envWithDefault('MTN_API_KEY', ''));
define('MTN_ENV', envWithDefault('MTN_ENV', 'sandbox'));
define('MTN_FLOW', envWithDefault('MTN_FLOW', 'collection'));

// Security: Enforce HTTPS in production
if (PAYPAL_MODE === 'live' && !str_starts_with(APP_URL, 'https://')) {
    throw new RuntimeException(
        'HTTPS is required for live mode. Update APP_URL to use https:// scheme.'
    );
}

// Development mode warning
if (APP_DEBUG && PAYPAL_MODE === 'live') {
    error_log('WARNING: APP_DEBUG is enabled in live mode. This may expose sensitive information.');
}

// Validate required credentials
$requiredEnvVars = [
    'PAYPAL_CLIENT_ID',
    'PAYPAL_CLIENT_SECRET',
    'MTN_SUBSCRIPTION_KEY',
    'MTN_API_USER_ID',
    'MTN_API_KEY',
];

foreach ($requiredEnvVars as $var) {
    if (empty($GLOBALS[strtolower($var)] ?? constant($var))) {
        throw new RuntimeException("Required environment variable missing: $var");
    }
}

// SSL Certificate Path (optional)
$curlCaPath = envWithDefault('CURL_CA_BUNDLE_PATH', '');
if ($curlCaPath && file_exists($curlCaPath)) {
    define('CURL_CA_BUNDLE', $curlCaPath);
}

// Rate limiting configuration
define('RATE_LIMIT_WINDOW', 300);
define('RATE_LIMIT_ATTEMPTS', 10);

// Transaction timeout
define('TRANSACTION_TIMEOUT', 3600);