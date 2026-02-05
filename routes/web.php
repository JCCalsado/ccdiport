<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentFeeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AccountingDashboardController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\StudentPaymentController;
use App\Http\Controllers\PaymentGateways\GCashPaymentController;
use App\Http\Controllers\PaymentGateways\MayaPaymentController;
use App\Http\Controllers\PaymentGateways\BankTransferController;
use App\Models\OfficialReceipt;

// ============================================
// PUBLIC ROUTES
// ============================================

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// ============================================
// AUTHENTICATED ROUTES - ROLE-AGNOSTIC
// ============================================

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// ============================================
// STUDENT-SPECIFIC ROUTES
// ============================================

Route::middleware(['auth', 'verified', 'role:student'])->prefix('student')->group(function () {
    Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
    Route::get('/account', [StudentAccountController::class, 'index'])->name('student.account');
    Route::get('/profile', [StudentController::class, 'profile'])->name('student.profile');
    Route::get('/payment', [PaymentController::class, 'create'])->name('payment.create');
});

// ============================================
// STUDENT ARCHIVE ROUTES (Admin/Accounting)
// ✅ ALL ROUTES USE {accountId} PARAMETER
// ============================================

Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    // ✅ Student Archive - List all students
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    
    // ✅ Student Archive - View student profile by account_id
    Route::get('students/{accountId}', [StudentController::class, 'show'])->name('students.show');
    
    // ✅ Student Archive - Edit student by account_id
    Route::get('students/{accountId}/edit', [StudentController::class, 'edit'])->name('students.edit');
    
    // ✅ Student Archive - Update student by account_id
    Route::put('students/{accountId}', [StudentController::class, 'update'])->name('students.update');
    
    Route::delete('students/{accountId}', [StudentController::class, 'destroy'])->name('students.destroy');
    
    Route::post('students/{accountId}/payments', [StudentController::class, 'storePayment'])
        ->name('students.payments.store');
});

// ============================================
// STUDENT FEE MANAGEMENT ROUTES
// ✅ PRIMARY STUDENT CREATION & ASSESSMENT FLOW
// ✅ ALL ROUTES USE account_id
// ============================================

Route::middleware(['auth', 'verified', 'role:admin,accounting'])->prefix('student-fees')->group(function () {
    
    // ──────────────────────────────────────────
    // SECTION 1: LIST & CREATE ROUTES
    // ──────────────────────────────────────────
    
    // List all students for fee management
    Route::get('/', [StudentFeeController::class, 'index'])
        ->name('student-fees.index');

    // ✅ CREATE NEW STUDENT (Primary Flow)
    Route::get('/create-student', [StudentFeeController::class, 'createStudent'])
        ->name('student-fees.create-student');
    
    Route::post('/store-student', [StudentFeeController::class, 'storeStudent'])
        ->name('student-fees.store-student');

    // ✅ CREATE ASSESSMENT FOR EXISTING STUDENT
    Route::get('/create', [StudentFeeController::class, 'create'])
        ->name('student-fees.create');
    
    Route::post('/', [StudentFeeController::class, 'store'])
        ->name('student-fees.store');

    // ──────────────────────────────────────────
    // SECTION 2: STUDENT-SPECIFIC ROUTES
    // ⚠️  Wildcard {accountId} - MUST come LAST
    // ──────────────────────────────────────────
    
    // Export PDF by account_id (BEFORE show to avoid conflict)
    Route::get('/{accountId}/export-pdf', [StudentFeeController::class, 'exportPdf'])
        ->name('student-fees.export-pdf')
        ->where('accountId', 'ACC-\d{8}-\d{4}'); // Validate account_id format

    // Edit assessment by account_id
    Route::get('/{accountId}/edit', [StudentFeeController::class, 'edit'])
        ->name('student-fees.edit')
        ->where('accountId', 'ACC-\d{8}-\d{4}');

    // Update assessment by account_id
    Route::put('/{accountId}', [StudentFeeController::class, 'update'])
        ->name('student-fees.update')
        ->where('accountId', 'ACC-\d{8}-\d{4}');

    // Record payment by account_id
    Route::post('/{accountId}/payments', [StudentFeeController::class, 'storePayment'])
        ->name('student-fees.payments.store')
        ->where('accountId', 'ACC-\d{8}-\d{4}');

    // Show assessment by account_id (LAST)
    Route::get('/{accountId}', [StudentFeeController::class, 'show'])
        ->name('student-fees.show')
        ->where('accountId', 'ACC-\d{8}-\d{4}');
});

// ============================================
// STUDENT PAYMENT ROUTES
// ✅ ADDED RATE LIMITING
// ============================================

Route::middleware(['auth', 'verified', 'role:student'])->prefix('student')->group(function () {
    Route::get('/payment/create', [StudentPaymentController::class, 'create'])
        ->name('student.payment.create');
    
    Route::post('/payment/process', [StudentPaymentController::class, 'process'])
        ->middleware('throttle:10,1') // ✅ 10 attempts per minute
        ->name('student.payment.process');
    
    Route::get('/payment/success/{transaction}', [StudentPaymentController::class, 'success'])
        ->name('student.payment.success');
    
    Route::get('/payment/failed/{transaction}', [StudentPaymentController::class, 'failed'])
        ->name('student.payment.failed');
    
    Route::get('/payment/receipt/{transaction}', [StudentPaymentController::class, 'receipt'])
        ->name('student.payment.receipt');
});

