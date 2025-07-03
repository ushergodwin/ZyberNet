<?php

namespace App\Services;

use RouterOS\Client;
use RouterOS\Query;
use App\Models\RouterConfiguration;
use App\Models\RouterLog;
use App\Traits\RouterTrait;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * MikroTikService
 *
 * This service class provides methods to interact with MikroTik routers,
 * specifically for creating hotspot users.
 */
class MikroTikService
{
    use RouterTrait;

    protected $client;
    protected $router;
    /**
     * Constructor to initialize the MikroTik client with router configuration.
     *
     * @param RouterConfiguration $router
     */
    public function __construct(RouterConfiguration $router)
    {
        try {
            if (!$router) {
                throw new \Exception('Router configuration not found');
            }
            if (!$router->host || !$router->username) {
                throw new \Exception('Router configuration is incomplete');
            }
            $this->router = $router;
            // Initialize the RouterOS client with the router configuration
            $this->client = new Client([
                'host' => $router->host,
                'user' => $router->username,
                'pass' => $router->password ?? '', // Use empty string if password is null
                'port' => $router->port,
            ]);
            // create log entry for successful initialization
            RouterLog::create([
                'voucher_id' => null, // No voucher ID at this point
                'action' => 'initialize_mikrotik_client',
                'success' => true,
                'message' => 'MikroTik client initialized successfully',
                'is_manual' => false, // Set to true if this is a manual action
                'router_name' => $router->name ?? 'Unknown Router', // Store the router name or host
            ]);
        } catch (\Throwable $th) {
            //throw $th;
            // create log entry for error
            RouterLog::create([
                'voucher_id' => null, // No voucher ID at this point
                'action' => 'initialize_mikrotik_client',
                'success' => false,
                'message' => $th->getMessage(),
                'is_manual' => false, // Set to true if this is a manual action
                'router_name' => $router->name ?? 'Unknown Router', // Store the router name or host
            ]);
            throw new \Exception('Failed to initialize MikroTik client: ' . $th->getMessage());
        }
    }

    public function createHotspotUser(string $username, string $password, string $profile = 'default')
    {
        try {
            $query = new Query('/ip/hotspot/user/add');
            $query->equal('name', $username)
                ->equal('password', $password)
                ->equal('profile', $profile);

            // create log entry
            RouterLog::create([
                'voucher_id' => $username, // You can set this if you have a voucher ID
                'action' => 'create_hotspot_user',
                'success' => true,
                'message' => "Created user $username with profile $profile",
                'is_manual' => false, // Set to true if this is a manual action,
                'router_name' => $this->router->name, // Store the router name or host
            ]);
            return $this->client->query($query)->read();
        } catch (\Throwable $th) {
            //throw $th;
            // create log entry for error
            RouterLog::create([
                'voucher_id' => $username, // You can set this if you have a voucher ID
                'action' => 'create_hotspot_user',
                'success' => false,
                'message' => $th->getMessage(),
                'is_manual' => false, // Set to true if this is a manual action
            ]);
        }
    }

    // get profiles in MikroTik
    public function getProfiles()
    {
        $query = new Query('/ip/hotspot/user/profile/print');
        return $this->client->query($query)->read();
    }

    // get router name 
    public function getRouterName()
    {
        $query = new \RouterOS\Query('/system/identity/print');
        $response = $this->client->query($query)->read();

        $routerName = $response[0]['name'] ?? null;
        if (!$routerName) {
            throw new \Exception('Router name not found');
        }
        return $routerName;
    }

    // test connection
    public function testConnection()
    {
        try {
            $this->client->query(new Query('/system/resource/print'))->read();
            return [
                'success' => true,
                'message' => 'Connection to MikroTik router is successful.',
            ];

            // create log entry for successful connection
            RouterLog::create([
                'voucher_id' => null, // No voucher ID at this point
                'action' => 'test_connection',
                'success' => true,
                'message' => 'Connection to MikroTik router is successful.',
                'is_manual' => false, // Set to true if this is a manual action
                'router_name' => $this->router->name ?? 'Unknown Router', // Store the router name or host
            ]);
        } catch (\Throwable $th) {
            // create log entry for error
            RouterLog::create([
                'voucher_id' => null, // No voucher ID at this point
                'action' => 'test_connection',
                'success' => false,
                'message' => $th->getMessage(),
                'is_manual' => false, // Set to true if this is a manual action
                'router_name' => $this->router->name ?? 'Unknown Router', // Store the router name or host
            ]);
            return [
                'success' => false,
                'message' => 'Failed to connect to MikroTik router: ' . $th->getMessage(),
            ];
        }
    }


    public function pushProfileToRouter($profile)
    {
        try {
            // Step 1: Check if profile already exists on router
            $checkQuery = new Query('/ip/hotspot/user/profile/print');
            $checkQuery->where('name', $profile->profile_name);
            $existingProfiles = $this->client->query($checkQuery)->read();

            // If it exists, skip creation
            if (!empty($existingProfiles)) {
                RouterLog::create([
                    'voucher_id' => null,
                    'action' => 'push_profile_to_router',
                    'success' => true,
                    'message' => "Profile '{$profile->profile_name}' already exists on router",
                    'is_manual' => false,
                    'router_name' => $this->router->name ?? 'Unknown Router',
                ]);
                return $existingProfiles[0]; // return existing profile info
            }

            // Step 2: Prepare and add new profile
            $query = new Query('/ip/hotspot/user/profile/add');
            $query->equal('name', $profile->profile_name)
                ->equal('shared-users', $profile->shared_users);

            if ($profile->rate_limit) {
                $query->equal('rate-limit', $profile->rate_limit);
            }
            if ($profile->session_timeout) {
                $query->equal('session-timeout', $profile->session_timeout);
            }
            if ($profile->limit_bytes_total) {
                $query->equal('limit-bytes-total', $profile->limit_bytes_total);
            }

            $data = $this->client->query($query)->read();

            // Step 3: Log success
            RouterLog::create([
                'voucher_id' => null,
                'action' => 'push_profile_to_router',
                'success' => true,
                'message' => "Profile '{$profile->profile_name}' successfully pushed to router",
                'is_manual' => false,
                'router_name' => $this->router->name ?? 'Unknown Router',
            ]);

            return $data;
        } catch (\Throwable $th) {
            // Step 4: Log failure
            RouterLog::create([
                'voucher_id' => null,
                'action' => 'push_profile_to_router',
                'success' => false,
                'message' => $th->getMessage(),
                'is_manual' => false,
                'router_name' => $this->router->name ?? 'Unknown Router',
            ]);

            Log::error('Failed to push profile to router: ' . $th->getMessage(), [
                'router' => $this->router->name ?? 'Unknown Router',
                'profile' => $profile->name,
                'trace' => $th->getTrace(),
            ]);

            throw new \Exception('Failed to push profile to router: ' . $th->getMessage());
        }
    }


    static  function convertToBytes(int|float $value, string $unit = 'MB'): int
    {
        $unit = strtoupper(trim($unit));

        return match ($unit) {
            'GB' => (int)($value * 1024 * 1024 * 1024),
            'MB' => (int)($value * 1024 * 1024),
            'KB' => (int)($value * 1024),
            default => throw new InvalidArgumentException("Unsupported unit: $unit"),
        };
    }
}