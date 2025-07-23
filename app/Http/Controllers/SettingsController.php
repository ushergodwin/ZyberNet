<?php

namespace App\Http\Controllers;

use App\Models\SupportContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SettingsController extends Controller
{
    //

    public function settings()
    {
        return Inertia::render('Settings/Index', [
            'title' => 'Settings',
            'description' => 'Manage your application settings.',
            'user' => Auth::user(),
        ]);
    }
}