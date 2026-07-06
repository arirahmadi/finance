<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    /**
     * Display a listing of the Chart of Accounts.
     */
    public function index(): JsonResponse
    {
        $accounts = Account::orderBy('code')->get();

        return response()->json([
            'data' => $accounts,
        ]);
    }
}
