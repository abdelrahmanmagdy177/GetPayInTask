<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestFlashSaleFlow extends Command
{
    protected $signature = 'test:flow';
    protected $description = 'Run a simple verification of the Flash-Sale flow';

    public function handle()
    {
        $baseUrl = 'http://127.0.0.1:8000/api';
        $productId = 1;

        $this->info("Starting Flash-Sale Flow Verification...");

        // 1. Create Hold
        $this->line("1. Creating Hold...");
        try {
            $response = Http::post("$baseUrl/holds", ['product_id' => $productId, 'quantity' => 1]);
        } catch (\Exception $e) {
            $this->error("Connection failed. Is the server running? " . $e->getMessage());
            return 1;
        }
        
        if ($response->failed()) {
            $this->error("Failed to create hold: " . $response->body());
            return 1;
        }
        
        $holdId = $response->json('hold_id');
        $this->info("   Hold Created: $holdId");

        // 2. Create Order
        $this->line("2. Creating Order...");
        $response = Http::post("$baseUrl/orders", ['hold_id' => $holdId]);

        if ($response->failed()) {
            $this->error("Failed to create order: " . $response->body());
            return 1;
        }

        $orderId = $response->json('order_id');
        $this->info("   Order Created: $orderId");

        // 3. Payment Webhook
        $this->line("3. Processing Payment...");
        $txnId = 'txn_' . uniqid();
        $response = Http::post("$baseUrl/payments/webhook", [
            'order_id' => $orderId,
            'status' => 'success',
            'transaction_id' => $txnId
        ]);

        if ($response->failed()) {
            $this->error("Failed to process payment: " . $response->body());
            return 1;
        }

        $this->info("   Payment Processed: " . $response->json('message'));

        // 4. Idempotency
        $this->line("4. Verifying Idempotency...");
        $response = Http::post("$baseUrl/payments/webhook", [
            'order_id' => $orderId,
            'status' => 'success',
            'transaction_id' => $txnId
        ]);

        $this->info("   Idempotency Response: " . $response->json('message'));

        $this->info("Verification Complete! ✅");
        return 0;
    }
}
