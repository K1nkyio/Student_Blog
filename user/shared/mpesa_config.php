<?php
// M-Pesa Daraja STK Push configuration.
// Switch MPESA_MODE to 'sandbox' or 'live' when you add real credentials.
define('MPESA_MODE', 'mock'); // mock | sandbox | live

// Daraja app credentials
define('MPESA_CONSUMER_KEY', '');
define('MPESA_CONSUMER_SECRET', '');

// STK Push credentials
define('MPESA_SHORTCODE', '');
define('MPESA_PASSKEY', '');

// Callback URL must be publicly reachable (no localhost).
define('MPESA_CALLBACK_URL', 'https://your-domain.example/user/public/mpesa_callback.php');
