<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Giveaway Centre
     */
    public function up(): void
    {
        // Update giveaways table structure
        Schema::table('giveaways', function (Blueprint $table) {
            // Remove required_entries if it exists (since it's being removed)
            if (Schema::hasColumn('giveaways', 'required_entries')) {
                $table->dropColumn('required_entries');
            }
            
            // Add new fields if they don't exist
            if (!Schema::hasColumn('giveaways', 'position')) {
                $table->integer('position')->default(0)->after('id');
            }
            
            if (!Schema::hasColumn('giveaways', 'draw_time')) {
                $table->time('draw_time')->nullable()->after('draw_date');
            }
            
            if (!Schema::hasColumn('giveaways', 'scheduled_at')) {
                $table->dateTime('scheduled_at')->nullable()->after('draw_time');
            }
            
            // Update status to enum if it's not already
            if (Schema::hasColumn('giveaways', 'status')) {
                $table->string('status', 20)->default('draft')->change();
            } else {
                $table->string('status', 20)->default('draft');
            }
            
            if (!Schema::hasColumn('giveaways', 'allow_temp_entries')) {
                $table->boolean('allow_temp_entries')->default(true)->after('status');
            }
            
            if (!Schema::hasColumn('giveaways', 'max_entries_per_user')) {
                $table->integer('max_entries_per_user')->nullable()->after('allow_temp_entries');
            }
            
            if (!Schema::hasColumn('giveaways', 'auto_draw')) {
                $table->boolean('auto_draw')->default(true)->after('draw_time');
            }
        });

        // Create giveaway_entry_packages pivot table (links specific packages to specific giveaways)
        if (!Schema::hasTable('giveaway_entry_packages')) {
            Schema::create('giveaway_entry_packages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('giveaway_id')->constrained('giveaways')->onDelete('cascade');
                $table->foreignId('entry_package_id')->constrained('entry_packages')->onDelete('cascade');
                $table->decimal('custom_price', 10, 2)->nullable(); // Allow giveaway-specific pricing
                $table->boolean('is_active')->default(true);
                $table->integer('position')->default(0);
                $table->timestamps();
                
                $table->unique(['giveaway_id', 'entry_package_id']);
            });
        }

        // Update entry_packages table if needed
        Schema::table('entry_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('entry_packages', 'position')) {
                $table->integer('position')->default(0)->after('id');
            }
            
            if (!Schema::hasColumn('entry_packages', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            
            if (!Schema::hasColumn('entry_packages', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('status');
            }
        });

        // Create user_permanent_entries table if it doesn't exist
        if (!Schema::hasTable('user_permanent_entries')) {
            Schema::create('user_permanent_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->integer('permanent_entries')->default(0);
                $table->integer('total_earned')->default(0);
                $table->integer('total_used')->default(0);
                $table->timestamp('last_updated_at')->nullable();
                $table->timestamps();
                
                $table->unique('user_id');
            });
        }

        // Create giveaway_draw_logs table to track all draws
        if (!Schema::hasTable('giveaway_draw_logs')) {
            Schema::create('giveaway_draw_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('giveaway_id')->constrained('giveaways')->onDelete('cascade');
                $table->foreignId('winner_id')->nullable()->constrained('users')->onDelete('set null');
                $table->dateTime('drawn_at');
                $table->integer('total_entries_at_draw');
                $table->integer('total_participants_at_draw');
                $table->string('draw_method')->default('random'); // random, weighted, etc.
                $table->boolean('was_automatic')->default(false);
                $table->boolean('notification_sent')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('giveaway_draw_logs');
        Schema::dropIfExists('user_permanent_entries');
        Schema::dropIfExists('giveaway_entry_packages');
        
        Schema::table('giveaways', function (Blueprint $table) {
            $columns = ['position', 'draw_time', 'scheduled_at', 'allow_temp_entries', 
                       'max_entries_per_user', 'auto_draw'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('giveaways', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        
        Schema::table('entry_packages', function (Blueprint $table) {
            $columns = ['position', 'description', 'is_featured'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('entry_packages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};