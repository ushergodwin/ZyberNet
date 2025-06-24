<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RouterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $authUser = Auth::user();
        return Inertia::render('Routers/List', [
            'user' => $authUser,
        ]);
    }

    // show logs 
    public function logs(Request $request)
    {
        $authUser = Auth::user();
        return Inertia::render('Routers/Logs', [
            'user' => $authUser,
        ]);
    }
}
