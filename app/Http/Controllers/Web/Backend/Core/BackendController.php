<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\Giveaway;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

class BackendController extends Controller
{
    /** Orders in these states still need someone to do something. */
    private const OPEN_STATUSES = ['pending', 'confirmed', 'processing', 'shipped'];

    /**
     * payment_status started life as an enum of pending|completed|failed, but
     * 2026_01_02_215822_change_payment_status_to_string_in_orders_table.php
     * turned it into a string and the app writes 'paid'. Everything else in the
     * codebase queries 'paid', so revenue must too.
     */
    private const PAID = 'paid';

    public function index()
    {
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();

        // --- Headline numbers -------------------------------------------------
        $ordersToday = Order::whereDate('created_at', $today)->count();
        $openOrders = Order::whereIn('order_status', self::OPEN_STATUSES)->count();
        $awaitingPayment = Order::where('payment_status', 'pending')->count();

        // Refunded orders took money and gave it back — they are not revenue.
        $revenueMonth = (float) Order::where('payment_status', self::PAID)
            ->where('created_at', '>=', $monthStart)
            ->where('order_status', '!=', 'cancelled')
            ->sum('total');

        $totalOrders = Order::count();
        $totalProducts = Product::count();
        $totalCustomers = User::where('is_admin', 0)->count();
        $totalReviews = Review::count();

        // --- Fourteen-day order trend ----------------------------------------
        // Grouped in PHP rather than SQL so the result is portable across the
        // MySQL/SQLite split between production and local test runs.
        $since = $today->subDays(13);

        $recentCounts = Order::where('created_at', '>=', $since->startOfDay())
            ->get(['created_at'])
            ->groupBy(fn ($order) => $order->created_at->format('Y-m-d'))
            ->map->count();

        $trend = collect(range(0, 13))->map(function ($offset) use ($since, $recentCounts) {
            $day = $since->addDays($offset);

            return [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('D j M'),
                'short' => $day->format('D'),
                'count' => (int) $recentCounts->get($day->format('Y-m-d'), 0),
            ];
        })->all();

        // --- Recent activity --------------------------------------------------
        $needsAction = Order::with('user')
            ->whereIn('order_status', ['pending', 'confirmed'])
            ->latest()
            ->take(6)
            ->get();

        $recentOrders = Order::with('user')->latest()->take(6)->get();
        $recentCustomers = User::where('is_admin', 0)->latest()->take(6)->get();
        $recentReviews = Review::with('user', 'product')->latest()->take(6)->get();

        // Giveaways are an optional module on some deployments.
        $recentWinners = Schema::hasTable('giveaways')
            ? Giveaway::with('winner')->whereNotNull('winner_id')->latest()->take(6)->get()
            : collect();

        return view('backend.layouts.home.index', compact(
            'ordersToday',
            'openOrders',
            'awaitingPayment',
            'revenueMonth',
            'totalOrders',
            'totalProducts',
            'totalCustomers',
            'totalReviews',
            'trend',
            'needsAction',
            'recentOrders',
            'recentCustomers',
            'recentReviews',
            'recentWinners'
        ));
    }
}
