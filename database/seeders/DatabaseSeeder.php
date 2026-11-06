<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\RoleSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SubCategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\CouponSeeder;
use Database\Seeders\GiveawaySeeder;
use Database\Seeders\EntryPackageSeeder;
use Database\Seeders\DeliveryTypeSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\InfoSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Database\Seeders\ServiceSubCategorySeeder;
use Database\Seeders\ServiceProductSeeder;
use Database\Seeders\BankTransferSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PaymentSettingSeeder;
use Database\Seeders\TestDataSeeder;
use Database\Seeders\RepeatDeliverySeeder;


class DatabaseSeeder extends Seeder
{
    public function run(): void
    {

        $this->call([
            RoleSeeder::class,
            CategorySeeder::class,
            SubCategorySeeder::class,
            ProductSeeder::class,
            CouponSeeder::class,
            GiveawaySeeder::class,
            EntryPackageSeeder::class,
            DeliveryTypeSeeder::class,
            UserSeeder::class,
            InfoSeeder::class,
            ServiceCategorySeeder::class,
            ServiceSubCategorySeeder::class,
            ServiceProductSeeder::class,
            BankTransferSeeder::class,
            PaymentMethodSeeder::class,
            PaymentSettingSeeder::class,
            TestDataSeeder::class,
            RepeatDeliverySeeder::class,
        ]);


    }
}
