<?php

use App\Models\Coupon;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\StripeController;
use App\Http\Controllers\API\Core\CartController;
use App\Http\Controllers\API\Core\InfoController;
use App\Http\Controllers\API\Core\OrderController;
use App\Http\Controllers\API\Core\BannerController;
use App\Http\Controllers\API\Core\CouponController;
use App\Http\Controllers\API\Core\ReviewController;
use App\Http\Controllers\API\Core\DeliveryFollowUpController;
use App\Http\Controllers\API\Core\PaymentController;
use App\Http\Controllers\API\Core\ProductController;
use App\Http\Controllers\API\Core\CategoryController;
use App\Http\Controllers\API\Core\GiveawayController;
use App\Http\Controllers\API\Core\ScheduleController;
use App\Http\Controllers\API\Core\UserAuthController;
use App\Http\Controllers\API\Core\WishlistController;
use App\Http\Controllers\API\Core\CalculatorController;
use App\Http\Controllers\API\Core\SubCategoryController;
use App\Http\Controllers\API\Core\DeliveryTypeController;
use App\Http\Controllers\API\Core\EntryPackageController;
use App\Http\Controllers\API\Core\NotificationController;
use App\Http\Controllers\API\Core\SellingPointController;
use App\Http\Controllers\API\Core\LoyaltyHistoryController;
use App\Http\Controllers\API\Core\ServicesProductController;
use App\Http\Controllers\API\Core\ShippingAddressController;
use App\Http\Controllers\API\Core\ServicesCategoryController;
use App\Http\Controllers\API\Core\UserNotificationController;

use App\Http\Controllers\API\Core\HomeShippingAddressController;
use App\Http\Controllers\API\Core\ServicesSubCategoryController;
use App\Http\Controllers\API\Core\OfficeShippingAddressController;
use App\Http\Controllers\API\Core\ReportController;
use App\Http\Controllers\API\Core\FAQController;
use App\Http\Controllers\API\Core\TipsAndGuidesController;
use App\Http\Controllers\API\Core\ContactUsController;
use App\Http\Controllers\API\Core\BlogController;
use App\Http\Controllers\API\Core\LegalPagesController;
use App\Http\Controllers\API\Core\WoodEducationController;
use App\Http\Controllers\API\Core\BundleController;
use App\Http\Controllers\API\Core\ProductUpsellController;

// Versioning policy:
// - Every route already below (no /v2 prefix) is live in the App Store /
//   Play Store build (v1.2.1). Its path is frozen — do not rename, move,
//   or change the URL of an existing unprefixed route.
// - Any NEW endpoint, or any behavior-breaking change to an endpoint the
//   shipped app doesn't call, must be added under a '/v2/' path
//   (e.g. Route::get('/v2/whatever', ...) or Route::prefix('v2')->group(...)).
// - Check lib/networks/endpoints.dart in the Flutter app for the
//   authoritative list of paths the live app actually calls.

Route::prefix('user')->controller(UserAuthController::class)->group(function () {

    Route::post('register', 'UserRegister');
    Route::post('login', 'Login');
    Route::post('guest_login', 'guestLogin');
    Route::post('forget_password', 'forgetPassword');
    Route::post('verify_otp', 'verifyOtp');
    Route::post('reset_password', 'resetPassword');
    Route::post('resend-otp', 'resendOtp');
    Route::post('google_login', 'googleLogin');
    Route::post('apple_login', 'appleLogin');


    Route::middleware('auth:api')->group(function () {
        // Route::post('/use-referral', 'useReferralCode');
        Route::post('logout', 'logout');
        Route::get('show', 'showUser');
        Route::post('update', 'updateProfile');
        Route::post('change_password', 'changePassword');
        Route::post('verify_email', 'verifyEmail');

    });
});

Route::post('app-account-delete', [UserAuthController::class, 'appAccountDelete']);
Route::post('account-delete', [UserAuthController::class, 'accountDelete'])->middleware('auth:api');



Route::post('/store-user-fcm-token', [UserAuthController::class, 'fmstoreFcmToken'])->middleware('auth:api')->name('user.fmstoreFcmToken');
Route::post('/delete-user-fcm-token', [UserAuthController::class, 'fmdeleteFcmToken'])->middleware('auth:api')->name('user.fmdeleteFcmToken');

Route::get('/v2/faqs', [FAQController::class, 'index']);

Route::get('/v2/tips-and-guides', [TipsAndGuidesController::class, 'index']);
Route::get('/v2/tips-and-guides/{id}', [TipsAndGuidesController::class, 'show']);
Route::get('/v2/blogs', [BlogController::class, 'index']);

Route::get('/v2/legal-pages', [LegalPagesController::class, 'index']);
Route::get('/v2/legal-pages/{slug}', [LegalPagesController::class, 'show']);
Route::get('/v2/wood-education', [WoodEducationController::class, 'index']);

