<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->unsignedSmallInteger('current_round')->default(1)->after('extension_minutes');
            $table->decimal('duration_hours', 8, 2)->default(24)->after('current_round');
            $table->unsignedInteger('display_order')->default(0)->index()->after('duration_hours');
        });

        DB::table('auctions')->orderBy('id')->chunkById(100, function ($auctions): void {
            foreach ($auctions as $auction) {
                $hours = 24.0;

                if ($auction->ends_at !== null && $auction->created_at !== null) {
                    $endsAt = Carbon::parse($auction->ends_at);
                    $createdAt = Carbon::parse($auction->created_at);
                    $diffMinutes = abs($createdAt->diffInMinutes($endsAt));
                    $hours = min(720, max(0.25, round($diffMinutes / 60, 2)));
                }

                DB::table('auctions')->where('id', $auction->id)->update([
                    'duration_hours' => $hours,
                    'display_order' => (int) $auction->id * 10,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['current_round', 'duration_hours', 'display_order']);
        });
    }
};
