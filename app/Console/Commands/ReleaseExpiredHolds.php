<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hold;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReleaseExpiredHolds extends Command
{
    protected $signature = 'holds:release';
    protected $description = 'Release expired holds and return stock';

    public function handle()
    {
        $expiredHolds = Hold::where('status', 'active')
                            ->where('expires_at', '<', Carbon::now())
                            ->get();

        foreach ($expiredHolds as $hold) {
            try {
                DB::beginTransaction();
                
                // Re-fetch with lock to ensure no race condition
                $holdLocked = Hold::where('id', $hold->id)->lockForUpdate()->first();

                if ($holdLocked && $holdLocked->status === 'active') {
                    $holdLocked->status = 'expired';
                    $holdLocked->save();

                    $product = Product::where('id', $holdLocked->product_id)->lockForUpdate()->first();
                    $product->stock += $holdLocked->quantity;
                    $product->save();
                    
                    \Illuminate\Support\Facades\Cache::forget("product_{$product->id}_stock");

                    $this->info("Released hold {$hold->id}");
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed to release hold {$hold->id}: " . $e->getMessage());
            }
        }
    }
}
