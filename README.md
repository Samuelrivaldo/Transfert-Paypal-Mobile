# Transfert PayPal -> MTN MoMo

Application PHP sans framework qui cree un paiement PayPal, capture le paiement au retour, puis declenche une operation MTN MoMo.

## Pre-requis

- PHP 8+ avec extensions `curl`, `json`, `pdo`, `pdo_sqlite`
- Certificats CA (`cacert.pem`) pour verification TLS

## Configuration (variables d environnement)

Definir ces variables avant de lancer le serveur:

- `APP_URL` (ex: `http://localhost:8080/transfert_paypal_momo`)
- `PAYPAL_CLIENT_ID`
- `PAYPAL_CLIENT_SECRET`
- `PAYPAL_MODE` (`sandbox` ou `live`)
- `MTN_SUBSCRIPTION_KEY`
- `MTN_API_USER_ID`
- `MTN_API_KEY`
- `MTN_ENV` (`sandbox` ou `production`)
- `MTN_FLOW` (`collection` ou `disbursement`)
- `CURL_CA_BUNDLE_PATH` (optionnel)

## Lancement

```powershell
C:\xampp\php\php.exe -S localhost:8080
```

Puis ouvrir:

- `http://localhost:8080/transfert_paypal_momo/index.html`

## Securite et flux

- Les secrets ne doivent jamais etre stockes en clair dans le depot.
- TLS est actif par defaut (`CURLOPT_SSL_VERIFYPEER=true`).
- Une transaction locale (SQLite `storage/app.sqlite`) lie strictement:
  - montant attendu
  - MSISDN attendu
  - commande PayPal attendue
- Le callback PayPal n utilise plus le numero MTN passe en querystring.

## MTN: clarification importante

- `MTN_FLOW=collection` utilise `requesttopay` (le wallet MTN paie).
- `MTN_FLOW=disbursement` n est pas implemente dans ce depot tant que les credentials/endpoint disbursement ne sont pas fournis.

## Endpoints

- `GET /index.html` - Formulaire de paiement
- `POST /payment.php` - Creation de commande PayPal
- `GET /execute-payment.php?state=...&token=...` - Callback PayPal
- `GET /cancel.php?state=...` - Annulation
- `GET /health/paypal.php` - Health check
