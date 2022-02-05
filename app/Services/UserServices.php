<?php


namespace App\services;


use App\Domain\OnboardingUser\Kernel;
use App\Events\ServiceEvents;
use App\Events\SMSEvent;
use App\helpers\GeneralHelper;
use App\helpers\PaymentHelpers;
use App\helpers\ServiceHelpers;
use App\helpers\SMSHelpers;
use App\Jobs\TransferFunds;
use App\Interfaces\SettingsInterface;
use App\Models\Card;
use App\Models\Code;
use App\Models\MembershipPlan;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Pin;
use App\Models\Service;
use App\Models\SubService;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserMembership;
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
    public static function verifyEmailCode($request): \Illuminate\Http\JsonResponse
    {
        try {

            $code = $request->input('code');
            $user = $request->user();

            $codeData = Code::where(['code' => $code, 'type' => 'email'])->first();

            if (is_null($codeData)) return response()->json(['message' => 'Invalid verify code provided'], 400);

            $user = User::find($user->id);
            $user->is_email_verified = true;
            $user->save();

            Code::where(['code' => $code, 'type' => 'email'])->delete();

            $kernel = new Kernel();
            $processes = $kernel->process;

            foreach ($processes as $process) {
                $thisProcess = new $process($user);
                $thisProcess->process();
            }

            GeneralHelper::sendPhoneNumberVerificationCode($user);

            return response()->json([
                'message' => 'Email verified successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'An error occurred verifying code provided']
            );
        }
    }

    public static function verifyPhoneNumber($request): \Illuminate\Http\JsonResponse
    {
        try {

            $code = $request->input('code');
            $user = $request->user();

            $codeData = Code::where(['uid' => $user->id, 'type' => 'phone'])->orderBy('created_at', 'desc')->first();

            if (is_null($codeData)) return response()->json(['message' => 'Invalid verify code provided'], 400);
            $sms = new SMSHelpers();
            $verifyToken = $sms->verifySMSToken($codeData['code'], $code);
            if ($verifyToken === 'failed') return response()->json(['message' => 'Invalid code provided'], 400);
            $user = User::find($user->id);
            $user->is_phone_verified = true;
            $user->save();
            Code::where(['id' => $codeData->id])->delete();

            return response()->json([
                'message' => 'Phone number verified successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'An error occurred verifying code provided', 'short_description' => $e->getMessage()]
            );
        }
    }

    public static function resendVerificationCode($request): \Illuminate\Http\JsonResponse
    {

        try {
            $medium = $request->input('medium');
            if (!in_array($medium, ['email', 'phone'])) return response()->json(['message' => 'Invalid medium provided'], 400);

            $id = $request->user()->id;

            $user = User::find($id);

            switch ($medium) {
                case 'email':
                    GeneralHelper::sendEmailVerificationCode($user);
                    break;
                case 'phone':
                    GeneralHelper::sendPhoneNumberVerificationCode($user);
                    break;
            }

            return response()->json(
                ['message' => 'Code sent successfully']
            );
        } catch (\Exception $e) {
            return response()->json(['message' => "An error occurred performing action", 'short_description' => $e->getMessage()]);
        }
    }

    public static function setUserPin($request): \Illuminate\Http\JsonResponse
    {
        try {
            $pin = $request->input('pin');
            $user = $request->user();

            $userPin = Pin::where('uid', $user->id)->first();

            if ($userPin)
                return response()->json(array('message' => 'Unable to set pin. Contact support'), 400);

            $newPin = new Pin();
            $newPin->pin = bcrypt($pin);
            $newPin->uid = $user->id;
            $newPin->save();
            return response()->json(
                [
                    'message' => 'Pin set successfully.',
                    'data' => [
                        'user' => User::find($user->id)
                    ]
                ],
                200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unable to set user pin at this time. Contact support for further help.'], 400);
        }
    }

    public static function requestPinResetCode($request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $phone_number = $user->phone_number;
        $generated_code = GeneralHelper::generateCode();
        $data = [
            'code' => $generated_code,
            'message' => 'OTP verification code: ' . $generated_code
        ];
        $code = GeneralHelper::saveCode($generated_code, $user, 'phone', 'pin_reset');
        event(new SMSEvent($phone_number, $data, $code));
        return response()->json(['message' => 'Pin reset code sent successfully'], 200);
    }

    public static function updatePin($request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();
            $code = $request->input('code');
            $new_pin = $request->input('new_pin');
            $uid = $user->id;

            $pin = Pin::where('uid', $user->id)->first();

//            if (!password_verify($old_pin, $pin->pin))
//                return response()->json(['message' => 'Old pin does not match'], 400);

            $resetCode = Code::where(['uid' => $uid, 'event' => 'pin_reset'])->orderBy('created_at', 'desc')->first();

            if (is_null($resetCode))
                return response()->json(['message' => 'No reset record found for this user.'], 400);

            $sms = new SMSHelpers();
            $verifyToken = $sms->verifySMSToken($resetCode->code, $code);

            if ($verifyToken === 'failed') return response()->json(['message' => 'Invalid code provided'], 400);

            Pin::where('uid', $user->id)->update([
                'pin' => bcrypt($new_pin)
            ]);

            Code::where(['id' => $resetCode->id, 'event' => 'pin_reset'])->delete();

            return response()->json(['message' => 'Pin updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(array('message' => 'Unable to update pin at this time. Kindly contact support' . $e->getMessage() . $e->getLine()), 400);
        }
    }

    public static function updateProfile($request): \Illuminate\Http\JsonResponse
    {
        try {
            $rules = [
                'phone_number' => 'required',
                'email' => 'required',
                'full_name' => 'required',
            ];

            $validate = Validator::make($request->input(), $rules);

            if ($validate->failed())
                return response()->json(array('message' => $validate->errors()->first()), 400);

            $full_name = $request->input('full_name');
            $email = $request->input('email');
            $phone_number = $request->input('phone_number');

            $check_email = User::where('email', $email)->where('id', '!=', $request->user()->id)->first();

            if (!is_null($check_email)) return response()->json(['message' => 'Email already in use by another user.s'], 400);

            $check_phone_number = User::where('phone_number', $phone_number)->where('id', '!=', $request->user()->id)->first();

            if (!is_null($check_phone_number)) return response()->json(['message' => 'Email already in use by another user.s'], 400);

            $user = User::find($request->user()->id);
            if ($full_name) $user->full_name = $full_name;
            if ($email) {
                if ($email != $user->email) $user->is_email_verified = false;
                $user->email = $email;
            }
            if ($phone_number) {
                if ($phone_number != $user->phone_number) $user->is_phone_verified = false;
                $user->phone_number = $phone_number;
            }
            $user->save();

            return response()->json(
                [
                    'message' => 'Profile Updated',
                    'data' => [
                        'user' => $user
                    ]
                ]
            );

        } catch (\Exception $e) {
            return response()->json(array('message' => 'An error occurred while updating.'), 400);
        }
    }

    public static function updatePassword($request): \Illuminate\Http\JsonResponse
    {
        try {
            $new_password = $request->input('new_password');
            $old_password = $request->input('old_password');
            $user = User::find($request->user()->id);
            if (!password_verify($old_password, $user->password))
                return response()->json(['message' => 'Password does not match old password'], 400);
            $user->password = bcrypt($new_password);
            $user->save();
            return response()->json(array('message' => 'Password updated successfully.'), 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while updating password'], 400);
        }
    }

    public static function upgradeMembershipAccount($request): \Illuminate\Http\JsonResponse
    {
        try {

            $id_card = $request->file('id_card');
            $passport = $request->file('passport');
            $bvn = $request->input('bvn');
            $mid = $request->input('mid');
            $agreement = $request->file('agreement');
            $utility_bill = $request->file('utility_bull');
            $need_pos = $request->input('need_pos');
            $rule = [
                'id_card' => 'Required',
                'passport' => 'Required'
            ];
            $validate = Validator::make($request->input(), $rule);
            if ($validate->failed())
                return response()->json(['message' => $validate->errors()->first()]);

            $membership = MembershipPlan::where(['status' => 'active', 'id' => $mid])->first();
            if (is_null($membership))
                return response()->json(['message' => 'Invalid membership selected'], 400);

            $user = $request->user();

            $user_memberships = UserMembership::where('uid', $user->id)->where('status', 'pending')->first();

            if (!is_null($user_memberships)) return response()->json(['message' => 'Pending upgrade awaiting approval.']);

            $payment = new PaymentHelpers();

//            check wallet balance
            $check_wallet = $payment->checkWalletBalance($user, $membership->upgrade_amount);
            if (!$check_wallet) return response()->json(['message' => 'Insufficient balance. Fund account and try again.'], 400);

            $id_card_url = '';
            if (!empty($id_card)) {
                $id_card_url = Storage::disk('s3')->put('files/' . $user->id, $id_card);
            }

            $passport_url = '';
            if (!empty($passport)) {
                $passport_url = Storage::disk('s3')->put('files/' . $user->id, $passport);
            }

            $agreement_url = '';
            if (!empty($agreement)) {
                $agreement_url = Storage::disk('s3')->put('files/' . $user->id, $agreement);
            }

            $utility_url = '';
            if (!empty($utility_bill)) {
                $utility_url = Storage::disk('s3')->put('files/' . $user->id, $utility_bill);
            }

            $userMembership = new UserMembership();
            $userMembership->uid = $user->id;
            $userMembership->mid = $mid;
            $userMembership->bvn = $bvn;
            $userMembership->passport_url = $passport_url;
            $userMembership->id_card_url = $id_card_url;
            $userMembership->utility_bill = $utility_url;
            $userMembership->agreement_url = $agreement_url;
            $userMembership->need_pos = $need_pos;
            $userMembership->save();

            $current_time = explode(' ', Carbon::now());

            $time_reference = join('-', $current_time);

            $reference = 'PU' . Str::random(6) . $time_reference;

            while (self::where('reference', $reference)->count() > 0) {
                $reference = 'PU' . Str::random(2) . '-' . $time_reference;
            }

            $transaction = new Transaction();
            $transaction->reference = $reference;
            $transaction->uid = $user->id;
            $transaction->amount = $membership->upgrade_amount;
            $transaction->charge_amount = $membership->upgrade_amount;
            $transaction->payment_description = 'Payment for upgrade';
            $transaction->description = 'Payment for upgrade';
            $transaction->status = 'completed';
            $transaction->save();

            $userMembership->tid = $transaction->id;
            $userMembership->save();


            $payment->topUserWallet($user->id, $transaction->charge_amount, $transaction, 'dr');

            return response()->json(
                ['message' => 'Upgrade request has been successfully sent for verification.'], 200
            );
        } catch (\Exception $e) {
            return response()->json(array('message' => 'An error occurred while upgrading account' . $e->getMessage()), 400);
        }
    }

    public static function membershipPlan($request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $userMembership = UserMembership::with('membership')->where('uid', $user->id)->orderBy('created_at', 'desc')->get();
        $allPlans = MembershipPlan::get();
        $user = User::where('id', $request->user()['id'])->first();

        $current_level = $user->current_level ?? 'user';

        $can_upgrade = $current_level !== 'agent';
        $nextLevel = [];
        if ($current_level == 'user') $nextLevel = ['reseller', 'agent'];
        if ($current_level == 'reseller') $nextLevel = ['agent'];
        if ($current_level == 'agent') $nextLevel = [];

        return response()->json([
            'message' => 'User Membership',
            'data' => [
                'plan' => $allPlans,
                'user_memberships' => $userMembership,
                'current_level' => $current_level,
                'can_upgrade' => $can_upgrade,
                'next_level' => $nextLevel
            ]
        ]);
    }

    public static function userWallet($request): \Illuminate\Http\JsonResponse
    {
        try {
            $uid = $request->user()['id'];

            $wallets = Wallet::where('uid', $uid)->first();

            $cards = Card::where('uid', $uid)->get();


            $payment = Payment::where('uid', $uid)->orderBy('created_at', 'desc')->paginate(100);

            return response()->json(['message' => 'user wallet', 'data' => [
                'wallet' => $wallets,
                'card' => $cards,
                'payments' => $payment
            ]]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred fetching wallet information'], 400);
        }
    }

    public static function fundWallet($request): \Illuminate\Http\JsonResponse
    {
        try {
            $reference = $request->input('transaction_id');
            $amount = $request->input('amount');
            $user = $request->user();
            $type = $request->input('type');
            $card_id = $request->input('card_id');

            if (!empty($reference))
                if (Transaction::where('external_reference', $reference)->count() > 0)
                    return response()->json(['message' => 'Invalid reference provided. Kindly try again'], 400);

            $tnx_ref = 'JO-' . Str::random(10);
            while (Transaction::where('reference', $tnx_ref)->count() > 0) {
                $tnx_ref = 'JO-' . Str::random(10);
            }

//        initiate transaction
            $transaction = new Transaction();
            $transaction->uid = $user->id;
            $transaction->reference = $tnx_ref;
            $transaction->external_reference = $reference;
            $transaction->amount = $amount;
            $transaction->transaction_type = 'funding';
            $transaction->save();

            $response = null;
            $payment = new PaymentHelpers();
            switch ($type) {
                case 'card':
                    $transaction->card_used = $card_id;
                    $transaction->save();
                    $response = $payment->fundWalletFromSaveCard($transaction);
                    break;
                default:
                    $response = $payment->fundWalletFlutterCards($transaction);
                    break;
            }

            if (is_null($response)) {
                $transaction->status = 'declined';
                $transaction->save();
                return response()->json(['message' => 'Transaction failed processing. Kindly confirm all input are valid'], 400);
            }

            if ($response['status'] == 'failed') {
                $transaction->status = 'failed';
                $transaction->save();
                return response()->json(['message' => $response['message']], 400);
            }

            return response()->json([
                'message' => 'Account funding successfully',
                'data' => [
                    'transaction' => $response['transaction']
                ]
            ]);

        } catch (\Exception $e) {
            GeneralHelper::logExceptionErrors($e);
            return response()->json([
                'message' => 'An error occurred verifying payment. Kindly contact support if you have been debited.',
                'short_description' => $e->getMessage()
            ], 400);
        }
    }

    public static function services($request): \Illuminate\Http\JsonResponse
    {
        $services = Service::all();

        return response()->json([
            'message' => 'All services',
            'data' => [
                'services' => $services
            ]
        ]);
    }

    public static function serviceDetails($request): \Illuminate\Http\JsonResponse
    {
        $service_id = $request->input('service_id');

        $services = Service::where('name', $service_id)->first();

        $sub_service = SubService::with(['packages', 'groupings'])->where('services', $services->id)->get();

        $data_grouped = SettingsInterface::DATA_GROUP;

        $electricity_grouped = SettingsInterface::ELECTRICITY_GROUP;

        return response()->json([
            'message' => 'Service details',
            'data' => [
                'services' => $services,
                'sub_services' => $sub_service,
                'data_grouped' => $data_grouped,
                'electricity_grouped' => $electricity_grouped
            ]
        ]);

    }

    public static function initializeNewTransaction($request): \Illuminate\Http\JsonResponse
    {
        try {
            $service_id = $request->input('service_id');
            $sub_service_id = $request->input('sub_service_id');
            $package_id = $request->input('package_id');
            $service_type = $request->input('service_type');
            $amount = $request->input('amount');
            $biller_number = $request->input('biller_number');
            $type = $request->input('type');

            $user = $request->user();

            $packages = null;
            $sub_services = null;
            $services = null;

            if (!empty($package_id)) {
                $packages = Package::where('id', $package_id)->first();
                if (!is_null($packages)) $sub_services = SubService::where('id', $packages->sub_services)->first();
            }

            if (is_null($packages))
                $sub_services = SubService::where('id', $sub_service_id)->first();

            if (is_null($sub_services))
                $services = Service::where('id', $service_id)->first();

            if (empty($amount))
                $amount = $packages->amount;


            $can_skip = false;

            if ($sub_services || $services) {
                $services = $services ?: $sub_services->service;
                if (in_array($services->name, ['data_e_pin', 'airtime_e_pin', 'fund_transfer']))
                    $can_skip = true;
            }

            $kernel = new \App\Domain\ServicePurchase\Kernel();
            $processes = $kernel->process;

            foreach ($processes as $process) {
                $thisProcess = new $process($user, $amount, $can_skip, ['membership_transfer_limit_check']);
                $process = $thisProcess->process();
                if (!$process) return response()->json(['message' => $thisProcess->errorMessage()], 400);
            }


            if (in_array($services->name, ['data', 'airtime'])) {
                $kernel = new \App\Domain\ServicePurchase\Kernel();
                $processes = $kernel->processAirtimeDataPhoneCheck;
                foreach ($processes as $process) {
                    $thisProcess = new $process($user, $amount, $can_skip, ['membership_transfer_limit_check'], $biller_number);
                    $process = $thisProcess->process();
                    if (!$process) return response()->json(['message' => $thisProcess->errorMessage()], 400);
                }
            }

            $user_level = $request->user()['current_level'];

            $resolveServices = $packages ?: $sub_services ?: $services;

            $userCommission = GeneralHelper::sortPurchaseCommission($user_level, $resolveServices);

            $percentage = $userCommission / 100;

            $calculateAmountCommission = $amount * $percentage;

            $charge_amount = $amount - $calculateAmountCommission;

            // check if extra data is required
            $data = [
                'biller_number' => $biller_number,
                'service_type' => $service_type,
                'service_id' => $sub_services['extra_info'],
                'type' => $type,
                'charge_amount' => $charge_amount
            ];

            $serviceKernel = new \App\Domain\Services\Kernel();
            $extra_info = ServiceHelpers::initialize(new $serviceKernel->providers[$resolveServices['medium']], $data);

            if (isset($extra_info['status']))
                if ($extra_info['status'] == 'failed') return response()->json(['message' => 'Unable to verify merchant number at the moment. Please try again'], 400);

            return response()->json([
                'message' => 'Initialize service',
                'data' => [
                    'commission' => $userCommission ?? 0,
                    'charge_amount' => $charge_amount,
                    'amount' => $amount,
                    'extra_info' => $extra_info
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred processing service purchase',
                'short_description' => $e->getMessage() . $e->getFile() . $e->getLine()
            ], 400);
        }
    }

    public static function purchaseAirtime($request): \Illuminate\Http\JsonResponse
    {

        try {
            $sub_service_id = $request->input('sub_service_id');
            $amount = $request->input('amount');
            $phone_number = $request->input('phone_number');

            $user = $request->user();

//            verify pin
            $pin = $request->input('pin');

            $verifyPin = GeneralHelper::verifyUserPin($pin, $user->id);
            if ($verifyPin['status'] == 'failed') return response()->json(['message' => $verifyPin['message']], 400);

            $kernel = new \App\Domain\ServicePurchase\Kernel();
            $processes = $kernel->process;

            foreach ($processes as $process) {
                $thisProcess = new $process($user, $amount, false, ['membership_transfer_limit_check']);
                $process = $thisProcess->process();
                if (!$process) return response()->json(['message' => $thisProcess->errorMessage()], 400);
            }

            $sub_service = SubService::where('id', $sub_service_id)->first();

            if (!$sub_service) return response()->json(['message' => 'Invalid services provided'], 400);

//        create a record of transaction
            $data = [
                'uid' => $user['id'],
                'amount' => $amount,
                'biller_number' => $phone_number,
                'service_id' => $sub_service_id,
                'transactionable_type' => 'App\Models\SubService',
                'transaction_type' => 'service'
            ];
            $transaction = Transaction::createNewTransaction($data);

//        calculate commission
            $user_level = $request->user()['current_level'];

            $resolveServices = $sub_service;

            $userCommission = GeneralHelper::sortPurchaseCommission($user_level, $resolveServices);

            $percentage = $userCommission / 100;

            $calculateAmountCommission = $amount * $percentage;

            $charge_amount = $amount - $calculateAmountCommission;

//       debit user wallet
            $payment = new PaymentHelpers();
            $payment->topUserWallet($user['id'], $charge_amount, $transaction);

            $transaction->charge_amount = $charge_amount;
            $transaction->save();

            GeneralHelper::logTransactionFlow($transaction, [
                'description' => 'Wallet debited',
                'time' => Carbon::now()
            ]);

//      process purchase
            event(new ServiceEvents($transaction, 'airtime', $resolveServices));

            return response()->json(
                [
                    'message' => 'Service purchase processing. ',
                    'data' => [
                        'transaction' => $transaction
                    ]
                ]
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'message' => 'An error occurred while purchasing airtime',
                    'short_description' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ],
                400
            );
        }

    }

    public static function purchaseData($request): \Illuminate\Http\JsonResponse
    {
        try {
            $package_id = $request->input('package_id');
            $phone_number = $request->input('phone_number');
            $user = $request->user();

//            verify pin
            $pin = $request->input('pin');

            $verifyPin = GeneralHelper::verifyUserPin($pin, $user->id);
            if ($verifyPin['status'] == 'failed') return response()->json(['message' => $verifyPin['message']], 400);

            $package = Package::find($package_id);

            $amount = $package->amount;

            $kernel = new \App\Domain\ServicePurchase\Kernel();
            $processes = $kernel->process;

            foreach ($processes as $process) {
                $thisProcess = new $process($user, $amount, false, ['membership_transfer_limit_check']);
                $process = $thisProcess->process();
                if (!$process) return response()->json(['message' => $thisProcess->errorMessage()], 400);
            }

            //        create a record of transaction
            $data = [
                'uid' => $user['id'],
                'amount' => $amount,
                'biller_number' => $phone_number,
                'service_id' => $package_id,
                'transactionable_type' => 'App\Models\Package',
                'transaction_type' => 'service'
            ];
            $transaction = Transaction::createNewTransaction($data);

//        calculate commission
            $user_level = $request->user()['current_level'];

            $resolveServices = $package;

            $userCommission = GeneralHelper::sortPurchaseCommission($user_level, $resolveServices);

            $percentage = $userCommission / 100;

            $calculateAmountCommission = $amount * $percentage;

            $charge_amount = $amount - $calculateAmountCommission;

//       debit user wallet
            $payment = new PaymentHelpers();
            $payment->topUserWallet($user['id'], $charge_amount, $transaction);

            $transaction->charge_amount = $charge_amount;
            $transaction->save();

            GeneralHelper::logTransactionFlow($transaction, [
                'description' => 'Wallet debited',
                'time' => Carbon::now()
            ]);

//      process purchase
            event(new ServiceEvents($transaction, 'data', $resolveServices));

            return response()->json(
                [
                    'message' => 'Service purchase processing. ',
                    'data' => [
                        'transaction' => $transaction
                    ]
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred processing purchase.',
                'short_description' => $e->getMessage()
            ]);
        }

    }

    public static function purchaseElectricity($request): \Illuminate\Http\JsonResponse
    {
        try {
            $service_id = $request->input('service_id');
            $meter_number = $request->input('meter_number');
            $type = $request->input('type');
            $user = $request->user();

//            verify pin
            $pin = $request->input('pin');

            $verifyPin = GeneralHelper::verifyUserPin($pin, $user->id);
            if ($verifyPin['status'] == 'failed') return response()->json(['message' => $verifyPin['message']], 400);

            $amount = $request->input('amount');

            $sub_services = SubService::find($service_id);

            $kernel = new \App\Domain\ServicePurchase\Kernel();
            $processes = $kernel->process;

            foreach ($processes as $process) {
                $thisProcess = new $process($user, $amount, false, ['membership_transfer_limit_check']);
                $process = $thisProcess->process();
                if (!$process) return response()->json(['message' => $thisProcess->errorMessage()], 400);
            }

            //        create a record of transaction
            $data = [
                'uid' => $user['id'],
                'amount' => $amount,
                'biller_number' => $meter_number,
                'service_id' => $service_id,
                'transactionable_type' => 'App\Models\SubService',
                'transaction_type' => 'service',
                'electricity_type' => $type
            ];

            $transaction = Transaction::createNewTransaction($data);

//        calculate commission
            $user_level = $request->user()['current_level'];

            $resolveServices = $sub_services;

            $userCommission = GeneralHelper::sortPurchaseCommission($user_level, $resolveServices);

            $percentage = $userCommission / 100;

            $calculateAmountCommission = $amount * $percentage;

            $charge_amount = $amount - $calculateAmountCommission;

//       debit user wallet
            $payment = new PaymentHelpers();
            $payment->topUserWallet($user['id'], $charge_amount, $transaction);

            $transaction->charge_amount = $charge_amount;
            $transaction->save();

            GeneralHelper::logTransactionFlow($transaction, [
                'description' => 'Wallet debited',
                'time' => Carbon::now()
            ]);

//      process purchase
            event(new ServiceEvents($transaction, 'electricity', $resolveServices));

            return response()->json(
                [
                    'message' => 'Service purchase processing. ',
                    'data' => [
                        'transaction' => $transaction
                    ]
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred processing purchase.',
                'short_description' => $e->getMessage()
            ]);
        }

    }

    public static function purchaseCable($request): \Illuminate\Http\JsonResponse
    {
        try {
            $package_id = $request->input('package_id');
            $subscription_type = $request->input('subscription_type');
            $smart_card_number = $request->input('smart_card_number');
            $amount = $request->input('amount');
            $user = $request->user();

//            verify pin
            $pin = $request->input('pin');

            $verifyPin = GeneralHelper::verifyUserPin($pin, $user->id);
            if ($verifyPin['status'] == 'failed') return response()->json(['message' => $verifyPin['message']], 400);
            $package = Package::find($package_id);

            $amount = $amount ?? $package['amount'];

            $kernel = new \App\Domain\ServicePurchase\Kernel();
            $processes = $kernel->process;

            foreach ($processes as $process) {
                $thisProcess = new $process($user, $amount, false, ['membership_transfer_limit_check']);
                $process = $thisProcess->process();
                if (!$process) return response()->json(['message' => $thisProcess->errorMessage()], 400);
            }

            //        create a record of transaction
            $data = [
                'uid' => $user['id'],
                'amount' => $amount,
                'biller_number' => $smart_card_number,
                'service_id' => $package_id,
                'transactionable_type' => 'App\Models\Package',
                'transaction_type' => 'service'
            ];

            $transaction = Transaction::createNewTransaction($data);

//        calculate commission
            $user_level = $request->user()['current_level'];

            $resolveServices = $package;

            $userCommission = GeneralHelper::sortPurchaseCommission($user_level, $resolveServices);

            $percentage = $userCommission / 100;

            $calculateAmountCommission = $amount * $percentage;

            $charge_amount = $amount - $calculateAmountCommission;

//       debit user wallet
            $payment = new PaymentHelpers();
            $payment->topUserWallet($user['id'], $charge_amount, $transaction);

            $transaction->charge_amount = $charge_amount;
            $transaction->save();

            GeneralHelper::logTransactionFlow($transaction, [
                'description' => 'Wallet debited',
                'time' => Carbon::now()
            ]);

//      process purchase
            event(new ServiceEvents($transaction, 'cable', $resolveServices, $subscription_type));

            return response()->json(
                [
                    'message' => 'Service purchase processing. ',
                    'data' => [
                        'transaction' => $transaction
                    ]
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred processing purchase.',
                'short_description' => $e->getMessage()
            ]);
        }

    }

    public static function purchaseEducation($request): \Illuminate\Http\JsonResponse
    {
        try {
            $package_id = $request->input('package_id');
            $user = $request->user();

//            verify pin
            $pin = $request->input('pin');

            $verifyPin = GeneralHelper::verifyUserPin($pin, $user->id);
            if ($verifyPin['status'] == 'failed') return response()->json(['message' => $verifyPin['message']], 400);
            $package = Package::find($package_id);

            $amount = $package['amount'];

            $kernel = new \App\Domain\ServicePurchase\Kernel();
            $processes = $kernel->process;

            foreach ($processes as $process) {
                $thisProcess = new $process($user, $amount, false, ['membership_transfer_limit_check']);
                $process = $thisProcess->process();
                if (!$process) return response()->json(['message' => $thisProcess->errorMessage()], 400);
            }

            //        create a record of transaction
            $data = [
                'uid' => $user['id'],
                'amount' => $amount,
                'biller_number' => null,
                'service_id' => $package_id,
                'transactionable_type' => 'App\Models\Package',
                'transaction_type' => 'service'
            ];

            $transaction = Transaction::createNewTransaction($data);

//        calculate commission
            $user_level = $request->user()['current_level'];

            $resolveServices = $package;

            $userCommission = GeneralHelper::sortPurchaseCommission($user_level, $resolveServices);

            $percentage = $userCommission / 100;

            $calculateAmountCommission = $amount * $percentage;

            $charge_amount = $amount - $calculateAmountCommission;

//       debit user wallet
            $payment = new PaymentHelpers();
            $payment->topUserWallet($user['id'], $charge_amount, $transaction);

            $transaction->charge_amount = $charge_amount;
            $transaction->save();

            GeneralHelper::logTransactionFlow($transaction, [
                'description' => 'Wallet debited',
                'time' => Carbon::now()
            ]);

//      process purchase
            event(new ServiceEvents($transaction, 'education', $resolveServices));

            return response()->json(
                [
                    'message' => 'Service purchase processing. ',
                    'data' => [
                        'transaction' => $transaction
                    ]
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred processing purchase.',
                'short_description' => $e->getMessage()
            ]);
        }

    }

    public static function purchaseAirtimeToCash($request): \Illuminate\Http\JsonResponse
    {

        $sub_service_id = $request->input('sub_service_id');
        $amount = $request->input('amount');
        $biller_number = $request->input('biller_number');

//            verify pin
        $pin = $request->input('pin');

        $user = $request->user();

        $verifyPin = GeneralHelper::verifyUserPin($pin, $user->id);
        if ($verifyPin['status'] == 'failed') return response()->json(['message' => $verifyPin['message']], 400);

        $kernel = new \App\Domain\ServicePurchase\Kernel();
        $processes = $kernel->process;

        foreach ($processes as $process) {
            $thisProcess = new $process($user, $amount, false, ['membership_transfer_limit_check']);
            $process = $thisProcess->process();
            if (!$process) return response()->json(['message' => $thisProcess->errorMessage()], 400);
        }

        $sub_service = SubService::where('id', $sub_service_id)->first();

        if (!$sub_service) return response()->json(['message' => 'Invalid services provided'], 400);

//        create a record of transaction
        $data = [
            'uid' => $user['id'],
            'amount' => $amount,
            'biller_number' => $biller_number,
            'service_id' => $sub_service_id,
            'transactionable_type' => 'App\Models\SubService',
            'transaction_type' => 'service'
        ];
        $transaction = Transaction::createNewTransaction($data);
        $transaction->airtime_to_cash_type = $request->input('type');
        $transaction->save();

//        calculate commission
        $user_level = $request->user()['current_level'];

        $resolveServices = $sub_service;

        $userCommission = GeneralHelper::sortPurchaseCommission($user_level, $resolveServices);

        $percentage = $userCommission / 100;

        $calculateAmountCommission = $amount * $percentage;

        $charge_amount = $amount - $calculateAmountCommission;

        $transaction->charge_amount = $charge_amount;
        $transaction->save();

        GeneralHelper::logTransactionFlow($transaction, [
            'description' => 'Wallet commission calculated',
            'time' => Carbon::now()
        ]);

//      process purchase
        event(new ServiceEvents($transaction, 'airtime_to_cash', $resolveServices));

        return response()->json(
            [
                'message' => 'Service purchase processing. ',
                'data' => [
                    'transaction' => $transaction
                ]
            ]
        );

    }

    public static function purchaseEPin($request): \Illuminate\Http\JsonResponse
    {
        try {

            $sub_service_id = $request->input('sub_service_id');
            $user = $request->user();
            $amount = $request->input('amount');
            $phone_number = $request->input('phone_number');

//            verify pin
            $pin = $request->input('pin');
            $verifyPin = GeneralHelper::verifyUserPin($pin, $user->id);
            if ($verifyPin['status'] == 'failed') return response()->json(['message' => $verifyPin['message']], 400);


            $kernel = new \App\Domain\ServicePurchase\Kernel();
            $processes = $kernel->process;

            foreach ($processes as $process) {
                $thisProcess = new $process($user, $amount, false, ['membership_transfer_limit_check']);
                $process = $thisProcess->process();
                if (!$process) return response()->json(['message' => $thisProcess->errorMessage()], 400);
            }

            //        create a record of transaction
            $data = [
                'uid' => $user['id'],
                'amount' => $amount,
                'biller_number' => $phone_number,
                'service_id' => $sub_service_id,
                'transactionable_type' => 'App\Models\SubService',
                'transaction_type' => 'service'
            ];

            $sub_services = SubService::find($sub_service_id);

            if (!$sub_services) return response()->json(['message' => 'invalid service selected.']);

            $transaction = Transaction::createNewTransaction($data);

//        calculate commission
            $user_level = $request->user()['current_level'];

            $resolveServices = $sub_services;

            $userCommission = GeneralHelper::sortPurchaseCommission($user_level, $resolveServices);

            $percentage = $userCommission / 100;

            $calculateAmountCommission = $amount * $percentage;

            $charge_amount = $amount - $calculateAmountCommission;

//       debit user wallet
            $payment = new PaymentHelpers();
            $payment->topUserWallet($user['id'], $charge_amount, $transaction);

            $transaction->charge_amount = $charge_amount;
            $transaction->save();

            GeneralHelper::logTransactionFlow($transaction, [
                'description' => 'Wallet debited',
                'time' => Carbon::now()
            ]);

//      process purchase
            event(new ServiceEvents($transaction, 'epin', $resolveServices));

            return response()->json(
                [
                    'message' => 'Service purchase processing. ',
                    'data' => [
                        'transaction' => $transaction
                    ]
                ]
            );

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred processing purchase.',
                'short_description' => $e->getMessage() . $e->getLine() . $e->getFile()
            ]);
        }
    }

    public static function transaction($request): \Illuminate\Http\JsonResponse
    {
        try {
            $start_date = $request->input('start_date');

            $end_date = $request->input('end_date');

            $reference = $request->input('reference');

            $value = $request->input('value');

            $status = $request->input('status');

            $uid = $request->user()['id'];

            $transaction = Transaction::with('transactionable')
                ->where('uid', $uid)
                ->when($status, function ($query) use ($status) {
                    return $query->where('status', $status);
                })
                ->when($start_date, function ($query) use ($start_date, $end_date) {
                    return $query->whereBetween('created_at', [$start_date, $end_date]);
                })
                ->when($value, function ($query) use ($value, $reference) {
                    return $query->where($reference, '%' . $value . '%');
                })
                ->orderBy('created_at', 'desc')->paginate(100);

            return response()->json(
                [
                    'transaction' => $transaction,
                ],
                200
            );
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred fetching transactions'], 400);
        }
    }

    public static function transactionDetails($request): \Illuminate\Http\JsonResponse
    {
        $tid = $request->input('tid');

        $transaction_details = Transaction::with(['user', 'flow', 'transactionable'])->where('reference', $tid)->first();

        return response()->json(
            [
                'message' => 'Transaction details',
                'data' => [
                    'transaction' => $transaction_details
                ]
            ]
        );
    }

    public static function addBankDetails($request): \Illuminate\Http\JsonResponse
    {
        $uid = $request->user()['id'];
        $bank_name = $request->input('bank_name');
        $account_number = $request->input('account_number');
        $account_name = $request->input('account_name');

        $bank_details = new BankAccounts();
        $bank_details->uid = $uid;
        $bank_details->bank_name = $bank_name;
        $bank_details->account_number = $account_number;
        $bank_details->account_name = $account_name;


        $bank_details->save();

        return response()->json(['message' => 'Bank Details Added', 'data' => $bank_details], 200);
    }


    public static function transferFunds($request)
    {
        $user = $request->user();
        $uid = $request->user()->id;
        $bank_name = $request->input('bank_name');
        $account_number = $request->input('account_number');
        $narration = $request->input('narration');
        $currency = "NGN";
        $reference = 'JO-' . Str::random(10);
        $debit_currency = "NGN";
        $amount = $request->input('amount');
        $desc = 'Wallet to Bank Transfer';
        $type = 'dr';

        $payment = new PaymentHelpers();

        $check_wallet = $payment->checkWalletBalance($user, $amount);

        if (!$check_wallet) return response()->json(['message' => 'Insufficient balance.'], 400);
        else
            $data = ['user' => $user,
                'uid' => $uid,
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'narration' => $narration,
                'currency' => $currency,
                'reference' => $reference,
                'debit_currency' => $debit_currency,
                'amount' => $amount,
                'desc' => $desc
            ];
        $wallet = $payment->updateWalletBalance($amount, $uid, $type = 'dr');
        TransferFunds::dispatch($data);//->delay(now()->addMinutes(1));
        return response()->json([
            'message' => 'Logged Successfully',
            // 'data' => $response
        ], 200);
    }


    public static function dashboard($request): \Illuminate\Http\JsonResponse
    {

        $user = $request->user();

        $start_date = Carbon::now()->startOfDay();
        $end_date = Carbon::now()->endOfDay();

        $sales = [];

        $sales['today_transaction'] = Transaction::where(['uid' => $user->id])->whereBetween('created_at', [$start_date, $end_date])->where('status', 'completed')->sum('charge_amount');

        $sales['total_purchase'] = Transaction::where(['uid' => $user->id, 'channel' => 'system'])->whereBetween('created_at', [$start_date, $end_date])->where('status', 'completed')->sum('charge_amount');

        $sales['api_transactions'] = Transaction::where(['uid' => $user->id, 'channel' => 'api'])->whereBetween('created_at', [$start_date, $end_date])->where('status', 'completed')->sum('charge_amount');

        $transactions = Transaction::where('uid', $user->id)->orderBy('created_at', 'desc')->take(50)->get();

        $current_balance = Wallet::where('uid', $user->id)->first();

        return response()->json([
            'purchase' => $sales,
            'transactions' => $transactions,
            'wallet' => $current_balance,
            'services' => Service::all()
        ]);
    }

    public static function addBanks()
    {

        try {
            $client = new Client();
            $res = $client->request('get', 'https://api.flutterwave.com/v3/banks/NG', [
                'headers' => [
                    'Authorization' => 'Bearer ' . 'FLWSECK_TEST-3f4ad8a3c27e4a70a3283f51c4c2c624-X'],
            ]);

            $response = json_decode($res->getBody(), true);
            $respp = $response['data'];
            foreach ($respp as $res) {
                $name = $res['name'];
                $code = $res['code'];
                $banks = new Bank();
                $banks->name = $name;
                $banks->code = $code;
                $banks->save();
            }
            return response()->json(['message' => 'log successfully'], 200);
            // return $response;
        } catch (\exception $e) {
            return response()->json([
                'message' => 'An error occurred accessing your account',
                'short_description' => $e->getMessage(),
            ], 400);
        }

    }

    public static function getBanks()
    {

        $banks = bank::all();

        return response()->json([
            'data' => $banks
        ], 200);

    }

    public static function verifyBankAccount($request)
    {
        $account_no = $request->account_number;
        $bank_name = $request->account_bank;

        $verify = PaymentHelpers::verifyAccount($account_no, $bank_name);
        // if($verify['status'] == 'failed') return response()->json(['message' => $verify['message']], 400);
        return $verify;
    }

}

