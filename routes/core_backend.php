<?php

use App\Http\Controllers\Web\Backend\Core\BannerController;
use App\Http\Controllers\Web\Backend\Core\BackendController;
use App\Http\Controllers\Web\Backend\Core\UserController;
use App\Http\Controllers\Web\Backend\Core\SettingController;
use App\Http\Controllers\Web\Backend\Core\DynamicPagesController;
use App\Http\Controllers\Web\Backend\Core\ProfileSettingController;
use App\Http\Controllers\Web\Backend\Core\SystemSettingController;
use App\Http\Controllers\Web\Backend\Core\AdminSettingController;
use App\Http\Controllers\Web\Backend\Core\RoleController;
use App\Http\Controllers\Web\Backend\Core\PermissionController;
use App\Http\Controllers\Web\Backend\Core\CategoryController;
use App\Http\Controllers\Web\Backend\Core\SubCategoryController;
use App\Http\Controllers\Web\Backend\Core\ProductController;
use App\Http\Controllers\Web\Backend\Core\CouponController;
use App\Http\Controllers\Web\Backend\Core\OrderController;
use App\Http\Controllers\Web\Backend\Core\GiveawayController;
use App\Http\Controllers\Web\Backend\Core\EntryPackageController;
use App\Http\Controllers\Web\Backend\Core\DeliveryTypeController;
use App\Http\Controllers\Web\Backend\Core\DeliveryCalculatorController;
use App\Http\Controllers\Web\Backend\Core\InfoController;
use App\Http\Controllers\Web\Backend\Core\Services\ServicesCategoryController;
use App\Http\Controllers\Web\Backend\Core\Services\ServicesSubCategoryController;
use App\Http\Controllers\Web\Backend\Core\Services\ServicesProductController;
use App\Http\Controllers\Web\Backend\Core\ReviewController;
use App\Http\Controllers\Web\Backend\Core\SellingPointController;
use App\Http\Controllers\Web\Backend\Core\CustomerController;
use App\Http\Controllers\Web\Backend\Core\PushNotificationController;
use App\Http\Controllers\Web\Backend\Core\ManageUserEntryController;
use App\Http\Controllers\Web\Backend\Core\NotificationCentreController;
use App\Http\Controllers\Web\Backend\CustomerManagementController;
use App\Http\Controllers\Web\Backend\GiveawayCentreController;
use App\Http\Controllers\Web\Backend\GiveawayPoolController;
use App\Http\Controllers\Web\Backend\OrderCentreController;
use App\Http\Controllers\Web\Backend\Core\BankTransferController;
use App\Http\Controllers\Web\Backend\Core\ZipCodeController;
use App\Http\Controllers\Web\Backend\Core\PaymentMethodController;
use App\Http\Controllers\Web\Backend\Core\ReportController;
use App\Http\Controllers\Web\Backend\Core\FAQController;
use App\Http\Controllers\Web\Backend\Core\FAQCategoryController;
use App\Http\Controllers\Web\Backend\Core\TipCategoryController;
use App\Http\Controllers\Web\Backend\Core\TipsAndGuidesController;
use App\Http\Controllers\Web\Backend\Core\ContactUsController;
use App\Http\Controllers\Web\Backend\Core\BlogController;
use App\Http\Controllers\Web\Backend\Core\WoodEducationController;
use App\Http\Controllers\Web\Backend\Core\WeatherCampaignController;
use App\Http\Controllers\Web\Backend\Core\BundleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (Admin Panel)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'isAdmin'])->group(function () {
    Route::get('/dashboard', [BackendController::class, 'index'])->name('dashboard');
    
    //User Management
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/data', [UserController::class, 'getData'])->name('users.data');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');

    //setting Management
    Route::get('/mail', [SettingController::class, 'mail'])->name('settings.mail');
    Route::post('/mail/store', [SettingController::class, 'mailstore'])->name('settings.mailstore');

    //dynamic pages Management
    Route::get('/dynamicpage', [DynamicPagesController::class, 'index'])->name('dynamicpage.index');
    Route::get('/dynamicpage/data', [DynamicPagesController::class, 'getData'])->name('dynamicpage.data');
    Route::get('/dynamicpage/create', [DynamicPagesController::class, 'create'])->name('dynamicpage.create');
    Route::post('/dynamicpage', [DynamicPagesController::class, 'store'])->name('dynamicpage.store');
    Route::get('/dynamicpage/{id}/edit', [DynamicPagesController::class, 'edit'])->name('dynamicpage.edit');
    Route::put('/dynamicpage/{id}', [DynamicPagesController::class, 'update'])->name('dynamicpage.update');
    Route::delete('/dynamicpage/{id}', [DynamicPagesController::class, 'destroy'])->name('dynamicpage.destroy');

    //Profile Settings
    Route::get('/profile_upload/index', [ProfileSettingController::class, 'index'])->name('profile.index');
    Route::post('/profile/update', [ProfileSettingController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/password/update', [ProfileSettingController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/avatar', [ProfileSettingController::class, 'updatePhoto'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileSettingController::class, 'removeAvatar'])->name('profile.avatar.remove');

    //System Settings
    Route::get('/system-settings', [SystemSettingController::class, 'edit'])->name('system-settings.edit');
    Route::post('/system-settings', [SystemSettingController::class, 'update'])->name('system-settings.update');
    Route::post('/sync-timezone', [SystemSettingController::class, 'syncTimezone'])->name('system-settings.sync-timezone');

    //Payment Settings
    Route::get('/payment-settings', [\App\Http\Controllers\Web\Backend\Core\PaymentSettingController::class, 'edit'])->name('payment-settings.edit');
    Route::post('/payment-settings', [\App\Http\Controllers\Web\Backend\Core\PaymentSettingController::class, 'update'])->name('payment-settings.update');

    //Admin Settings
    Route::get('/admin/settings', [AdminSettingController::class, 'edit'])->name('admin.settings.edit');
    Route::post('/admin/settings', [AdminSettingController::class, 'update'])->name('admin.settings.update');

    //Role Management
    Route::get('role', [RoleController::class, 'index'])->name('role.index');
    Route::post('role/store', [RoleController::class, 'store'])->name('role.store');
    Route::get('role/{id}/edit', [RoleController::class, 'edit'])->name('role.edit');
    Route::post('role/update/{id}', [RoleController::class, 'update'])->name('role.update');
    Route::get('role/permission/{id}', [RoleController::class, 'permission'])->name('role.permission');
    Route::post('role/set-permission', [RoleController::class, 'setPermission'])->name('role.setPermission');
    Route::delete('role/delete/{id}', [RoleController::class, 'destroy'])->name('role.destroy');

    //Banner Management
    Route::get('/banner', [BannerController::class, 'index'])->name('banner.index');
    Route::get('banner/data', [BannerController::class, 'getData'])->name('banner.data');
    Route::get('/banner/create', [BannerController::class, 'create'])->name('banner.create');
    Route::post('/banner', [BannerController::class, 'store'])->name('banner.store');
    Route::get('/banner/{id}/edit', [BannerController::class, 'edit'])->name('banner.edit');
    Route::post('/banner/{id}', [BannerController::class, 'update'])->name('banner.update');
    Route::delete('/banner/{id}', [BannerController::class, 'destroy'])->name('banner.destroy');
    Route::post('banner/{id}/status', [BannerController::class, 'updateStatus'])->name('banner.updateStatus');

    //Category Management
    Route::get('/category', [CategoryController::class, 'index'])->name('category.index');
    Route::get('category/data', [CategoryController::class, 'getData'])->name('category.data');
    Route::get('/category/create', [CategoryController::class, 'create'])->name('category.create');
    Route::post('/category', [CategoryController::class, 'store'])->name('category.store');
    Route::get('/category/{id}/edit', [CategoryController::class, 'edit'])->name('category.edit');
    Route::post('/category/{id}', [CategoryController::class, 'update'])->name('category.update');
    Route::delete('/category/{id}', [CategoryController::class, 'destroy'])->name('category.destroy');
    Route::post('category/{id}/status', [CategoryController::class, 'updateStatus'])->name('category.updateStatus');
    Route::post('/category/{id}/duplicate', [CategoryController::class, 'duplicate'])->name('category.duplicate');

    // SubCategory Management
    Route::get('/subcategory', [SubCategoryController::class, 'index'])->name('subcategory.index');
    Route::get('subcategory/data', [SubCategoryController::class, 'getData'])->name('subcategory.data');
    Route::get('/subcategory/create', [SubCategoryController::class, 'create'])->name('subcategory.create');
    Route::post('/subcategory', [SubCategoryController::class, 'store'])->name('subcategory.store');
    Route::get('/subcategory/{id}/edit', [SubCategoryController::class, 'edit'])->name('subcategory.edit');
    Route::post('/subcategory/{id}', [SubCategoryController::class, 'update'])->name('subcategory.update');
    Route::delete('/subcategory/{id}', [SubCategoryController::class, 'destroy'])->name('subcategory.destroy');
    Route::post('subcategory/{id}/status', [SubCategoryController::class, 'updateStatus'])->name('subcategory.updateStatus');
    Route::post('/subcategory/{id}/duplicate', [SubCategoryController::class, 'duplicate'])->name('subcategory.duplicate');

    // Product Management
    Route::post('/products/{id}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/data', [ProductController::class, 'getData'])->name('products.data');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{id}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::post('/products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('products/{id}/status', [ProductController::class, 'updateStatus'])->name('products.updateStatus');
    Route::get('/get-subcategories/{category_id}', [ProductController::class, 'getSubcategories'])->name('get.subcategories');
    Route::get('/get-upsell-products', [ProductController::class, 'getProductsForUpsell'])->name('products.upsells');

    //Coupon
    Route::get('/coupon', [CouponController::class, 'index'])->name('coupon.index');
    Route::get('coupon/data', [CouponController::class, 'getData'])->name('coupon.data');
    Route::get('/coupon/create', [CouponController::class, 'create'])->name('coupon.create');
    Route::post('/coupon', [CouponController::class, 'store'])->name('coupon.store');
    Route::get('/coupon/{id}/edit', [CouponController::class, 'edit'])->name('coupon.edit');
    Route::post('/coupon/{id}', [CouponController::class, 'update'])->name('coupon.update');
    Route::delete('/coupon/{id}', [CouponController::class, 'destroy'])->name('coupon.destroy');
    Route::post('coupon/{id}/status', [CouponController::class, 'updateStatus'])->name('coupon.updateStatus');
    Route::get('coupon/generate-code', [CouponController::class, 'generateCode'])->name('coupon.generateCode');

    // FAQ Management
    Route::get('/faqs', [FAQController::class, 'index'])->name('faqs.index');
    Route::get('faqs/data', [FAQController::class, 'getData'])->name('faqs.data');
    Route::get('/faqs/create', [FAQController::class, 'create'])->name('faqs.create');
    Route::post('/faqs', [FAQController::class, 'store'])->name('faqs.store');
    Route::get('/faqs/{id}/edit', [FAQController::class, 'edit'])->name('faqs.edit');
    Route::post('/faqs/{id}', [FAQController::class, 'update'])->name('faqs.update');
    Route::delete('/faqs/{id}', [FAQController::class, 'destroy'])->name('faqs.destroy');
    Route::post('/faqs/{id}/status', [FAQController::class, 'updateStatus'])->name('faqs.updateStatus');

    // FAQ Categories
    Route::get('/faq-categories', [FAQCategoryController::class, 'index'])->name('faq-categories.index');
    Route::get('faq-categories/data', [FAQCategoryController::class, 'getData'])->name('faq-categories.data');
    Route::get('/faq-categories/create', [FAQCategoryController::class, 'create'])->name('faq-categories.create');
    Route::post('/faq-categories', [FAQCategoryController::class, 'store'])->name('faq-categories.store');
    Route::get('/faq-categories/{id}/edit', [FAQCategoryController::class, 'edit'])->name('faq-categories.edit');
    Route::post('/faq-categories/{id}', [FAQCategoryController::class, 'update'])->name('faq-categories.update');
    Route::delete('/faq-categories/{id}', [FAQCategoryController::class, 'destroy'])->name('faq-categories.destroy');
    Route::post('/faq-categories/{id}/status', [FAQCategoryController::class, 'updateStatus'])->name('faq-categories.updateStatus');

    //Blog Management
    Route::get('/blogs', [BlogController::class, 'index'])->name('blog.index');
    Route::get('blogs/data', [BlogController::class, 'getData'])->name('blog.data');
    Route::get('/blogs/create', [BlogController::class, 'create'])->name('blog.create');
    Route::post('/blogs', [BlogController::class, 'store'])->name('blog.store');
    Route::get('/blogs/{id}/edit', [BlogController::class, 'edit'])->name('blog.edit');
    Route::post('/blogs/{id}', [BlogController::class, 'update'])->name('blog.update');
    Route::delete('/blogs/{id}', [BlogController::class, 'destroy'])->name('blog.destroy');
    Route::post('/blogs/{id}/status', [BlogController::class, 'updateStatus'])->name('blog.updateStatus');
    Route::post('/blogs/{id}/featured', [BlogController::class, 'updateFeatured'])->name('blog.updateFeatured');

    // Wood Education
    Route::get('/wood-education', [WoodEducationController::class, 'index'])->name('wood-education.index');
    Route::get('wood-education/data', [WoodEducationController::class, 'getData'])->name('wood-education.data');
    Route::get('/wood-education/create', [WoodEducationController::class, 'create'])->name('wood-education.create');
    Route::post('/wood-education', [WoodEducationController::class, 'store'])->name('wood-education.store');
    Route::get('/wood-education/{id}/edit', [WoodEducationController::class, 'edit'])->name('wood-education.edit');
    Route::post('/wood-education/{id}', [WoodEducationController::class, 'update'])->name('wood-education.update');
    Route::delete('/wood-education/{id}', [WoodEducationController::class, 'destroy'])->name('wood-education.destroy');
    Route::post('/wood-education/{id}/status', [WoodEducationController::class, 'updateStatus'])->name('wood-education.updateStatus');

    // Bundles
    Route::get('/bundles', [BundleController::class, 'index'])->name('bundles.index');
    Route::get('bundles/data', [BundleController::class, 'getData'])->name('bundles.data');
    Route::get('/bundles/create', [BundleController::class, 'create'])->name('bundles.create');
    Route::post('/bundles', [BundleController::class, 'store'])->name('bundles.store');
    Route::get('/bundles/{id}/edit', [BundleController::class, 'edit'])->name('bundles.edit');
    Route::post('/bundles/{id}', [BundleController::class, 'update'])->name('bundles.update');
    Route::delete('/bundles/{id}', [BundleController::class, 'destroy'])->name('bundles.destroy');
    Route::post('/bundles/{id}/status', [BundleController::class, 'updateStatus'])->name('bundles.updateStatus');

    // Contact Us Management
    Route::get('/contact-us', [ContactUsController::class, 'index'])->name('contact-us.index');
    Route::get('contact-us/data', [ContactUsController::class, 'getData'])->name('contact-us.data');
    Route::get('/contact-us/{id}', [ContactUsController::class, 'show'])->name('contact-us.show');
    Route::delete('/contact-us/{id}', [ContactUsController::class, 'destroy'])->name('contact-us.destroy');


    // Tips and Guides Management
    Route::get('/tips-and-guides', [TipsAndGuidesController::class, 'index'])->name('tips-and-guides.index');
    Route::get('tips-and-guides/data', [TipsAndGuidesController::class, 'getData'])->name('tips-and-guides.data');
    Route::get('/tips-and-guides/create', [TipsAndGuidesController::class, 'create'])->name('tips-and-guides.create');
    Route::post('/tips-and-guides', [TipsAndGuidesController::class, 'store'])->name('tips-and-guides.store');
    Route::get('/tips-and-guides/{id}/edit', [TipsAndGuidesController::class, 'edit'])->name('tips-and-guides.edit');
    Route::post('/tips-and-guides/{id}', [TipsAndGuidesController::class, 'update'])->name('tips-and-guides.update');
    Route::delete('/tips-and-guides/{id}', [TipsAndGuidesController::class, 'destroy'])->name('tips-and-guides.destroy');
    Route::post('/tips-and-guides/{id}/status', [TipsAndGuidesController::class, 'updateStatus'])->name('tips-and-guides.updateStatus');

    // Tip Categories
    Route::get('/tip-categories', [TipCategoryController::class, 'index'])->name('tip-categories.index');
    Route::get('tip-categories/data', [TipCategoryController::class, 'getData'])->name('tip-categories.data');
    Route::get('/tip-categories/create', [TipCategoryController::class, 'create'])->name('tip-categories.create');
    Route::post('/tip-categories', [TipCategoryController::class, 'store'])->name('tip-categories.store');
    Route::get('/tip-categories/{id}/edit', [TipCategoryController::class, 'edit'])->name('tip-categories.edit');
    Route::post('/tip-categories/{id}', [TipCategoryController::class, 'update'])->name('tip-categories.update');
    Route::delete('/tip-categories/{id}', [TipCategoryController::class, 'destroy'])->name('tip-categories.destroy');
    Route::post('/tip-categories/{id}/status', [TipCategoryController::class, 'updateStatus'])->name('tip-categories.updateStatus');

    Route::prefix('order')->name('order.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/data', [OrderController::class, 'getData'])->name('data');
        Route::get('/stats', [OrderController::class, 'getStats'])->name('stats');
        Route::get('/{id}/details', [OrderController::class, 'show'])->name('show');
        Route::post('/{id}/confirm', [OrderController::class, 'confirmOrder'])->name('confirm');
        Route::post('/{id}/refund', [OrderController::class, 'processRefund'])->name('refund');
        Route::post('/{id}/stripe-refund', [OrderController::class, 'stripeRefund'])->name('stripe-refund');
        Route::post('/{id}/cancel', [OrderController::class, 'cancelOrder'])->name('cancel');
        Route::post('/{id}/status', [OrderController::class, 'updateStatus'])->name('status');
        Route::post('/{id}/payment', [OrderController::class, 'updatePaymentStatus'])->name('payment');
        Route::get('/{id}/edit', [OrderController::class, 'edit'])->name('edit');
        Route::post('/{id}', [OrderController::class, 'update'])->name('update');
        Route::delete('/{id}', [OrderController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/delivery-date', [OrderController::class, 'updateDeliveryDate'])->name('delivery-date');
        Route::get('/cancelled', [OrderController::class, 'cancelledOrders'])->name('cancelled');
        Route::get('/cancelled/data', [OrderController::class, 'getCancelledData'])->name('cancelled.data');
        Route::post('/{id}/refund', [OrderController::class, 'processRefund'])->name('refund');
    });

    // ========================================
    // LEGACY GIVEAWAY ROUTES (Keep for existing functionality)
    // ========================================
    Route::get('/giveaways/data', [GiveawayCentreController::class, 'getGiveawaysData'])->name('giveaways.data');
    Route::get('/giveaways/upcoming', [GiveawayCentreController::class, 'getUpcoming'])->name('giveaways.upcoming');
    Route::get('/giveaways/drawn', [GiveawayCentreController::class, 'getDrawn'])->name('giveaways.drawn');
    Route::get('/giveaways/create', [GiveawayCentreController::class, 'create'])->name('giveaways.create');
    Route::get('/giveaways', [GiveawayCentreController::class, 'index'])->name('giveaways.index');
    Route::post('/giveaways', [GiveawayCentreController::class, 'store'])->name('giveaways.store');
    Route::get('/giveaways/{id}/edit', [GiveawayCentreController::class, 'edit'])->name('giveaways.edit');
    Route::post('/giveaways/{id}', [GiveawayCentreController::class, 'update'])->name('giveaways.update');
    Route::get('/giveaways/{id}', [GiveawayCentreController::class, 'show'])->name('giveaways.show');
    Route::delete('/giveaways/{id}', [GiveawayCentreController::class, 'destroy'])->name('giveaways.destroy');
    Route::post('/giveaways/{id}/status', [GiveawayCentreController::class, 'updateStatus'])->name('giveaways.updateStatus');
    Route::post('/giveaways/draw-winner/{id}', [GiveawayCentreController::class, 'drawWinner'])->name('giveaways.drawWinner');

    // ========================================
    // NEW GIVEAWAY CENTRE ROUTES (ADMIN)
    // ========================================
    Route::prefix('giveaway-centre')->name('giveaway-centre.')->group(function () {
        Route::get('/', [GiveawayCentreController::class, 'index'])->name('index');
        Route::get('/create', [GiveawayCentreController::class, 'create'])->name('create');
        Route::post('/store', [GiveawayCentreController::class, 'store'])->name('store');
        
        Route::get('/giveaways/data', [GiveawayCentreController::class, 'getGiveawaysData']);
        Route::get('/entry-packages/data', [GiveawayCentreController::class, 'getEntryPackagesData']);

        // AUTO POOL (weekly rotation)
        Route::prefix('pool')->name('pool.')->controller(GiveawayPoolController::class)->group(function () {
            Route::get('/data', 'data')->name('data');
            Route::post('/settings', 'saveSettings')->name('settings');
            Route::post('/run-now', 'runNow')->name('run-now');
            Route::post('/reroll', 'reroll')->name('reroll');
            Route::post('/import/{giveawayId}', 'importFromGiveaway')->name('import');
            Route::post('/templates', 'store')->name('templates.store');
            Route::get('/templates/{id}', 'show')->name('templates.show');
            Route::post('/templates/{id}', 'update')->name('templates.update');
            Route::delete('/templates/{id}', 'destroy')->name('templates.destroy');
            Route::post('/templates/{id}/toggle', 'toggle')->name('templates.toggle');
            Route::post('/templates/{id}/next', 'useNext')->name('templates.next');
        });
        
        // GIVEAWAY CRUD OPERATIONS
        Route::post('/{id}/draw', [GiveawayCentreController::class, 'draw'])->name('draw');
        Route::post('/{id}/duplicate', [GiveawayCentreController::class, 'duplicate'])->name('duplicate');
        Route::get('/{id}/edit', [GiveawayCentreController::class, 'edit'])->name('edit');
        Route::put('/{id}', [GiveawayCentreController::class, 'update'])->name('update');
        Route::delete('/{id}', [GiveawayCentreController::class, 'destroy'])->name('destroy');
        
        // ADMIN DRAW LIVE VIEW
        Route::get('/{id}/draw-live', [GiveawayCentreController::class, 'drawLive'])->name('draw-live');
        
        Route::prefix('permanent-entries')->group(function () {
            Route::get('/data', [GiveawayCentreController::class, 'getPermanentEntriesData']);
            Route::post('/user/{userId}/update', [GiveawayCentreController::class, 'updateOrCreatePermanentEntries']);
            Route::get('/user/{userId}/history', [GiveawayCentreController::class, 'getUserEntryHistory']);
        });
    });

    // ========================================
    // LEGACY ENTRY PACKAGE ROUTES
    // ========================================
    Route::get('/entry-package', [EntryPackageController::class, 'index'])->name('entry-package.index');
    Route::get('/entry-package/data', [EntryPackageController::class, 'getData'])->name('entry-package.data');
    Route::get('/entry-package/create', [EntryPackageController::class, 'create'])->name('entry-package.create');
    Route::post('/entry-package', [EntryPackageController::class, 'store'])->name('entry-package.store');
    Route::get('/entry-package/{id}/edit', [EntryPackageController::class, 'edit'])->name('entry-package.edit');
    Route::post('/entry-package/{id}', [EntryPackageController::class, 'update'])->name('entry-package.update');
    Route::delete('/entry-package/{id}', [EntryPackageController::class, 'destroy'])->name('entry-package.destroy');
    Route::post('/entry-package/{id}/status', [EntryPackageController::class, 'updateStatus'])->name('entry-package.updateStatus');

    //Delivery Type Management
    Route::get('/delivery-type', [DeliveryTypeController::class, 'index'])->name('delivery-type.index');
    Route::get('delivery-type/data', [DeliveryTypeController::class, 'getData'])->name('delivery-type.data');
    Route::get('/delivery-type/create', [DeliveryTypeController::class, 'create'])->name('delivery-type.create');
    Route::post('/delivery-type', [DeliveryTypeController::class, 'store'])->name('delivery-type.store');
    Route::get('/delivery-type/{id}/edit', [DeliveryTypeController::class, 'edit'])->name('delivery-type.edit');
    Route::post('/delivery-type/{id}', [DeliveryTypeController::class, 'update'])->name('delivery-type.update');
    Route::delete('/delivery-type/{id}', [DeliveryTypeController::class, 'destroy'])->name('delivery-type.destroy');
    Route::post('/delivery-type/{id}/status', [DeliveryTypeController::class, 'updateStatus'])->name('delivery-type.updateStatus');

    //Delivery Calculator (staff tool)
    Route::get('/delivery-calculator', [DeliveryCalculatorController::class, 'index'])->name('delivery-calculator.index');
    Route::post('/delivery-calculator/calculate', [DeliveryCalculatorController::class, 'calculate'])->name('delivery-calculator.calculate');

    //Info Management
    Route::get('/info', [InfoController::class, 'index'])->name('info.index');
    Route::get('info/data', [InfoController::class, 'getData'])->name('info.data');
    Route::get('/info/create', [InfoController::class, 'create'])->name('info.create');
    Route::post('/info', [InfoController::class, 'store'])->name('info.store');
    Route::get('/info/{id}/edit', [InfoController::class, 'edit'])->name('info.edit');
    Route::post('/info/{id}', [InfoController::class, 'update'])->name('info.update');
    Route::delete('/info/{id}', [InfoController::class, 'destroy'])->name('info.destroy');

    // Service Categories Management
    Route::get('/services/category', [ServicesCategoryController::class, 'index'])->name('services.category.index');
    Route::get('services/category/data', [ServicesCategoryController::class, 'getData'])->name('services.category.data');
    Route::get('/services/category/create', [ServicesCategoryController::class, 'create'])->name('services.category.create');
    Route::post('/services/category', [ServicesCategoryController::class, 'store'])->name('services.category.store');
    Route::get('/services/category/{id}/edit', [ServicesCategoryController::class, 'edit'])->name('services.category.edit');
    Route::post('/services/category/{id}', [ServicesCategoryController::class, 'update'])->name('services.category.update');
    Route::delete('/services/category/{id}', [ServicesCategoryController::class, 'destroy'])->name('services.category.destroy');
    Route::post('/services/category/{id}/status', [ServicesCategoryController::class, 'updateStatus'])->name('services.category.updateStatus');

    // Service SubCategories Management
    Route::get('/services/sub_category', [ServicesSubCategoryController::class, 'index'])->name('services_sub_category.index');
    Route::get('services/sub_category/data', [ServicesSubCategoryController::class, 'getData'])->name('services_sub_category.data');
    Route::get('/services/sub_category/create', [ServicesSubCategoryController::class, 'create'])->name('services_sub_category.create');
    Route::post('/services/sub_category', [ServicesSubCategoryController::class, 'store'])->name('services_sub_category.store');
    Route::get('/services/sub_category/{id}/edit', [ServicesSubCategoryController::class, 'edit'])->name('services_sub_category.edit');
    Route::post('/services/sub_category/{id}', [ServicesSubCategoryController::class, 'update'])->name('services_sub_category.update');
    Route::delete('/services/sub_category/{id}', [ServicesSubCategoryController::class, 'destroy'])->name('services_sub_category.destroy');
    Route::post('/services/sub_category/{id}/status', [ServicesSubCategoryController::class, 'updateStatus'])->name('services_sub_category.updateStatus');

    //Selling Point Management
    Route::get('/selling-point', [SellingPointController::class, 'index'])->name('selling-point.index');
    Route::get('selling-point/data', [SellingPointController::class, 'getData'])->name('selling-point.data');
    Route::get('/selling-point/create', [SellingPointController::class, 'create'])->name('selling-point.create');
    Route::post('/selling-point', [SellingPointController::class, 'store'])->name('selling-point.store');
    Route::get('/selling-point/{id}/edit', [SellingPointController::class, 'edit'])->name('selling-point.edit');
    Route::post('/selling-point/{id}', [SellingPointController::class, 'update'])->name('selling-point.update');
    Route::delete('/selling-point/{id}', [SellingPointController::class, 'destroy'])->name('selling-point.destroy');
    Route::post('/selling-point/{id}/status', [SellingPointController::class, 'updateStatus'])->name('selling-point.updateStatus');

    // Service Products Management
    Route::get('/services/product', [ServicesProductController::class, 'index'])->name('services.product.index');
    Route::get('services/product/data', [ServicesProductController::class, 'getData'])->name('services.product.data');
    Route::get('/services/product/create', [ServicesProductController::class, 'create'])->name('services.product.create');
    Route::post('/services/product', [ServicesProductController::class, 'store'])->name('services.product.store');
    Route::get('/services/product/{id}/edit', [ServicesProductController::class, 'edit'])->name('services.product.edit');
    Route::post('/services/product/{id}', [ServicesProductController::class, 'update'])->name('services.product.update');
    Route::delete('/services/product/{id}', [ServicesProductController::class, 'destroy'])->name('services.product.destroy');
    Route::post('/services/product/{id}/status', [ServicesProductController::class, 'updateStatus'])->name('services.product.updateStatus');

    Route::get('/review', [ReviewController::class, 'index'])->name('review.index');
    Route::get('review/data', [ReviewController::class, 'getData'])->name('review.data');
    Route::post('/review/{id}/status', [ReviewController::class, 'updateStatus'])->name('review.updateStatus');
    Route::delete('/review/{id}', [ReviewController::class, 'destroy'])->name('review.destroy');
    Route::get('/review/{id}', [ReviewController::class, 'show'])->name('review.show');

    // Report Management
    Route::prefix('report')->name('report.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/data', [ReportController::class, 'getData'])->name('data');
        Route::get('/{id}', [ReportController::class, 'show'])->name('show');
        Route::delete('/{id}', [ReportController::class, 'destroy'])->name('destroy');
    });

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('customers.show');

    // Bank Transfer Management
    Route::get('/bank_transfer', [BankTransferController::class, 'index'])->name('bank_transfer.index');
    Route::get('/bank_transfer/data', [BankTransferController::class, 'getData'])->name('bank_transfer.data');
    Route::get('/bank_transfer/create', [BankTransferController::class, 'create'])->name('bank_transfer.create');
    Route::post('/bank_transfer', [BankTransferController::class, 'store'])->name('bank_transfer.store');
    Route::get('/bank_transfer/edit/{id}', [BankTransferController::class, 'edit'])->name('bank_transfer.edit');
    Route::post('/bank_transfer/{id}', [BankTransferController::class, 'update'])->name('bank_transfer.update');
    Route::post('/bank_transfer/{id}/status', [BankTransferController::class, 'updateStatus'])->name('bank_transfer.status');
    Route::delete('/bank_transfer/{id}', [BankTransferController::class, 'destroy'])->name('bank_transfer.destroy');      

    // Payment Method Management
    Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    Route::post('/payment-methods/{id}/status', [PaymentMethodController::class, 'updateStatus'])->name('payment-methods.updateStatus');

    // Push Notification Management
    Route::post('/notifications/{id}/status', [PushNotificationController::class, 'updateStatus'])->name('notifications.status');
    Route::post('/notifications/{id}/send', [PushNotificationController::class, 'send'])->name('notifications.send');
    Route::get('/notifications/{id}/edit', [PushNotificationController::class, 'edit'])->name('notifications.edit');
    Route::get('/notifications', [PushNotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/data', [PushNotificationController::class, 'getData'])->name('notifications.data');
    Route::get('/notifications/create', [PushNotificationController::class, 'create'])->name('notifications.create');
    Route::post('/notifications', [PushNotificationController::class, 'store'])->name('notifications.store');
    Route::get('/notifications/sent/data', [PushNotificationController::class, 'getSentData'])->name('notifications.sent.data');
    Route::post('/notifications/{id}', [PushNotificationController::class, 'update'])->whereNumber('id')->name('notifications.update');
    Route::delete('/notifications/{id}', [PushNotificationController::class, 'destroy'])->name('notifications.destroy');

    // Manage User Permanent Entries
    Route::get('/manage-entries', [ManageUserEntryController::class, 'index'])->name('manage-entries.index');
    Route::get('/manage-entries/data', [ManageUserEntryController::class, 'getData'])->name('manage-entries.data');
    Route::post('/manage-entries/{userId}/update', [ManageUserEntryController::class, 'update'])->name('manage-entries.update');
    Route::get('/manage-entries/{userId}', [ManageUserEntryController::class, 'getUserEntries'])->name('manage-entries.get');
    
    // Notification Centre V2
    Route::prefix('backend/notification-centre')->name('backend.notification_centre.')->group(function () {
        // Static routes (must be before {id} routes)
        Route::get('/', [NotificationCentreController::class, 'index'])->name('index');
        Route::post('/', [NotificationCentreController::class, 'store'])->name('store');
        Route::get('/queue-data', [NotificationCentreController::class, 'queueData'])->name('queue.data');
        Route::get('/queue-paginated', [NotificationCentreController::class, 'queuePaginated'])->name('queue.paginated');
        Route::get('/templates', [NotificationCentreController::class, 'getTemplates'])->name('templates');
        Route::post('/count-sms', [NotificationCentreController::class, 'countSmsCharacters'])->name('count_sms');
        Route::post('/upload-csv', [NotificationCentreController::class, 'uploadCsv'])->name('upload_csv');
        Route::post('/bulk', [NotificationCentreController::class, 'bulk'])->name('bulk');

        // Parameterized routes
        Route::post('/{id}/schedule', [NotificationCentreController::class, 'schedule'])->name('schedule');
        Route::post('/{id}/update', [NotificationCentreController::class, 'update'])->name('update');
        Route::post('/{id}/cancel', [NotificationCentreController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/pause', [NotificationCentreController::class, 'pause'])->name('pause');
        Route::post('/{id}/resume', [NotificationCentreController::class, 'resume'])->name('resume');
        Route::post('/{id}/send-test', [NotificationCentreController::class, 'sendTest'])->name('send_test');
        Route::post('/{id}/send-now', [NotificationCentreController::class, 'sendNow'])->name('send_now');
        Route::post('/{id}/toggle-testmode', [NotificationCentreController::class, 'toggleTestMode'])->name('toggle_testmode');
        Route::post('/{id}/test-email', [NotificationCentreController::class, 'testEmail'])->name('test_email');
        Route::post('/{id}/test-sms', [NotificationCentreController::class, 'testSms'])->name('test_sms');
        Route::get('/{id}/logs', [NotificationCentreController::class, 'logs'])->name('logs');

        // ---- Centre rebuild (additive, won't break existing) ----
        Route::get('/recipients/search',     [NotificationCentreController::class, 'searchRecipients'])->name('recipients.search');
        Route::post('/audience/estimate',    [NotificationCentreController::class, 'estimateAudience'])->name('audience.estimate');
        Route::get('/audience/suburbs',      [NotificationCentreController::class, 'getSuburbs'])->name('audience.suburbs');
        Route::post('/send-test-direct',     [NotificationCentreController::class, 'sendTestDirect'])->name('send_test_direct');
        Route::post('/{id}/send-test-targeted', [NotificationCentreController::class, 'sendTestTargeted'])->name('send_test_targeted');

        // Suppressions
        Route::get('/suppressions',          [NotificationCentreController::class, 'listSuppressions'])->name('suppressions.list');
        Route::post('/suppressions',         [NotificationCentreController::class, 'addSuppression'])->name('suppressions.add');
        Route::delete('/suppressions/{id}',  [NotificationCentreController::class, 'deleteSuppression'])->name('suppressions.delete');

        // Templates (custom — separate from existing Brevo template fetcher)
        Route::get('/templates-library',     [NotificationCentreController::class, 'listTemplates'])->name('templates.list');
        Route::post('/templates-library',    [NotificationCentreController::class, 'saveTemplate'])->name('templates.save');
        Route::delete('/templates-library/{id}', [NotificationCentreController::class, 'deleteTemplate'])->name('templates.delete');
    });
    
    // Customer Management Routes
    Route::get('/customer-management', [CustomerManagementController::class, 'index'])->name('customer-management.index');
    Route::get('/customer-management/create', [CustomerManagementController::class, 'create'])->name('customer-management.create');
    Route::post('/customer-management', [CustomerManagementController::class, 'store'])->name('customer-management.store');
    Route::get('/customer-management/{id}', [CustomerManagementController::class, 'show'])->name('customer-management.show');
    Route::get('/customer-management/{id}/edit', [CustomerManagementController::class, 'edit'])->name('customer-management.edit');
    Route::put('/customer-management/{id}', [CustomerManagementController::class, 'update'])->name('customer-management.update');
    Route::delete('/customer-management/{id}', [CustomerManagementController::class, 'destroy'])->name('customer-management.destroy');
    Route::post('/customer-management/bulk-delete', [CustomerManagementController::class, 'bulkDelete'])->name('customer-management.bulk-delete');
    Route::get('/customer-management-export', [CustomerManagementController::class, 'export'])->name('customer-management.export');
    Route::post('/customer-management/{id}/send-notification', [CustomerManagementController::class, 'sendNotification'])->name('customer-management.send-notification');
    Route::post('/customer-management/{id}/test-reachability', [CustomerManagementController::class, 'testReachability'])->name('customer-management.test-reachability');
    Route::post('/customer-management/bulk-test-reachability/candidates', [CustomerManagementController::class, 'bulkTestReachabilityCandidates'])->name('customer-management.bulk-reachability.candidates');
    Route::post('/customer-management/bulk-test-reachability/batch', [CustomerManagementController::class, 'bulkTestReachabilityBatch'])->name('customer-management.bulk-reachability.batch');
    Route::post('/customer-management/{id}/manage-entries', [CustomerManagementController::class, 'manageEntries'])->name('customer-management.manage-entries');

    // Setmore Reviews — admin panel for reviewing Zapier-ingested appointments
    Route::get('/setmore-reviews',
        [\App\Http\Controllers\Web\Backend\SetmoreReviewController::class, 'index'])
        ->name('setmore-reviews.index');
    Route::post('/setmore-reviews/{eventId}/apply',
        [\App\Http\Controllers\Web\Backend\SetmoreReviewController::class, 'apply'])
        ->name('setmore-reviews.apply');
    Route::post('/setmore-reviews/{eventId}/reject',
        [\App\Http\Controllers\Web\Backend\SetmoreReviewController::class, 'reject'])
        ->name('setmore-reviews.reject');
    Route::post('/setmore-reviews/auto-apply/toggle',
        [\App\Http\Controllers\Web\Backend\SetmoreReviewController::class, 'toggleAutoApply'])
        ->name('setmore-reviews.toggle-auto-apply');
    Route::post('/setmore-reviews/auto-cancel/toggle',
        [\App\Http\Controllers\Web\Backend\SetmoreReviewController::class, 'toggleAutoCancel'])
        ->name('setmore-reviews.toggle-auto-cancel');
    Route::get('/setmore-reviews/delivery-email/log',
        [\App\Http\Controllers\Web\Backend\SetmoreReviewController::class, 'deliveryEmailLog'])
        ->name('setmore-reviews.delivery-email-log');

    // Zip Code Management Routes (Grouped Zones + Delivery Fees)
    Route::prefix('zip-code')->name('zip-code.')->group(function () {
        // Zone (Group) routes
        Route::get('/', [ZipCodeController::class, 'index'])->name('index');
        Route::get('/groups-data', [ZipCodeController::class, 'getGroupsData'])->name('groups-data');
        Route::get('/groups-list', [ZipCodeController::class, 'getGroupsList'])->name('groups-list');
        Route::get('/create-group', [ZipCodeController::class, 'createGroup'])->name('create-group');
        Route::post('/store-group', [ZipCodeController::class, 'storeGroup'])->name('store-group');
        Route::get('/group/{id}', [ZipCodeController::class, 'showGroup'])->name('show');
        Route::get('/group/{id}/edit', [ZipCodeController::class, 'editGroup'])->name('edit-group');
        Route::post('/group/{id}/update', [ZipCodeController::class, 'updateGroup'])->name('update-group');
        Route::post('/group/{id}/status', [ZipCodeController::class, 'updateGroupStatus'])->name('update-group-status');
        Route::delete('/group/{id}', [ZipCodeController::class, 'destroyGroup'])->name('destroy-group');

        // Zip codes within a group
        Route::get('/group/{groupId}/zip-data', [ZipCodeController::class, 'getZipCodesData'])->name('zip-data');
        Route::post('/group/{groupId}/add-zips', [ZipCodeController::class, 'addZipCodes'])->name('add-zips');
        Route::post('/group/{groupId}/zip/{zipId}/status', [ZipCodeController::class, 'updateZipCodeStatus'])->name('update-zip-status');
        Route::delete('/group/{groupId}/zip/{zipId}', [ZipCodeController::class, 'destroyZipCode'])->name('destroy-zip');
        Route::post('/group/{groupId}/bulk-delete', [ZipCodeController::class, 'bulkDeleteZipCodes'])->name('bulk-delete-zips');

        // Delivery fees per zone
        Route::post('/group/{groupId}/delivery-fee', [ZipCodeController::class, 'storeDeliveryFee'])->name('store-delivery-fee');
        Route::put('/group/{groupId}/delivery-fee/{pivotId}', [ZipCodeController::class, 'updateDeliveryFee'])->name('update-delivery-fee');
        Route::delete('/group/{groupId}/delivery-fee/{pivotId}', [ZipCodeController::class, 'destroyDeliveryFee'])->name('destroy-delivery-fee');

        // Radius map tool
        Route::get('/all-existing', [ZipCodeController::class, 'getAllExisting'])->name('all-existing');

        // Global search
        Route::get('/search', [ZipCodeController::class, 'searchZipCodes'])->name('search');
    });
  
    // Weather-Triggered Campaigns
    Route::prefix('weather-campaigns')->name('weather-campaigns.')->group(function () {
        Route::get('/', [WeatherCampaignController::class, 'index'])->name('index');
        Route::get('/data', [WeatherCampaignController::class, 'getData'])->name('data');
        Route::get('/create', [WeatherCampaignController::class, 'create'])->name('create');
        Route::post('/', [WeatherCampaignController::class, 'store'])->name('store');
        Route::post('/estimate', [WeatherCampaignController::class, 'estimate'])->name('estimate');
        Route::get('/{id}/edit', [WeatherCampaignController::class, 'edit'])->name('edit');
        Route::post('/{id}/test', [WeatherCampaignController::class, 'sendTest'])->name('sendTest');
        Route::post('/{id}', [WeatherCampaignController::class, 'update'])->name('update');
        Route::delete('/{id}', [WeatherCampaignController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/status', [WeatherCampaignController::class, 'updateStatus'])->name('updateStatus');
    });

    // Product Centre
    Route::prefix('product-centre')->name('product-centre.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'index'])->name('index');
        Route::get('/categories/data', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'getCategoriesData'])->name('categories.data');
        Route::post('/categories/reorder', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'reorderCategories'])->name('categories.reorder');
        Route::post('/categories/{id}/status', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'updateCategoryStatus'])->name('categories.status');
        Route::post('/categories/bulk-delete', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'bulkDeleteCategories'])->name('categories.bulk-delete');
        Route::get('/subcategories/data', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'getSubCategoriesData'])->name('subcategories.data');
        Route::post('/subcategories/reorder', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'reorderSubCategories'])->name('subcategories.reorder');
        Route::post('/subcategories/{id}/status', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'updateSubCategoryStatus'])->name('subcategories.status');
        Route::post('/subcategories/bulk-delete', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'bulkDeleteSubCategories'])->name('subcategories.bulk-delete');
        Route::get('/products/data', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'getProductsData'])->name('products.data');
        Route::post('/products/reorder', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'reorderProducts'])->name('products.reorder');
        Route::post('/products/{id}/status', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'updateProductStatus'])->name('products.status');
        Route::post('/products/bulk-delete', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'bulkDeleteProducts'])->name('products.bulk-delete');
        Route::get('/filter-data', [\App\Http\Controllers\Web\Backend\ProductCentreController::class, 'getFilterData'])->name('filter-data');
    });

    // ============================================================
    // ORDER CENTRE — comprehensive order management
    // Standalone from the legacy /order/* routes (which still work).
    // ============================================================
    Route::prefix('order-centre')->name('order-centre.')->group(function () {
        // Page + stats
        Route::get('/',      [OrderCentreController::class, 'index'])->name('index');
        Route::get('/stats', [OrderCentreController::class, 'getStats'])->name('stats');

        // All Orders DataTable
        Route::get('/data/all-orders', [OrderCentreController::class, 'getAllOrdersData'])->name('data.all-orders');

        // Single order: drawer payload
        Route::get('/orders/{id}/details', [OrderCentreController::class, 'getOrderDetails'])->name('orders.details');

        // Notes
        Route::get('/orders/{id}/notes',                [OrderCentreController::class, 'getNotes'])->name('orders.notes.list');
        Route::post('/orders/{id}/notes',               [OrderCentreController::class, 'addNote'])->name('orders.notes.add');
        Route::delete('/orders/{id}/notes/{noteId}',    [OrderCentreController::class, 'deleteNote'])->name('orders.notes.delete');

        // Audit log
        Route::get('/orders/{id}/audit-log', [OrderCentreController::class, 'getAuditLog'])->name('orders.audit-log');

        // Tags (global + per-order)
        Route::get('/tags',          [OrderCentreController::class, 'listTags'])->name('tags.list');
        Route::post('/tags',         [OrderCentreController::class, 'createTag'])->name('tags.create');
        Route::put('/tags/{id}',     [OrderCentreController::class, 'updateTag'])->name('tags.update');
        Route::delete('/tags/{id}',  [OrderCentreController::class, 'deleteTag'])->name('tags.delete');
        Route::post('/orders/{id}/tags',           [OrderCentreController::class, 'attachTag'])->name('orders.tags.attach');
        Route::delete('/orders/{id}/tags/{tagId}', [OrderCentreController::class, 'detachTag'])->name('orders.tags.detach');

        // ---- Phase 3: Bulk operations ----
        Route::post('/bulk/status',        [OrderCentreController::class, 'bulkUpdateStatus'])->name('bulk.status');
        Route::post('/bulk/payment',       [OrderCentreController::class, 'bulkUpdatePayment'])->name('bulk.payment');
        Route::post('/bulk/delivery-date', [OrderCentreController::class, 'bulkUpdateDeliveryDate'])->name('bulk.delivery-date');
        Route::post('/bulk/notify',        [OrderCentreController::class, 'bulkSendNotification'])->name('bulk.notify');

        // Needs Action dismiss / restore (single + bulk)
        Route::post('/orders/{id}/dismiss',  [OrderCentreController::class, 'dismissOrder'])->name('orders.dismiss');
        Route::post('/orders/{id}/restore',  [OrderCentreController::class, 'restoreOrder'])->name('orders.restore');
        Route::post('/bulk/dismiss',         [OrderCentreController::class, 'dismissOrder'])->name('bulk.dismiss');
        Route::post('/bulk/restore',         [OrderCentreController::class, 'restoreOrder'])->name('bulk.restore');

        // Auto-delivery (end-of-day automation)
        Route::post('/auto-deliver/toggle',  [OrderCentreController::class, 'toggleAutoDeliver'])->name('auto-deliver.toggle');
        Route::get('/auto-deliver/preview',  [OrderCentreController::class, 'previewAutoDeliver'])->name('auto-deliver.preview');
        Route::post('/auto-deliver/run',     [OrderCentreController::class, 'runAutoDeliver'])->name('auto-deliver.run');

        // ---- Phase 3: CSV export ----
        Route::get('/export/csv', [OrderCentreController::class, 'exportCsv'])->name('export.csv');

        // ---- Phase 4: Manual order edit ----
        Route::post('/orders/{id}/edit-details', [OrderCentreController::class, 'updateOrderDetails'])->name('orders.edit-details');

        // ---- Phase 4: Needs Action data ----
        Route::get('/data/needs-action', [OrderCentreController::class, 'getNeedsActionData'])->name('data.needs-action');

        // ---- Phase 4: Refunds Queue ----
        Route::get('/data/refunds-queue',          [OrderCentreController::class, 'getRefundsQueueData'])->name('data.refunds-queue');
        Route::post('/orders/{id}/refund-dismiss', [OrderCentreController::class, 'dismissFromRefundQueue'])->name('orders.refund-dismiss');
        Route::post('/orders/{id}/refund-restore', [OrderCentreController::class, 'restoreToRefundQueue'])->name('orders.refund-restore');

        // ---- Phase 5: Analytics ----
        Route::get('/data/analytics',         [OrderCentreController::class, 'getAnalytics'])->name('data.analytics');

        // ---- Phase 6: WooCommerce settings ----
        Route::get('/woocommerce/settings',   [OrderCentreController::class, 'getWooCommerceSettings'])->name('woocommerce.get');
        Route::post('/woocommerce/settings',  [OrderCentreController::class, 'saveWooCommerceSettings'])->name('woocommerce.save');
        Route::post('/woocommerce/test',      [OrderCentreController::class, 'testWooCommerceConnection'])->name('woocommerce.test');

        // ---- Phase 10 hotfix v3: Notification settings (email/push toggles) ----
        Route::get('/notifications/settings',  [OrderCentreController::class, 'getNotificationSettings'])->name('notifications.get');
        Route::post('/notifications/settings', [OrderCentreController::class, 'saveNotificationSettings'])->name('notifications.save');
        Route::post('/notifications/delivery-test', [OrderCentreController::class, 'sendDeliveryTestEmail'])->name('notifications.delivery-test');

        // ---- Phase 7/10: Website Orders + WooCommerce sync ----
        Route::get('/data/website-orders',           [OrderCentreController::class, 'getWebsiteOrdersData'])->name('data.website-orders');
        Route::post('/woocommerce/sync',             [OrderCentreController::class, 'syncWooCommerce'])->name('woocommerce.sync');
        Route::post('/woocommerce/reset-pull-latest',[OrderCentreController::class, 'resetAndPullLatestWcOrders'])->name('woocommerce.reset-pull-latest');

        // ---- Phase 10: WC order detail + edit + push ----
        Route::get('/wc-orders/{id}/details',        [OrderCentreController::class, 'getWcOrderDetails'])->name('wc.details');
        Route::post('/wc-orders/{id}/edit',          [OrderCentreController::class, 'updateWcOrderDetails'])->name('wc.edit');
        Route::post('/wc-orders/{id}/push-to-wc',    [OrderCentreController::class, 'pushOrderToWooCommerce'])->name('wc.push');

        // ---- Phase 10: WC notes ----
        Route::get('/wc-orders/{id}/notes',          [OrderCentreController::class, 'getWcNotes'])->name('wc.notes.list');
        Route::post('/wc-orders/{id}/notes',         [OrderCentreController::class, 'addWcNote'])->name('wc.notes.add');
        Route::delete('/wc-orders/{orderId}/notes/{noteId}', [OrderCentreController::class, 'deleteWcNote'])->name('wc.notes.delete');

        // ---- Phase 10: WC audit log ----
        Route::get('/wc-orders/{id}/audit',          [OrderCentreController::class, 'getWcAuditLog'])->name('wc.audit');

        // ---- Phase 10: WC tags ----
        Route::post('/wc-orders/{id}/tags',          [OrderCentreController::class, 'attachWcTag'])->name('wc.tags.attach');
        Route::delete('/wc-orders/{id}/tags/{tagId}',[OrderCentreController::class, 'detachWcTag'])->name('wc.tags.detach');

        // ---- Phase 10: WC refunds ----
        Route::post('/wc-orders/{id}/refund',        [OrderCentreController::class, 'processWcRefund'])->name('wc.refund');
        Route::post('/wc-orders/{id}/refund-dismiss',[OrderCentreController::class, 'dismissWcFromRefundQueue'])->name('wc.refund-dismiss');
        Route::post('/wc-orders/{id}/refund-restore',[OrderCentreController::class, 'restoreWcToRefundQueue'])->name('wc.refund-restore');

        // ---- Phase 10: WC customers ----
        Route::get('/wc-customers',                  [OrderCentreController::class, 'getWcCustomersData'])->name('wc.customers.data');
        Route::get('/wc-customers/{id}/profile',     [OrderCentreController::class, 'getWcCustomerProfile'])->name('wc.customers.profile');

        // ---- Phase 9: Today's Deliveries widget ----
        Route::get('/today-deliveries',              [OrderCentreController::class, 'getTodayDeliveries'])->name('today-deliveries');
        Route::post('/orders/{id}/mark-delivered',   [OrderCentreController::class, 'markAsDelivered'])->name('orders.mark-delivered');

        // ---- Phase 9: Customer drill-through (native) ----
        Route::get('/customers/{id}/profile',        [OrderCentreController::class, 'getCustomerProfile'])->name('customers.profile');
    });

}); // ← END OF AUTHENTICATED ROUTES

