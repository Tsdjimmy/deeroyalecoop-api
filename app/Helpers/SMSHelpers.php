<?php


namespace App\Helpers;


use GuzzleHttp\Client;

class SMSHelpers
{

    public function sendSMS($phone_number, $message)
    {
        $phone_number = preg_replace('/^0/', '234', $phone_number);

        $data = array("api_key" => "TLkI89jKjiuZQ8CS6Vp3kRVOzmiVu6iAYWdeN6YvekyqgWq7wokuc0rqWZMWS0",
            "type" => "plain",
            "to" => $phone_number,
            "from" => "N-Alert",
            "channel" => "dnd",
            "sms" => $message
        );
        $client = new Client();
        $request = $client->request(
            'POST',
            'https://api.ng.termii.com/api/sms/send',
            [
                'json' => $data
            ]
        );
        $response = json_decode($request->getBody(), true);
    }

    public function sendOtpSms($phone_number, $message, $data, $code)
    {
        $phone_number = preg_replace('/^0/', '234', $phone_number);

        $data = array("api_key" => "TLkI89jKjiuZQ8CS6Vp3kRVOzmiVu6iAYWdeN6YvekyqgWq7wokuc0rqWZMWS0",
            "message_type" => "NUMERIC",
            "to" => $phone_number,
            "from" => "N-Alert",
            "channel" => "dnd",
            "pin_attempts" => 5,
            "pin_time_to_live" => 5,
            "pin_length" => 6,
            "pin_placeholder" => "< 1234 >",
            "message_text" => "Your pin is < 1234 >",
            "pin_type" => "NUMERIC");
        $client = new Client();
        $request = $client->request(
            'POST',
            'https://termii.com/api/sms/otp/send',
            [
                'json' => $data
            ]
        );
        $response = json_decode($request->getBody(), true);
        $code->code = $response['pinId'];
        $code->save();
    }

    public function verifySMSToken($pin_id, $pin)
    {
        $data = array(
            "api_key" => "TLkI89jKjiuZQ8CS6Vp3kRVOzmiVu6iAYWdeN6YvekyqgWq7wokuc0rqWZMWS0",
            "pin_id" => $pin_id,
            "pin" => $pin
        );
        $client = new Client();
        $request = $client->request(
            'POST',
            'https://termii.com/api/sms/otp/verify',
            [
                'json' => $data
            ]
        );
        $response = json_decode($request->getBody(), true);

        if ($response['verified']) {
            return 'success';
        }
        return 'failed';
    }

}
