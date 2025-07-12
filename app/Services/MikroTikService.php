<?php

namespace App\Services;

use RouterOS\Client;
use RouterOS\Query;
use App\Models\RouterConfiguration;
use App\Models\RouterLog;
use App\Traits\RouterTrait;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use App\Models\Voucher;
use App\Models\VoucherPackage;
use Carbon\Carbon;

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
            if (config('app.env') == 'local') {
                $this->client = null; // No client in local environment
            } else {
                $this->client = new Client([
                    'host' => $router->host,
                    'user' => $router->username,
                    'pass' => $router->password ?? '', // Use empty string if password is null
                    'port' => $router->port,
                ]);
            }
            // create log entry for successful initialization
            RouterLog::create([
                'voucher_id' => null, // No voucher ID at this point
                'action' => 'initialize_mikrotik_client',
                'success' => true,
                'message' => 'MikroTik client initialized successfully',
                'is_manual' => false, // Set to true if this is a manual action
                'router_name' => $router->name ?? 'Unknown Router', // Store the router name or host
                'router_id' => $router->id ?? null, // Store the router ID
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
                'router_id' => $this->router->id ?? null,
            ]);
            throw new \Exception('Failed to initialize MikroTik client: ' . $th->getMessage());
        }
    }

    public function createHotspotUser(string $username, string $password, string $limitUpTime, string $profile)
    {
        try {
            if (!$this->client) {
                Log::error('MikroTik client is not initialized. Cannot create hotspot user.', [
                    'username' => $username,
                    'profile' => $profile,
                ]);
                return;
            }

            $query = new Query('/ip/hotspot/user/add');
            $query->equal('name', $username)
                ->equal('password', $password)
                ->equal('profile', $profile)
                ->equal('limit-uptime', $limitUpTime)
                ->equal('comment', 'Created via Captive Portal');

            // create log entry
            RouterLog::create([
                'voucher_id' => $username,
                'action' => 'create_hotspot_user',
                'success' => true,
                'message' => "Created user $username with profile $profile",
                'is_manual' => false,
                'router_name' => $this->router->name,
                'router_id' => $this->router->id ?? null,
            ]);

            return $this->client->query($query)->read();
        } catch (\Throwable $th) {
            // log failure
            RouterLog::create([
                'voucher_id' => $username,
                'action' => 'create_hotspot_user',
                'success' => false,
                'message' => $th->getMessage(),
                'is_manual' => false,
                'router_name' => $this->router->name ?? 'Unknown Router',
                'router_id' => $this->router->id ?? null,
            ]);
        }
    }


    // get profiles in MikroTik
    public function getProfiles()
    {
        if (!$this->client) {
            Log::error('MikroTik client is not initialized. Cannot create hotspot user.', [
                'action' => 'getProfiles',
            ]);
            return;
        }
        $query = new Query('/ip/hotspot/user/profile/print');
        return $this->client->query($query)->read();
    }

    // get router name 
    public function getRouterName()
    {
        if (!$this->client) {
            Log::error('MikroTik client is not initialized. Cannot get router name.', [
                'action' => 'getRouterName',
            ]);
            return;
        }
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
                'router_id' => $this->router->id ?? null,
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
                'router_id' => $this->router->id ?? null,
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
            if (!$this->client) {
                Log::error('MikroTik client is not initialized. Cannot push profile to router.', [
                    'profile' => $profile->profile_name,
                ]);
                return;
            }
            // Step 1: Check if profile already exists
            $checkQuery = new Query('/ip/hotspot/user/profile/print');
            $checkQuery->where('name', $profile->profile_name);
            $existingProfiles = $this->client->query($checkQuery)->read();

            $profileFields = [
                'name' => $profile->profile_name,
                'shared-users' => $profile->shared_users ?? 1,
                'session-timeout' => $profile->session_timeout,
                'keepalive-timeout' => '2m',
                'idle-timeout' => '5m',
                'mac-cookie-timeout' => $profile->session_timeout,
            ];

            if ($profile->rate_limit) {
                $profileFields['rate-limit'] = $profile->rate_limit;
            }

            if ($profile->limit_bytes_total) {
                $profileFields['limit-bytes-total'] = $profile->limit_bytes_total;
            }

            if (!empty($existingProfiles)) {
                // Update existing profile
                $existingId = $existingProfiles[0]['.id'];

                $updateQuery = new Query('/ip/hotspot/user/profile/set');
                $updateQuery->equal('.id', $existingId);
                foreach ($profileFields as $key => $value) {
                    $updateQuery->equal($key, $value);
                }

                $this->client->query($updateQuery)->read();

                RouterLog::create([
                    'voucher_id' => null,
                    'action' => 'update_profile_on_router',
                    'success' => true,
                    'message' => "Updated profile '{$profile->profile_name}' on router",
                    'is_manual' => false,
                    'router_name' => $this->router->name ?? 'Unknown Router',
                    'router_id' => $this->router->id ?? null,
                ]);

                $status = 'updated';
            } else {
                // Create new profile
                $addQuery = new Query('/ip/hotspot/user/profile/add');
                foreach ($profileFields as $key => $value) {
                    $addQuery->equal($key, $value);
                }

                $this->client->query($addQuery)->read();

                RouterLog::create([
                    'voucher_id' => null,
                    'action' => 'create_profile_on_router',
                    'success' => true,
                    'message' => "Created profile '{$profile->profile_name}' on router",
                    'is_manual' => false,
                    'router_name' => $this->router->name ?? 'Unknown Router',
                    'router_id' => $this->router->id ?? null,
                ]);

                $status = 'created';
            }

            return ['status' => $status, 'profile' => $profile->profile_name];
        } catch (\Throwable $th) {
            RouterLog::create([
                'voucher_id' => null,
                'action' => 'push_profile_to_router',
                'success' => false,
                'message' => $th->getMessage(),
                'is_manual' => false,
                'router_name' => $this->router->name ?? 'Unknown Router',
                'router_id' => $this->router->id ?? null,
            ]);

            Log::error('Failed to push profile to router: ' . $th->getMessage(), [
                'router' => $this->router->name ?? 'Unknown Router',
                'profile' => $profile->profile_name,
                'trace' => $th->getTrace(),
            ]);

            throw new \Exception('Failed to push profile to router: ' . $th->getMessage());
        }
    }

    public function deleteHotspotUser(string $username): bool
    {
        try {
            if (!$this->client) {
                Log::error('MikroTik client is not initialized. Cannot delete hotspot user.', [
                    'username' => $username,
                ]);
                return false;
            }
            // Step 1: Fetch user ID
            $query = new Query('/ip/hotspot/user/print');
            $query->where('name', $username);
            $result = $this->client->query($query)->read();

            if (empty($result)) {
                throw new \Exception("User {$username} not found on the router.");
            }

            $userId = $result[0]['.id'];

            // Step 2: Delete the user
            $deleteQuery = new Query('/ip/hotspot/user/remove');
            $deleteQuery->equal('.id', $userId);
            $this->client->query($deleteQuery)->read();

            // Step 3: Log success
            RouterLog::create([
                'voucher_id' => $username,
                'action' => 'delete_hotspot_user',
                'success' => true,
                'message' => "Deleted user {$username} from router",
                'is_manual' => false,
                'router_name' => $this->router->name,
                'router_id' => $this->router->id ?? null,
            ]);

            return true;
        } catch (\Throwable $th) {
            // Step 4: Log failure
            RouterLog::create([
                'voucher_id' => $username,
                'action' => 'delete_hotspot_user',
                'success' => false,
                'message' => $th->getMessage(),
                'is_manual' => false,
                'router_name' => $this->router->name ?? 'Unknown Router',
                'router_id' => $this->router->id ?? null,
            ]);

            // Step 5: Rethrow for upstream handling
            throw new \Exception("Failed to delete user {$username}: " . $th->getMessage(), 0, $th);
        }
    }

    public function removeExpiredHotspotUsers()
    {
        try {
            if (!$this->client) {
                Log::error('MikroTik client is not initialized. Cannot remove expired hotspot users.');
                return;
            }

            // Step 1: Get all packages with session timeout mapping
            $packages = VoucherPackage::all()
                ->keyBy('profile_name')
                ->map(function ($package) {
                    return [
                        'timeout' => $package->js_session_timeout,
                        'unit'    => $package->session_timeout_unit,
                    ];
                });

            // Step 2: Get all hotspot users
            $query = new Query('/ip/hotspot/user/print');
            $users = $this->client->query($query)->read();

            $expiredUsernames = [];

            foreach ($users as $user) {
                $username = $user['name'] ?? null;
                $profile = $user['profile'] ?? null;
                $lastLoggedIn = $user['last-logged-in'] ?? null;

                if (!$username || !$profile || !$lastLoggedIn || !isset($packages[$profile])) {
                    continue;
                }

                try {
                    $lastLogin = Carbon::parse($lastLoggedIn);
                } catch (\Exception $e) {
                    continue;
                }

                $timeout = $packages[$profile]['timeout'];
                $unit = strtolower($packages[$profile]['unit']);

                if (!$timeout || !$unit) continue;

                $expiresAt = match ($unit) {
                    'hours' => $lastLogin->copy()->addHours($timeout),
                    'days' => $lastLogin->copy()->addDays($timeout),
                    'minutes' => $lastLogin->copy()->addMinutes($timeout),
                    default => null,
                };

                if (!$expiresAt || now()->lt($expiresAt)) {
                    continue;
                }

                // Step 3: Remove from MikroTik
                $findQuery = (new Query('/ip/hotspot/user/print'))->where('name', $username);
                $found = $this->client->query($findQuery)->read();

                if (isset($found[0]['.id'])) {
                    $removeQuery = (new Query('/ip/hotspot/user/remove'))->equal('.id', $found[0]['.id']);
                    $this->client->query($removeQuery)->read();
                }

                $expiredUsernames[] = $username;

                Log::info("Expired hotspot user [{$username}] removed from MikroTik (profile: {$profile}).");
            }

            // Step 4: Batch delete vouchers
            if (!empty($expiredUsernames)) {
                Voucher::whereIn('code', $expiredUsernames)->delete();
                Log::info("Deleted expired vouchers for users: " . implode(', ', $expiredUsernames));
            }
            // Step 5: Log success
            RouterLog::create([
                'voucher_id' => null,
                'action' => 'remove_expired_hotspot_users',
                'success' => true,
                'message' => "Removed expired hotspot users: " . implode(', ', $expiredUsernames),
                'is_manual' => false,
                'router_name' => $this->router->name ?? 'Unknown Router',
                'router_id' => $this->router->id ?? null,
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to remove expired hotspot users: ' . $th->getMessage(), [
                'trace' => $th->getTrace(),
            ]);
            RouterLog::create([
                'voucher_id' => null,
                'action' => 'remove_expired_hotspot_users',
                'success' => false,
                'message' => $th->getMessage(),
                'is_manual' => false,
                'router_name' => $this->router->name ?? 'Unknown Router',
                'router_id' => $this->router->id ?? null,
            ]);
        }
    }
}
