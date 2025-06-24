<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserController extends Controller
{
    //

    public function index()
    {
        return Inertia::render('Users/Index', [
            'user' => Auth::user(),
        ]);
    }
}
