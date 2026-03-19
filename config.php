<?php

declare(strict_types=1);

function loadDotEnv(string $path, bool $overrideExisting = false): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        if ($name === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if ($overrideExisting || getenv($name) === false) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function envRequired(string $key): string
{
    $value = getenv($key);
    if ($value === false || trim($value) === '') {
        throw new RuntimeException(
            "Missing required environment variable: {$key}. ".
            "Create .env from .env.example and set a non-empty value."
        );
    }

    return trim($value);
}

function envWithDefault(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || trim($value) === '') {
        return $default;
    }

    return trim($value);
}

$certPath = __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem';
if (!is_file($certPath)) {
    $certPath = '';
}

loadDotEnv(__DIR__ . '/.env', true);
loadDotEnv(__DIR__ . '/.env.example', false);

define('APP_URL', rtrim(envWithDefault('APP_URL', ''), '/'));
define('APP_DEBUG', in_array(strtolower(envWithDefault('APP_DEBUG', '0')), ['1', 'true', 'yes'], true));
define('CURL_CA_BUNDLE_PATH', envWithDefault('CURL_CA_BUNDLE_PATH', $certPath));

// PayPal config (Checkout v2 REST API)
define('PAYPAL_CLIENT_ID', envRequired('PAYPAL_CLIENT_ID'));
define('PAYPAL_CLIENT_SECRET', envRequired('PAYPAL_CLIENT_SECRET'));
define('PAYPAL_MODE', strtolower(envWithDefault('PAYPAL_MODE', 'sandbox')));
define(
    'PAYPAL_API_BASE',
    PAYPAL_MODE === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com'
);

// MTN MoMo config
define('MTN_SUBSCRIPTION_KEY', envRequired('MTN_SUBSCRIPTION_KEY'));
define('MTN_API_USER_ID', envRequired('MTN_API_USER_ID'));
define('MTN_API_KEY', envRequired('MTN_API_KEY'));
define('MTN_ENV', strtolower(envWithDefault('MTN_ENV', 'sandbox'))); // production|sandbox
define('MTN_FLOW', strtolower(envWithDefault('MTN_FLOW', 'collection'))); // collection|disbursement

define(
    'MTN_COLLECTION_BASE',
    MTN_ENV === 'production' ? 'https://momodeveloper.mtn.com' : 'https://sandbox.momodeveloper.mtn.com'
);

// Security: Enforce HTTPS in production
if (PAYPAL_MODE === 'live' && !str_starts_with(APP_URL, 'https://')) {
    throw new RuntimeException(
        'HTTPS is required for live mode. Update APP_URL to use https:// scheme.'
    );
}

// Rate limiting configuration
define('RATE_LIMIT_WINDOW', 300);
define('RATE_LIMIT_ATTEMPTS', 10);
define('TRANSACTION_TIMEOUT', 3600);

?>