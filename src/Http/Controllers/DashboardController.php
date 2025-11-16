<?php

namespace Flux\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the dashboard
     */
    public function index(): View
    {
        return view('smart-migration::dashboard');
    }
}
