<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Giveaway Pool — weekly auto-rotation.
 *
 *  giveaway_templates                 the pool of prizes that rotate each week
 *  giveaway_template_entry_packages   entry packages (+ custom price) copied onto each spawned giveaway
 *  giveaway_pool_settings             single row: on/off, draw day/time, rotation mode, next pick, heartbeat
 *  giveaways.template_id / source     which template a giveaway came from, and 'pool' vs 'manual'
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('giveaway_templates')) {
            Schema::create('giveaway_templates', function (Blueprint $table) {
                $table->id();
                $table->string('prize_name');
                $table->text('description')->nullable();
                $table->string('prize_type', 30)->default('others');
                $table->decimal('prize_value', 10, 2)->default(0);
                $table->string('image')->nullable();
                $table->integer('max_entries_per_user')->nullable();
                $table->integer('required_entries')->nullable();
                $table->string('animation_style', 30)->default('lottery_balls');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('times_used')->default(0);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('giveaway_template_entry_packages')) {
            Schema::create('giveaway_template_entry_packages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('giveaway_template_id')->constrained('giveaway_templates')->cascadeOnDelete();
                $table->foreignId('entry_package_id')->constrained('entry_packages')->cascadeOnDelete();
                $table->decimal('custom_price', 10, 2)->nullable();
                $table->timestamps();
                $table->unique(['giveaway_template_id', 'entry_package_id'], 'gt_pkg_unique');
            });
        }

        if (!Schema::hasTable('giveaway_pool_settings')) {
            Schema::create('giveaway_pool_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(true);
                $table->unsignedTinyInteger('draw_day')->default(5);      // 0 = Sunday … 5 = Friday
                $table->string('draw_time', 8)->default('20:00:00');
                $table->string('rotation_mode', 30)->default('random_no_repeat');
                $table->unsignedBigInteger('next_template_id')->nullable();
                $table->timestamp('last_heartbeat_at')->nullable();
                $table->timestamps();
            });

            DB::table('giveaway_pool_settings')->insert([
                'id' => 1,
                'enabled' => true,
                'draw_day' => 5,
                'draw_time' => '20:00:00',
                'rotation_mode' => 'random_no_repeat',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('giveaways', function (Blueprint $table) {
            if (!Schema::hasColumn('giveaways', 'template_id')) {
                $table->unsignedBigInteger('template_id')->nullable()->index();
            }
            if (!Schema::hasColumn('giveaways', 'source')) {
                $table->string('source', 20)->default('manual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('giveaways', function (Blueprint $table) {
            foreach (['template_id', 'source'] as $col) {
                if (Schema::hasColumn('giveaways', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('giveaway_template_entry_packages');
        Schema::dropIfExists('giveaway_templates');
        Schema::dropIfExists('giveaway_pool_settings');
    }
};
