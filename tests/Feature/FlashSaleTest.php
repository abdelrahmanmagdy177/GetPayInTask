<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Product;
use App\Models\Hold;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_endpoint_returns_correct_stock()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 100,
            'stock' => 10
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
                 ->assertJson(['stock' => 10]);
    }

    public function test_hold_creation_reduces_stock()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 100,
            'stock' => 10
        ]);

        $response = $this->postJson('/api/holds', [
            'product_id' => $product->id,
            'quantity' => 2
        ]);

        $response->assertStatus(201);
        
        $this->assertEquals(8, $product->fresh()->stock);
    }

    public function test_hold_expiration_releases_stock_via_command()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 100,
            'stock' => 10
        ]);

        $hold = Hold::create([
            'product_id' => $product->id,
            'quantity' => 2,
            'expires_at' => Carbon::now()->subMinute(), // Expired
            'status' => 'active'
        ]);

        // Manually decrement stock as controller would
        $product->stock -= 2;
        $product->save();

        $this->assertEquals(8, $product->fresh()->stock);

        Artisan::call('holds:release');

        $this->assertEquals(10, $product->fresh()->stock);
        $this->assertEquals('expired', $hold->fresh()->status);
    }

    public function test_order_creation_converts_hold()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 100,
            'stock' => 10
        ]);

        $hold = Hold::create([
            'product_id' => $product->id,
            'quantity' => 1,
            'expires_at' => Carbon::now()->addMinutes(2),
            'status' => 'active'
        ]);

        $response = $this->postJson('/api/orders', [
            'hold_id' => $hold->id
        ]);

        $response->assertStatus(201);
        $this->assertEquals('converted', $hold->fresh()->status);
        $this->assertDatabaseHas('orders', ['hold_id' => $hold->id]);
    }

    public function test_payment_webhook_idempotency()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 100,
            'stock' => 10
        ]);

        $hold = Hold::create([
            'product_id' => $product->id,
            'quantity' => 1,
            'expires_at' => Carbon::now()->addMinutes(2),
            'status' => 'converted'
        ]);

        $order = Order::create([
            'hold_id' => $hold->id,
            'payment_status' => 'pending'
        ]);

        $payload = [
            'order_id' => $order->id,
            'status' => 'success',
            'transaction_id' => 'tx_123'
        ];

        // First call
        $response1 = $this->postJson('/api/payments/webhook', $payload);
        $response1->assertStatus(200);
        $this->assertEquals('paid', $order->fresh()->payment_status);

        // Second call (Idempotent)
        $response2 = $this->postJson('/api/payments/webhook', $payload);
        $response2->assertStatus(200);
    }

    public function test_caching_and_invalidation()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 100,
            'stock' => 10
        ]);

        // Cache miss
        $this->getJson("/api/products/{$product->id}");
        $this->assertTrue(Cache::has("product_{$product->id}_stock"));

        // Create hold -> should invalidate
        $this->postJson('/api/holds', [
            'product_id' => $product->id,
            'quantity' => 1
        ]);

        $this->assertFalse(Cache::has("product_{$product->id}_stock"));

        // Re-cache
        $this->getJson("/api/products/{$product->id}");
        $this->assertTrue(Cache::has("product_{$product->id}_stock"));
    }
}
