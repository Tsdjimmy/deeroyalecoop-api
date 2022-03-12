<?php

namespace App\Services;


use App\Models\Transaction;
use App\Models\User;
use App\Models\UserMembership;
use App\Models\Wallet;
use App\Models\BankAccounts;
use App\Models\FundTransfer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\WebhookLog;
use Illuminate\Http\Response;

class WebhookServices
{

    public static function webhook($request)
    {
    $body = $request->getContent("php://input");


    $signature = (isset($_SERVER['HTTP_VERIF_HASH']) ? $_SERVER['HTTP_VERIF_HASH'] : '');

    if (!$signature) {

        exit();
    }

    $local_signature = env('SECRET_HASH');

    if( $signature !== $local_signature ){

    exit();
    }

    $transferStatus = FundTransfer::where('status', 'processing')->get();
    if($transferStatus->status == 'processing')
    {
    return Response::json([
        'data' => $body
    ], 200); // Status code here

    if ($body->status == 'successful') {
        // $event = $response->event;
                FundTransfer::where('status', 'processing')
                ->update(['status' => $body->status]);

        }
    }
}

        }
?>
