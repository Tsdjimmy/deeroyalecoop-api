<?php


namespace App\services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\BankAccounts;
use App\Models\FundTransfer;
use App\Models\Bank;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class UserServices
{
    public static function register($request)
    {
        try {

            $firstname = $request->input('first_name');
            $middle_name = $request->input('middle_name');
            $last_name = $request->input('last_name');
            $email = $request->input('email');
            $password = $request->input('password');
            $status = $request->input('status');

            $user = new User();
            $user->first_name = $firstname;
            $user->middle_name = $middle_name;
            $user->last_name = $last_name;
            $user->email = $email;
            $user->password = password_hash($password, PASSWORD_DEFAULT);

            //$staff->last_seen = carbon::now();
            $user->save();

            return response()->json(['message' => 'Account created successfully'], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred accessing your account',
                'short_description' => $e->getMessage(),
            ], 400);
        }

    }
}

