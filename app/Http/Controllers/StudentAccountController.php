<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AssessmentDataService;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Ensure account exists
        if (!$user->account) {
            $user->account()->create(['balance' => 0]);
        }

        // ✅ USE UNIFIED DATA SERVICE
        $data = AssessmentDataService::getUnifiedAssessmentData($user);

        // Render with standardized data
        return Inertia::render('Student/AccountOverview', array_merge($data, [
            'tab' => request('tab', 'fees'),
        ]));
    }
}