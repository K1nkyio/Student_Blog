<?php
require_once __DIR__ . '/mpesa_config.php';

function mpesa_base_url(): string {
    return MPESA_MODE === 'live' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
}

function mpesa_is_configured(): bool {
    return MPESA_MODE !== 'mock'
        && MPESA_CONSUMER_KEY !== ''
        && MPESA_CONSUMER_SECRET !== ''
        && MPESA_SHORTCODE !== ''
        && MPESA_PASSKEY !== ''
        && MPESA_CALLBACK_URL !== '';
}

function mpesa_format_phone(string $phone): ?string {
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '') return null;
    if (strpos($digits, '254') === 0 && strlen($digits) === 12) return $digits;
    if ($digits[0] === '0' && strlen($digits) === 10) return '254' . substr($digits, 1);
    if ($digits[0] === '7' && strlen($digits) === 9) return '254' . $digits;
    return null;
}

function mpesa_ensure_tables(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS mpesa_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        merchant_request_id VARCHAR(100) DEFAULT NULL,
        checkout_request_id VARCHAR(100) NOT NULL UNIQUE,
        amount DECIMAL(10,2) NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        account_reference VARCHAR(50) DEFAULT NULL,
        status ENUM('pending','paid','failed','mock') DEFAULT 'pending',
        result_code INT DEFAULT NULL,
        result_desc VARCHAR(255) DEFAULT NULL,
        mpesa_receipt VARCHAR(50) DEFAULT NULL,
        transaction_date VARCHAR(30) DEFAULT NULL,
        raw_request TEXT DEFAULT NULL,
        raw_callback TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function mpesa_get_access_token(?string &$error = null): ?string {
    $url = mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials';
    $auth = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $auth],
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = 'Failed to connect to M-Pesa. ' . curl_error($ch);
        curl_close($ch);
        return null;
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($response, true);
    if ($status >= 400 || empty($data['access_token'])) {
        $error = 'Failed to get M-Pesa access token.';
        return null;
    }
    return $data['access_token'];
}

function mpesa_initiate_stk_push(float $amount, string $phone, string $accountRef, string $desc): array {
    if (!mpesa_is_configured()) {
        return [
            'success' => true,
            'mode' => 'mock',
            'message' => 'M-Pesa is in mock mode. Add credentials to enable real payments.',
            'checkoutRequestId' => 'MOCK_' . uniqid(),
            'merchantRequestId' => 'MOCK_MR_' . uniqid(),
            'raw' => ['mock' => true],
        ];
    }

    $error = null;
    $token = mpesa_get_access_token($error);
    if ($token === null) {
        return ['success' => false, 'message' => $error ?: 'M-Pesa token error'];
    }

    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => (int)round($amount),
        'PartyA' => $phone,
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => $phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $accountRef,
        'TransactionDesc' => $desc,
    ];

    $url = mpesa_base_url() . '/mpesa/stkpush/v1/processrequest';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $msg = 'Failed to reach M-Pesa. ' . curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'message' => $msg];
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($response, true);

    if ($status >= 400 || empty($data['CheckoutRequestID'])) {
        $msg = $data['errorMessage'] ?? $data['ResponseDescription'] ?? 'STK push failed.';
        return ['success' => false, 'message' => $msg, 'raw' => $data];
    }

    return [
        'success' => true,
        'mode' => MPESA_MODE,
        'message' => $data['CustomerMessage'] ?? 'STK push sent.',
        'checkoutRequestId' => $data['CheckoutRequestID'],
        'merchantRequestId' => $data['MerchantRequestID'] ?? null,
        'raw' => $data,
    ];
}
