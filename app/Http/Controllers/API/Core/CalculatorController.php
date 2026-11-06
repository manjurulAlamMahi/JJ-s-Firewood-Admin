<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Calculator;
use Illuminate\Support\Facades\Validator;

class CalculatorController extends Controller
{

    public function calculate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pieces_per_night' => 'required|integer|min:1',
            'nights_per_week' => 'required|integer|min:1',
            'months_in_season' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $piecesPerNight = $request->pieces_per_night;
        $nightsPerWeek = $request->nights_per_week;
        $monthsInSeason = $request->months_in_season;

        $weeksInSeason = $monthsInSeason * 4;
        $totalPieces = $piecesPerNight * $nightsPerWeek * $weeksInSeason;

        $pieceWeightKg = 5;
        $totalWeightKg = $totalPieces * $pieceWeightKg;
        $firewoodBags = ceil($totalWeightKg / 20);
        $totalTonnes = $totalWeightKg / 1000;
        $trailerLoads = ceil($totalTonnes / 0.5);

        // Fetch products dynamically based on subcategory names and weight tiers
        $woodTypes = ['Budget Burner', 'Jarrah Bush Mix', 'Jarrah Only'];
        $dynamicLoads = $this->getDynamicAvailableLoads($woodTypes);

        $recommendedLoads = $this->calculateRecommendedLoads($totalTonnes, $dynamicLoads);

        $productData = $this->fetchRealProductDetails($recommendedLoads, $woodTypes);

        $calculation = Calculator::create([
            'pieces_per_night' => $piecesPerNight,
            'nights_per_week' => $nightsPerWeek,
            'months_in_season' => $monthsInSeason,
            'total_pieces' => $totalPieces,
            'firewood_bags_needed' => $firewoodBags,
            'estimated_weight_tonnes' => $totalTonnes,
            'trailer_loads' => $trailerLoads,
            'recommended_loads' => $recommendedLoads,
            'prices' => $productData['prices'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Calculation successful with real product data',
            'data' => [
                'id' => $calculation->id,
                'total_pieces' => $totalPieces,
                'firewood_bags_needed' => $firewoodBags,
                'estimated_weight_tonnes' => number_format($totalTonnes, 2),
                'trailer_loads' => $trailerLoads,
                'recommended_loads' => $this->formatRecommendedLoads($recommendedLoads),
                'prices' => $productData['prices'],
                'product_details' => $productData['details']
            ]
        ]);
    }

    private function getDynamicAvailableLoads($woodTypes)
    {
        $weights = [4, 3, 2.5, 2, 1, 0.5];
        $weightNames = [
            4 => '4/4 Truck Load (16 Scoops)',
            3 => '3/4 Truck Load (12 Scoops)',
            2.5 => '2.5/4 Truck Load (10 Scoops)',
            2 => '2/4 Truck Load (8 Scoops)',
            1 => 'Full Cage (4 Scoops)',
            0.5 => 'Half Cage (2 Scoops)'
        ];

        // Fetch all products from relevant subcategories
        $products = \App\Models\Product::whereHas('subcategory', function ($query) use ($woodTypes) {
            $query->whereIn('name', $woodTypes);
        })->with('subcategory')->get();

        $loads = [];
        foreach ($weights as $weight) {
            $variationIds = [];
            foreach ($woodTypes as $type) {
                // Find product in this subcategory matching the weight
                $match = $products->filter(function ($p) use ($type, $weight) {
                    return $p->subcategory->name === $type && $this->getWeightFromProductName($p->name) == $weight;
                })->first();

                $variationIds[] = $match ? $match->id : null;
            }

            $loads[] = [
                'name' => $weightNames[$weight],
                'weight' => $weight,
                'variation_ids' => $variationIds
            ];
        }

        return $loads;
    }

    private function getWeightFromProductName($name)
    {
        $name = strtolower($name);
        if (str_contains($name, '2.5t') || str_contains($name, '2.5 t') || str_contains($name, '10 scoops')) return 2.5;
        if (str_contains($name, '4t') || str_contains($name, '16 scoops')) return 4.0;
        if (str_contains($name, '3t') || str_contains($name, '12 scoops')) return 3.0;
        if (str_contains($name, '2t') || str_contains($name, '8 scoops')) return 2.0;
        if (str_contains($name, '1t') || str_contains($name, '4 scoops') || str_contains($name, 'full cage')) return 1.0;
        if (str_contains($name, '0.5t') || str_contains($name, '2 scoops') || str_contains($name, 'half cage') || str_contains($name, 'ht')) return 0.5;
        return null;
    }

