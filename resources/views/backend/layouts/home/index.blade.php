@extends('backend.master')

@section('title', 'Dashboard')

@php
    /** Maps an order_status enum value onto a pill tone. */
    $statusTone = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
    ];

    // payment_status is a free-form string column; these are the values the app
    // and the Order Centre actually write.
    $payTone = [
        'pending' => 'warning',
        'paid' => 'success',
        'refunded' => 'neutral',
        'partially_refunded' => 'neutral',
        'failed' => 'danger',
    ];

    $payLabel = fn ($v) => ucfirst(str_replace('_', ' ', (string) $v));

    $peak = collect($trend)->max('count') ?: 1;
@endphp

@section('body')

    <x-admin.page-header
        title="Dashboard"
        :subtitle="'Here is where things stand as of ' . now()->format('D j M, g:ia') . '.'">
        <x-slot:actions>
            @if (Route::has('order-centre.index'))
                <a href="{{ route('order-centre.index') }}" class="btn btn-primary">
                    <x-admin-icon name="receipt" :size="17" /> Order Centre
                </a>
            @endif
            @if (Route::has('products.create'))
                <a href="{{ route('products.create') }}" class="btn btn-secondary">
                    <x-admin-icon name="plus" :size="17" /> New product
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Four numbers worth acting on, not four vanity totals. --}}
    <div class="av-stats">
        <x-admin.stat
            label="Orders today"
            :value="number_format($ordersToday)"
            icon="receipt"
            tone="primary"
            :note="number_format($totalOrders) . ' all time'"
            :href="Route::has('order-centre.index') ? route('order-centre.index') : null" />

        <x-admin.stat
            label="Open orders"
            :value="number_format($openOrders)"
            icon="clock"
            tone="warning"
            note="Not yet delivered"
            :href="Route::has('order-centre.index') ? route('order-centre.index') : null" />

        <x-admin.stat
            label="Awaiting payment"
            :value="number_format($awaitingPayment)"
            icon="credit-card"
            :tone="$awaitingPayment > 0 ? 'danger' : 'success'"
            note="Payment status pending" />

        <x-admin.stat
            label="Revenue this month"
            :value="'$' . number_format($revenueMonth, 2)"
            icon="dollar"
            tone="success"
            note="Paid orders only" />
    </div>

    <div class="row g-3">

        {{-- ---------------------------------------------------------------
             Needs attention — the first thing to look at on a phone.
             --------------------------------------------------------------- --}}
        <div class="col-12 col-xl-7">
            <x-admin.card title="Needs attention" flush>
                <x-slot:actions>
                    @if (Route::has('order-centre.index'))
                        <a href="{{ route('order-centre.index') }}" class="btn btn-sm btn-secondary">View all</a>
                    @endif
                </x-slot:actions>

                @forelse ($needsAction as $order)
                    <a class="av-list__row"
                       href="{{ Route::has('order.show') ? route('order.show', $order->id) : '#' }}">
                        <span class="av-list__main">
                            <span class="av-list__title">
                                #{{ $order->order_number }}
                                <span class="av-pill av-pill--{{ $statusTone[$order->order_status] ?? 'neutral' }}">
                                    {{ ucfirst($order->order_status) }}
                                </span>
                            </span>
                            <span class="av-list__sub">
                                {{ $order->user->name ?? 'Guest' }} ·
                                {{ $order->created_at?->diffForHumans() }}
                            </span>
                        </span>
                        <span class="av-list__meta">${{ number_format((float) $order->total, 2) }}</span>
                    </a>
                @empty
                    <x-admin.empty-state
                        icon="check-circle"
                        title="Nothing waiting"
                        text="Every order has moved past confirmation." />
                @endforelse
            </x-admin.card>
        </div>

        {{-- ---------------------------------------------------------------
             Order volume — real counts, no placeholder series.
             --------------------------------------------------------------- --}}
        <div class="col-12 col-xl-5">
            <x-admin.card title="Orders · last 14 days">
                <div class="av-bars" role="img"
                     aria-label="Daily order counts for the last fourteen days">
                    @foreach ($trend as $day)
                        <div class="av-bars__col" title="{{ $day['label'] }}: {{ $day['count'] }}">
                            <div class="av-bars__track">
                                <div class="av-bars__fill"
                                     style="height: {{ $day['count'] > 0 ? max(6, round($day['count'] / $peak * 100)) : 2 }}%"></div>
                            </div>
                            <span class="av-bars__tick">{{ $day['short'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="av-row av-row--between" style="margin-top:12px">
                    <span class="av-stat__note">Peak day: {{ $peak }}</span>
                    <span class="av-stat__note">
                        Total: {{ collect($trend)->sum('count') }}
                    </span>
                </div>
            </x-admin.card>
        </div>

        {{-- --------------------------------------------------------------- --}}
        <div class="col-12 col-lg-6">
            <x-admin.card title="Latest orders" flush>
                @forelse ($recentOrders as $order)
                    <a class="av-list__row"
                       href="{{ Route::has('order.show') ? route('order.show', $order->id) : '#' }}">
                        <span class="av-list__main">
                            <span class="av-list__title">#{{ $order->order_number }}</span>
                            <span class="av-list__sub">{{ $order->user->name ?? 'Guest' }}</span>
                        </span>
                        <span class="av-list__meta">
                            ${{ number_format((float) $order->total, 2) }}
                            <span class="av-pill av-pill--{{ $payTone[$order->payment_status] ?? 'neutral' }}"
                                  style="display:block;margin-top:4px">
                                {{ $payLabel($order->payment_status) }}
                            </span>
                        </span>
                    </a>
                @empty
                    <x-admin.empty-state title="No orders yet" />
                @endforelse
            </x-admin.card>
        </div>

        <div class="col-12 col-lg-6">
            <x-admin.card title="New customers" flush>
                <x-slot:actions>
                    @if (Route::has('customer-management.index'))
                        <a href="{{ route('customer-management.index') }}"
                           class="btn btn-sm btn-secondary">View all</a>
                    @endif
                </x-slot:actions>

                @forelse ($recentCustomers as $customer)
                    <a class="av-list__row"
                       href="{{ Route::has('customer-management.show') ? route('customer-management.show', $customer->id) : '#' }}">
                        <x-admin-avatar :user="$customer" :size="34" />
                        <span class="av-list__main">
                            <span class="av-list__title">{{ $customer->name ?: 'Unnamed' }}</span>
                            <span class="av-list__sub">{{ $customer->email ?: $customer->phone }}</span>
                        </span>
                        <span class="av-list__meta av-stat__note">
                            {{ $customer->created_at?->diffForHumans(null, true) }}
                        </span>
                    </a>
                @empty
                    <x-admin.empty-state title="No customers yet" />
                @endforelse
            </x-admin.card>
        </div>

        <div class="col-12 col-lg-6">
            <x-admin.card title="Latest reviews" flush>
                <x-slot:actions>
                    @if (Route::has('review.index'))
                        <a href="{{ route('review.index') }}" class="btn btn-sm btn-secondary">Moderate</a>
                    @endif
                </x-slot:actions>

                @forelse ($recentReviews as $review)
                    <div class="av-list__row">
                        <span class="av-list__main">
                            <span class="av-list__title">{{ $review->product->name ?? 'Deleted product' }}</span>
                            <span class="av-list__sub">
                                {{ $review->user->name ?? 'Anonymous' }}
                                @if ($review->comment)
                                    — {{ Str::limit($review->comment, 60) }}
                                @endif
                            </span>
                        </span>
                        <span class="av-list__meta">{{ $review->rating }}/5</span>
                    </div>
                @empty
                    <x-admin.empty-state title="No reviews yet" />
                @endforelse
            </x-admin.card>
        </div>

        <div class="col-12 col-lg-6">
            <x-admin.card title="Recent giveaway winners" flush>
                @forelse ($recentWinners as $giveaway)
                    <div class="av-list__row">
                        <span class="av-list__main">
                            <span class="av-list__title">{{ $giveaway->winner->name ?? 'Winner pending' }}</span>
                            <span class="av-list__sub">{{ $giveaway->prize_name }}</span>
                        </span>
                        <span class="av-list__meta av-stat__note">
                            {{ $giveaway->winner_drawn_at?->diffForHumans() ?? '' }}
                        </span>
                    </div>
                @empty
                    <x-admin.empty-state icon="gift" title="No winners drawn yet" />
                @endforelse
            </x-admin.card>
        </div>
    </div>

    {{-- Catalogue totals live at the bottom: useful context, not daily work. --}}
    <div class="av-stats" style="margin-top:4px">
        <x-admin.stat label="Products" :value="number_format($totalProducts)" icon="box" tone="info"
            :href="Route::has('product-centre.index') ? route('product-centre.index') : null" />
        <x-admin.stat label="Customers" :value="number_format($totalCustomers)" icon="users" tone="primary"
            :href="Route::has('customer-management.index') ? route('customer-management.index') : null" />
        <x-admin.stat label="Reviews" :value="number_format($totalReviews)" icon="star" tone="warning"
            :href="Route::has('review.index') ? route('review.index') : null" />
        <x-admin.stat label="Orders all time" :value="number_format($totalOrders)" icon="receipt" tone="success" />
    </div>
@endsection
