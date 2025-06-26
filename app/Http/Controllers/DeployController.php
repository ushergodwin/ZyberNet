<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;

class DeployController extends Controller
{
    public function migrate(Request $request)
    {
        $key = $request->query('key');

        // Replace with your secret key (store in .env ideally)
        if ($key !== config('app.deploy_key')) {
            abort(403, 'Unauthorized');
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]); // optional
            return response()->json(['status' => '✅ Migration complete']);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => '❌ Failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}