<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

$state = $_GET['state'] ?? '';
$orderId = $_GET['token'] ?? '';

if ($state === '' || $orderId === '') {
    http_response_code(400);
    exit('Donnees manquantes pour finaliser le paiement.');
}

$transfer = getTransferByState($state);
if ($transfer === null) {
    http_response_code(404);
    exit('Transaction introuvable.');
}

if (($transfer['status'] ?? '') !== 'pending_paypal') {
    http_response_code(409);
    exit('Cette transaction a deja ete traitee.');
}

if (($transfer['paypal_order_id'] ?? '') !== $orderId) {
    markTransferFailed($state, 'PayPal order mismatch.');
    http_response_code(400);
    exit('Incoherence de commande PayPal.');
    die("Données manquantes pour finaliser le paiement");
}

try {
    // Exécuter paiement PayPal
    $paymentResult = executePaypalPayment($paymentId, $payerId);

    if ($paymentResult->getState() === 'approved') {
        $amount = $paymentResult->getTransactions()[0]->getAmount()->getTotal();

        // Lancer le transfert MTN MoMo
        $externalId = "PAYPAL_TO_MTN_" . uniqid();
        $uuid = sendToMobileMoney($msisdn, $amount, $externalId);

        echo "✅ Paiement PayPal validé, transfert MTN lancé.<br>";
        echo "Référence MTN : $uuid<br>";
    } else {
        echo "Le paiement PayPal n'a pas été approuvé.";
    }
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}

?>