<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Only add if column doesn't exist
            if (!Schema::hasColumn('students', 'account_id')) {
                $table->string('account_id', 50)
                    ->after('id')
                    ->unique()
                    ->nullable(); // Keep nullable - don't change this!
                
                $table->index('account_id');
            }
        });

        // Generate account_id for existing students
        $this->backfillAccountIds();

        // ✅ REMOVED THE PROBLEMATIC SECTION - DO NOT ADD IT BACK!
        // The enforce_account_id_constraints migration will handle making it NOT NULL later
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'account_id')) {
                $table->dropIndex(['account_id']);
                $table->dropColumn('account_id');
            }
        });
    }

    /**
     * Generate account_id for all existing students
     */
    protected function backfillAccountIds(): void
    {
        $students = DB::table('students')
            ->whereNull('account_id')
            ->orderBy('id')
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $date = now()->format('Ymd');
        $counter = $this->getNextAccountCounter($date);

        foreach ($students as $student) {
            $accountId = $this->generateUniqueAccountId($date, $counter);
            
            DB::table('students')
                ->where('id', $student->id)
                ->update(['account_id' => $accountId]);
            
            $counter++;
        }

        $this->command->info("✓ Generated account_id for {$students->count()} students");
    }

    /**
     * Generate unique account_id in format ACC-YYYYMMDD-XXXX
     */
    protected function generateUniqueAccountId(string $date, int $counter): string
    {
        $prefix = "ACC-{$date}-";
        $number = str_pad($counter, 4, '0', STR_PAD_LEFT);
        $accountId = "{$prefix}{$number}";

        // Ensure uniqueness
        $attempts = 0;
        while (DB::table('students')->where('account_id', $accountId)->exists() && $attempts < 100) {
            $counter++;
            $number = str_pad($counter, 4, '0', STR_PAD_LEFT);
            $accountId = "{$prefix}{$number}";
            $attempts++;
        }

        if ($attempts >= 100) {
            throw new \Exception("Unable to generate unique account_id after 100 attempts");
        }

        return $accountId;
    }

    /**
     * Get next available counter for today's date
     */
    protected function getNextAccountCounter(string $date): int
    {
        $prefix = "ACC-{$date}-";
        
        $lastStudent = DB::table('students')
            ->where('account_id', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(account_id, 14) AS UNSIGNED) DESC')
            ->first();

        if ($lastStudent) {
            $lastNumber = intval(substr($lastStudent->account_id, -4));
            return $lastNumber + 1;
        }

        return 1;
    }
};