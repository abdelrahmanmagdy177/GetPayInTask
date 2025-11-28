<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HoldController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $product = Product::where('id', $request->product_id)->lockForUpdate()->first();
            
            if ($product->stock < $request->quantity) {
                DB::rollBack();
                return response()->json(['message' => 'Insufficient stock'], 400);
            }

            $product->stock -= $request->quantity;
            $product->save();

            $hold = Hold::create([
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'expires_at' => Carbon::now()->addMinutes(2),
                'status' => 'active',
            ]);

            DB::commit();

            \Illuminate\Support\Facades\Cache::forget("product_{$product->id}_stock");

            return response()->json([
                'hold_id' => $hold->id,
                'expires_at' => $hold->expires_at,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Hold failed', 'error' => $e->getMessage()], 500);
        }
    }
}
