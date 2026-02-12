<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "═══════════════════════════════════════════════════════════════\n";
echo "        SEEDER ANALYSIS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Get all seeder files
$seederPath = database_path('seeders');
$seederFiles = glob($seederPath . '/*.php');

foreach ($seederFiles as $file) {
    $fileName = basename($file);
    echo "📄 Seeder: {$fileName}\n";
    echo str_repeat("─", 60) . "\n";
    
    // Read file content
    $content = file_get_contents($file);
    
    // Find model usage
    preg_match_all('/([A-Z][a-zA-Z]+)::(create|firstOrCreate|updateOrCreate|insert)/', $content, $modelMatches);
    if (!empty($modelMatches[1])) {
        echo "   Models used:\n";
        foreach (array_unique($modelMatches[1]) as $model) {
            echo "      - {$model}\n";
        }
    }
    
    // Find relationship calls
    preg_match_all('/\$[a-zA-Z]+->([a-zA-Z]+)\(\)/', $content, $relationMatches);
    if (!empty($relationMatches[1])) {
        echo "   Relationship calls:\n";
        $relations = array_unique($relationMatches[1]);
        foreach ($relations as $relation) {
            // Filter out obvious non-relationships
            if (!in_array($relation, ['create', 'update', 'save', 'delete', 'get', 'first', 'count', 'where'])) {
                echo "      - {$relation}()\n";
            }
        }
    }
    
    // Find with() calls
    preg_match_all('/->with\([\'"]([a-zA-Z]+)[\'"]\)/', $content, $withMatches);
    if (!empty($withMatches[1])) {
        echo "   Eager loading:\n";
        foreach (array_unique($withMatches[1]) as $with) {
            echo "      - with('{$with}')\n";
        }
    }
    
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "Analysis complete!\n";
echo "═══════════════════════════════════════════════════════════════\n";