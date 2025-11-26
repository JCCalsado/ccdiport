<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\Transaction;

class PayablesSeeder extends Seeder
{
    public function run(): void
    {
        // This seeder has been disabled to prevent automatic charge generation
        // Charges should only be created through:
        // 1. OBE Curriculum Assessment (StudentFeeController)
        // 2. Manual fee assignment by admin/accounting staff
        // 3. Legacy subject enrollment system
        
        $this->command->info('⚠️  PayablesSeeder is disabled. Use StudentFeeController to create assessments.');
        $this->command->info('   For OBE students: Generate assessment with curriculum');
        $this->command->info('   For legacy students: Manually assign fees through admin panel');
    }
}