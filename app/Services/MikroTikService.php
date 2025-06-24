<?php

namespace App\Services;

use RouterOS\Client;
use RouterOS\Query;
use App\Models\RouterConfiguration;
use App\Models\RouterLog;
use Illuminate\Routing\Route;

/**
 * MikroTikService
 *
 * This service class provides methods to interact with MikroTik routers,
 * specifically for creating hotspot users.
 */
class MikroTikService
{
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
            if (!$router->host || !$router->username || !$router->password) {
                throw new \Exception('Router configuration is incomplete');
            }
            $this->router = $router;
            // Initialize the RouterOS client with the router configuration
            $this->client = new Client([
                'host' => $router->host,
                'user' => $router->username,
                'pass' => $router->password,
                'port' => $router->port ?? 8728,
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
}
