<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Only add if column doesn't exist
            if (!Schema::hasColumn('students', 'account_id')) {
                $table->string('account_id', 50)
                    ->after('id')
                    ->unique()
                    ->nullable();
                
                $table->index('account_id');
            }
        });

        // Generate account_id for existing students
        $this->backfillAccountIds();

        // ❌ REMOVED - DO NOT MAKE IT NOT NULL HERE
        // The enforce_account_id_constraints migration handles this
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'account_id')) {
                $table->dropIndex(['account_id']);
                $table->dropColumn('account_id');
            }
        });
    }

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
    }

    protected function generateUniqueAccountId(string $date, int $counter): string
    {
        $prefix = "ACC-{$date}-";
        $number = str_pad($counter, 4, '0', STR_PAD_LEFT);
        $accountId = "{$prefix}{$number}";

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