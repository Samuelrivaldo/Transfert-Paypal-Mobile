<?php

declare(strict_types=1);

header("Content-Security-Policy: default-src 'self' https://*.paypal.com https://*.paypalobjects.com; script-src 'self' 'unsafe-inline' https://*.paypal.com https://*.paypalobjects.com; connect-src 'self' https://*.paypal.com https://*.paypalobjects.com; img-src 'self' data: https://*.paypal.com https://*.paypalobjects.com;");

require_once __DIR__ . '/config.php';

function http(string $url, array $headers, string $method = 'GET', ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    if (defined('CURL_CA_BUNDLE_PATH') && CURL_CA_BUNDLE_PATH !== '' && is_file(CURL_CA_BUNDLE_PATH)) {
        curl_setopt($ch, CURLOPT_CAINFO, CURL_CA_BUNDLE_PATH);
    }

    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    }

    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [$code, (string) $resp, $err];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // à sécuriser en prod
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$code, $resp, $err];
}

// Obtenir token MTN MoMo
function getMtnAccessToken()
{
    $url = "https://sandbox.momodeveloper.mtn.com/collection/token/";
    $headers = [
        "Authorization: Basic " . base64_encode(MTN_API_USER_ID . ':' . MTN_API_KEY),
        "Ocp-Apim-Subscription-Key: " . MTN_SUBSCRIPTION_KEY
    ];

    [$code, $resp, $err] = http($url, $headers, 'POST', '');

    if ($code !== 200) {
        throw new Exception("Erreur token MTN ($code): $resp");
    }

    $data = json_decode($resp, true);
    return $data['access_token'];
}

function generateUUIDv4()
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Envoyer l'argent via MTN requesttopay
function sendToMobileMoney($msisdn, $amount, $externalId)
{
    $token = getMtnAccessToken();

    $url = "https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay";

    $uuid = generateUUIDv4();

    $body = json_encode([
        "amount"       => $amount,
        "currency"     => "XOF",
        "externalId"   => $externalId,
        "payer"        => [
            "partyIdType" => "MSISDN",
            "partyId"     => $msisdn
        ],
        "payerMessage" => "Paiement reçu",
        "payeeNote"    => "Merci"
    ]);

    $headers = [
        "Authorization: Bearer $token",
        "X-Reference-Id: $uuid",
        "X-Target-Environment: " . MTN_ENV,
        "Ocp-Apim-Subscription-Key: " . MTN_SUBSCRIPTION_KEY,
        "Content-Type: application/json"
    ];

    [$code, $resp, $err] = http($url, $headers, 'POST', $body);

    if ($code !== 202) {
        throw new Exception("Erreur MTN requesttopay ($code): $resp");
    }

    return $uuid;
}

// Initialiser PayPal API Context
function getPaypalApiContext()
{
    $apiContext = new ApiContext(
        new OAuthTokenCredential(
            PAYPAL_CLIENT_ID,
            PAYPAL_CLIENT_SECRET
        )
    );

    $apiContext->setConfig([
        'mode' => PAYPAL_MODE,
    ]);

    return $apiContext;
}

// Créer un paiement PayPal
function createPaypalPayment($amount, $returnUrl, $cancelUrl)
{
    $apiContext = getPaypalApiContext();

    $payer = new Payer();
    $payer->setPaymentMethod("paypal");

    $amountObj = new Amount();
    $amountObj->setCurrency("EUR")->setTotal($amount);

    $transaction = new Transaction();
    $transaction->setAmount($amountObj)->setDescription("Paiement vers MTN MoMo");

    $redirectUrls = new RedirectUrls();
    $redirectUrls->setReturnUrl($returnUrl)
                 ->setCancelUrl($cancelUrl);

    $payment = new Payment();
    $payment->setIntent("sale")
        ->setPayer($payer)
        ->setTransactions([$transaction])
        ->setRedirectUrls($redirectUrls);

    try {
        $payment->create($apiContext);
    } catch (Exception $ex) {
        throw new Exception("Erreur création paiement PayPal : " . $ex->getMessage());
    }

    return $payment;
}

// Exécuter paiement PayPal
function executePaypalPayment($paymentId, $payerId)
{
    $apiContext = getPaypalApiContext();

    $payment = Payment::get($paymentId, $apiContext);

    $execution = new PaymentExecution();
    $execution->setPayerId($payerId);

    try {
        $result = $payment->execute($execution, $apiContext);
        return $result;
    } catch (Exception $ex) {
        throw new Exception("Erreur exécution paiement PayPal : " . $ex->getMessage());
    }
}


?>