<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pivot table for giveaway-specific entry package pricing
        Schema::create('giveaway_entry_package', function (Blueprint $table) {
            $table->id();
            $table->foreignId('giveaway_id')->constrained()->onDelete('cascade');
            $table->foreignId('entry_package_id')->constrained()->onDelete('cascade');
            $table->decimal('custom_price', 10, 2)->nullable(); // Override price for this giveaway
            $table->timestamps();
            
            $table->unique(['giveaway_id', 'entry_package_id']);
        });
        
        // Add new fields to giveaways table
        if (!Schema::hasColumn('giveaways', 'start_date')) {
            Schema::table('giveaways', function (Blueprint $table) {
                $table->date('start_date')->nullable()->after('draw_time');
                $table->date('end_date')->nullable()->after('start_date');
            });
        }
        
        if (!Schema::hasColumn('giveaways', 'max_entries_per_user')) {
            Schema::table('giveaways', function (Blueprint $table) {
                $table->integer('max_entries_per_user')->nullable()->after('status');
            });
        }
        
        if (!Schema::hasColumn('giveaways', 'auto_draw')) {
            Schema::table('giveaways', function (Blueprint $table) {
                $table->boolean('auto_draw')->default(true)->after('winner_id');
            });
        }
        
        if (!Schema::hasColumn('giveaways', 'allow_temp_entries')) {
            Schema::table('giveaways', function (Blueprint $table) {
                $table->boolean('allow_temp_entries')->default(true)->after('auto_draw');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('giveaway_entry_package');
        
        Schema::table('giveaways', function (Blueprint $table) {
            $table->dropColumn([
                'start_date', 
                'end_date', 
                'max_entries_per_user', 
                'auto_draw', 
                'allow_temp_entries'
            ]);
        });
    }
};