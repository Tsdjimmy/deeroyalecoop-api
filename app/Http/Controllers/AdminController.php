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

     public function registerUsers(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::registerUsers($request);
    }

    public function addBranch(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::addBranch($request);
    }
    
    public function creditSavings(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::creditSavings($request);
    }

    public function debitSavings(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::debitSavings($request);
    }

    public function createLoanPlan(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::createLoanPlan($request);
    }

    public function addCard(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::addCard($request);
    }
}
