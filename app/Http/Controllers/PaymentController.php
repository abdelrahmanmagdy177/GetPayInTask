<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Hold;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function webhook(Request $request)
    {
        
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'status' => 'required|in:success,failure',
            'transaction_id' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $order = Order::where('id', $request->order_id)->lockForUpdate()->first();

            // Idempotency Check
            if ($order->transaction_id === $request->transaction_id) {
                DB::commit();
                return response()->json(['message' => 'Processed (Idempotent)'], 200);
            }

            if ($order->payment_status !== 'pending') {
                 DB::commit();
                 return response()->json(['message' => 'Order already processed'], 200);
            }

            $order->transaction_id = $request->transaction_id;

            if ($request->status === 'success') {
                $order->payment_status = 'paid';
                $order->save();
            } else {
                $order->payment_status = 'failed';
                $order->save();

                // Release stock
                $hold = Hold::find($order->hold_id);
                if ($hold) {
                    $hold->status = 'released';
                    $hold->save();

                    $product = Product::where('id', $hold->product_id)->lockForUpdate()->first();
                    $product->stock += $hold->quantity;
                    $product->save();

                    \Illuminate\Support\Facades\Cache::forget("product_{$product->id}_stock");
                }
            }

            DB::commit();
            return response()->json(['message' => 'Webhook processed'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Webhook failed', 'error' => $e->getMessage()], 500);
        }
    }
}
