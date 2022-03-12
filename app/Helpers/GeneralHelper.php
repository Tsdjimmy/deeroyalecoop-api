<?php


namespace App\Helpers;


use App\Events\MailEvent;
use App\Events\SMSEvent;
use App\Models\Code;
use App\Models\TransactionLogs;
use App\Models\PayFlow;
use App\Models\Pin;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GeneralHelper
{

    public static function generateCode(): string
    {
        $code = Str::random(5);

        while (Code::where('code', $code)->count() > 0) {
            $code = Str::random(5);
        }
        return $code;
    }

    public static function formatGuzzleExceptionResponse($exception)
    {
//        $response = $exception->getResponse();
        return json_encode($exception->getMessage(), true);
    }

    public static function formatGuzzleExternalExceptionResponse($exception)
    {
//        $response = $exception->getResponse();
//        return json_encode($response->getBody(), true);
    }

    public static function logError($type, $description, $user_id = null)
    {
        $log = new Log();
        $log->type = $type;
        $log->description = $description;
        if (!empty($user_id))
            $log->uid = $user_id;
        $log->save();
    }

    public static function logExceptionErrors(\Exception $e)
    {
        self::logError('failed_funding', json_encode([
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]), request()->user()['id']);
    }

    public static function saveCode($codes, $user, $type, $event = 'verify', $is_staff = false)
    {
        $code = new Code();
        $code->code = $codes;
        $code->type = $type;
        $code->event = $event;
        $code->uid = $user->id;
        $code->is_staff = $is_staff;
        $code->save();
        return $code;
    }

    public static function customMessage()
    {
        return [
            'required' => 'The :attribute field is required.',
            'unique' => "The :attribute is already in use."
        ];
    }

    public static function sendEmailVerificationCode($user)
    {
        $code = GeneralHelper::generateCode();
        $email = $user->email;
        $data = [
            'type' => 'otp_code',
            'code' => $code,
            'full_name' => $user->full_name
        ];
        event(new MailEvent($email, $data));
        GeneralHelper::saveCode($code, $user, 'email', 'verify');
    }

    public static function sendPhoneNumberVerificationCode($user)
    {
        $phone_number = $user->phone_number;
        $code = GeneralHelper::generateCode();
        $data = [
            'code' => $code,
            'message' => 'OTP verification code: ' . $code
        ];
        $code = GeneralHelper::saveCode($code, $user, 'phone', 'verify');
        event(new SMSEvent($phone_number, $data, $code));
    }

    public static function sortPurchaseCommission($user_type, $service)
    {
        return $service[$user_type . '_discount'];
    }

    public static function logTransactionFlow($transaction, $data)
    {
        $pay_flow = new PayFlow();
        $pay_flow->tid = $transaction->id;
        $pay_flow->description = $data['description'];
        $pay_flow->extra = $data['extra'] ?? null;
        $pay_flow->save();
    }

    public static function verifyUserPin($pin, $uid): array
    {
        $userPin = Pin::where('uid', $uid)->first();

        if (!$userPin)
            return ['message' => 'Invalid transaction pin provided', 'status' => 'failed'];

        if (!password_verify($pin, $userPin->pin)) return ['message' => 'Invalid transaction pin provided', 'status' => 'failed'];

        return [
            'message' => 'verified',
            'status' => 'verified'
        ];
    }

    public static function transaction($amount, $amount_after, $transaction_type)
    {
        if($transaction_type == 'cr')
        {
            $amount_after = $amount_after + $amount;
            return $amount_after;
        }

        if($transaction_type == 'dr')
        {
            $amount_after = $amount_after - $amount;
            return $amount_after;
        }
    }
    
    public static function transactionLog($user_id, $amount, $staff_id, $transaction_tag, $transaction_type)
    {
        $transaction_log = new TransactionLogs();
        $transaction_log->user_id = $user_id;
        $transaction_log->amount = $amount;
        $transaction_log->staff_id = $staff_id;
        $transaction_log->transaction_tag = $transaction_tag;
        $transaction_log->transaction_type = $transaction_type;

        $transaction_log->save();
    }
    
}
