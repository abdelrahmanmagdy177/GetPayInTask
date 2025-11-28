<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use App\Models\Product;
use App\Models\Hold;

class TestConcurrency extends Command
{
    protected $signature = 'test:concurrency {--count=20 : Number of concurrent requests} {--id=1 : Product ID}';
    protected $description = 'Test concurrency by sending multiple async requests to create holds';

    public function handle()
    {
        $count = $this->option('count');
        $productId = $this->option('id');
        $baseUrl = 'http://127.0.0.1:8000/api';

        $this->info("Preparing test...");
        $product = Product::find($productId);
        if (!$product) {
            $this->error("Product $productId not found.");
            return 1;
        }
        
        $initialStock = 10;
        $product->stock = $initialStock;
        $product->save();
        
        $holdIds = Hold::where('product_id', $productId)->pluck('id');
        if ($holdIds->isNotEmpty()) {
            \App\Models\Order::whereIn('hold_id', $holdIds)->delete();
            Hold::whereIn('id', $holdIds)->delete();
        }

        $this->info("Product Stock reset to: $initialStock");
        $this->info("Sending $count concurrent requests to reserve 1 item each...");

        $start = microtime(true);
        
        $responses = Http::pool(function (Pool $pool) use ($count, $baseUrl, $productId) {
            $requests = [];
            for ($i = 0; $i < $count; $i++) {
                $requests[] = $pool->post("$baseUrl/holds", [
                    'product_id' => $productId,
                    'quantity' => 1
                ]);
            }
            return $requests;
        });

        $duration = round(microtime(true) - $start, 2);
        $this->info("Requests completed in {$duration}s");

        // 3. Analyze Results
        $successful = 0;
        $failed = 0;
        $errors = 0;

        foreach ($responses as $response) {
            if ($response->successful()) {
                $successful++;
            } elseif ($response->status() === 400) {
                $failed++;
            } else {
                $errors++;
                $this->error("Unexpected error: " . $response->status() . " - " . $response->body());
            }
        }

        // 4. Verification
        $product->refresh();
        $finalStock = $product->stock;
        $activeHolds = Hold::where('product_id', $productId)->where('status', 'active')->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Requests Sent', $count],
                ['Successful Holds', $successful],
                ['Failed (Out of Stock)', $failed],
                ['Unexpected Errors', $errors],
                ['Initial Stock', $initialStock],
                ['Final Stock', $finalStock],
                ['Active Holds in DB', $activeHolds],
            ]
        );

        if ($successful > $initialStock) {
            $this->error("❌ FAILURE: Overselling detected! Sold $successful items but only had $initialStock.");
            return 1;
        }

        if ($successful + $finalStock !== $initialStock) {
             $this->error("❌ FAILURE: Stock mismatch! Successful ($successful) + Final ($finalStock) != Initial ($initialStock)");
             return 1;
        }

        if ($successful === $initialStock && $finalStock === 0) {
            $this->info("✅ SUCCESS: Stock exhausted correctly without overselling.");
        } elseif ($successful < $initialStock && $finalStock > 0) {
             // This is fine if count < initialStock
             if ($count < $initialStock) {
                 $this->info("✅ SUCCESS: Requests processed correctly (Stock remaining).");
             } else {
                 $this->warn("⚠️ WARNING: Not all stock sold, but requests > stock. Might be network latency or other issues.");
             }
        } else {
            $this->info("✅ SUCCESS: Test completed.");
        }

        return 0;
    }
}
