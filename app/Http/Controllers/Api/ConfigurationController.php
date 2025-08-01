<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RouterConfiguration;
use App\Models\SupportContact;
use App\Models\User;
use App\Models\VoucherPackage;
use App\Services\MikroTikService;
use App\Traits\RouterTrait;
use Illuminate\Support\Facades\Log;

class ConfigurationController extends Controller
{
    //
    use RouterTrait;
    // get Router Configurations
    public function getRouters(Request $request)
    {
        $configurations = RouterConfiguration::when($request->has('search'), function ($query) use ($request) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('host', 'like', '%' . $request->search . '%');
        });

        if ($request->no_paging) {
            $configurations = $configurations->get();
        } else {
            $configurations = $configurations->paginate(10);
        }
        return response()->json($configurations);
    }

    // save Router Configuration
    public function createRouter(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'host' => 'required|string|max:60',
            'port' => 'required|integer',
            'username' => 'required|string|max:100',
            'password' => 'string|nullable',
        ]);

        $data = [
            'name' => $validated['name'],
            'host' => $validated['host'],
            'port' => $validated['port'],
            'username' => $validated['username'],
            'password' => $validated['password'] ? encrypt($validated['password']) : encrypt(''),
        ];
        $configuration = RouterConfiguration::create($data);
        return response()->json([
            'message' => 'Router Configuration saved successfully',
            'configuration' => $configuration,
        ]);
    }

    // update Router Configuration
    public function updateRouter(Request $request, $id)
    {
        $configuration = RouterConfiguration::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'host' => 'required|string|max:60',
            'port' => 'required|integer',
            'username' => 'required|string|max:100',
            'password' => 'nullable|string',
        ]);

        // Encrypt the password if provided
        $validated['password'] = encrypt($validated['password'] ?? '');
        $configuration->update($validated);
        return response()->json([
            'message' => 'Router Configuration updated successfully',
            'configuration' => $configuration,
        ]);
    }

    // delete Router Configuration
    public function deleteRouter($id)
    {
        $configuration = RouterConfiguration::findOrFail($id);
        $configuration->delete();
        return response()->json([
            'message' => 'Router Configuration deleted successfully',
        ]);
    }

    // get Router Configuration by ID
    public function getRouter($id)
    {
        $configuration = RouterConfiguration::findOrFail($id);
        return response()->json($configuration);
    }

    // voucher packages
    public function getVoucherPackages(Request $request)
    {
        $packages = VoucherPackage::when($request->has('search'), function ($query) use ($request) {
            $query->where('name', 'like', '%' . $request->search . '%');
        })->when($request->has('router_id'), function ($query) use ($request) {
            $query->where('router_id', $request->router_id);
        })
            // ->where('is_active', true) // Only active packages
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'packages' => $packages
        ]);
    }

    // save Voucher Package
    public function createVoucherPackage(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'price' => 'required|numeric',
            'profile_name' => 'required|string|max:100',
            'rate_limit' => 'integer|max:100',
            'session_timeout' => 'integer|max:100',
            'limit_bytes_total' => 'integer',
            'shared_users' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
            'router_id' => 'required|exists:router_configurations,id',
        ]);

        $limit_bytes_total_unit = $request->input('limit_bytes_total_unit', 'MB');
        $limit_bytes_total = $request->input('limit_bytes_total', 0);

        if ($limit_bytes_total !== 0 && $limit_bytes_total !== null) {
            $validated['limit_bytes_total'] = MikroTikService::convertToBytes($limit_bytes_total, $limit_bytes_total_unit);
        } else {
            $validated['limit_bytes_total'] = null; // Ensure it's set to null if not provided
        }

        // format session_timeout using session_timeout_unit
        $session_timeout_unit = $request->input('session_timeout_unit', 'hours');
        $session_timeout = $session_timeout_unit === 'days' ? $validated['session_timeout'] . 'd' : $validated['session_timeout'] . 'h';
        $validated['session_timeout'] = $session_timeout;

        // format rate_limit
        if ($validated['rate_limit'] === null || $validated['rate_limit'] === 0) {
            $validated['rate_limit'] = null; // Ensure it's set to null if not provided
        } else {
            $validated['rate_limit'] = MikroTikService::convertToBytes($validated['rate_limit'], 'MB'); // Convert to bytes
        }

        $validated['description'] = "{$validated['name']} - {$validated['price']} UGX";
        $package = VoucherPackage::create($validated);

        // create router profile if it doesn't exist
        if (config('app.env') != 'local') {
            $router = RouterConfiguration::findOrFail($validated['router_id']);
            $routerService = new MikroTikService($router);
            $routerService->pushProfileToRouter($package);
        }
        return response()->json([
            'message' => 'Voucher Package saved successfully',
            'package' => $package,
        ]);
    }

    //getVoucherPackage
    public function getVoucherPackage($id)
    {
        $package = VoucherPackage::findOrFail($id);
        return response()->json($package);
    }

    // update Voucher Package
    public function updateVoucherPackage(Request $request, $id)
    {
        $package = VoucherPackage::with('router')->findOrFail($id);

        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'price' => 'required|numeric',
            'profile_name' => 'required|string|max:100',
            'rate_limit' => 'integer|max:100',
            'session_timeout' => 'integer|max:100',
            'limit_bytes_total' => 'integer',
            'shared_users' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $limit_bytes_total_unit = $request->input('limit_bytes_total_unit', 'MB');
        $limit_bytes_total = $request->input('limit_bytes_total', 0);

        if ($limit_bytes_total !== 0 && $limit_bytes_total !== null) {
            $validated['limit_bytes_total'] = MikroTikService::convertToBytes($limit_bytes_total, $limit_bytes_total_unit);
        } else {
            $validated['limit_bytes_total'] = null; // Ensure it's set to null if not provided
        }

        // format session_timeout using session_timeout_unit
        $session_timeout_unit = $request->input('session_timeout_unit', 'hours');
        $session_timeout = $session_timeout_unit === 'days' ? $validated['session_timeout'] . 'd' : $validated['session_timeout'] . 'h';
        $validated['session_timeout'] = $session_timeout;

        // format rate_limit
        if ($validated['rate_limit'] === null || $validated['rate_limit'] === 0) {
            $validated['rate_limit'] = null; // Ensure it's set to null if not provided
        } else {
            $validated['rate_limit'] = MikroTikService::convertToBytes($validated['rate_limit'], 'MB'); // Convert to bytes
        }

        $validated['description'] = "{$validated['name']} - {$validated['price']} UGX";

        $package->update($validated);
        $profile = VoucherPackage::with('router')->findOrFail($id);
        $routerService = new MikroTikService($profile->router);
        $routerService->pushProfileToRouter($profile);
        return response()->json([
            'message' => 'Voucher Package updated successfully',
            'package' => $package,
        ]);
    }

    // delete Voucher Package
    public function deleteVoucherPackage($id)
    {

        $package = VoucherPackage::findOrFail($id);
        $package->delete();
        return response()->json([
            'message' => 'Voucher Package deleted successfully',
        ]);
    }

    // toggleVoucherPackage
    public function toggleVoucherPackage($id)
    {
        try {
            $package = VoucherPackage::findOrFail($id);
            $package->is_active = !$package->is_active; // Toggle the is_active status
            $message = $package->is_active ? 'Voucher Package activated successfully' : 'Voucher Package deactivated successfully';
            $package->save();
            return response()->json([
                'message' => $message,
                'package' => $package,
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling Voucher Package: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error toggling Voucher Package: ' . $e->getMessage(),
            ], 500);
        }
    }

    // get users 
    public function getUsers(Request $request)
    {

        $users = User::when($request->has('search'), function ($query) use ($request) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%');
        })
            ->orderBy('created_at', 'desc');
        if ($request->has('deleted') && $request->deleted == 'true') {
            $users = $users->onlyTrashed()->paginate(10);
        } else {
            $users = $users->paginate(10);
        }
        return response()->json($users);
    }

    // get support-contacts
    public function getSupportContacts(Request $request)
    {

        $contacts = SupportContact::query()
            ->when(
                $request->router_id != 0,
                function ($query) use ($request) {
                    $query->where('router_id', $request->router_id);
                }
            )
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            })
            ->orderBy('created_at', 'desc')->get();
        return response()->json($contacts);
    }

    // save support contacts
    public function saveSupportContact(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'nullable|email',
                'phone_number' => 'required|string|max:15',
                'type' => 'required|string|max:100',
                'router_id' => 'integer|nullable|exists:router_configurations,id',
            ]);

            $id = $request->input('id', null);
            if ($id) {
                $supportContact = SupportContact::findOrFail($id);
                $supportContact->update($validated);
            } else {
                $supportContact = new SupportContact($validated);
                $supportContact->save();
            }
            return response()->json([
                'message' => $id ? 'Support contact updated successfully' : 'Support contact created successfully',
                'contact' => $supportContact,
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving support contact: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Error saving support contact',
            ], 500);
        }
    }

    // delete support contact
    public function deleteSupportContact($id)
    {
        try {
            $contact = SupportContact::findOrFail($id);
            $contact->delete();
            return response()->json([
                'message' => 'Support contact deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting support contact: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error deleting support contact',
            ], 500);
        }
    }
}