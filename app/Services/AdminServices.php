<?php


namespace App\services;


use App\Domain\Services\Kernel;
use App\Domain\Services\ServicesInterface;
use App\helpers\GeneralHelper;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use SebastianBergmann\RecursionContext\Exception;
use App\Models\Staff;
use App\Models\Branches;
use App\Models\Savings;
use App\Models\Rates;
use App\Models\Loans;
use App\Models\Cards;
use Illuminate\Support\Facades\Validator;

class AdminServices
{
    public static function login($request): \Illuminate\Http\JsonResponse
    {
        try {

            $email = $request->input('email');
            $password = $request->input('password');

            $staff = Staff::where('email', $email)->first();

            if (is_null($staff)) return response()->json(['message' => 'Invalid credentials provided'], 400);

            if (!password_verify($password, $staff->password)) return response()->json(['message' => 'Invalid credentials provided'], 400);


            $token = $staff->createToken('Personal Access Token', ['staff'])->accessToken;

            Staff::where('email', $email)->update(['last_seen' => Carbon::now()]);

            return response()->json(
                [
                    'message' => 'Access granted successfully',
                    'data' => [
                        'user' => $staff,
                        'token' => $token
                    ]
                ],
                200
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while logging in',
                'short_description' => $e->getMessage(),
            ], 400);
        }
    }

    public static function register($request): \Illuminate\Http\JsonResponse
    {

        try {

            $full_name = $request->input('full_name');
            $email = $request->input('email');
            $password = $request->input('password');
            $role = $request->input('role');
            $status = $request->input('status');

            $staff = new Staff();
            $staff->full_name = $full_name;
            $staff->email = $email;
            $staff->password = password_hash($password, PASSWORD_DEFAULT);
            $staff->role = $role;
            $staff->status = $status;
            //$staff->last_seen = carbon::now();
            $staff->save();

            return response()->json(['message' => 'Account created successfully'], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred accessing your account',
                'short_description' => $e->getMessage(),
            ], 400);
        }

    }

    public static function getAdminUsers()
    {
        try{
            $all_staff = staff::all();

        return response()->json([
            'message' => "All Admin",
            'Data'  => $all_staff
        ],200);
        
        }catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred accessing your account',
                'short_description' => $e->getMessage(),
            ], 400);
        }

        
    }

    public static function registerUsers($request)
    {
        try{
            $user = new User();
            $user->email = request()->input('email');
            $user->password = request()->input('password');
            $user->save();

            $savings = new Savings();
            $savings->user_id = $user->id;
            $savings->amount = 0;
            $savings->amount_before = 0;
            $savings->amount_after = 0;
            $savings->transaction_type = 'credit';
            $savings->staff_id = $request->user()->id;
            $savings->save();

            return response()->json([
                'message' => "Successfully registered",
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred accessing your account',
                'short_description' => $e->getMessage(),
            ], 400);
    }
   
}

    public static function addBranch($request)
        {
            try{
                $branch = new Branches;
                $branch->branch_name = request()->input('branch_name');
                $branch->branch_cordinator = request()->input('branch_cordinator');
                $branch->branch_address = request()->input('branch_address');
                $branch->branch_code = "DC".mt_rand(1111,9999);
                $branch->save();

                return response()->json([
                    'message' => "Successfully registered",
                    'data' => $branch
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'An error occurred accessing your account',
                    'short_description' => $e->getMessage(),
                ], 400);
        }
    
    }

    public static function addCard($request)
    {
        try{
            $user_id = $request->input('user_id');
            $card = new Cards([
                'card_no' => $request->input('card_no'),
                'user_id' => $user_id,
                'active' => '1',
                'status' => 'running',
            ]);
                $card->save();
                return response()->json([
                    "message" => "Card saved successfully",
                    "data" => $card
                ],200);

    }catch (\Exception $e) {
        return response()->json([
             "message" => 'An error occurred',
        "short_description" => $e->getMessage(),
        ],200);
       
    }
        }
        

    public static function creditSavings($request)
    {
        try{
            $user_id = $request->input('user_id');
            $staff_id = $request->user()->id;
            $user = User::where('id', $user_id)->first();
            $admin = Staff::where('id', $staff_id)->first();
            $card_id = Card::where('user_id', $user_id)->pluck('id');
            $savings = Savings::where(['user_id' => $user_id, 'card_id' => $card_id])->first();
            $amount = $request->amount;

            $transaction = new GeneralHelper;
            $amount_after = $transaction->transaction($amount, $savings->amount_after, 'cr');  
            $transaction_type = 'credit';

            $savingsData = Savings::where('user_id', $user->id)
            ->update(['staff_id' =>  $staff_id,
                    'amount' => $request->amount,
                    'amount_before' => $savings->amount_after,
                    'amount_after' => $amount_after,
                    'transaction_type' => $transaction_type
                ]);
                $transaction->transactionLog($user_id, $amount, $staff_id, 'savings', $transaction_type);

            return response()->json([
                'message' => 'Credited Successfully',
                'data' => $savingsData
            ], 200);
        }catch (\Exception $e)
            {
                return response()->json([
                    'message' => 'An error occurred accessing your account',
                    'short_description' => $e->getMessage(),
                ], 400);
            }
    }

    public static function debitSavings($request)
    {
        try{
            $user_id = $request->input('user_id');
            $staff_id = $request->user()->id;
            $user = User::where('id', $user_id)->first();
            $admin = Staff::where('id', $staff_id)->first();
            $savings = Savings::where(['user_id' => $user_id, 'card_id' => $card_id])->first();
            $amount = $request->amount;

            $transaction = new GeneralHelper;
            $amount_after = $transaction->transaction($amount, $savings->amount_after, 'dr');  
            $transaction_type = 'debit';

            $savingsData = Savings::where('user_id', $user->id)
            ->update(['staff_id' =>  $staff_id,
                    'amount' => $request->amount,
                    'amount_before' => $savings->amount_after,
                    'amount_after' => $amount_after,
                    'transaction_type' => $transaction_type
                ]);
                $transaction->transactionLog($user_id, $amount, $staff_id, 'savings', $transaction_type);

            return response()->json([
                'message' => 'Debited Successfully',
                'data' => $savingsData
            ], 200);
        }catch (\Exception $e)
            {
                return response()->json([
                    'message' => 'An error occurred accessing your account',
                    'short_description' => $e->getMessage(),
                ], 400);
            }
    }

    public static function createLoanPlan($request)
    {
        try{
            $rules = [
                'start_date' => 'date_format:d/m/Y',
                'end_date' => 'date_format:d/m/Y',
                'loan_purpose' => 'required',
                'amount_borrowed' => 'required, double',
                ];
    
            $validate = Validator::make($request->input(), $rules, GeneralHelper::customMessage());
    
            if ($validate->failed())
                return response()->json(array('message' => $validate->errors()->first()), 400);
    
            $loan_purpose = $request->input('loan_purpose');
            $amount_borrowed = $request->input('amount_borrowed');
            $amount_paid = 0;
            $balance = $amount_borrowed;
            $user_id = $request->input('user_id');
            $staff_id = $request->user()->id;
            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');
            $duration = $request->input('duration');
            $rate = Rates::where('duration', $duration)->first();
            $interest_id = $rate->interest;
            $interest = $rate->interest;
            $status = 'running';
    
            $loan = new Loans();
            $loan->loan_purpose = $loan_purpose;
            $loan->amount_borrowed = $amount_borrowed;
            $loan->amount_paid = $amount_paid;
            $loan->user_id = $user_id;
            $loan->staff_id = $staff_id;
            $loan->start_date = $start_date;
            $loan->end_date = $end_date;
            $loan->duration = $duration;
            $loan->interest = $interest;
            $loan->interest_id = $interest_id;
            $loan->status = $status;
            $loan->save();
    
            return response()->json([
                'message' => 'Loan Plan Created Successfully',
                'data' => $loan
            ],200);
        }catch (\Exception $e)
        {
            return response()->json([
                'message' => 'An error occurred creating the loan plan',
                'short_description' => $e->getMessage()
            ],400);
        }
        
    }

    public static function disburseLoan($request)
    {
        try{
            $loan = Loans::where('');
        }catch (\Exception $e)
        {
            return response()->json([
                "message" => "An error occurred",
                "short_description" => $e->getMessage()
            ],200);
        }
    }

}
