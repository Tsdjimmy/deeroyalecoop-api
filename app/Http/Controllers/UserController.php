<?php

namespace App\Http\Controllers;

use App\Services\UserServices;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function register(Request $request)
    {
        return UserServices::register($request);
    }
}
