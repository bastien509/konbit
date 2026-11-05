<?php
// Copy to config.php on the server. config.php is denied by inc/.htaccess
// and ignored by git. Never put these values in JS, HTML or error text.
defined('FA') || exit;

return [
    'app' => [
        'name'     => 'Konbit',
        // Absolute URL of the site, no trailing slash. Used for PayPal return URLs.
        'base_url' => 'https://petiht.com/freelanceragent',
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'DB_NAME',
        'user' => 'DB_USER',
        'pass' => 'DB_PASSWORD',
    ],
    'ai' => [
        // Anthropic API key for the Konbit agent (server-side only).
        'anthropic_api_key' => '',
    ],
    'paypal' => [
        'mode'       => 'sandbox',   // 'sandbox' or 'live'
        'client_id'  => '',          // REST app with Payouts enabled
        'secret'     => '',
        'webhook_id' => '',          // from the app's webhook settings (payouts + capture events)
    ],
];
