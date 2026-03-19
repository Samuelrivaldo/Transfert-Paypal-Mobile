<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$state = $_GET['state'] ?? '';
if ($state !== '') {
    $transfer = getTransferByState($state);
    if ($transfer !== null && ($transfer['status'] ?? '') === 'pending_paypal') {
        markTransferCanceled($state);
    }
}

echo 'Paiement annule par l utilisateur.';

?>