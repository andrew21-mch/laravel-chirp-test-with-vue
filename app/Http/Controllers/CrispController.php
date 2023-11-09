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

        resolve(CrispService::class)->handleWebhookEvents( $payload );
        return response()->json(['success' => true]);
    }
}
