<?php
require 'vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');

// Only allow POST for this endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/create-checkout-session') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $amount = $input['amount'] ?? 0;
        $metadata = $input['metadata'] ?? [];
        $email = $metadata['email'] ?? null;

        if (!is_int($amount)) {
            http_response_code(400);
            echo json_encode(['error' => 'Amount must be an integer (in cents).']);
            exit;
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        $session = Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $amount,
                    'product_data' => [
                        'name' => 'Donation to CFRC'
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $_ENV['SUCCESS_URL'],
            'cancel_url' => $_ENV['CANCEL_URL'],
            'metadata' => $metadata,
            'customer_email' => $email,
        ]);

        echo json_encode(['id' => $session->id, 'url' => $session->url]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to create checkout session.', 'details' => $e->getMessage()]);
    }
} else {
    echo json_encode(['ok' => true, 'service' => 'cfrc-stripe-php-backend']);
}