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

    public function removeExpiredHotspotUsersByUptime()
    {
        try {
            if (!$this->client) {
                Log::error('MikroTik client not initialized.');
                return;
            }

            // Fetch all user profiles with session-timeout
            $profilesRaw = $this->client->query(new Query('/ip/hotspot/user/profile/print'))->read();
            $profiles = collect($profilesRaw)->mapWithKeys(function ($profile) {
                return [$profile['name'] => $profile['session-timeout'] ?? null];
            });

            // Fetch all hotspot users
            $users = $this->client->query(new Query('/ip/hotspot/user/print'))->read();

            $expiredUsernames = [];

            foreach ($users as $user) {
                $username = $user['name'] ?? null;
                $profileName = $user['profile'] ?? null;
                $uptime = $user['uptime'] ?? null;

                if (!$username || !$profileName || !$uptime || !isset($profiles[$profileName])) {
                    continue;
                }

                $sessionTimeout = $profiles[$profileName];
                if (!$sessionTimeout) continue;

                // Convert uptime and session-timeout to seconds
                $uptimeSeconds = $this->durationToSeconds($uptime);
                $timeoutSeconds = $this->durationToSeconds($sessionTimeout);

                if ($uptimeSeconds >= $timeoutSeconds) {
                    // Remove from MikroTik
                    $findQuery = (new Query('/ip/hotspot/user/print'))->where('name', $username);
                    $found = $this->client->query($findQuery)->read();

                    if (isset($found[0]['.id'])) {
                        $removeQuery = (new Query('/ip/hotspot/user/remove'))->equal('.id', $found[0]['.id']);
                        $this->client->query($removeQuery)->read();
                        $expiredUsernames[] = $username;
                        Log::info("Removed expired user [{$username}] (profile: {$profileName}, uptime: {$uptime})");
                    }
                }
            }

            // Delete from database
            if (!empty($expiredUsernames)) {
                Voucher::whereIn('code', $expiredUsernames)->delete();
                Log::info("Deleted vouchers for expired users: " . implode(', ', $expiredUsernames));
            }

            // Log to router logs
            RouterLog::create([
                'voucher_id' => null,
                'action' => 'remove_expired_hotspot_users_by_uptime',
                'success' => true,
                'message' => 'Removed users by uptime: ' . implode(', ', $expiredUsernames),
                'is_manual' => false,
                'router_name' => $this->router->name ?? 'Unknown Router',
                'router_id' => $this->router->id ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to remove expired users by uptime: ' . $e->getMessage());
            RouterLog::create([
                'voucher_id' => null,
                'action' => 'remove_expired_hotspot_users_by_uptime',
                'success' => false,
                'message' => $e->getMessage(),
                'is_manual' => false,
                'router_name' => $this->router->name ?? 'Unknown Router',
                'router_id' => $this->router->id ?? null,
            ]);
        }
    }

    /**
     * Convert MikroTik time format (e.g., 1d2h30m15s) to seconds.
     */
    private function durationToSeconds($duration)
    {
        $pattern = '/(?:(\d+)d)?(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?/';
        preg_match($pattern, $duration, $matches);
        return ((int)($matches[1] ?? 0) * 86400) +
            ((int)($matches[2] ?? 0) * 3600) +
            ((int)($matches[3] ?? 0) * 60) +
            ((int)($matches[4] ?? 0));
    }


    public function getUserStatistics()
    {
        if (!$this->client) {
            Log::error('MikroTik client is not initialized. Cannot fetch user statistics.');
            return [];
        }

        try {
            $userQuery = new Query('/ip/hotspot/user/print');
            $users = $this->client->query($userQuery)->read();

            $activeQuery = new Query('/ip/hotspot/active/print');
            $activeSessions = $this->client->query($activeQuery)->read();
            $activeUsernames = array_column($activeSessions, 'user');

            $now = Carbon::now()->format('Y-m-d');
            $profileCountMap = [];

            $totalUsers = count($users);
            $activeUsers = 0;
            $inactiveUsers = 0;
            $onlineUsers = 0;
            $usersCreatedToday = 0;

            foreach ($users as $user) {
                $lastLogin = $user['last-logged-in'] ?? null;
                $username = $user['name'] ?? null;
                $creationTime = $user['creation-time'] ?? null;
                $profile = $user['profile'] ?? 'unknown';

                // Profile count
                $profileCountMap[$profile] = ($profileCountMap[$profile] ?? 0) + 1;

                if ($lastLogin && strtolower($lastLogin) !== 'never') {
                    $activeUsers++;
                } else {
                    $inactiveUsers++;
                }

                if (in_array($username, $activeUsernames)) {
                    $onlineUsers++;
                }

                if ($creationTime && str_contains($creationTime, $now)) {
                    $usersCreatedToday++;
                }
            }

            // Sort profiles by count
            arsort($profileCountMap);
            $mostUsedProfile = array_key_first($profileCountMap);
            $topProfiles = array_slice($profileCountMap, 0, 5, true);

            return [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $inactiveUsers,
                'online_users' => $onlineUsers,
                'users_created_today' => $usersCreatedToday,
                'most_used_profile' => $mostUsedProfile,
                'top_profiles_by_user_count' => $topProfiles,
            ];
        } catch (\Throwable $th) {
            Log::error('Failed to fetch MikroTik user statistics: ' . $th->getMessage());
            return [];
        }
    }
}
