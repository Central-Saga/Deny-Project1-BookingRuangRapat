<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Display the welcome landing page.
     */
    public function __invoke(): View
    {
        return view('welcome');
    }
}
