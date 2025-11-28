<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'hold_id' => 'required|exists:holds,id',
        ]);

        try {
            DB::beginTransaction();

            $hold = Hold::where('id', $request->hold_id)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->first();

            if (!$hold) {
                DB::rollBack();
                return response()->json(['message' => 'Invalid or expired hold'], 400);
            }

            if ($hold->expires_at < Carbon::now()) {
                $hold->status = 'expired';
                $hold->save();
                
                $product = \App\Models\Product::where('id', $hold->product_id)->lockForUpdate()->first();
                $product->stock += $hold->quantity;
                $product->save();

                \Illuminate\Support\Facades\Cache::forget("product_{$product->id}_stock");

                DB::commit();
                return response()->json(['message' => 'Hold expired'], 400);
            }

            $hold->status = 'converted';
            $hold->save();

            $order = Order::create([
                'hold_id' => $hold->id,
                'payment_status' => 'pending',
            ]);

            DB::commit();

            return response()->json(['order_id' => $order->id, 'status' => 'pending_payment'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Order creation failed', 'error' => $e->getMessage()], 500);
        }
    }
}
