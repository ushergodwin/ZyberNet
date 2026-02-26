<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class WireguardController extends Controller
{
    public function addPeer(Request $request)
    {
        if (!hasPermission('create_router')) {
            return response()->json([
                'message' => 'You are not authorized to create WireGuard peers'
            ], 403);
        }

        $validated = $request->validate([
            'peer_name' => 'required|string|max:100|regex:/^[A-Za-z0-9_-]+$/'
        ]);

        $peerName = $validated['peer_name'];

        try {
            $process = new Process([
                'sudo',
                '/usr/local/bin/wireguard/add-peer.sh',
                $peerName
            ]);

            $process->setTimeout(60);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('WireGuard script failed', [
                    'error_output' => $process->getErrorOutput()
                ]);

                return response()->json([
                    'error' => 'WireGuard provisioning failed.',
                    'details' => $process->getErrorOutput()
                ], 500);
            }

            $output = $process->getOutput();
            $data = json_decode($output, true);

            if (!$data || isset($data['error'])) {
                return response()->json([
                    'error' => $data['error'] ?? 'Invalid JSON returned from WireGuard script.'
                ], 500);
            }

            return response()->json($data);
        } catch (\Throwable $e) {

            Log::error('WireGuard peer creation exception', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to create WireGuard peer.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function parseWireguardOutput(Request $request)
    {
        if (!hasPermission('create_router')) {
            return response()->json([
                'message' => 'You are not authorized to perform this action'
            ], 403);
        }

        $validated = $request->validate([
            'output' => 'required|string'
        ]);

        $data = json_decode($validated['output'], true);

        if (!$data || isset($data['error'])) {
            return response()->json([
                'error' => $data['error'] ?? 'Invalid JSON output.'
            ], 422);
        }

        $requiredFields = [
            'peer_name',
            'peer_ip',
            'server_public_key',
            'server_public_ip',
            'mikrotik_instructions'
        ];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                return response()->json([
                    'error' => "Missing required field: {$field}"
                ], 422);
            }
        }

        return response()->json($data);
    }
}
