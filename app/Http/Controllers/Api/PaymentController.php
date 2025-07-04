<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\VoucherPackage;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function purchaseVoucher(Request $request)
    {
        try {
            $validated = $request->validate([
                'phone_number' => 'required|string',
                'package_id'   => 'required|exists:voucher_packages,id',
                'voucher_code' => 'nullable|string',
            ]);

            $voucher_code = $validated['voucher_code'] ?? null;

            $package = VoucherPackage::find($validated['package_id']);
            if (!$package) {
                return response()->json(['message' => 'Package not found'], 202);
            }
            $phoneNumber = $validated['phone_number'];
            // Validate phone number format (optional, depending on your requirements)
            if (!preg_match('/^\+?256[0-9]{9}$/', $phoneNumber)) {
                return response()->json(['message' => 'Invalid phone number format'], 202);
            }

            // Prepare payment payload
            $payload = [
                'phone_number' => $phoneNumber,
                'amount'       => $package->price,
                'currency'     => 'UGX',
            ];

            // Make payment request
            $response = Http::withToken(env('CINEMAUG_API_TOKEN'))
                ->post('https://cinemaug.com/payments/collect.php', $payload);

            if (!$response->successful()) {
                return response()->json(['message' => 'Payment request failed. Please make sure you provided a correct phone number'], 202);
            }

            $paymentData = $response->json();
            // Store transaction
            $transaction = Transaction::create([
                'phone_number'  => $paymentData['contact']['phone_number'],
                'amount'        => $paymentData['amount'],
                'currency'      => $paymentData['currency'],
                'status'        => $paymentData['status'],
                'payment_id'    => $paymentData['id'],
                'mfscode'       => $paymentData['mfscode'],
                'package_id'    => $package->id,
                'response_json' => json_encode($paymentData),
                'channel'       => 'mobile_money',
            ]);

            if ($voucher_code) {
                $voucher = Voucher::where('code', $voucher_code)->first();
                if ($voucher) {
                    $voucher->transaction_id = $transaction->id;
                    $voucher->save();
                }
            }
            return response()->json([
                'message'     => 'A payment prompt has been sent to your phone. Please complete the payment by entering your pin.',
                'paymentData' => $paymentData,
            ]);
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('Payment error: ' . $th->getMessage(), [
                'request' => $request->all(),
                'trace'   => $th->getTrace(),
            ]);

            return response()->json([
                'message' => 'An error occurred while processing the payment',
                'error'   => $th->getMessage(),
            ], 500);
        }
    }

    public function checkTransactionStatus($id)
    {
        try {
            $voucher_code = request()->input('voucher_code');
            // Make payment request
            $response = Http::withToken(env('CINEMAUG_API_TOKEN'))
                ->get('https://cinemaug.com/payments/collect.php?id=' . $id);

            if (!$response->successful()) {
                return response()->json(['message' => 'Payment request failed'], 202);
            }
            $paymentData = $response->json();
            // Check if transaction exists
            $transaction = Transaction::where('payment_id', $id)->with('package')->first();
            if (!$transaction) {
                return response()->json(['message' => 'Transaction not found'], 202);
            }
            // Update transaction status
            $transaction->status = $paymentData['status'];
            $transaction->response_json = json_encode($paymentData);
            $transaction->save();

            $voucher = null;
            if ($voucher_code) {
                $voucher = Voucher::where('code', $voucher_code)->first();
                if ($voucher) {
                    $voucher->transaction_id = $transaction->id;
                    $voucher->save();
                    $transaction->voucher = $voucher;
                }
            }
            if ($transaction->status === 'successful' && !$transaction->voucher) {
                $session_timeout = substr($transaction->package->session_timeout, 0, -1); // Remove the last character (h or d)
                $session_timeout_unit = substr($transaction->package->session_timeout, -1); // Get the last character (h or d)
                // Calculate expiration date based on session timeout
                $expiresAt = now()->add($session_timeout_unit === 'd' ? $session_timeout . ' days' : $session_timeout . ' hours');

                $code = "SSW" . strtoupper(Str::random(6));
                $voucher = [
                    'code'           => $code,
                    'transaction_id' => $transaction->id,
                    'package_id'     => $transaction->package_id,
                    'expires_at'     => $expiresAt,
                ];
                // Create voucher
                $voucherService = new VoucherService();
                $voucher = $voucherService->createVouchersAndPushToRouter([$voucher])[0];
            }
            return response()->json([
                'message' => 'Transaction status updated successfully',
                'transaction' => $transaction,
                'voucher' => $transaction->status === 'successful' ? $voucher : null,
            ]);
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('Transaction status check error: ' . $th->getMessage(), [
                'id'      => $id,
                'error'   => $th->getMessage(),
            ]);
            return response()->json([
                'message' => 'An error occurred while checking the transaction status',
                'error'   => $th->getMessage(),
            ], 500);
        }
    }

    public function getTransactions(Request $request)
    {
        $transactions = Transaction::with('package')
            ->when($request->search, function ($query) use ($request) {
                $searchTerm = $request->input('search');
                return $query->where('phone_number', 'like', '%' . $searchTerm . '%')
                    //payment_id
                    ->orWhere('payment_id', 'like', '%' . $searchTerm . '%')
                    // status 
                    ->orWhere('status', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('package', function ($q) use ($request) {
                        $searchTerm = $request->input('search');
                        $q->where('name', 'like', '%' . $searchTerm . '%');
                    });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($transactions);
    }
}
