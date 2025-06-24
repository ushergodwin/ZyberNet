<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PaymentsController extends Controller
{
    //

    public function index()
    {

        return Inertia::render('Payments/Index', [
            'user' => Auth::user(),
        ]);
    }
}
