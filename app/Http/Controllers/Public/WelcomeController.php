<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    public function index()
    {
        if (Auth::check()) {
            return redirect()->route('beranda');
        }

        return view('welcome');
    }

    public function help()
    {
        return view('bantuan');
    }

    public function terms()
    {
        return view('legal.syarat');
    }

    public function privacy()
    {
        return view('legal.privasi');
    }
}