Route::get('/v2/bundles', [BundleController::class, 'index']);
Route::get('/v2/bundles/{id}', [BundleController::class, 'show']);

Route::get('/v2/products/{id}/upsells', [ProductUpsellController::class, 'index']);

Route::get('/v2/campaigns/{id}', [\App\Http\Controllers\API\Core\WeatherCampaignController::class, 'show']);

Route::get('/v2/reorder-suggestions', [\App\Http\Controllers\API\Core\ReorderSuggestionController::class, 'index'])->middleware('auth:api');

Route::prefix('banners')->controller(BannerController::class)->group(function () {
    Route::get('/', 'index');
});

Route::prefix('campaigns')->controller(\App\Http\Controllers\API\Core\WeatherCampaignController::class)->group(function () {
    Route::get('/active', 'active');
});

Route::prefix('selling_points')->controller(SellingPointController::class)->group(function () {
    Route::get('/', 'index');
});

Route::prefix('categories')->controller(CategoryController::class)->group(function () {
    Route::get('/', 'index');
});

Route::prefix('subcategories')->controller(SubCategoryController::class)->group(function () {
    Route::get('/', 'index');
});


Route::prefix('products')->middleware('auth:api')->controller(ProductController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/card/{id}', 'cardProduct');
    Route::get('/{id}', 'show');
});

Route::prefix('reviews')->controller(ReviewController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/store', 'store')->middleware('auth:api');
});

Route::prefix('cart')->controller(CartController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
    Route::post('/store', 'store');
    Route::post('/update/{id}', 'update');
    Route::delete('/{id}', 'destroy');
    Route::post('/calculate-total', 'calculateTotal');
});

Route::prefix('shipping_address')->controller(ShippingAddressController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
    Route::post('/store', 'store');
    // Route::post('/{id}', 'update');
    Route::delete('/{id}', 'destroy');
});

Route::prefix('home_shipping_address')->controller(HomeShippingAddressController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
    Route::post('/store', 'store');
    Route::delete('/{id}', 'destroy');
});

Route::prefix('office_shipping_address')->controller(OfficeShippingAddressController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
    Route::post('/store', 'store');
    Route::delete('/{id}', 'destroy');
});

Route::prefix('orders')->controller(OrderController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
    Route::get('/letestOne', 'LetestOne');
    // Route::post('/store', 'store');
    Route::post('/store', 'newstore');
    Route::get('/track/{orderNumber}', 'track');
    Route::post('/cancel/{id}', 'cancel');
    Route::get('/{id}', 'show');
    Route::put('/{id}/update-details', 'updateCustomerDetails');
    Route::post('/{id}/request-cancellation', 'requestCancellation');
});

Route::prefix('orders/{orderId}/follow-up')->controller(DeliveryFollowUpController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'show');
    Route::post('/open', 'open');
    Route::post('/dismiss', 'dismiss');
    Route::post('/feedback', 'feedback');
    Route::post('/review', 'review');
    Route::post('/reorder', 'reorder');
});

Route::prefix('reports')->controller(ReportController::class)->middleware('auth:api')->group(function () {
    Route::post('/store', 'store');
});

Route::post('/v2/contact-us', [ContactUsController::class, 'store']);

Route::prefix('notifications')->controller(NotificationController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
    Route::get('/unread_count', 'unreadCount');
    // Route::post('/', 'store');
    Route::get('/{id}', 'show');
    Route::put('/{id}/read', 'markAsRead');
    Route::put('/read_all', 'markAllAsRead');
    Route::delete('/{id}', 'destroy');
    Route::delete('/', 'destroyAll');
    // Route::post('/bulk-action', 'bulkAction');
});

// push notifications
  Route::get('/push-notifications', [UserNotificationController::class, 'index'])->middleware('auth:api');
  Route::post('/push-notifications/read-all', [UserNotificationController::class, 'markAllRead'])->middleware('auth:api');
  Route::post('/push-notifications/{id}/read', [UserNotificationController::class, 'markAsRead'])->middleware('auth:api');

Route::prefix('wishlists')->controller(WishlistController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
    Route::post('/store', 'store');
    Route::delete('/{product_id}', 'destroy');
});

Route::prefix('payments')->controller(PaymentController::class)->middleware('auth:api')->group(function () {
    // Payment processing
    Route::post('/pay', 'pay');
    Route::post('/{paymentId}/refund', 'refund');
    Route::post('/{paymentId}/cancel', 'cancelPayment');
    Route::post('/refund-by-order/{orderId}', 'refundByOrder');

    // Payment retrieval
    Route::get('/', 'listPayments');
    Route::get('/{paymentId}', 'getPayment');
    Route::get('/{paymentId}/status', 'getPaymentStatus');
    Route::get('/{paymentId}/verify', 'verifyPayment');

    // Order payment
    Route::get('/order/{orderId}', 'getOrderPayment');

    // Payment success (authenticated)
    Route::get('/success/{orderId}', 'getPaymentSuccess');

    // Diagnostic
    Route::get('/config/check-square', 'checkSquareConfig');
});

