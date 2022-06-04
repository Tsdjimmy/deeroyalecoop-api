<?php


namespace App\services;

use App\Models\User;
use App\Models\Cards;
use App\Models\Lease;
use App\Models\Loans;
use App\Models\Savings;
use App\Models\Purchase;
use Illuminate\Support\Str;
use App\Models\TransactionLogs;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;


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

    public static function getCard($request)
    {
        $uid = Auth::user()->id;
        $cards = Cards::where('user_id', $uid)->first();

        if(empty($cards))
        return response()->json(['message' => 'No cards were found'],404);
        
        if(!empty($cards))
        return response()->json(['message' => 'Fetched Successfully', 'data' => $cards],200);
    }

    // public static function notification($request)
    // {
    //     $notification = Notification::latest()->take(5)->get();
    //     if(empty($notification))
    //     return response()->json(['message' => 'Notifications will appear here'],200);

    //     if(!empty($notification))
    //     return response()->json(['message' => 'Fetched Successfully', 'data' => $notification],200);
    // }

    public static function transactionHistory($request)
    {
        $transaction_log = TransactionLogs::latest()->take(10)->get();
        if(empty($transaction_log))
        return response()->json(['message' => 'Transaction history will appear here'],200);

        if(!empty($transaction_log))
        return response()->json(['message' => 'Fetched Successfully', 'data' => $transaction_log],200);

    }

    public static function transactionByType($request)
    {
        try {
            if(empty($request))
        return response()->json(['message' => 'Request is empty'],400);

        if(!empty($request))
        {
            if($request->input('type') == 'savings')
            $transaction = Savings::latest()->take(50)->get();

            if($request->input('type') == 'loan')
            $transaction = Loans::latest()->take(50)->get();

            if($request->input('type') == 'purchases')
            $transaction = Purchase::latest()->take(50)->get();

            if($request->input('type') == 'leases')
            $transaction = Lease::latest()->take(50)->get();
        }

        return response()->json(['message' => 'Data Fetched', 'data' => $transaction],200);
        }catch (\Exception $e)
        {
            return response()->json(['message' => 'An error occurred', 'short_description' => $e->getMessage()],400);
        }

    }
}

