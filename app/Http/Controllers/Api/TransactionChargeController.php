<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TransactionChargeController extends Controller
{
    /**
     * Get all transaction charges
     */
    public function index(Request $request)
    {
        try {
            $searchTerm = $request->input('search');
            $network = $request->input('network');

            $charges = TransactionCharge::query()
                ->when($network, fn($q) => $q->where('network', $network))
                ->when($searchTerm, function ($q) use ($searchTerm) {
                    $q->where(function ($sub) use ($searchTerm) {
                        $sub->where('network', 'like', "%$searchTerm%")
                            ->orWhere('min_amount', 'like', "%$searchTerm%")
                            ->orWhere('max_amount', 'like', "%$searchTerm%");
                    });
                })
                ->orderBy('network')
                ->orderBy('min_amount')
                ->get();

            return response()->json($charges);
        } catch (\Exception $e) {
            Log::error('Failed to fetch transaction charges: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch transaction charges',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single transaction charge
     */
    public function show($id)
    {
        try {
            $charge = TransactionCharge::findOrFail($id);
            return response()->json($charge);
        } catch (\Exception $e) {
            Log::error('Failed to fetch transaction charge: ' . $e->getMessage());
            return response()->json([
                'message' => 'Transaction charge not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create a new transaction charge
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'min_amount' => 'required|integer|min:0',
                'max_amount' => 'required|integer|gt:min_amount',
                'charge' => 'required|numeric|min:0|max:999999.99',
                'network' => ['required', Rule::in(['MTN', 'AIRTEL'])],
            ], [
                'max_amount.gt' => 'Maximum amount must be greater than minimum amount',
                'network.in' => 'Network must be either MTN or AIRTEL',
            ]);

            // Check for overlapping ranges for the same network
            $overlap = TransactionCharge::where('network', $validated['network'])
                ->where(function ($q) use ($validated) {
                    $q->whereBetween('min_amount', [$validated['min_amount'], $validated['max_amount']])
                        ->orWhereBetween('max_amount', [$validated['min_amount'], $validated['max_amount']])
                        ->orWhere(function ($sub) use ($validated) {
                            $sub->where('min_amount', '<=', $validated['min_amount'])
                                ->where('max_amount', '>=', $validated['max_amount']);
                        });
                })
                ->exists();

            if ($overlap) {
                return response()->json([
                    'message' => 'Amount range overlaps with an existing charge configuration for this network'
                ], 422);
            }

            $charge = TransactionCharge::create($validated);

            return response()->json([
                'message' => 'Transaction charge created successfully',
                'charge' => $charge
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create transaction charge: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create transaction charge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing transaction charge
     */
    public function update(Request $request, $id)
    {
        try {
            $charge = TransactionCharge::findOrFail($id);

            $validated = $request->validate([
                'min_amount' => 'required|integer|min:0',
                'max_amount' => 'required|integer|gt:min_amount',
                'charge' => 'required|numeric|min:0|max:999999.99',
                'network' => ['required', Rule::in(['MTN', 'AIRTEL'])],
            ], [
                'max_amount.gt' => 'Maximum amount must be greater than minimum amount',
                'network.in' => 'Network must be either MTN or AIRTEL',
            ]);

            // Check for overlapping ranges for the same network (excluding current record)
            $overlap = TransactionCharge::where('network', $validated['network'])
                ->where('id', '!=', $id)
                ->where(function ($q) use ($validated) {
                    $q->whereBetween('min_amount', [$validated['min_amount'], $validated['max_amount']])
                        ->orWhereBetween('max_amount', [$validated['min_amount'], $validated['max_amount']])
                        ->orWhere(function ($sub) use ($validated) {
                            $sub->where('min_amount', '<=', $validated['min_amount'])
                                ->where('max_amount', '>=', $validated['max_amount']);
                        });
                })
                ->exists();

            if ($overlap) {
                return response()->json([
                    'message' => 'Amount range overlaps with an existing charge configuration for this network'
                ], 422);
            }

            $charge->update($validated);

            return response()->json([
                'message' => 'Transaction charge updated successfully',
                'charge' => $charge
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update transaction charge: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update transaction charge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a transaction charge
     */
    public function destroy($id)
    {
        try {
            $charge = TransactionCharge::findOrFail($id);
            $charge->delete();

            return response()->json([
                'message' => 'Transaction charge deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete transaction charge: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete transaction charge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate charge for a given amount and network
     */
    public function calculateCharge(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|integer|min:0',
                'network' => ['required', Rule::in(['MTN', 'AIRTEL'])],
            ]);

            $charge = TransactionCharge::where('network', $validated['network'])
                ->where('min_amount', '<=', $validated['amount'])
                ->where('max_amount', '>=', $validated['amount'])
                ->first();

            if (!$charge) {
                return response()->json([
                    'message' => 'No charge configuration found for this amount and network',
                    'charge' => 0
                ]);
            }

            return response()->json([
                'charge' => $charge->charge,
                'total_amount' => $validated['amount'] + $charge->charge,
                'configuration' => $charge
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to calculate charge: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to calculate charge',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
