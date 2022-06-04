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

    public function listBranches(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::listBranches($request);
    }
    
    public function creditSavings(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::creditSavings($request);
    }

    public function debitSavings(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::debitSavings($request);
    }

    public function getUserSavings(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getUserSavings($request);
    }

    public function getUserSavingsbyCard(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getUserSavingsbyCard($request);
    }

    public function getAllSavings(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getAllSavings($request);
    }

    public function createLoanPlan(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::createLoanPlan($request);
    }

    public function repayLoan(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::repayLoan($request);
    }

    public function getLoanPlans(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getLoanPlans($request);
    }

    public function getLoanPlanByCard(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getLoanPlanByCard($request);
    }
    
    public function getUserLoanPlans(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getUserLoanPlans($request);
    }

    public function addCard(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::addCard($request);
    }

    public function getCard(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getCard($request);
    }

    public function listAllCard(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::listAllCard($request);
    }
   
    public function createPurchase(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::createPurchase($request);
    }

    public function updatePurchase(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::updatePurchase($request);
    }

    public function getPurchasePlans(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getPurchasePlans($request);
    }

    public function getUserPurchasePlans(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getUserPurchasePlans($request);
    }

    public function getPurchasePlanByCard(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getPurchasePlanByCard($request);
    }

    public function createLeasePlan(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::createLeasePlan($request);
    }

    public function repayLease(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::repayLease($request);
    }

    public function getLeasePlans(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getLeasePlans($request);
    }

    public function getUserLeasePlans(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getUserLeasePlans($request);
    }

    public function getLeasePlanByCard(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getLeasePlanByCard($request);
    }

    public function listUsers(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::listUsers($request);
    }

    public function getUser(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::getUser($request);
    }

    public function editUser(Request $request): \Illuminate\Http\JsonResponse
    {
        return AdminServices::editUser($request);
    }
}
