<?php

namespace App\Http\Controllers\Api;

use App\Helpers\NetworkDetector;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\VoucherPackage;
use App\Services\PaymentGatewayFactory;
use App\Services\PaymentService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentTestController extends Controller
{
    /**
     * Test payment without voucher creation.
     * This endpoint allows testing gateway connectivity without creating vouchers.
     *
     * POST /api/admin/test/payment
     */
    public function testPayment(Request $request)
    {
        if (!hasPermission('test_payments')) {
            return response()->json([
                'message' => 'You are not authorized to test payments.',
            ], 403);
        }

        $validated = $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:500',
        ]);

        $phoneNumber = $validated['phone_number'];

        // Validate phone number format (Ugandan numbers)
        if (!preg_match('/^\+?256[0-9]{9}$/', $phoneNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format. Use format: 256XXXXXXXXX',
            ], 422);
        }

        $network = NetworkDetector::detectNetwork($phoneNumber);

        $payload = [
            'phone_number' => $phoneNumber,
            'amount' => (float) $validated['amount'],
            'currency' => 'UGX',
            'narrative' => 'Test Payment - SuperSpot WiFi',
        ];

        $result = PaymentService::processTestPayment($payload);

        return response()->json([
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? 'Unknown result',
            'gateway' => $result['gateway'] ?? 'unknown',
            'network_detected' => $network,
            'transaction_id' => $result['transaction_id'] ?? null,
            'payment_id' => $result['payment_id'] ?? null,
            'status' => $result['status'] ?? null,
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * Test voucher purchase flow (mirrors production).
     * This endpoint tests the complete voucher purchase flow.
     *
     * POST /api/admin/test/voucher-purchase
     */
    public function testVoucherPurchase(Request $request)
    {
        if (!hasPermission('test_payments')) {
            return response()->json([
                'message' => 'You are not authorized to test payments.',
            ], 403);
        }

        $validated = $request->validate([
            'phone_number' => 'required|string',
            'package_id' => 'required|exists:voucher_packages,id',
        ]);

        $phoneNumber = $validated['phone_number'];

        // Validate phone number format
        if (!preg_match('/^\+?256[0-9]{9}$/', $phoneNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format. Use format: 256XXXXXXXXX',
            ], 422);
        }

        $package = VoucherPackage::findOrFail($validated['package_id']);

        $network = NetworkDetector::detectNetwork($phoneNumber);
        if (!$network) {
            return response()->json([
                'success' => false,
                'message' => 'Could not detect network from phone number',
            ], 422);
        }

        $chargeDetails = PaymentService::calculateTotalWithCharge($package->price, $network);

        $payload = [
            'phone_number' => $phoneNumber,
            'amount' => $chargeDetails->total,
            'currency' => 'UGX',
        ];

        try {
            $paymentData = PaymentService::processPayment($payload, $package, $chargeDetails);

            return response()->json([
                'success' => true,
                'message' => 'Test voucher purchase initiated. Payment prompt sent to phone.',
                'gateway' => $paymentData['_gateway'] ?? 'unknown',
                'network_detected' => $network,
                'package' => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'price' => $package->price,
                ],
                'charge_details' => [
                    'charge' => $chargeDetails->charge,
                    'total' => $chargeDetails->total,
                ],
                'payment_data' => $paymentData,
            ]);
        } catch (\Exception $e) {
            Log::error('Test voucher purchase error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error during test voucher purchase',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check status of a test payment.
     *
     * GET /api/admin/test/payment/status/{reference}
     */
    public function testPaymentStatus($reference)
    {
        if (!hasPermission('test_payments')) {
            return response()->json([
                'message' => 'You are not authorized to test payments.',
            ], 403);
        }

        // Try to find transaction by payment_id first, then by id
        $transaction = Transaction::where('payment_id', $reference)
            ->orWhere('id', $reference)
            ->with(['package', 'voucher'])
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        try {
            // Use the gateway that originally processed this transaction
            $gateway = PaymentGatewayFactory::make($transaction->gateway);
            $gatewayResponse = $gateway->checkPaymentStatus((string) $transaction->payment_id);

            // Update transaction if gateway returned new status
            if (($gatewayResponse['success'] ?? false) && isset($gatewayResponse['status'])) {
                $transaction->status = $gatewayResponse['status'];
                $transaction->response_json = json_encode($gatewayResponse['raw_response'] ?? $gatewayResponse);

                if (!empty($gatewayResponse['mfscode'])) {
                    $transaction->mfscode = $gatewayResponse['mfscode'];
                }

                $transaction->save();
            }

            return response()->json([
                'success' => true,
                'gateway' => $gateway->getName(),
                'transaction' => [
                    'id' => $transaction->id,
                    'payment_id' => $transaction->payment_id,
                    'phone_number' => $transaction->phone_number,
                    'amount' => $transaction->amount,
                    'status' => $transaction->status,
                    'mfscode' => $transaction->mfscode,
                    'created_at' => $transaction->created_at,
                ],
                'voucher' => $transaction->voucher ? [
                    'code' => $transaction->voucher->code,
                    'expires_at' => $transaction->voucher->expires_at,
                    'is_used' => $transaction->voucher->is_used,
                ] : null,
                'gateway_response' => $gatewayResponse,
            ]);
        } catch (\Exception $e) {
            Log::error('Test payment status check error', [
                'reference' => $reference,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking payment status',
                'error' => $e->getMessage(),
                'transaction' => [
                    'id' => $transaction->id,
                    'payment_id' => $transaction->payment_id,
                    'status' => $transaction->status,
                ],
            ], 500);
        }
    }

    /**
     * Get current gateway configuration info.
     *
     * GET /api/admin/test/gateway-info
     */
    public function gatewayInfo()
    {
        if (!hasPermission('test_payments')) {
            return response()->json([
                'message' => 'You are not authorized to view gateway info.',
            ], 403);
        }

        try {
            $gateway = PaymentGatewayFactory::make();

            return response()->json([
                'success' => true,
                'active_gateway' => $gateway->getName(),
                'configured_gateway' => config('services.payment_gateway', 'yopayments'),
                'supported_gateways' => PaymentGatewayFactory::getSupportedGateways(),
                'auto_switch_enabled' => (bool) config('services.payment_gateway_auto_switch', false),
                'auto_switch_every' => (int) config('services.payment_gateway_switch_every', 10),
                'yopayments_configured' => !empty(config('services.yopayments.username')),
                'cinemaug_configured' => !empty(config('services.cinemaug.token')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting gateway info',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
