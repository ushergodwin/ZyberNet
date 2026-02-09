<?php

namespace App\Http\Controllers\Api;

use App\Helpers\NetworkDetector;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VoucherPackage;
use App\Models\Transaction;
use App\Services\PaymentService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
            // Normalize phone number to 256XXXXXXXXX format
            $phoneNumber = NetworkDetector::normalizePhoneNumber($validated['phone_number']);
            if (!$phoneNumber) {
                return response()->json(['message' => 'Invalid phone number format'], 202);
            }

            $network = NetworkDetector::detectNetwork($phoneNumber);
            if (!$network) {
                return response()->json(['message' => 'Could not detect network from phone number'], 202);
            }

            $chargeDetails = PaymentService::calculateTotalWithCharge($package->price, $network);

            // Prepare payment payload
            $payload = [
                'phone_number' => $phoneNumber,
                'amount'       => $chargeDetails->total,
                'currency'     => 'UGX',
            ];

            // Make payment request
            $paymentData = PaymentService::processPayment($payload, $package, $chargeDetails, $voucher_code);
            return response()->json([
                'message'     => 'A payment prompt has been sent to your phone. Please enter your pin to complete the payment and an SMS with a voucher will be sent to you in less than 2 minutes.',
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
            $transaction = Transaction::withTrashed()->where('payment_id', $id)->with(['package', 'voucher'])->first();
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
            } else if ($transaction->status === 'pending') {
                $message .= 'the transaction is still pending.';
            } else if ($transaction->status === 'processing_started') {
                $message .= 'the transaction is being processed.';
            } elseif ($transaction->status === 'failed') {
                $message .= 'failed. Please try again or contact support.';
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

                // CinemaUG transactions are not needed after successful completion —
                // detach the voucher and permanently delete the transaction record.
                if ($transaction->gateway === 'cinemaug') {
                    $voucher->transaction_id = null;
                    $voucher->save();
                    $transaction->forceDelete();

                    Log::info('CinemaUG transaction deleted after successful payment', [
                        'payment_id' => $id,
                    ]);
                }
            }

            return response()->json([
                'message' => $message,
                'transaction' => $transaction->gateway === 'cinemaug' && $transaction->status === 'successful' ? null : $transaction,
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
        // Check permissions
        if (!hasPermission('view_payments')) {
            return response()->json(['message' => 'You are not authorized to view transactions. Please contact system admin.'], 401);
        }

        if (!$request->date_from) {
            $request->merge(['date_from' => now()->subMonths(2)->format('Y-m-d')]);
        }
        // Validate the request parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'router_id' => 'nullable|integer|exists:router_configurations,id',
            'status' => 'nullable|string|in:new,pending,instructions_sent,processing_started,successful,failed',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:10|max:500',
        ]);

        $query = Transaction::with(['package', 'voucher']);

        // Apply router filter
        if ($request->filled('router_id') && $request->input('router_id') != 0) {
            $query->where('router_id', $request->input('router_id'));
        }

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply date range filter
        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse($request->input('date_from'))->startOfDay();
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse($request->input('date_to'))->endOfDay();
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Apply search filter
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('phone_number', 'like', '%' . $searchTerm . '%')
                    ->orWhere('payment_id', 'like', '%' . $searchTerm . '%')
                    ->orWhere('status', 'like', '%' . $searchTerm . '%')
                    ->orWhere('mfscode', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('package', function ($q2) use ($searchTerm) {
                        $q2->where('name', 'like', '%' . $searchTerm . '%');
                    })
                    ->orWhereHas('voucher', function ($q3) use ($searchTerm) {
                        $q3->where('code', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        // Get per_page value or default to 150
        $perPage = $request->input('per_page', 150);

        // Execute query with pagination
        $transactions = $query->paginate($perPage);

        // Add additional computed fields to each transaction
        $transactions->getCollection()->transform(function ($transaction) {
            $transaction->formatted_amount = $this->formatCurrency($transaction->amount, $transaction->currency);
            $transaction->days_old = Carbon::parse($transaction->created_at)->diffInDays(now());

            return $transaction;
        });

        // Add summary statistics to the response
        $summary = [
            'total_transactions' => $transactions->total(),
            'status_breakdown' => $this->getStatusBreakdown($request),
            'date_range_summary' => $this->getDateRangeSummary($request),
        ];

        return response()->json([
            'data' => $transactions->items(),
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'from' => $transactions->firstItem(),
            'to' => $transactions->lastItem(),
            'summary' => $summary,
        ]);
    }

    /**
     * Get status breakdown for current filters
     */
    private function getStatusBreakdown(Request $request)
    {
        $query = Transaction::query();

        // Apply same filters except status
        if ($request->filled('router_id') && $request->input('router_id') != 0) {
            $query->where('router_id', $request->input('router_id'));
        }

        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse($request->input('date_from'))->startOfDay();
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse($request->input('date_to'))->endOfDay();
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('phone_number', 'like', '%' . $searchTerm . '%')
                    ->orWhere('payment_id', 'like', '%' . $searchTerm . '%')
                    ->orWhere('mfscode', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('package', function ($q2) use ($searchTerm) {
                        $q2->where('name', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        return $query->selectRaw('status, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    $item->status => [
                        'count' => $item->count,
                        'total_amount' => $item->total_amount,
                        'formatted_amount' => $this->formatCurrency($item->total_amount, 'UGX')
                    ]
                ];
            });
    }

    /**
     * Get date range summary
     */
    private function getDateRangeSummary(Request $request)
    {
        if (!$request->filled('date_from') && !$request->filled('date_to')) {
            return null;
        }

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : null;

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : null;

        return [
            'date_from' => $dateFrom ? $dateFrom->format('Y-m-d') : null,
            'date_to' => $dateTo ? $dateTo->format('Y-m-d') : null,
            'days_span' => $dateFrom && $dateTo ? $dateFrom->diffInDays($dateTo) + 1 : null,
        ];
    }

    /**
     * Format currency amount
     */
    private function formatCurrency($amount, $currency = 'UGX')
    {
        $amount = floatval($amount);

        switch (strtoupper($currency)) {
            case 'UGX':
                return 'UGX ' . number_format($amount, 0);
            case 'USD':
                return '$' . number_format($amount, 2);
            case 'EUR':
                return '€' . number_format($amount, 2);
            default:
                return $currency . ' ' . number_format($amount, 2);
        }
    }

    public function saveTransaction(Request $request)
    {
        try {
            $request->validate([
                'phone_number' => 'required|string',
                'amount' => 'required|string|max:20',
                'currency' => 'required|string|max:3',
                'status' => 'required|string|in:pending,successful,failed',
                'router_id' => 'required|exists:router_configurations,id',
                'created_at' => 'nullable|date',
            ]);

            // generate a unique payment ID, only digits, 8 characters long prefix ME
            $paymentId = '82' . random_int(100000, 999999);
            $transactionData = [
                'phone_number' => $request->input('phone_number'),
                'amount' => intval($request->input('amount')),
                'currency' => $request->input('currency'),
                'status' => $request->input('status'),
                'payment_id' => $paymentId,
                'mfscode' => uniqid('MW'),
                'package_id' => null,
                'channel' => 'mobile_money',
                'router_id' => $request->input('router_id')
            ];

            $transaction = new Transaction($transactionData);
            $transaction->save();

            // update created_at if provided
            if ($request->filled('created_at')) {
                $transaction->created_at = Carbon::parse($request->input('created_at'));
                $transaction->save();
            }

            // fetch the newly created transaction
            $transaction = Transaction::where('id', $transaction->id)->first();
            return response()->json([
                'message' => 'Transaction saved successfully',
                'transaction' => $transaction,
            ]);
        } catch (\Throwable $e) {
            Log::error("Error Recording Withdraw Transaction" . $e->getMessage(), $e->getTrace());
            return response()->json([
                "message" => "An error occurred while recording your transaction: " .  $e->getMessage()
            ], 202);
        }
    }


    /**
     * Export transactions to CSV
     */
    public function exportTransactions(Request $request)
    {
        if (!hasPermission('export_payments')) {
            return response()->json(['message' => 'You are not authorized to export transactions.'], 401);
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'router_id' => 'nullable|integer|exists:router_configurations,id',
            'status' => 'nullable|string|in:new,pending,instructions_sent,processing_started,successful,failed',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = Transaction::with(['package', 'voucher']);

        // Apply the same filters as getTransactions
        if ($request->filled('router_id') && $request->input('router_id') != 0) {
            $query->where('router_id', $request->input('router_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse($request->input('date_from'))->startOfDay();
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse($request->input('date_to'))->endOfDay();
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('phone_number', 'like', '%' . $searchTerm . '%')
                    ->orWhere('payment_id', 'like', '%' . $searchTerm . '%')
                    ->orWhere('status', 'like', '%' . $searchTerm . '%')
                    ->orWhere('mfscode', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('package', function ($q2) use ($searchTerm) {
                        $q2->where('name', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $filename = 'transactions_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'Payment ID',
                'Phone Number',
                'Amount',
                'Currency',
                'Package',
                'Voucher Code',
                'Status',
                'Channel',
                'Router ID',
                'MFS Code',
                'Created At',
                'Updated At'
            ]);

            // CSV data
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->payment_id,
                    $transaction->phone_number,
                    $transaction->amount,
                    $transaction->currency,
                    $transaction->package ? $transaction->package->name : 'N/A',
                    $transaction->voucher ? $transaction->voucher->code : 'N/A',
                    $transaction->status,
                    $transaction->channel,
                    $transaction->router_id,
                    $transaction->mfscode,
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    $transaction->updated_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
