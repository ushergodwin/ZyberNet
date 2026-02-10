<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WireguardController extends Controller
{
    public function addPeer(Request $request)
    {
        if (!hasPermission('create_router')) {
            return response()->json(['message' => 'You are not authorized to create WireGuard peers'], 401);
        }

        try {
            $request->validate(['peer_name' => 'required|string|max:100']);

            $peerName = $request->input('peer_name');
            $scriptPath = base_path('wireguard/add-wg-peer-json.sh');
            $command = escapeshellcmd($scriptPath . ' ' . escapeshellarg($peerName));
            $output = shell_exec($command);

            $data = json_decode($output, true);
            if (!$data || isset($data['error'])) {
                return response()->json(['error' => $data['error'] ?? 'Failed to create WireGuard peer. Is WireGuard available on this host?'], 500);
            }

            return response()->json($data);
        } catch (\Throwable $th) {
            Log::error('Failed to create peer: ' . $th->getMessage());
            return response()->json(['error' => 'Failed to create peer: ' . $th->getMessage()], 500);
        }
    }

    public function parseWireguardOutput(Request $request)
    {
        if (!hasPermission('create_router')) {
            return response()->json(['message' => 'You are not authorized to perform this action'], 401);
        }

        $validated = $request->validate(['output' => 'required|string']);

        $data = json_decode($validated['output'], true);
        if (!$data || isset($data['error'])) {
            return response()->json(['error' => $data['error'] ?? 'Invalid JSON output. Please paste the complete output from the WireGuard script.'], 422);
        }

        $required = ['peer_name', 'peer_ip', 'server_public_key', 'server_public_ip', 'mikrotik_instructions'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return response()->json(['error' => "Missing required field: {$field}"], 422);
            }
        }

        return response()->json($data);
    }
}
