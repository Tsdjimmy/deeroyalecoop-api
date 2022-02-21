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
            $all_staff = "a na";

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


    public static function updateAccount($request): \Illuminate\Http\JsonResponse
    {
        try {

            $uid = $request->input('uid');
            $full_name = $request->input('full_name');
            $email = $request->input('email');
            $role = $request->input('role');


            $staff = Staff::find($uid);
            $staff->email = $email;
            $staff->role = $role;
            $staff->full_name = $full_name;
            $staff->save();

            return response()->json(['message' => 'Account updated successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred updating your account', 'short_description' => $e->getMessage()]);
        }
    }

    public static function updateAccountPassword($request): \Illuminate\Http\JsonResponse
    {
        try {

            $password = $request->input('password');
            $uid = $request->input('uid');

            $staff = Staff::find($uid);
            $staff->password = bcrypt($password);
            $staff->save();

            return response()->json(array('message' => 'Password updated successfully'), 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating your account password',
                'short_description' => $e->getMessage()
            ], 400);
        }

    }

    public static function deleteAccount($request): \Illuminate\Http\JsonResponse
    {
        try {

            $uid = $request->input('uid');

            Staff::where('id', $uid)->delete();

            return response()->json(['message' => 'Account deleted successfully'], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the account',
                'short_description' => $e->getMessage()
            ], 400);
        }
    }

    public static function activateAccount($request): \Illuminate\Http\JsonResponse
    {
        try {

            $uid = $request->input('uid');


            Staff::where('id', $uid)->update(['status' => 'active']);

            return response()->json(['message' => 'Account activated successfully']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while activating',
                'short_description' => $e->getMessage()
            ], 400);
        }
    }

    public static function deactivateAccount($request): \Illuminate\Http\JsonResponse
    {
        try {
            $uid = $request->input('uid');


            Staff::where('id', $uid)->update(['status' => 'inactive']);

            return response()->json(['message' => 'Account activated successfully']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating your account password',
                'short_description' => $e->getMessage()
            ], 400);
        }
    }


    public static function reQuery($request)
    {
        $tid = $request->input('tid');
        $transaction = Transaction::find($tid);


        $app = new Kernel();
        $service = $app->providers[$transaction->medium];

        $query = ServiceHelpers::reQuery(new $service, ['request_id' => $transaction['external_reference']]);

        return response()->json(
            [
                'query' => $query
            ],
            200
        );
    }

    public static function testExternalEndpoints()
    {

        $medium = 'vtpass';

        $app = new Kernel();
        $service = $app->providers[$medium];
        $service_id = 'ikeja-electric';

        $allPackages = self::externalPackages(new $service, $service_id);

        return response()->json(['data' => $allPackages]);
    }

    public static function users($request): \Illuminate\Http\JsonResponse
    {
        try {

            $start_time = $request->input('start_date');
            $end_time = $request->input('end_date');
            $status = $request->input('status');
            $reference = $request->input('reference');
            $value = $request->input('value');
            $user = [];

            if (!empty($value) && !empty($start_time) && !empty($end_time) && !empty($status)) {
                $user = User::where($reference, 'LIKE', '%' . $value . '%')->whereBetween('created_at', [Carbon::parse($start_time)->startOfDay(), Carbon::parse($end_time)->endOfDay()])->where('status', $status)->orderBy('create_at', 'desc')->paginate(100);
            } else if (!empty($value) && empty($start_time) && empty($end_time) && empty($status)) {
                $user = User::where($reference, 'LIKE', '%' . $value . '%')->orderBy('created_at', 'desc')->paginate(100);
            } else if (empty($value) && !empty($start_time) && !empty($end_time) && empty($status)) {
                $user = User::whereBetween('created_at', [Carbon::parse($start_time)->startOfDay(), Carbon::parse($end_time)->endOfDay()])->orderBy('created_at', 'desc')->paginate(100);
            } else if (empty($value) && empty($start_time) && empty($end_time) && !empty($status)) {
                $user = User::where('status', $status)->orderBy('created_at', 'desc')->paginate(100);
            } else {
                $user = User::orderBy('created_at', 'desc')->paginate(100);
            }

            return response()->json(
                [
                    'message' => 'User list',
                    'data' => [
                        'user' => $user
                    ]
                ],
                200
            );

        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred fetching users',
                'short_description' => $e->getMessage()
            ], 400);
        }
    }

    public static function userDetails($request)
    {
        try {

            $uid = $request->input('uid');

            $user = User::find($uid);

            $transactionCount = Transaction::where('uid', $uid)->count();

            $user['transaction_count'] = $transactionCount;

            $transactionSuccessCount = Transaction::where(['uid' => $uid, 'status' => 'success'])->count();
            $transactionFailedCount = Transaction::where(['uid' => $uid, 'status' => 'failed'])->count();
            $user['transaction_count_success'] = $transactionSuccessCount;
            $user['transaction_count_failed'] = $transactionFailedCount;
            $transaction = Transaction::where('uid', $uid)->orderBy('created_at', 'desc')->take(100)->get();

            $cards = Card::where('uid', $uid)->get();

            $payments = Payment::where('uid', $uid)->orderBy('created_at', 'desc')->paginate(100);

            return response()->json([
                'user' => $user,
                'transaction' => $transaction,
                'cards' => $cards,
                'payments' => $payments
            ]);

        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred fetching user details', 'short_description' => $e->getMessage()], 400);
        }
    }

    public static function banUserAccount($request)
    {
        $uid = $request->input('uid');

        $user = User::find($uid);
        $user->status = 'ban';
        $user->save();

        return response()->json(['message' => 'Account banned successfully'], 200);
    }

    public static function unBanUserAccount($request)
    {
        $uid = $request->input('uid');

        $user = User::find($uid);
        $user->status = 'active';
        $user->save();

        return response()->json(['message' => 'Account banned successfully'], 200);
    }

    public static function resetUserPinAccount($request)
    {

        $uid = $request->input('uid');

        Pin::where('uid', $uid)->delete();

        return response()->json(['message' => 'Pin reset successfully'], 200);
    }

    public static function membershipPlans($request)
    {

        $plans = MembershipPlan::get();

        return response()->json(['message' => 'Membership plans', 'data' => ['plans' => $plans]]);
    }

    public static function updateMembershipPlans($request)
    {

        $plans = $request->input('mid');

        $upgrade_amount = $request->input('upgrade_amount');
        $daily_transaction_limit = $request->input('daily_transaction_limit');
        $daily_transfer_limit = $request->input('daily_transfer_limit');
        $status = $request->input('status');

        $mPlan = MembershipPlan::find($plans);
        if ($upgrade_amount) $mPlan->upgrade_amount = $upgrade_amount;
        if ($daily_transfer_limit) $mPlan->daily_transfer_limit = $daily_transfer_limit;
        if ($daily_transaction_limit) $mPlan->daily_transaction_limit = $daily_transaction_limit;
        if ($status) $mPlan->status = $status;
        $mPlan->save();

        return response()->json(['message' => 'Membership plans updated', 'data' => [
            'plan' => $mPlan
        ]]);
    }

    public static function allUserMembership($request)
    {
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        $reference = $request->input('reference');
        $value = $request->input('value');

        $status = $request->input('status');

        $users = $request->input('user_id');

        $membership = UserMembership::orderBy('created_at', 'desc')
            ->when($users, function ($query) use ($users) {
                return $query->where('uid', $users);
            })
            ->when($status, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->when($start_date, function ($query) use ($start_date, $end_date) {
                return $query->where('created_at', '<', Carbon::parse($end_date)->startOfDay())->where('created_at', '>', Carbon::parse($start_date)->startOfDay());
            })
            ->when($value, function ($query) use ($value, $reference) {
                return $query->where($reference, 'LIKE', '%' . $value . '%');
            })
            ->with(['user', 'membership'])->paginate(100);


        return response()->json(
            [
                'membership' => $membership
            ],
            200
        );
    }

    public static function userMembershipsDetails($request): \Illuminate\Http\JsonResponse
    {
        $umid = $request->input('umid');

        $user_memberships = UserMembership::with(['user', 'membership'])->find($umid);

        $user_details = User::find($user_memberships->uid);

        $other_upgrades = UserMembership::where('uid', $user_memberships->uid)->where('id', '!=', $umid)->get();

        return response()->json(
            [
                'message' => 'Upgrade information',
                'data' => [
                    'user' => $user_details,
                    'current_record' => $user_memberships,
                    'previous_record' => $other_upgrades
                ]
            ]
        );
    }

    public static function approveMembership($request)
    {
        try {
            $umid = $request->input('umid');

            $user_memberships = UserMembership::find($umid);

            $user_memberships->is_verified = 'verified';
            $user_memberships->status = 'approved';
            $user_memberships->save();

            $user = User::find($user_memberships->uid);
            $user->current_level = $user_memberships->code;
            $user->save();

            return response()->json(['message' => 'User membership upgraded successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unable to upgrade user account level'], 400);
        }
    }

    public static function declineMembershipUpgrade($request)
    {
        try {
            $umid = $request->input('umid');

            $user_memberships = UserMembership::with('membership')->find($umid);

            $user_memberships->is_verified = 'declined';
            $user_memberships->status = 'declined';
            $user_memberships->save();

            $user = User::find($user_memberships->uid);

            $current_time = explode(' ', Carbon::now());

            $time_reference = join('-', $current_time);

            $reference = 'PU' . Str::random(6) . $time_reference;

            while (Transaction::where('reference', $reference)->count() > 0) {
                $reference = 'PU' . Str::random(2) . '-' . $time_reference;
            }

            $transaction = new Transaction();
            $transaction->reference = $reference;
            $transaction->uid = $user->id;
            $transaction->amount = $user_memberships->membership->upgrade_amount;
            $transaction->charge_amount = $user_memberships->membership->upgrade_amount;
            $transaction->payment_description = 'Refund for upgrade declined';
            $transaction->description = 'Refund for upgrade declined';
            $transaction->status = 'completed';
            $transaction->save();

            $payment = new PaymentHelpers();
            $payment->topUserWallet($user->id, $transaction->charge_amount, $transaction, 'cr');


            return response()->json(['message' => 'User membership upgraded declined successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unable to upgrade user account level'], 400);
        }
    }

    public static function userFunding($request): \Illuminate\Http\JsonResponse
    {
        try {
            $amount = $request->input('amount');
            $uid = $request->input('uid');

            $reference = 'AD' . Str::random(3) . Carbon::now()->format('ymdhms');

            while (Transaction::where('reference', $reference)->count() > 0) {
                $reference = 'AD' . Str::random(6);
            }

            $transaction = new Transaction();
            $transaction->uid = $uid;
            $transaction->reference = $reference;
            $transaction->transaction_type = 'funding';
            $transaction->amount = $amount;
            $transaction->payment_description = 'Account funded by admin';
            $transaction->save();

            $payment = new PaymentHelpers();
            $payment->topUserWallet($uid, $amount, $transaction);

            $transaction->status = 'completed';
            $transaction->save();

            return response()->json(
                ['message' => 'User account funded successfully']
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'An error occurred funding user account']
            );
        }
    }

    public static function transaction($request)
    {

        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        $reference = $request->input('reference');
        $value = $request->input('value');

        $status = $request->input('status');

        $users = $request->input('user_id');

        $transaction = Transaction::orderBy('created_at', 'desc')
            ->when($users, function ($query) use ($users) {
                return $query->where('uid', $users);
            })
            ->when($status, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->when($start_date, function ($query) use ($start_date, $end_date) {
                return $query->where('created_at', '<', Carbon::parse($end_date)->startOfDay())->where('created_at', '>', Carbon::parse($start_date)->endOfDay());
            })
            ->when($value, function ($query) use ($value, $reference) {
                return $query->where($reference, 'LIKE', '%' . $value . '%');
            })
            ->with(['user', 'transactionable'])->paginate(100);

        return response()->json(
            [
                'transaction' => $transaction,
            ],
            200
        );
    }

    public static function transactionDetails($request)
    {

        $tid = $request->input('tid');

        $transaction_details = Transaction::with(['user', 'flow', 'transactionable'])->where('id', $tid)->first();

        return response()->json(
            [
                'message' => 'Transaction details',
                'data' => [
                    'transaction' => $transaction_details
                ]
            ]
        );
    }

    public static function refundUser($request): \Illuminate\Http\JsonResponse
    {
        try {
            $tid = $request->input('tid');

            $transaction = Transaction::find($tid);

            $amount_to_refund = $transaction->charge_amount;

            $transaction->status = 'refund';
            $transaction->save();

            GeneralHelper::logTransactionFlow($transaction, [
                'description' => 'Refund occurred on this transaction',
                'time' => Carbon::now()
            ]);

            $payment = new PaymentHelpers();
            $payment->topUserWallet($transaction->uid, $amount_to_refund, $transaction, 'refund');

            $description = [
                'message' => 'Performed a refund on transaction with id: ' . $transaction->id
            ];

            GeneralHelper::auditLogAdmin($description, $request->user()['id']);

            return response()->json([
                'message' => 'Refund performed successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'An error occurred refunding user account', 'short_description' => $e->getMessage()],
                400
            );
        }
    }

    public static function debitUser($request): \Illuminate\Http\JsonResponse
    {
        try {
            $tid = $request->input('tid');

            $transaction = Transaction::find($tid);

            $amount_to_refund = $transaction->charge_amount;

            $transaction->status = 'completed';
            $transaction->save();

            GeneralHelper::logTransactionFlow($transaction, [
                'description' => 'User account was debited by admin fo',
                'time' => Carbon::now(),
                'extra' => json_encode([
                    'debited_by' => $request->user()['full_name']
                ])
            ]);

            $payment = new PaymentHelpers();
            $payment->topUserWallet($transaction->uid, $amount_to_refund, $transaction, 'debit');

            $description = [
                'message' => 'Performed a debit on transaction with id: ' . $transaction->id,
                'transaction' => json_encode($transaction)
            ];

            GeneralHelper::auditLogAdmin($description, $request->user()['id']);

            return response()->json([
                'message' => 'Refund performed successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'An error occurred refunding user account', 'short_description' => $e->getMessage()],
                400
            );
        }
    }

    public static function dashboard($request): \Illuminate\Http\JsonResponse
    {

        $todays_start = Carbon::now()->startOfDay();
        $today_end = Carbon::now()->endOfDay();

        $month_start = Carbon::now()->startOfMonth();
        $month_end = Carbon::now()->endOfMonth();

        $year_start = Carbon::now()->startOfYear();
        $year_end = Carbon::now()->endOfYear();

        $last_transaction = Transaction::where(
            [
                'transaction_type' => 'service'
            ]
        )->orderBy('created_at', 'desc')->first();

//        stat start
        $stat = [];

        $stat['api'] = Transaction::where(
            [
                'status' => 'completed',
                'channel' => 'api',
                'transaction_type' => 'service'
            ]
        )->whereBetween('created_at', [$todays_start, $today_end])->count();

        $stat['system'] = Transaction::where(
            [
                'status' => 'completed',
                'channel' => 'system',
                'transaction_type' => 'service'
            ]
        )->whereBetween('created_at', [$todays_start, $today_end])->count();


        $stat['api_sales'] = Transaction::where(
            [
                'status' => 'completed',
                'channel' => 'api',
                'transaction_type' => 'service'
            ]
        )->whereBetween('created_at', [$todays_start, $today_end])->sum('charge_amount');

        $stat['system_sales'] = Transaction::where(
            [
                'status' => 'completed',
                'channel' => 'system',
                'transaction_type' => 'service'
            ]
        )->whereBetween('created_at', [$todays_start, $today_end])->sum('charge_amount');
//stat end

        $sales = [];

        $sales['today'] = Transaction::where(
            [
                'status' => 'completed',
                'transaction_type' => 'service'
            ]
        )->whereBetween('created_at', [$todays_start, $today_end])->sum('charge_amount');

        $sales['month'] = Transaction::where(
            [
                'status' => 'completed',
                'transaction_type' => 'service'
            ]
        )->whereBetween('created_at', [$month_start, $month_end])->sum('charge_amount');

        $sales['year'] = Transaction::where(
            [
                'status' => 'completed',
                'transaction_type' => 'service'
            ]
        )->whereBetween('created_at', [$year_start, $year_end])->sum('charge_amount');

        $sales['last_sales_date'] = $last_transaction->created_at;

        $transactions = [];

        $transactions['today'] = Transaction::where(
            [
                'status' => 'completed',
                'transaction_type' => 'service'
            ]
        )->whereBetween('created_at', [$todays_start, $today_end])->count();

        $transactions['month'] = Transaction::where(
            [
                'status' => 'completed',
                'transaction_type' => 'service'
            ]
        )->whereBetween('created_at', [$month_start, $month_end])->count();

        $transactions['year'] = Transaction::where(
            [
                'status' => 'completed',
                'transaction_type' => 'service'
            ]
        )->whereBetween('created_at', [$year_start, $year_end])->count();

        $transactions['reference'] = $last_transaction->reference;

        $last_10transactions = Transaction::orderBy('created_at', 'desc')
            ->with(['user', 'transactionable'])->take(30)->limit(30)->get();

//        $user = User::with('transactions')->groupBy('current_level')->get();


        $tnx_helper = Transaction::query()
            ->join('users', 'transaction.uid', '=', 'users.id')
            ->get()
            ->groupBy('users.current_level');


        return response()->json([
            'transactions' => $transactions,
            'sales' => $sales,
//            'users' => $user,
            'user_level' => [],
            'last_10transactions' => $last_10transactions,
            'stat' => $stat
        ]);
    }

    public static function saveFireBaseKey($request)
    {
        $uid = $request->user()['id'];
        $firebase_key = $request->input('firebase_key');

        $firebase = new FirebaseKey();
        $firebase->firebase_key = $firebase_key;
        $firebase->uid = $uid;
        $firebase->save();

        return response()->json(['message' => 'token saved']);
    }


    public static function createGrouping($request)
    {
        $grouping = $request->input('name');
        $sub_service_id = $request->input('sub_service_id');

        $group = new Grouping();
        $group->name = $grouping;
        $group->sub_service_id = $sub_service_id;
        $group->save();

        return response()->json(['message' => 'Grouping created successfully'], 200);
    }

    public static function assignGrouping($request): \Illuminate\Http\JsonResponse
    {

        try {
            $grouping = $request->input('grouping');

            $type = $request->input('service_type');
            $service_id = $request->input('service_id');

            switch ($type) {
                case 'data':
                    Package::where('id', $service_id)->update(['group_by' => $grouping]);
                    break;
                case 'electricity':
                    SubService::where('id', $service_id)->update(['group_by' => $grouping]);
                    break;
            }

            return response()->json(['message' => 'Service grouped successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred performing the operation', 'short_description' => $e->getMessage()], 400);
        }
    }

    public static function confirmAirtimeCash($request): \Illuminate\Http\JsonResponse
    {
        try {

            $tid = $request->input('tid');

            $transaction = Transaction::where('id', $tid)->first();

            if ($transaction->status !== 'processing') return response()->json(['message' => 'No action can be performed on this transaction'], 400);

            $transaction->status = 'completed';
            $transaction->description = 'Airtime to cash confirmed and account credited';
            $transaction->save();

            $payment = new PaymentHelpers();
            $payment->topUserWallet($transaction->uid, $transaction->charge_amount, $transaction, 'cr');

            return response()->json(['message' => 'Airtime to cash confirmed'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred performing the operation', 'short_description' => $e->getMessage()], 400);
        }
    }

    public static function declineAirtimeCash($request): \Illuminate\Http\JsonResponse
    {
        try {

            $tid = $request->input('tid');

            $transaction = Transaction::where('id', $tid)->first();

            if ($transaction->status !== 'processing') return response()->json(['message' => 'No action can be performed on this transaction'], 400);

            $transaction->status = 'declined';
            $transaction->save();

            $payment = new PaymentHelpers();
            $payment->topUserWallet($transaction->uid, $transaction->charge_amount, $transaction, 'dr');

            GeneralHelper::auditLogAdmin('Declined airtime to cash purchase', $request->user()['id'], '');

            return response()->json(['message' => 'Airtime to cash confirmed'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred performing the operation', 'short_description' => $e->getMessage()], 400);
        }
    }

}
