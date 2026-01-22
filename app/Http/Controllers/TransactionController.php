<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transaction;
use App\Models\Student;
use App\Models\StudentPaymentTerm;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    /**
     * ✅ Enhanced index with filtering and grouping
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get role value
        $roleValue = is_object($user->role) ? $user->role->value : $user->role;
        $adminRoles = ['admin', 'accounting'];
        
        // Base query
        if (in_array($roleValue, $adminRoles)) {
            $query = Transaction::with(['student', 'fee']);
        } else {
            $student = Student::where('user_id', $user->id)->first();
            
            if (!$student || !$student->account_id) {
                return back()->withErrors([
                    'error' => 'Student profile not found.'
                ]);
            }

            $query = Transaction::byAccountId($student->account_id)->with('fee');
        }

        // ✅ Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhereHas('fee', function($fq) use ($search) {
                      $fq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('kind')) {
            $query->where('kind', $request->kind);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // ✅ Get transactions
        $transactions = $query->orderByDesc('created_at')->get();

        // ✅ Group by term (Year + Semester)
        $transactionsByTerm = $transactions->groupBy(function($txn) {
            return ($txn->year ?? 'Unknown') . ' ' . ($txn->semester ?? 'Unknown');
        });

        // ✅ Calculate summary stats
        $totalCharges = $transactions->where('kind', 'charge')->sum('amount');
        $totalPayments = $transactions->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $pendingCharges = $transactions->where('kind', 'charge')->where('status', 'pending')->sum('amount');

        // ✅ Get filter options
        $years = Transaction::distinct()->pluck('year')->filter()->sort()->values();
        $semesters = Transaction::distinct()->pluck('semester')->filter()->unique()->values();
        $types = Transaction::distinct()->pluck('type')->filter()->unique()->values();

        return Inertia::render('Transactions/Index', [
            'transactionsByTerm' => $transactionsByTerm,
            'transactions' => $transactions,
            'filters' => $request->only([
                'search', 'kind', 'status', 'type', 'year', 'semester', 'date_from', 'date_to'
            ]),
            'filterOptions' => [
                'years' => $years,
                'semesters' => $semesters,
                'types' => $types,
            ],
            'stats' => [
                'total_charges' => (float) $totalCharges,
                'total_payments' => (float) $totalPayments,
                'pending_charges' => (float) $pendingCharges,
                'net_balance' => (float) ($totalCharges - $totalPayments),
                'transaction_count' => $transactions->count(),
            ],
            'account' => $user->account,
            'currentTerm' => $this->getCurrentTerm(),
        ]);
    }

    /**
     * ✅ Show single transaction details
     */
    public function show(Transaction $transaction)
    {
        $transaction->load(['student', 'fee']);

        // Authorization check
        $user = request()->user();
        $roleValue = is_object($user->role) ? $user->role->value : $user->role;
        
        if (!in_array($roleValue, ['admin', 'accounting'])) {
            $student = Student::where('user_id', $user->id)->first();
            
            if (!$student || $transaction->account_id !== $student->account_id) {
                abort(403, 'Unauthorized access to this transaction.');
            }
        }

        return Inertia::render('Transactions/Show', [
            'transaction' => [
                'id' => $transaction->id,
                'account_id' => $transaction->account_id,
                'reference' => $transaction->reference,
                'kind' => $transaction->kind,
                'type' => $transaction->type,
                'year' => $transaction->year,
                'semester' => $transaction->semester,
                'amount' => (float) $transaction->amount,
                'status' => $transaction->status,
                'payment_channel' => $transaction->payment_channel,
                'paid_at' => $transaction->paid_at?->toISOString(),
                'created_at' => $transaction->created_at->toISOString(),
                'meta' => $transaction->meta,
                'student' => $transaction->student ? [
                    'id' => $transaction->student->id,
                    'account_id' => $transaction->student->account_id,
                    'name' => $transaction->student->full_name,
                    'student_id' => $transaction->student->student_id,
                ] : null,
                'fee' => $transaction->fee ? [
                    'id' => $transaction->fee->id,
                    'name' => $transaction->fee->name,
                    'category' => $transaction->fee->category,
                ] : null,
            ],
        ]);
    }

    /**
     * ✅ Pay Now - Student payment submission
     */
    public function payNow(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        if (!$student->account_id) {
            return back()->withErrors([
                'error' => 'Account ID not found. Please contact administration.'
            ]);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'paid_at' => 'nullable|date',
            'reference_number' => 'nullable|string',
            'description' => 'nullable|string',
            'term_id' => 'nullable|exists:student_payment_terms,id',
        ]);

        DB::beginTransaction();
        try {
            // Create payment transaction
            $transaction = Transaction::create([
                'account_id' => $student->account_id,
                'user_id' => $user->id,
                'reference' => 'PAY-' . Str::upper(Str::random(8)),
                'kind' => 'payment',
                'type' => 'Payment',
                'amount' => $validated['amount'],
                'status' => 'paid',
                'payment_channel' => $validated['payment_method'],
                'paid_at' => $validated['paid_at'] ?? now(),
                'year' => now()->year,
                'semester' => $this->getCurrentSemester(),
                'meta' => [
                    'reference_number' => $validated['reference_number'] ?? null,
                    'description' => $validated['description'] ?? 'Student payment',
                    'term_id' => $validated['term_id'] ?? null,
                ],
            ]);

            // Update payment terms
            if (isset($validated['term_id'])) {
                $term = StudentPaymentTerm::findOrFail($validated['term_id']);
                
                if ($term->account_id !== $student->account_id) {
                    throw new \Exception('Payment term does not belong to this student.');
                }
                
                $term->paid_amount += $validated['amount'];
                
                if ($term->paid_amount >= $term->amount) {
                    $term->status = 'paid';
                } elseif ($term->paid_amount > 0) {
                    $term->status = 'partial';
                }
                
                $term->save();
            } else {
                // Apply to earliest unpaid terms
                $remainingAmount = $validated['amount'];
                $terms = StudentPaymentTerm::byAccountId($student->account_id)
                    ->unpaid()
                    ->orderBy('term_order')
                    ->get();

                foreach ($terms as $term) {
                    if ($remainingAmount <= 0) break;

                    $termBalance = $term->amount - $term->paid_amount;
                    $paymentForThisTerm = min($remainingAmount, $termBalance);

                    $term->paid_amount += $paymentForThisTerm;
                    
                    if ($term->paid_amount >= $term->amount) {
                        $term->status = 'paid';
                    } else {
                        $term->status = 'partial';
                    }
                    
                    $term->save();
                    $remainingAmount -= $paymentForThisTerm;
                }
            }

            // Recalculate account balance
            \App\Services\AccountService::recalculate($user);

            DB::commit();

            Log::info('Payment recorded successfully', [
                'account_id' => $student->account_id,
                'amount' => $validated['amount'],
                'transaction_id' => $transaction->id,
            ]);

            return redirect()
                ->route('transactions.index')
                ->with('success', 'Payment recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Payment recording failed', [
                'account_id' => $student->account_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to record payment: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * ✅ Download transactions PDF
     */
    public function download(Request $request)
    {
        $user = $request->user();
        $roleValue = is_object($user->role) ? $user->role->value : $user->role;

        if (in_array($roleValue, ['admin', 'accounting'])) {
            $transactions = Transaction::with(['student', 'fee'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $student = Student::where('user_id', $user->id)->first();
            
            if (!$student) {
                return back()->withErrors(['error' => 'Student profile not found.']);
            }

            $transactions = Transaction::byAccountId($student->account_id)
                ->with('fee')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $pdf = Pdf::loadView('pdf.transactions', [
            'transactions' => $transactions,
            'user' => $user,
        ]);

        return $pdf->download('transactions-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Helper: Get current term
     */
    private function getCurrentTerm(): string
    {
        $year = now()->year;
        $month = now()->month;

        $semester = match(true) {
            $month >= 6 && $month <= 10 => '1st Sem',
            $month >= 11 || $month <= 3 => '2nd Sem',
            default => 'Summer',
        };

        return "{$year} {$semester}";
    }

    /**
     * Helper: Get current semester
     */
    private function getCurrentSemester(): string
    {
        $month = now()->month;

        return match(true) {
            $month >= 6 && $month <= 10 => '1st Sem',
            $month >= 11 || $month <= 3 => '2nd Sem',
            default => 'Summer',
        };
    }
}