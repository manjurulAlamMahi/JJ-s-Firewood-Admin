<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create pivot table for giveaway-specific entry package pricing
        if (!Schema::hasTable('giveaway_entry_package')) {
            Schema::create('giveaway_entry_package', function (Blueprint $table) {
                $table->id();
                $table->foreignId('giveaway_id')->constrained()->onDelete('cascade');
                $table->foreignId('entry_package_id')->constrained()->onDelete('cascade');
                $table->decimal('custom_price', 10, 2)->nullable();
                $table->timestamps();
                
                $table->unique(['giveaway_id', 'entry_package_id']);
            });
        }
        
        // Add columns to giveaways table if they don't exist
        Schema::table('giveaways', function (Blueprint $table) {
            if (!Schema::hasColumn('giveaways', 'scheduled_at')) {
                $table->datetime('scheduled_at')->nullable()->after('draw_time');
            }
            
            if (!Schema::hasColumn('giveaways', 'start_date')) {
                $table->date('start_date')->nullable()->after('scheduled_at');
            }
            
            if (!Schema::hasColumn('giveaways', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            
            if (!Schema::hasColumn('giveaways', 'max_entries_per_user')) {
                $table->integer('max_entries_per_user')->nullable()->after('status');
            }
            
            if (!Schema::hasColumn('giveaways', 'auto_draw')) {
                $table->boolean('auto_draw')->default(true)->after('winner_id');
            }
            
            if (!Schema::hasColumn('giveaways', 'allow_temp_entries')) {
                $table->boolean('allow_temp_entries')->default(true)->after('auto_draw');
            }
            
            if (!Schema::hasColumn('giveaways', 'winner_drawn_at')) {
                $table->datetime('winner_drawn_at')->nullable()->after('winner_announced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('giveaway_entry_package');
        
        Schema::table('giveaways', function (Blueprint $table) {
            $columns = [
                'scheduled_at',
                'start_date', 
                'end_date', 
                'max_entries_per_user', 
                'auto_draw', 
                'allow_temp_entries',
                'winner_drawn_at'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('giveaways', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};