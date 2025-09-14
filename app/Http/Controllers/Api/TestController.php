<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestController extends Controller
{
     public function index(): JsonResponse
    {
        return response()->json(['success' => true]);
    }
}
