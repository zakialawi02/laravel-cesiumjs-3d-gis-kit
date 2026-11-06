<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the 3D map viewer.
     */
    public function index(): View
    {
        return view('pages.map.index');
    }
}
