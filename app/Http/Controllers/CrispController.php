<?php

namespace App\Http\Controllers;

use App\Services\CrispService;
use Illuminate\Http\Request;

class CrispController extends Controller
{
    protected $crispService;

    public function __construct(CrispService $crispService)
    {
        $this->crispService = $crispService;
    }

    public function __invoke(Request $request)
    {
        $payload = $request->all();

        $this->crispService->handleWebhookEvents($payload);

        return response()->json(['success' => true]);
    }
}
