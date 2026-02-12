<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════\n";
echo "        DATABASE SCHEMA ANALYSIS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Get all tables
$tables = DB::select('SHOW TABLES');
$databaseName = DB::getDatabaseName();
$tableKey = "Tables_in_{$databaseName}";

echo "📊 Found " . count($tables) . " tables\n\n";

foreach ($tables as $table) {
    $tableName = $table->$tableKey;
    
    echo "📋 Table: {$tableName}\n";
    echo str_repeat("─", 60) . "\n";
    
    // Get columns
    $columns = Schema::getColumnListing($tableName);
    echo "   Columns: " . implode(', ', $columns) . "\n";
    
    // Get foreign keys
    $foreignKeys = DB::select("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM 
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE 
            TABLE_SCHEMA = '{$databaseName}'
            AND TABLE_NAME = '{$tableName}'
            AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    if (!empty($foreignKeys)) {
        echo "   Foreign Keys:\n";
        foreach ($foreignKeys as $fk) {
            echo "      - {$fk->COLUMN_NAME} → {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n";
        }
    }
    
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";

// Model-to-table mapping check
echo "\n        MODEL TO TABLE MAPPING\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$modelPath = app_path('Models');
$modelFiles = glob($modelPath . '/*.php');

foreach ($modelFiles as $file) {
    $className = 'App\\Models\\' . basename($file, '.php');
    
    if (!class_exists($className)) continue;
    
    try {
        $instance = new $className;
        $tableName = $instance->getTable();
        $modelName = basename($file, '.php');
        
        if (Schema::hasTable($tableName)) {
            echo "✅ {$modelName} → {$tableName} (EXISTS)\n";
        } else {
            echo "❌ {$modelName} → {$tableName} (MISSING!)\n";
        }
    } catch (Exception $e) {
        echo "⚠️  {$modelName} → ERROR: {$e->getMessage()}\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "Analysis complete!\n";
echo "═══════════════════════════════════════════════════════════════\n";