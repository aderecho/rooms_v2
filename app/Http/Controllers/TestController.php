<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function test()
    {
        return response()->json([
            'message' => 'API is working!',
            'data' => \App\Models\UserAccount::all()
        ]);
    }
}
