<?php
header("Content-Security-Policy: default-src 'self' https://*.paypal.com https://*.paypalobjects.com https://*.ngrok.io; script-src 'self' 'unsafe-inline' https://*.paypal.com https://*.paypalobjects.com; connect-src 'self' https://*.paypal.com https://*.paypalobjects.com https://*.ngrok.io; img-src 'self' data: https://*.paypal.com https://*.paypalobjects.com;");

// PAYPAL CONFIG
define('PAYPAL_CLIENT_ID', 'AaQkDZjsVoiVHV2EnjNc6mVXNSntYto3zFadMlxTTxpbWSkpTrRifhXnSTFwbQOEY7-qEcF4gEuSEEO_');
define('PAYPAL_CLIENT_SECRET', 'ENBBMF0GNGz-eXHvI1fWC_G3OW2Yphlq36GQHvtESKtBWrVl4-tBwe9hONttcP6GMkO-q4zg1jr25jDC');
define('PAYPAL_MODE', 'sandbox'); // 'live' pour prod

// MTN MoMo CONFIG
define('MTN_SUBSCRIPTION_KEY', 'f4f2da18c0db4033b897644dc8ef1fec');
define('MTN_API_USER_ID', 'c675c32a-0127-4bbf-a67c-a171fd977e2b');
define('MTN_API_KEY', 'aa59f5b0111841adb33bef0a663e428d');
define('MTN_ENV', 'sandbox');


?>