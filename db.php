<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('PDO SQLite extension is required.');
    }

    $storageDir = __DIR__ . '/storage';
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        throw new RuntimeException('Unable to create storage directory.');
    }

    $dbPath = $storageDir . '/app.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS transfers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            state_token TEXT UNIQUE NOT NULL,
            msisdn TEXT NOT NULL,
            amount_eur TEXT NOT NULL,
            status TEXT NOT NULL,
            paypal_order_id TEXT,
            mtn_reference_id TEXT,
            error_message TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    return $pdo;
}

function generateStateToken(): string
{
    return bin2hex(random_bytes(16));
}

function createTransfer(string $msisdn, string $amount): string
{
    $state = generateStateToken();
    $now = gmdate('c');

    $stmt = db()->prepare(
        'INSERT INTO transfers (state_token, msisdn, amount_eur, status, created_at, updated_at)
         VALUES (:state, :msisdn, :amount, :status, :created, :updated)'
    );

    $stmt->execute([
        ':state' => $state,
        ':msisdn' => $msisdn,
        ':amount' => $amount,
        ':status' => 'pending_paypal',
        ':created' => $now,
        ':updated' => $now,
    ]);

    return $state;
}

function attachPayPalOrder(string $state, string $orderId): void
{
    $stmt = db()->prepare(
        'UPDATE transfers
         SET paypal_order_id = :order_id, updated_at = :updated
         WHERE state_token = :state'
    );

    $stmt->execute([
        ':order_id' => $orderId,
        ':updated' => gmdate('c'),
        ':state' => $state,
    ]);
}

function getTransferByState(string $state): ?array
{
    $stmt = db()->prepare('SELECT * FROM transfers WHERE state_token = :state LIMIT 1');
    $stmt->execute([':state' => $state]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function updateTransferStatus(string $state, string $status, ?string $error = null): void
{
    $stmt = db()->prepare(
        'UPDATE transfers
         SET status = :status, error_message = :error, updated_at = :updated
         WHERE state_token = :state'
    );

    $stmt->execute([
        ':status' => $status,
        ':error' => $error,
        ':updated' => gmdate('c'),
        ':state' => $state,
    ]);
}

function markTransferPaid(string $state): void
{
    updateTransferStatus($state, 'paid_paypal');
}

function markTransferMtnRequested(string $state, string $referenceId): void
{
    $stmt = db()->prepare(
        'UPDATE transfers
         SET status = :status, mtn_reference_id = :reference, updated_at = :updated
         WHERE state_token = :state'
    );

    $stmt->execute([
        ':status' => 'requested_mtn',
        ':reference' => $referenceId,
        ':updated' => gmdate('c'),
        ':state' => $state,
    ]);
}

function markTransferFailed(string $state, string $error): void
{
    updateTransferStatus($state, 'failed', $error);
}

function markTransferCanceled(string $state): void
{
    updateTransferStatus($state, 'canceled');
}

?>
