<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Notification;
use App\Models\Transaction;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Ensure the student has an account
        $account = $user->account()
            ->with('transactions')
            ->first();

        if (!$account) {
            $account = $user->account()->create(['balance' => 0]);
        }

        // Calculate financial stats
        $totalCharges = $user->transactions()
            ->where('kind', 'charge')
            ->sum('amount');

        $totalPayments = $user->transactions()
            ->where('kind', 'payment')
            ->where('status', 'paid')
            ->sum('amount');

        $remainingBalance = (float) abs($account->balance);

        $pendingChargesCount = $user->transactions()
            ->where('kind', 'charge')
            ->where('status', 'pending')
            ->count();

        // Get notifications for student OR all
        $notifications = Notification::where(function ($q) use ($user) {
                $q->where('target_role', $user->role)
                  ->orWhere('target_role', 'all');
            })
            ->orderByDesc('start_date')
            ->take(5)
            ->get();

        // Recent transactions (latest 5)
        $recentTransactions = $user->transactions()
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn($txn) => [
                'id' => $txn->id,
                'reference' => $txn->reference,
                'type' => $txn->type ?? 'General',
                'amount' => $txn->amount,
                'status' => $txn->status,
                'created_at' => $txn->created_at,
            ]);

        return Inertia::render('Student/Dashboard', [
            'account' => $account,
            'notifications' => $notifications,
            'recentTransactions' => $recentTransactions,
            'stats' => [
                'total_fees' => (float) $totalCharges,
                'total_paid' => (float) $totalPayments,
                'remaining_balance' => $remainingBalance,
                'pending_charges_count' => $pendingChargesCount,
            ],
        ]);
    }
}