    private function calculateRecommendedLoads($totalTonnes, $availableLoads)
    {
        $recommendedLoads = [];
        $remainingWeight = $totalTonnes;

        foreach ($availableLoads as $index => $load) {
            if ($index > 0 && $remainingWeight > 0 && $remainingWeight + 0.3 >= $availableLoads[$index - 1]['weight']) {
                $lastLoad = $availableLoads[$index - 1];
                if (!isset($recommendedLoads[$lastLoad['name']])) {
                    $recommendedLoads[$lastLoad['name']] = ['count' => 0, 'weight' => $lastLoad['weight'], 'variation_ids' => $lastLoad['variation_ids']];
                }
                $recommendedLoads[$lastLoad['name']]['count']++;
                $remainingWeight = 0;
                break;
            }

            while ($remainingWeight >= $load['weight']) {
                if (!isset($recommendedLoads[$load['name']])) {
                    $recommendedLoads[$load['name']] = ['count' => 0, 'weight' => $load['weight'], 'variation_ids' => $load['variation_ids']];
                }
                $recommendedLoads[$load['name']]['count']++;
                $remainingWeight -= $load['weight'];
            }
        }

        if ($remainingWeight > 0 && empty($recommendedLoads)) {
            $smallestLoad = end($availableLoads);
            $recommendedLoads[$smallestLoad['name']] = ['count' => 1, 'weight' => $smallestLoad['weight'], 'variation_ids' => $smallestLoad['variation_ids']];
        }

        return $recommendedLoads;
    }


    private function fetchRealProductDetails($recommendedLoads, $woodTypes)
    {
        // Get the actual names from the database for these subcategories to use as keys
        $subcategories = \App\Models\ServiceSubCategory::whereIn('name', $woodTypes)->pluck('name')->toArray();
        
        // Ensure we preserve the order of $woodTypes if we want to maintain the same mapping logic
        // but the user wants the keys to be dynamic. 
        // We'll use the names found in the DB.
        
        $totalPrices = [];
        $productDetails = [];

        foreach ($subcategories as $name) {
            $totalPrices[$name] = 0;
            $productDetails[$name] = [];
        }

        $allIds = [];
        foreach ($recommendedLoads as $loadInfo) {
            $allIds = array_merge($allIds, array_filter($loadInfo['variation_ids']));
        }
        $allIds = array_unique($allIds);

        $products = \App\Models\Product::whereIn('id', $allIds)->with('subcategory')->get()->keyBy('id');

        foreach ($recommendedLoads as $loadName => $loadInfo) {
            $count = $loadInfo['count'];
            $variationIds = $loadInfo['variation_ids'];

            foreach ($variationIds as $variationId) {
                if (!$variationId) continue;
                
                $product = $products->get($variationId);
                if ($product && $product->subcategory) {
                    $type = $product->subcategory->name;

                    if (!isset($totalPrices[$type])) {
                        $totalPrices[$type] = 0;
                        $productDetails[$type] = [];
                    }

                    $priceValue = $product->price;
                    if (!is_numeric($priceValue)) {
                        $priceValue = preg_replace('/[^0-9.]/', '', (string) $priceValue);
                        $priceValue = $priceValue === '' ? 0 : (float) $priceValue;
                    } else {
                        $priceValue = (float) $priceValue;
                    }

                    $totalPrices[$type] += $priceValue * $count;

                    if (!isset($productDetails[$type][$variationId])) {
                        $productDetails[$type][$variationId] = [
                            'id' => $product->id,
                            'name' => $product->name,
                            'price' => $product->price,
                            'image' => $product->image,
                            'description' => $product->description,
                            'quantity' => $count,
                            'subcategory' => $product->subcategory->name
                        ];
                    } else {
                        $productDetails[$type][$variationId]['quantity'] += $count;
                    }
                }
            }
        }

        $formattedPrices = [];
        foreach ($totalPrices as $name => $price) {
            $formattedPrices[] = [
                'product_subcategory' => $name,
                'price' => $price
            ];
        }

        $formattedDetails = [];
        foreach ($productDetails as $name => $items) {
            $formattedDetails[] = [
                'product_subcategory' => $name,
                'items' => array_values($items)
            ];
        }

        return [
            'prices' => $formattedPrices,
            'details' => $formattedDetails
        ];
    }


    private function formatRecommendedLoads($recommendedLoads)
    {
        $formatted = [];
        foreach ($recommendedLoads as $loadName => $loadInfo) {
            $formatted[] = [
                'load_name' => $loadName,
                'quantity' => $loadInfo['count'],
                'weight_per_load' => $loadInfo['weight'],
                'total_weight' => $loadInfo['count'] * $loadInfo['weight'],
            ];
        }
        return $formatted;
    }
}
