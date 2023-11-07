<?php

namespace App\Http\Controllers;

use App\Services\CrispService;
use Illuminate\Http\Request;

class CrispController extends Controller
{
    public function __construct()
    {
        //
    }

    public function __invoke(Request $request)
    {
        $payload = $request->all();
        // Verify the authenticity of the webhook (optional but recommended)
        // You can implement your own verification logic here
        // $readable = json_encode($payload);
        // SendToTelegram::switchnFixNow("crisp submission: $readable");
        // if user email is leonelngande@gmail, proceed
        /*if ($payload['data']['user']['email'] === 'leonelngande@gmail.com') {
            resolve(CrispService::class)->handleWebhookEvents($payload);
        }*/
        resolve(CrispService::class)->handleWebhookEvents($payload);
        return response()->json(['success' => true]);
    }
}
