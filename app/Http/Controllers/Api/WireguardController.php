<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WireguardController extends Controller
{
    //

    public function addPeer(Request $request)
    {
        try {
            $peerName = $request->input('peer_name');
            $scriptPath = base_path('wireguard/add-wg-peer-json.sh');
            $command = escapeshellcmd($scriptPath . ' ' . escapeshellarg($peerName));
            $output = shell_exec($command);

            return response()->json(json_decode($output, true));
        } catch (\Throwable $th) {
            Log::error('Failed to create peer: ' . $th->getMessage());
            return response()->json(['error' => 'Failed to create peer: ' . $th->getMessage()], 500);
        }
    }
}
