<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\VoucherPackage;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\PaymentService;
use App\Services\SmsService;
use App\Services\VoucherService;
use Faker\Provider\ar_EG\Payment;
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

            $voucher_code = $validated['voucher_code'] ?? "";

            $package = VoucherPackage::findOrFail($validated['package_id']);
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
            $paymentData = PaymentService::processPayment($payload, $package, $voucher_code);
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

            $voucher_code = request()->input('voucher_code', '');
            $generate_voucher = request()->input('generate_voucher', true);
            // Check if transaction exists
            $transaction = Transaction::where('payment_id', $id)->with('package')->first();
            if (!$transaction) {
                return response()->json(['message' => 'Transaction not found'], 202);
            }

            // check status 
            $voucher = PaymentService::checkPaymentStatus($id, $transaction, $generate_voucher, $voucher_code);
            $message = 'Transaction status has been checked and it is ';
            if ($transaction->status === 'successful') {
                $message .= 'successful. You can now use your voucher.';
            } elseif ($transaction->status === 'new') {
                $message .= 'the transaction is still pending.';
            } else if ($transaction->status === 'instructions_sent') {
                $message .= 'instructions have been sent to your phone.';
            } else {
                $message .= 'failed. Please try again or contact support.';
            }

            $sms_sent = false;
            if ($voucher && $generate_voucher && $transaction->status === 'successful') {
                // send voucher to user via SMS
                // remove + from phone number
                $phoneNumber = preg_replace('/^\+/', '', $transaction->phone_number);

                // send sms 
                $sms_sent = SmsService::send($phoneNumber, "Your SuperSpotWiFi voucher code is: {$voucher->code}. Use it to access the internet. Thank you for using our service!");
            }
            return response()->json([
                'message' => $message,
                'transaction' => $transaction,
                'voucher' => $transaction->status === 'successful' ? $voucher : null,
                'sms_sent' => $sms_sent,
            ]);
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('Transaction status check error: ' . $th->getMessage(), [
                'id'      => $id,
                'error'   => $th->getMessage(),
            ]);
            return response()->json([
                'message' => 'An error occurred while checking the transaction status. Please contact support for assistance.',
                'error'   => $th->getMessage(),
            ], 500);
        }
    }

    public function getTransactions(Request $request)
    {
        $transactions = Transaction::with('package')
            ->when($request->has('router_id'), function ($query) use ($request) {
                $routerId = $request->input('router_id');
                $searchTerm = $request->input('search');

                $query->where('router_id', $routerId);

                if ($searchTerm) {
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('phone_number', 'like', '%' . $searchTerm . '%')
                            ->orWhere('payment_id', 'like', '%' . $searchTerm . '%')
                            ->orWhere('status', 'like', '%' . $searchTerm . '%')
                            ->orWhereHas('package', function ($q2) use ($searchTerm) {
                                $q2->where('name', 'like', '%' . $searchTerm . '%');
                            });
                    });
                }
            }, function ($query) use ($request) {
                $searchTerm = $request->input('search');

                if ($searchTerm) {
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('phone_number', 'like', '%' . $searchTerm . '%')
                            ->orWhere('payment_id', 'like', '%' . $searchTerm . '%')
                            ->orWhere('status', 'like', '%' . $searchTerm . '%')
                            ->orWhereHas('package', function ($q2) use ($searchTerm) {
                                $q2->where('name', 'like', '%' . $searchTerm . '%');
                            });
                    });
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($transactions);
    }
}