// Public payment routes - work without admin dashboard login
Route::prefix('payments')->controller(PaymentController::class)->group(function () {
    // Callback and public success - accessible without authentication
    Route::get('/callback/finalize', 'handleCallback')->name('payment.callback');
    Route::get('/success/public/{orderId}', 'getPaymentSuccessPublic')->name('payment.success.public');
});

// PayPal Orders v2 - authenticated capture + public webview return/cancel pages.
Route::post('/paypal/capture', [\App\Http\Controllers\API\Core\PayPalController::class, 'capture'])->middleware('auth:api');
Route::get('/paypal/return', fn () => response('<html><body>Payment Successful</body></html>'))->name('paypal.return');
Route::get('/paypal/cancel', fn () => response('<html><body>Payment Cancelled</body></html>'))->name('paypal.cancel');

Route::prefix('entry_packages')->controller(EntryPackageController::class)->group(function () {
    // Public success endpoint - accessible without authentication
    Route::get('/success/public/{entryId}', 'getPaymentSuccessPublic')->name('entry_package.success.public');

    // Authenticated endpoints
    Route::get('/my_entries', 'myEntries')->middleware('auth:api');
    Route::get('/', 'index')->middleware('auth:api');
    Route::get('/{id}', 'show')->middleware('auth:api');
    Route::post('/buy/points',  'buyWithPoints')->middleware('auth:api');
    Route::post('/{id}/buy/money', 'buyWithMoney')->middleware('auth:api');
});

Route::prefix('giveaways')->controller(\App\Http\Controllers\Web\Backend\GiveawayCentreController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'apiNextGiveaway');
    Route::get('/{id}', 'apiShow');
    Route::get('winners/recent', 'apiRecentWinners');
    Route::get('winners/my_prizes', 'apiMyPrizes');
    Route::post('prizes/{id}/claim', 'apiClaimPrize');
});

Route::prefix('delivery_types')->controller(DeliveryTypeController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
    Route::get('/zone_wise_delivery_type', 'zoneWiseDeliveryType');
    Route::post('/store', 'store');
    Route::get('/{id}', 'show');
});

Route::prefix('schedules')->controller(ScheduleController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'legacyIndex');
    Route::post('/store', 'store');
    Route::post('/update/{id}', 'legacyUpdate');
    Route::delete('/{id}', 'destroy');
});

Route::prefix('v2/schedules')->controller(ScheduleController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
    Route::put('/{id}', 'update');
    Route::post('/{id}/skip', 'skip');
    Route::post('/{id}/pause', 'pause');
    Route::post('/{id}/resume', 'resume');
    Route::post('/{id}/cancel', 'cancel');
});

Route::prefix('calculators')->controller(CalculatorController::class)->middleware('auth:api')->group(function () {
    Route::post('/calculate', 'calculate');
});

Route::prefix('info')->controller(InfoController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
});

Route::prefix('service_categories')->controller(ServicesCategoryController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
});

Route::prefix('service_sub_categories')->controller(ServicesSubCategoryController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
    Route::get('/{id}', 'show');
});

Route::prefix('service_products')->controller(ServicesProductController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
    Route::get('/{id}', 'show');
    Route::post('/request_quote', 'RequestQuote');
});

Route::prefix('loyalty_history')->controller(LoyaltyHistoryController::class)->middleware('auth:api')->group(function () {
    Route::get('/', 'index');
});

Route::prefix('coupons')->controller(CouponController::class)->middleware('auth:api')->group(function () {
    Route::get('/active', 'activeCoupons');
    Route::post('/apply', 'applyCoupon');
});

Route::post('/stripe/webhook', [StripeController::class, 'handleWebhook']);

// Setmore (via Zapier) webhook — token-authenticated, public POST.
// See SetmoreWebhookController docblock for payload + auth details.
Route::post('/webhooks/zapier/schedule-order',
    [\App\Http\Controllers\API\SetmoreWebhookController::class, 'handle'])
    ->name('webhooks.setmore.schedule');

Route::prefix('giveaway_centre')->controller(\App\Http\Controllers\Web\Backend\GiveawayCentreController::class)->middleware('auth:api')->group(function () {
    Route::get('/stats', 'apiDashboardStats');
    Route::get('/entry_packages', 'apiGetEntryPackages');
    Route::get('/', 'apiNextGiveaway');
    Route::post('/', 'store');
    Route::get('/{id}', 'apiShow');
    Route::post('/{id}', 'update');
    Route::post('/{id}/draw', 'draw');
    Route::get('/permanent_entries', 'apiGetPermanentEntries');
    Route::post('/permanent_entries/{userId}', 'apiUpdatePermanentEntries');
    Route::get('/permanent_entries/{userId}/history', 'apiGetUserHistory');
});
