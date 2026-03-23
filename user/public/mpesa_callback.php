<?php
define('BLOG_SYSTEM', true);
require_once '../shared/db_connect.php';
require_once '../shared/mpesa.php';

mpesa_ensure_tables($conn);

$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo 'No payload';
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo 'Invalid JSON';
    exit;
}

$stk = $data['Body']['stkCallback'] ?? null;
if (!$stk) {
    http_response_code(400);
    echo 'Missing stkCallback';
    exit;
}

$checkoutRequestId = (string)($stk['CheckoutRequestID'] ?? '');
$merchantRequestId = (string)($stk['MerchantRequestID'] ?? '');
$resultCode = isset($stk['ResultCode']) ? (int)$stk['ResultCode'] : null;
$resultDesc = (string)($stk['ResultDesc'] ?? '');

$metaItems = $stk['CallbackMetadata']['Item'] ?? [];
$meta = [];
foreach ($metaItems as $item) {
    if (!isset($item['Name'])) continue;
    $meta[$item['Name']] = $item['Value'] ?? null;
}

$amount = isset($meta['Amount']) ? (float)$meta['Amount'] : 0.0;
$receipt = isset($meta['MpesaReceiptNumber']) ? (string)$meta['MpesaReceiptNumber'] : null;
$phone = isset($meta['PhoneNumber']) ? (string)$meta['PhoneNumber'] : '';
$txnDate = isset($meta['TransactionDate']) ? (string)$meta['TransactionDate'] : null;
$status = ($resultCode === 0) ? 'paid' : 'failed';

if ($checkoutRequestId !== '') {
    $stmt = $conn->prepare("INSERT INTO mpesa_payments
        (merchant_request_id, checkout_request_id, amount, phone_number, status, result_code, result_desc, mpesa_receipt, transaction_date, raw_callback)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            merchant_request_id = VALUES(merchant_request_id),
            amount = VALUES(amount),
            phone_number = VALUES(phone_number),
            status = VALUES(status),
            result_code = VALUES(result_code),
            result_desc = VALUES(result_desc),
            mpesa_receipt = VALUES(mpesa_receipt),
            transaction_date = VALUES(transaction_date),
            raw_callback = VALUES(raw_callback)");
    $rawCallback = json_encode($data);
    $stmt->bind_param(
        'ssdssissss',
        $merchantRequestId,
        $checkoutRequestId,
        $amount,
        $phone,
        $status,
        $resultCode,
        $resultDesc,
        $receipt,
        $txnDate,
        $rawCallback
    );
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
