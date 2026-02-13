<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only add account_id if it doesn't already exist
        if (!Schema::hasColumn('payments', 'account_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('id');
                
                // Add foreign key constraint
                $table->foreign('account_id')
                    ->references('id')
                    ->on('accounts')
                    ->onDelete('cascade');
                
                // Add index for performance
                $table->index('account_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('payments', 'account_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            });
        }
    }
};