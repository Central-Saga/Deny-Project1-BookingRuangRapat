<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    /**
     * Display the welcome landing page.
     */
    public function __invoke(): View
    {
        return view('welcome');
    }

    /**
     * Redirect unauthenticated access to home.
     */
    public function redirectToHome(): RedirectResponse
    {
        return redirect('/');
    }
}
