<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename prize_type 'package' to 'discount' in giveaways table.
     */
    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        // 1. Alter the enum FIRST to include both old and new values (MySQL only — SQLite has no ENUM DDL)
        if ($isMysql) {
            DB::statement("ALTER TABLE giveaways MODIFY COLUMN prize_type ENUM('bundle', 'package', 'discount', 'accessory', 'others') DEFAULT 'bundle'");
        }

        // 2. Now update existing rows
        DB::table('giveaways')->where('prize_type', 'package')->update(['prize_type' => 'discount']);

        // 3. Remove 'package' from the enum (MySQL only)
        if ($isMysql) {
            DB::statement("ALTER TABLE giveaways MODIFY COLUMN prize_type ENUM('bundle', 'discount', 'accessory', 'others') DEFAULT 'bundle'");
        }

        // 4. Also update prizes table if it has prize_type
        if (Schema::hasColumn('prizes', 'prize_type')) {
            if ($isMysql) {
                DB::statement("ALTER TABLE prizes MODIFY COLUMN prize_type ENUM('bundle', 'package', 'discount', 'accessory', 'others') DEFAULT 'bundle'");
            }
            DB::table('prizes')->where('prize_type', 'package')->update(['prize_type' => 'discount']);
            if ($isMysql) {
                DB::statement("ALTER TABLE prizes MODIFY COLUMN prize_type ENUM('bundle', 'discount', 'accessory', 'others') DEFAULT 'bundle'");
            }
        }

        // 5. Update entries table if purchase_type references 'package'
        if (Schema::hasColumn('entries', 'purchase_type')) {
            DB::table('entries')->where('purchase_type', 'package')->update(['purchase_type' => 'discount']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        if ($isMysql) {
            DB::statement("ALTER TABLE giveaways MODIFY COLUMN prize_type ENUM('bundle', 'package', 'discount', 'accessory', 'others') DEFAULT 'bundle'");
        }
        DB::table('giveaways')->where('prize_type', 'discount')->update(['prize_type' => 'package']);
        if ($isMysql) {
            DB::statement("ALTER TABLE giveaways MODIFY COLUMN prize_type ENUM('bundle', 'package', 'accessory', 'others') DEFAULT 'bundle'");
        }

        if (Schema::hasColumn('prizes', 'prize_type')) {
            if ($isMysql) {
                DB::statement("ALTER TABLE prizes MODIFY COLUMN prize_type ENUM('bundle', 'package', 'discount', 'accessory', 'others') DEFAULT 'bundle'");
            }
            DB::table('prizes')->where('prize_type', 'discount')->update(['prize_type' => 'package']);
            if ($isMysql) {
                DB::statement("ALTER TABLE prizes MODIFY COLUMN prize_type ENUM('bundle', 'package', 'accessory', 'others') DEFAULT 'bundle'");
            }
        }

        if (Schema::hasColumn('entries', 'purchase_type')) {
            DB::table('entries')->where('purchase_type', 'discount')->update(['purchase_type' => 'package']);
        }
    }
};