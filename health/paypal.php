<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../functions.php';

function maskSecret(string $value, int $head = 6, int $tail = 4): string
{
    $len = strlen($value);
    if ($len <= $head + $tail) {
        return str_repeat('*', max(3, $len));
    }

    return substr($value, 0, $head) . str_repeat('*', $len - ($head + $tail)) . substr($value, -$tail);
}

function parsePaypalError(string $message): array
{
    $jsonPos = strpos($message, '{');
    if ($jsonPos === false) {
        return ['raw' => $message];
    }

    $json = substr($message, $jsonPos);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return ['raw' => $message];
    }

    return [
        'name' => $data['name'] ?? null,
        'issue' => $data['details'][0]['issue'] ?? null,
        'description' => $data['details'][0]['description'] ?? null,
        'debug_id' => $data['debug_id'] ?? null,
        'message' => $data['message'] ?? null,
    ];
}

$scope = strtolower((string) ($_GET['scope'] ?? 'oauth'));
if (!in_array($scope, ['oauth', 'order'], true)) {
    $scope = 'oauth';
}

$result = [
    'ok' => false,
    'scope' => $scope,
    'timestamp_utc' => gmdate('c'),
    'config' => [
        'paypal_mode' => PAYPAL_MODE,
        'paypal_api_base' => PAYPAL_API_BASE,
        'app_url' => APP_URL,
        'client_id_masked' => maskSecret(PAYPAL_CLIENT_ID),
    ],
    'checks' => [
        'oauth' => ['ok' => false],
        'create_order' => ['ok' => null],
    ],
];

try {
    $token = getPaypalAccessToken();
    $result['checks']['oauth'] = [
        'ok' => true,
        'token_received' => strlen($token) > 20,
    ];
} catch (Throwable $e) {
    $result['checks']['oauth'] = [
        'ok' => false,
        'error' => parsePaypalError($e->getMessage()),
    ];

    http_response_code(500);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($scope === 'order') {
    try {
        $order = createPaypalOrder(
            '1.00',
            APP_URL . '/execute-payment.php?state=healthcheck',
            APP_URL . '/cancel.php?state=healthcheck'
        );

        $result['checks']['create_order'] = [
            'ok' => true,
            'order_id' => $order['order_id'] ?? null,
            'approve_url_host' => isset($order['approve_url']) ? parse_url($order['approve_url'], PHP_URL_HOST) : null,
        ];
    } catch (Throwable $e) {
        $result['checks']['create_order'] = [
            'ok' => false,
            'error' => parsePaypalError($e->getMessage()),
        ];

        http_response_code(500);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$result['ok'] = true;
http_response_code(200);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>
