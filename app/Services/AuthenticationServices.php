<?php


namespace App\services;

use App\Events\MailEvent;
use App\Events\SMSEvent;
use App\helpers\GeneralHelper;
use App\helpers\SMSHelpers;
use App\Models\Code;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthenticationServices
{
    public static function signUp($request): JsonResponse
    {
        try {
            $rules = [
                'phone_number' => 'required',
                'email' => 'required',
                'full_name' => 'required',
            ];

            $validate = Validator::make($request->input(), $rules, GeneralHelper::customMessage());

            if ($validate->failed())
                return response()->json(array('message' => $validate->errors()->first()), 400);

            $full_name = $request->input('full_name');
            $email = $request->input('email');
            $password = $request->input('password');
            $phone_number = $request->input('phone_number');

            if (User::where('email', $email)->count() > 0) return response()->json(['message' => 'Email already in use.'], 400);

            if (User::where('phone_number', $phone_number)->count() > 0) return response()->json(['message' => 'Phone number already in use'], 400);

            $user = new User();
            $user->full_name = $full_name;
            $user->email = $email;
            $user->password = bcrypt($password);
            $user->phone_number = $phone_number;
            $user->last_login = Carbon::now();

            $wallet = new Wallet();
            $wallet->uid = $user->id;
            $wallet->save();

            GeneralHelper::sendEmailVerificationCode($user);
            self::sendWelcomeMail($user);

            $token = $user->createToken('Personal Access Token', ['user'])->accessToken;

            $unique_key = Str::random(16);
            $user->last_login_ip = $unique_key;
            $user->last_login = Carbon::now();
            $user->save();

            return response()->json(
                [
                    'message' => 'Account created successfully',
                    'data' => [
                        'user' => $user,
                        'token' => $token,
                        'unique_key' => $unique_key
                    ]
                ],
                200
            );
        } catch (\Exception $exception) {
            return response()->json(
                [
                    'message' => $exception->getMessage()
                ],
                400
            );
        }
    }

    public static function sendWelcomeMail($user)
    {
        $email = $user->email;
        $data = [
            'type' => 'welcome',
            'full_name' => $user->full_name
        ];
        event(new MailEvent($email, $data));
    }

    public static function signIn($request): JsonResponse
    {
        try {
            $rules = [
                'email' => 'required',
                'password' => 'required',
            ];
            $validate = Validator::make($request->input(), $rules);
            if ($validate->failed())
                return response()->json(['message' => $validate->errors()->first()], 400);

            $email = $request->input('email');
            $password = $request->input('password');

            $user = User::where('email', $email)->first();

            if (is_null($user))
                return response()->json(['message' => 'Invalid user credentials provided'], 400);

            if (!password_verify($password, $user->password))
                return response()->json(['message' => 'Invalid user credentials provided'], 400);

            $user->last_login = Carbon::now();
            $user->save();

            $token = $user->createToken('Personal Access Token', ['user'])->accessToken;

            $unique_key = Str::random(16);
            $user->last_login_ip = $unique_key;
            $user->save();

            return response()->json(
                [
                    'message' => "Access granted",
                    'data' => [
                        'user' => $user,
                        'token' => $token,
                        'unique_key' => $unique_key
                    ]
                ]
            );

        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'An error occurred performing this action at the moment.'],
                400
            );
        }
    }

    public static function forgotPassword($request): JsonResponse
    {
        try {
            $email = $request['email'];

            $user = User::where(['email' => $email])->orWhere('phone_number', $email)->first();

            if (!$user)
                return response()->json(['message' => 'An error occurred verifying email is a verified email. Contact support for further instructions'], 400);

            $generated_code = GeneralHelper::generateCode();

            $code = GeneralHelper::saveCode($generated_code, $user, 'email', 'reset');

            $data = [
                'code' => $generated_code,
                'type' => 'password_reset',
                'full_name' => $user->full_name
            ];

            event(new MailEvent($email, $data));

            return response()->json([
                'message' => 'Reset code sent to your registered email address.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred performing this action at the moment.' . $e->getMessage()], 400);
        }
    }

    public static function resetPassword($request): JsonResponse
    {
        try {
            $code = $request->input('code');
            $password = $request->input('password');
            $confirm_password = $request->input('confirm_password');
            $email = $request->input('email');

            if ($confirm_password !== $password)
                return response()->json(['message' => 'Password does not match confirm password'], 400);

            $user = User::where('email', $email)->first();

            if (is_null($user))
                return response()->json(['message' => 'No record found for this user'], 400);

            $uid = $user->id;

            $codeRecord = Code::where(['code' => $code,'uid' => $uid, 'event' => 'reset'])->orderBy('created_at', 'desc')->first();

            if (is_null($codeRecord))
                return response()->json(['message' => 'No reset record found for this user.'], 400);


            $user = User::find($uid);
            $user->password = bcrypt($password);
            $user->save();

            Code::where('id', $codeRecord->id)->delete();

            return response()->json(['message' => 'Password reset successfully'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred performing this action at the moment.', 'short_description' => $e->getMessage()], 400);
        }
    }
}
