<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard
     */
    public function index(Request $request)
    {
        $user = $request->attributes->get('user');
        
        return view('admin.dashboard', [
            'user' => $user,
        ]);
    }
}
