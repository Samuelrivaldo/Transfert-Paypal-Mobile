<?php
header("Content-Security-Policy: default-src 'self' https://*.paypal.com https://*.paypalobjects.com https://*.ngrok.io; script-src 'self' 'unsafe-inline' https://*.paypal.com https://*.paypalobjects.com; connect-src 'self' https://*.paypal.com https://*.paypalobjects.com https://*.ngrok.io; img-src 'self' data: https://*.paypal.com https://*.paypalobjects.com;");

require 'functions.php';

$paymentId = $_GET['paymentId'] ?? null;
$payerId = $_GET['PayerID'] ?? null;
$msisdn = $_GET['msisdn'] ?? null;

if (!$paymentId || !$payerId || !$msisdn) {
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