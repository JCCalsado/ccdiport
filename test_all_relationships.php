<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "═══════════════════════════════════════════════════════════════\n";
echo "        TESTING ALL MODEL RELATIONSHIPS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$modelsToTest = [
    'User' => ['account', 'transactions', 'student'],
    'Account' => ['user', 'transactions', 'student'],
    'Student' => ['account', 'user', 'assessments', 'paymentTerms'],
    'Transaction' => ['account', 'user'],
    'StudentAssessment' => ['student', 'paymentTerms'],
    'StudentPaymentTerm' => ['student', 'assessment'],
    'Fee' => [],
    'Payment' => [],
    'Notification' => [],
];

foreach ($modelsToTest as $modelName => $relationships) {
    $className = "App\\Models\\{$modelName}";
    
    echo "🔍 Testing: {$modelName}\n";
    echo str_repeat("─", 60) . "\n";
    
    if (!class_exists($className)) {
        echo "   ❌ Class does not exist!\n\n";
        continue;
    }
    
    try {
        $instance = new $className;
        
        // Test table exists
        $table = $instance->getTable();
        if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
            echo "   ❌ Table '{$table}' does not exist!\n\n";
            continue;
        }
        
        echo "   ✅ Model and table exist\n";
        
        // Test each relationship
        foreach ($relationships as $relationship) {
            try {
                $result = $instance->$relationship();
                if ($result instanceof Illuminate\Database\Eloquent\Relations\Relation) {
                    $type = class_basename(get_class($result));
                    $related = class_basename($result->getRelated());
                    echo "   ✅ {$relationship}() → {$type} → {$related}\n";
                } else {
                    echo "   ⚠️  {$relationship}() exists but is not a relationship\n";
                }
            } catch (Exception $e) {
                echo "   ❌ {$relationship}() → ERROR: {$e->getMessage()}\n";
            }
        }
        
        // Test fillable attributes match table columns
        $fillable = $instance->getFillable();
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
        
        $missing = array_diff($fillable, $columns);
        if (!empty($missing)) {
            echo "   ⚠️  Fillable columns not in table: " . implode(', ', $missing) . "\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Error: {$e->getMessage()}\n";
    }
    
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "Testing complete!\n";
echo "═══════════════════════════════════════════════════════════════\n";