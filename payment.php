<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

function normalizeMsisdn(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw) ?? '';

    if (preg_match('/^\d{8}$/', $digits)) {
        return '229' . $digits;
    }

    if (preg_match('/^0\d{8}$/', $digits)) {
        return '229' . substr($digits, 1);
    }

    if (preg_match('/^229\d{8}$/', $digits)) {
        return $digits;
    }

    return $digits;
}

$amountRaw = $_POST['amount'] ?? '';
$msisdnRaw = $_POST['msisdn'] ?? '';

if (!is_numeric($amountRaw)) {
    http_response_code(400);
    exit('Montant invalide.');
}

$amount = number_format((float) $amountRaw, 2, '.', '');
if ((float) $amount <= 0.0) {
    http_response_code(400);
    exit('Le montant doit etre superieur a 0.');
}

$msisdn = normalizeMsisdn($msisdnRaw);
if (!preg_match('/^229\d{8}$/', $msisdn)) {
    http_response_code(400);
    exit('Numero MTN invalide. Format attendu: 229XXXXXXXX.');
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