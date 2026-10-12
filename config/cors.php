<?php

/*
| Vue sitesi (toptanhasat.com) API'ye (api.toptanhasat.com) farklı bir adresten, çerezle
| (Sanctum oturumu) istek atar. Bu yüzden sadece sitenin adreslerine izin verilir ve
| supports_credentials açıktır ("*" ile çerezli istek tarayıcıda reddedilir).
|
| CORS_ALLOWED_ORIGINS: virgülle ayrılmış liste. Boşsa FRONTEND_URL kullanılır.
| Örn. CORS_ALLOWED_ORIGINS=https://toptanhasat.com,https://www.toptanhasat.com
*/

$origins = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins ?: [rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
