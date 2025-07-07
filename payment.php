<?php
header("Content-Security-Policy: default-src 'self' https://*.paypal.com https://*.paypalobjects.com https://*.ngrok.io; script-src 'self' 'unsafe-inline' https://*.paypal.com https://*.paypalobjects.com; connect-src 'self' https://*.paypal.com https://*.paypalobjects.com https://*.ngrok.io; img-src 'self' data: https://*.paypal.com https://*.paypalobjects.com;");

require 'functions.php';

$amount = $_POST['amount'] ?? null;
$msisdn = $_POST['msisdn'] ?? null;

if (!$amount || !$msisdn) {
    die("Montant ou numéro MoMo manquant");
}

try {
    // Créer un paiement PayPal
    $payment = createPaypalPayment(
        $amount,
        "execute-payment.php?msisdn=" . urlencode($msisdn),
        "transfert_paypal_momo/cancel.php"
    );

    // Rediriger vers PayPal pour paiement
    header("Location: " . $payment->getApprovalLink());
    exit;

} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}


?>