// ============================================
// TRANSACTIONS (ALL USERS)
// ============================================

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/download', [TransactionController::class, 'download'])->name('transactions.download');
    Route::post('/account/pay-now', [TransactionController::class, 'payNow'])
        ->middleware('throttle:10,1') // ✅ Rate limit
        ->name('account.pay-now');
});

// ============================================
// TRANSACTIONS (ADMIN/ACCOUNTING)
// ============================================

Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
});

// ============================================
// ADMIN DASHBOARD
// ============================================

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
});

// ============================================
// ACCOUNTING DASHBOARD
// ============================================

Route::middleware(['auth', 'verified', 'role:accounting,admin'])->prefix('accounting')->group(function () {
    Route::get('/dashboard', [AccountingDashboardController::class, 'index'])->name('accounting.dashboard');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('accounting.transactions.index');
});

// ============================================
// FEE MANAGEMENT
// ============================================

Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    Route::resource('fees', FeeController::class);
    Route::post('fees/{fee}/toggle-status', [FeeController::class, 'toggleStatus'])->name('fees.toggleStatus');
    Route::post('fees/assign-to-students', [FeeController::class, 'assignToStudents'])->name('fees.assignToStudents');
});

// ============================================
// USER MANAGEMENT (ADMIN ONLY)
// ============================================

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::resource('users', UserController::class);
});

// ============================================
// NOTIFICATIONS
// ============================================

Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications', [NotificationController::class, 'store'])->name('notifications.store');
});

// ============================================
// SETTINGS - PROFILE
// ============================================

Route::middleware('auth')->prefix('settings')->name('profile.')->group(function () {
    Route::get('profile', [\App\Http\Controllers\Settings\ProfileController::class, 'edit'])->name('edit');
    Route::patch('profile', [\App\Http\Controllers\Settings\ProfileController::class, 'update'])->name('update');
    Route::delete('profile', [\App\Http\Controllers\Settings\ProfileController::class, 'destroy'])->name('destroy');
    
    Route::post('profile-picture', [\App\Http\Controllers\Settings\ProfileController::class, 'updatePicture'])
        ->name('update-picture');
    Route::delete('profile-picture', [\App\Http\Controllers\Settings\ProfileController::class, 'removePicture'])
        ->name('remove-picture');
});

// ============================================
// SETTINGS - PASSWORD
// ============================================

Route::middleware('auth')->prefix('settings')->name('password.')->group(function () {
    Route::get('password', [\App\Http\Controllers\Settings\PasswordController::class, 'edit'])->name('edit');
    Route::put('password', [\App\Http\Controllers\Settings\PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('update');
});

// ============================================
// SETTINGS - APPEARANCE & SYSTEM
// ============================================

Route::middleware(['auth', 'verified'])->prefix('settings')->group(function () {
    Route::get('appearance', fn () => Inertia::render('settings/Appearance'))->name('appearance');

    Route::middleware('role:admin')->group(function () {
        Route::get('system', fn () => Inertia::render('settings/System'))->name('settings.system');
    });
});

// Payment Gateway Management (Admin Only)
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/payment-gateways', [App\Http\Controllers\Admin\PaymentGatewayController::class, 'index'])
        ->name('admin.payment-gateways.index');
    Route::post('/payment-gateways', [App\Http\Controllers\Admin\PaymentGatewayController::class, 'store'])
        ->name('admin.payment-gateways.store');
    Route::post('/payment-gateways/{gateway}/toggle', [App\Http\Controllers\Admin\PaymentGatewayController::class, 'toggleStatus'])
        ->name('admin.payment-gateways.toggle');
    Route::post('/payment-gateways/{gateway}/test', [App\Http\Controllers\Admin\PaymentGatewayController::class, 'test'])
        ->name('admin.payment-gateways.test');
});

Route::middleware(['auth', 'verified', 'role:student'])->prefix('payment')->group(function () {
    Route::get('/select-method', [PaymentController::class, 'selectMethod'])
        ->name('payment.select-method');
    
    // Future routes for each gateway
    Route::get('/gcash', [GCashPaymentController::class, 'create'])
        ->name('payment.gcash.create');
    Route::get('/maya', [MayaPaymentController::class, 'create'])
        ->name('payment.maya.create');
    Route::get('/bank', [BankTransferController::class, 'create'])
        ->name('payment.bank.create');
});

// ============================================
// WEBHOOK ROUTES (NO AUTH - PUBLIC)
// ✅ ADDED RATE LIMITING & IP LOGGING
// ============================================

Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('paymongo', [WebhookController::class, 'paymongo'])
        ->middleware('throttle:100,1') // ✅ 100 requests per minute
        ->name('paymongo');
    Route::post('gcash', [WebhookController::class, 'gcash'])
        ->middleware('throttle:100,1')
        ->name('gcash');
    Route::post('maya', [WebhookController::class, 'maya'])
        ->middleware('throttle:100,1')
        ->name('maya');
    
    // Test endpoint (local only)
    Route::get('test', [WebhookController::class, 'test'])->name('test');
});

// ============================================
// INCLUDE ADDITIONAL ROUTE FILES
// ============================================

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';