/*
|--------------------------------------------------------------------------
| PUBLIC DRAW VIEWING ROUTES (NO AUTHENTICATION REQUIRED)
|--------------------------------------------------------------------------
| These routes are OUTSIDE the auth middleware so anyone can access them
*/

// PUBLIC PAGES - No authentication, embeddable
Route::get('/public/draw/latest', [GiveawayCentreController::class, 'publicViewLatest'])
    ->name('public.draw.latest');

Route::get('/public/draw/{id}', [GiveawayCentreController::class, 'publicViewDraw'])
    ->name('public.draw.view');

// PUBLIC API - No authentication, CORS enabled for jjsfirewoodperth.com.au
Route::prefix('api/public')->middleware('public.api.cors')->group(function () {
    Route::get('/draw/latest', [GiveawayCentreController::class, 'publicLatestDraw'])
        ->name('api.public.draw.latest');
    
    Route::get('/draw/{id}', [GiveawayCentreController::class, 'publicDrawById'])
        ->name('api.public.draw.by-id');
    
    Route::get('/latest-draw-id', [GiveawayCentreController::class, 'publicLatestDrawId'])
        ->name('api.public.latest-draw-id');
});

// DIAGNOSTIC TEST ROUTE (Remove after setup is complete!)
Route::get('/test-setup', function() {
    $output = "🔍 GIVEAWAY SYSTEM DIAGNOSTIC\n";
    $output .= "===============================\n\n";
    
    // Test 1: Model exists
    $output .= "1. GiveawayDrawReplay Model: ";
    $output .= class_exists('App\Models\GiveawayDrawReplay') ? "✅ EXISTS\n" : "❌ NOT FOUND\n";
    
    // Test 2: Table exists
    $output .= "2. giveaway_draw_replays Table: ";
    $output .= Schema::hasTable('giveaway_draw_replays') ? "✅ EXISTS\n" : "❌ NOT FOUND (Run migrations!)\n";
    
    // Test 3: animation_style column
    $output .= "3. animation_style Column: ";
    $output .= Schema::hasColumn('giveaways', 'animation_style') ? "✅ EXISTS\n" : "❌ NOT FOUND (Run migrations!)\n";
    
    // Test 4: Controller exists
    $output .= "4. GiveawayCentreController: ";
    $output .= class_exists('App\Http\Controllers\Web\Backend\GiveawayCentreController') ? "✅ EXISTS\n" : "❌ NOT FOUND\n";
    
    // Test 5: View files
    $output .= "5. Public Draw Views: ";
    $viewExists = view()->exists('public.draw-viewer');
    $output .= $viewExists ? "✅ EXISTS\n" : "❌ NOT FOUND\n";
    
    $output .= "\n===============================\n";
    $output .= "Next Steps:\n";
    $output .= "- Upload migration files if tables missing\n";
    $output .= "- Run: php artisan migrate\n";
    $output .= "- Upload view files if missing\n";
    $output .= "- Clear caches\n";
    
    return response($output)->header('Content-Type', 'text/plain');
});
