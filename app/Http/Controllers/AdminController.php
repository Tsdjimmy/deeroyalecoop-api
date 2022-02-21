<?php

namespace App\Http\Controllers;

use App\services\AdminServices;
use Illuminate\Http\Request;

class AdminController extends Controller
{

    public function register(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::register($request);
    }

    public function getAdminUsers(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getAdminUsers($request);
    }

    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::login($request);
    }
}
