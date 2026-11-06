<?php

use Illuminate\Support\Facades\Route;



Route::get('/', function () {return redirect()->route('login');});





use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingAddress;

Route::get('/test-mail-preview', function () {
    // Mock Order Object
    $order = new Order();
    $order->order_number = 'JJS00000001';
    $order->created_at = now();
    $order->total = 18.00;
    $order->subtotal = 18.00;
    $order->payment_method = 'Direct bank transfer';
    $order->order_status = 'pending';
    $order->preferred_delivery_date = now()->addDays(2);
    
    // Mock Shipping Address
    $address = new ShippingAddress();
    $address->full_name = 'John Doe';
    $address->label = 'Home'; // e.g. Company or Label
    $address->address = '755 E North Grove Rd';
    $address->city = 'Mayville';
    $address->state = 'Michigan';
    $address->zip_code = '48744';
    $address->phone = '(910) 529-1147';
    
    $order->setRelation('address', $address);

    // Mock Items
    $item1 = new OrderItem();
    $item1->quantity = 2;
    $item1->price = 18.00;
    $product1 = new Product();
    $product1->name = 'Happy YayCommerce';
    $item1->setRelation('product', $product1);
    
    $items = collect([$item1]);
    $order->setRelation('items', $items);

    return view('emails.orders.placed', compact('order'));
});

// Payment checkout routes - accessible without authentication
// Security: Uses session_id validation instead of user auth
Route::get('/checkout/{session_id}', [App\Http\Controllers\PaymentPageController::class, 'checkout'])->name('checkout.show');
Route::post('/checkout/process', [App\Http\Controllers\PaymentPageController::class, 'processCheckout'])->name('checkout.process');

// Public order details page - accessible from email links without authentication
Route::get('/orders/{id}', [App\Http\Controllers\PaymentPageController::class, 'showOrder'])->name('public.order.show')->whereNumber('id');

require __DIR__.'/core_backend.php';