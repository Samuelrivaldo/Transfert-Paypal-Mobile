<?php

header("Content-Security-Policy: default-src 'self' https://*.paypal.com https://*.paypalobjects.com https://*.ngrok.io; script-src 'self' 'unsafe-inline' https://*.paypal.com https://*.paypalobjects.com; connect-src 'self' https://*.paypal.com https://*.paypalobjects.com https://*.ngrok.io; img-src 'self' data: https://*.paypal.com https://*.paypalobjects.com;");

// Récupérer les données depuis le formulaire HTML
$receiverPhone = $_POST['phone'] ?? '';
$amount = $_POST['amount'] ?? '';

// Nettoyer tout sauf chiffres
$receiverPhone = preg_replace('/\D/', '', $receiverPhone);

// Si le numéro commence par "2290", on enlève le zéro après l'indicatif
if (preg_match('/^2290\d{7}$/', $receiverPhone)) {
    $receiverPhone = '229' . substr($receiverPhone, 4);
}
// Si c’est un numéro local (8 chiffres), on ajoute "229"
elseif (preg_match('/^\d{8}$/', $receiverPhone)) {
    $receiverPhone = '229' . $receiverPhone;
}
// Si c’est juste "91208075", ça devient "22991208075"
elseif (preg_match('/^0\d{8}$/', $receiverPhone)) {
    $receiverPhone = '229' . substr($receiverPhone, 1);
}

// Le reste de la configuration ne change pas
$subscriptionKey = "f4f2da18c0db4033b897644dc8ef1fec";
$apiUserId       = "c675c32a-0127-4bbf-a67c-a171fd977e2b";
$apiKey          = "aa59f5b0111841adb33bef0a663e428d";
$externalId      = "TRANSACTION_" . uniqid();
/*****************  FONCTION GENERALE HTTP (cURL)  *****************/
function http($url, $headers, $method = 'GET', $body = '')
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ⚠️ À retirer en production
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

/*****************  OBTENIR LE TOKEN  *****************/
function getAccessToken($subscriptionKey, $apiUserId, $apiKey)
{
    $url = "https://sandbox.momodeveloper.mtn.com/collection/token/";
    $headers = [
        "Authorization: Basic " . base64_encode("$apiUserId:$apiKey"),
        "Ocp-Apim-Subscription-Key: $subscriptionKey"
    ];

    [$code, $resp, $err] = http($url, $headers, 'POST', '');

    if ($code === 0) {
        echo "<b>❌ Erreur réseau (code 0)</b><br>cURL Error: <pre>$err</pre>";
        exit;
    }

    $data = json_decode($resp, true);
    if ($code !== 200 || empty($data['access_token'])) {
        echo "<b>❌ Erreur token (code $code)</b><br>Réponse : <pre>$resp</pre>";
        exit;
    }

    return $data['access_token'];
}

/***********  VÉRIFIER SI LE COMPTE MO MO EST ACTIF  ***********/
function isAccountActive($token, $subscriptionKey, $msisdn)
{
    $url = "https://sandbox.momodeveloper.mtn.com/collection/v1_0/accountholder/msisdn/$msisdn/active";
    $headers = [
        "Authorization: Bearer $token",
        "X-Target-Environment: sandbox",
        "Ocp-Apim-Subscription-Key: $subscriptionKey"
    ];

    [$code, $resp, $err] = http($url, $headers);
    if ($code === 0) {
        echo "<b>❌ Erreur réseau lors de la vérification du compte</b><br>cURL Error: <pre>$err</pre>";
        exit;
    }

    $data = json_decode($resp, true);
    if ($code === 200 && isset($data['result']) && $data['result'] === true) {
        return true;
    }

    echo "<b>❌ Compte MoMo inactif ou introuvable (code $code)</b><br>Réponse : <pre>$resp</pre>";
    exit;
}

/*****************  REQUEST‑TO‑PAY (ENVOI D'ARGENT)  *****************/
function generateUUIDv4() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function sendToMobileMoney($token, $subscriptionKey, $msisdn, $amount, $externalId)
{
    $url = "https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay";

    $uuid = generateUUIDv4();

    $body = json_encode([
        "amount"       => $amount,
        "currency"     => "EUR",
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
        "X-Target-Environment: sandbox",
        "Ocp-Apim-Subscription-Key: $subscriptionKey",
        "Content-Type: application/json"
    ];

    [$code, $resp, $err] = http($url, $headers, 'POST', $body);

    if ($code === 0) {
        echo "<b>❌ Erreur réseau lors de l'envoi</b><br>cURL Error: <pre>$err</pre>";
        exit;
    }

    if ($code === 202) {
        echo "✅ Transaction lancée avec succès !<br>";
        echo "ID de référence : <b>$uuid</b><br>";
    } else {
        echo "<b>❌ Échec du transfert (code $code)</b><br>";
        echo "Réponse brute : <pre>$resp</pre>";
        echo "Erreur cURL (si présente) : <pre>$err</pre>";
        echo "<br><br><b>Données envoyées :</b><pre>$body</pre>";
    }

    return $uuid;
}

function checkTransactionStatus($token, $subscriptionKey, $referenceId) {
    $url = "https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay/$referenceId";

    $headers = [
        "Authorization: Bearer $token",
        "X-Target-Environment: sandbox",
        "Ocp-Apim-Subscription-Key: $subscriptionKey"
    ];

    [$code, $resp, $err] = http($url, $headers);

    if ($code === 0) {
        echo "<b>❌ Erreur réseau lors du suivi</b><br>cURL Error: <pre>$err</pre>";
        return null;
    }

    if ($code === 200) {
        $data = json_decode($resp, true);
        return $data['status'] ?? 'Status inconnu';
    } else {
        echo "<b>❌ Erreur lors du suivi (code $code)</b><br>Réponse : <pre>$resp</pre>";
        return null;
    }
}

/*********************  WORKFLOW COMPLET  ************************/
$token = getAccessToken($subscriptionKey, $apiUserId, $apiKey);
isAccountActive($token, $subscriptionKey, $receiverPhone);
sendToMobileMoney($token, $subscriptionKey, $receiverPhone, $amount, $externalId);
?>