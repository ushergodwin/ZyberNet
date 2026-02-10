<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RouterConfiguration;
use App\Models\RouterLog;
use App\Models\Voucher;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RouterController extends Controller
{
    //
    public function pushToRouter($voucherId)
    {

        $voucher = Voucher::withTrashed()->with('router', 'package')->findOrFail($voucherId);
        $router = $voucher->router ?? RouterConfiguration::findOrFail($voucher->router_id);

        try {
            if (config('app.env') != 'local') {
                $mikrotik = new MikroTikService($router);
                $mikrotik->createHotspotUser(
                    $voucher->code,
                    $voucher->code,
                    $voucher->package->session_timeout,
                    $voucher->package->profile_name,
                );
            }


            return response()->json(['message' => 'Voucher pushed to router']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to push voucher: ' . $e->getMessage()], 500);
        }
    }

    // test connection to router 

    public function testConnection($id)
    {
        $router = RouterConfiguration::find($id);

        if (!$router) {
            return response()->json(['error' => 'No router configuration found'], 404);
        }

        try {
            $mikrotik = new MikroTikService($router);
            $mikrotik->testConnection();

            return response()->json(['message' => 'Connection successful']);
        } catch (\Throwable $e) {
            Log::error('Failed to connect to router: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to connect to router: ' . $e->getMessage()], 202);
        }
    }

    // get router logs
    public function getRouterLogs(Request $request)
    {

        if (!hasPermission('view_router_logs')) {
            return response()->json(['message' => 'You are not authorized to view router logs. Please contact system admin.'], 401);
        }
        $start = $request->from ? $request->from : now()->startOfWeek();
        $end   = $request->to ? $request->to : now();
        $logs = RouterLog::orderBy('id', 'desc')->whereBetween('created_at', [$start, $end])->when($request->has('search'), function ($query) use ($request) {
            $query->where('message', 'like', '%' . $request->search . '%')
                //action
                ->orWhere('action', 'like', '%' . $request->search . '%');
        })->when($request->has('router_id'), function ($query) use ($request) {
            $query->where('router_id', $request->router_id);
        })
            ->paginate(200);

        return response()->json($logs);
    }
}
