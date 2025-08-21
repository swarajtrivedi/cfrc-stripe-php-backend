<?php
// server.php

require 'vendor/autoload.php';

// Load .env variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Extract allowed origins from environment
$allowed_origins = [
    $_ENV['FRONTEND_ORIGIN'],
    $_ENV['FRONTEND_ORIGIN_LOCAL'],
];

// Handle CORS dynamically
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit();
}

// Initialize Stripe
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

try {
    $amount = $input['amount'] ?? 0;
    $metadata = $input['metadata'] ?? [];
    $email = $metadata['email'] ?? null;

    if (!is_int($amount) || $amount <= 0) {
        throw new Exception("Amount must be a positive integer (in cents)");
    }

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode' => 'payment',
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'unit_amount' => $amount,
                'product_data' => [
                    'name' => 'Donation to CFRC',
                ],
            ],
            'quantity' => 1,
        ]],
        'success_url' => $_ENV['SUCCESS_URL'],
        'cancel_url' => $_ENV['CANCEL_URL'],
        'metadata' => array_merge($metadata, [
            'source' => 'cfrc-fundraiser-php',
        ]),
    ] + ($email ? ['customer_email' => $email] : []));

    echo json_encode([
        'id' => $session->id,
        'url' => $session->url,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}