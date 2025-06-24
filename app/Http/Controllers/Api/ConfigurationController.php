<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RouterConfiguration;
use App\Models\User;
use App\Models\VoucherPackage;
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
        })->paginate(10);
        return response()->json($configurations);
    }

    // save Router Configuration
    public function createRouter(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'host' => 'required|string|max:60',
            'port' => 'required|integer',
            'username' => 'required|string|max:100',
            'password' => 'required|string',
        ]);

        $data = $request->all();
        // Encrypt the password before saving
        $data['password'] = $this->encryptRouterPassword($data['password']);
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

        $request->validate([
            'name' => 'required|string|max:100',
            'host' => 'required|string|max:60',
            'port' => 'required|integer',
            'username' => 'required|string|max:100',
            'password' => 'required|string',
        ]);

        $configuration->update($request->all());
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
        })
            ->where('is_active', true) // Only active packages
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'packages' => $packages
        ]);
    }

    // save Voucher Package
    public function createVoucherPackage(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'duration_minutes' => 'required|integer',
            'price' => 'required|numeric',
            'speed_limit' => 'nullable|integer',
            'is_active' => 'boolean',
            'profile' => 'required|string|max:100', // Ensure profile is a string and optional
        ]);

        $package = VoucherPackage::create($request->all());
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
        $package = VoucherPackage::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100',
            'duration_minutes' => 'required|integer',
            'price' => 'required|numeric',
            'speed_limit' => 'nullable|integer',
            'is_active' => 'boolean',
            'profile' => 'required|string|max:100', // Ensure profile is a string and optional
        ]);

        $package->update($request->all());
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
}
