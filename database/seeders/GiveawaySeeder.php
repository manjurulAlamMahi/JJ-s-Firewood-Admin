<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Giveaway;
use Carbon\Carbon;

class GiveawaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Giveaway::create([
            'prize_name' => 'Prize 1',
            'description' => 'This is the first sample giveaway.',
            'image' => null,
            'prize_value' => 100,
            'prize_type' => 'bundle',
            'start_date' => Carbon::now()->subDays(2),
            'draw_date' => Carbon::now()->addDays(6),
            'status' => 1,
            'total_entries' => 0,
            'winner_id' => null,
            'winner_announced_at' => null,
        ]);

        Giveaway::create([
            'prize_name' => 'Prize 2',
            'description' => 'This is the second sample giveaway.',
            'image' => null,
            'prize_value' => 200,
            'prize_type' => 'discount',
            'start_date' => Carbon::now()->subDays(1),
            'draw_date' => Carbon::now()->addDays(5),
            'status' => 0,
            'total_entries' => 0,
            'winner_id' => null,
            'winner_announced_at' => null,
        ]);
    }
}