<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\DeliveryType;
use App\Models\User;
use App\Models\ReferralUsage;
use App\Models\ShippingAddress;
use App\Models\ZipCode;
use App\Models\ZoneWiseDeliveryType;

class OrderCalculationService
{
    public static function calculate(User $user, ?string $couponCode = null, ?int $deliveryTypeId = null, ?int $shippingAddressId = null)
    {
        $cartItems = Cart::with(['product', 'giveaway'])
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            throw new \Exception('Cart is empty');
        }

        $subtotal = $cartItems->sum('subtotal');
        $tax = $subtotal * 0.1;

        $deliveryFee = 0;
        $deliveryInfo = null;
        $deliveryTypeId = $deliveryTypeId ?: 1;

        if ($deliveryTypeId) {
            $postcode = null;
            if ($shippingAddressId) {
                $address = ShippingAddress::find($shippingAddressId);
                $postcode = $address->zip_code ?? null;
            }

            $resolved = self::resolveDeliveryFee($postcode, (int) $deliveryTypeId);

            if ($resolved['delivery_type']) {
                $deliveryFee = $resolved['fee'];
                $deliveryInfo = [
                    'id' => $resolved['delivery_type']['id'],
                    'name' => $resolved['delivery_type']['name'],
                    'fee' => $deliveryFee,
                    'days' => $resolved['delivery_type']['days'],
                ];
            }
        }

        $giveawayDiscount = 0;
        $packageGiveaway = $cartItems->first(
            fn ($item) => $item->giveaway && $item->giveaway->prize_type === 'discount'
        );

        if ($packageGiveaway) {
            $giveawayDiscount = $packageGiveaway->giveaway->prize_value;
        }

        $couponDiscount = 0;
        $referralDiscount = 0;
        $couponInfo = null;

        if ($couponCode) {

            $coupon = Coupon::where('code', $couponCode)->first();
            if ($coupon && $coupon->isValid($user->id)) {
                $couponDiscount = $coupon->calculateDiscount($subtotal, $user->id);
                $couponInfo = [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'type' => 'coupon',
                    'discount_amount' => round($couponDiscount, 2),
                ];
            } 
            else {
                $referrer = User::where('referral_code', $couponCode)->first();

                if ($referrer && $referrer->id !== $user->id) {
                    $alreadyReferred = ReferralUsage::where('referred_user_id', $user->id)
                        ->exists();

                    if (!$alreadyReferred) {
                        $referralDiscount = min(10.00, $subtotal); 
                        $couponInfo = [
                            'code' => $couponCode,
                            'type' => 'referral',
                            'discount_amount' => round($referralDiscount, 2),
                            'referrer_name' => $referrer->name,
                        ];
                    }
                }
            }
        }

        $totalDiscount = $giveawayDiscount + $couponDiscount + $referralDiscount;

        $total = round(
            $subtotal  + $deliveryFee - $totalDiscount,
            2
        );

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => $tax,
            'delivery_fee' => round($deliveryFee, 2),
            'delivery_info' => $deliveryInfo,
            'giveaway_discount' => round($giveawayDiscount, 2),
            'coupon_discount' => round($couponDiscount, 2),
            'referral_discount' => round($referralDiscount, 2),
            'total_discount' => round($totalDiscount, 2),
            'total' => $total,
            'coupon' => $couponInfo,
        ];
    }

    /**
     * Zone-fee-then-base-fee lookup, shared by checkout calculation and the
     * staff delivery calculator admin tool. $postcode may be null/empty —
     * that's treated the same as "no zone match", falling back to the
     * delivery type's base fee (same as calculate() did before this was
     * extracted).
     */
    public static function resolveDeliveryFee(?string $postcode, int $deliveryTypeId): array
    {
        $deliveryType = DeliveryType::find($deliveryTypeId);
        if (!$deliveryType) {
            return ['fee' => 0, 'delivery_type' => null, 'zone_matched' => false];
        }

        $fee = $deliveryType->fee;
        $zoneMatched = false;

        if ($postcode) {
            $zipCode = ZipCode::where('zip_code', $postcode)->first();
            if ($zipCode && $zipCode->group_id) {
                $zoneFee = ZoneWiseDeliveryType::where('zip_code_group_id', $zipCode->group_id)
                    ->where('delivery_type_id', $deliveryTypeId)
                    ->first();

                if ($zoneFee) {
                    $fee = $zoneFee->fee;
                    $zoneMatched = true;
                }
            }
        }

        return [
            'fee' => round((float) $fee, 2),
            'delivery_type' => [
                'id' => $deliveryType->id,
                'name' => $deliveryType->name,
                'days' => $deliveryType->days,
            ],
            'zone_matched' => $zoneMatched,
        ];
    }
}