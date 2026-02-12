<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "═══════════════════════════════════════════════════════════════\n";
echo "        MODEL ANALYSIS - Finding All Relationships\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Get all model files
$modelPath = app_path('Models');
$modelFiles = glob($modelPath . '/*.php');

$allModels = [];
$relationshipIssues = [];

foreach ($modelFiles as $file) {
    $className = 'App\\Models\\' . basename($file, '.php');
    
    if (!class_exists($className)) {
        continue;
    }
    
    $reflection = new ReflectionClass($className);
    if ($reflection->isAbstract()) {
        continue;
    }
    
    echo "📦 Model: " . basename($file, '.php') . "\n";
    echo str_repeat("─", 60) . "\n";
    
    $allModels[$className] = [
        'file' => $file,
        'methods' => [],
        'fillable' => [],
        'relationships' => []
    ];
    
    // Get fillable attributes
    try {
        $instance = new $className;
        $fillable = $instance->getFillable();
        $allModels[$className]['fillable'] = $fillable;
        echo "   Fillable: " . implode(', ', $fillable ?: ['NONE']) . "\n";
    } catch (Exception $e) {
        echo "   ⚠️  Cannot instantiate: {$e->getMessage()}\n";
    }
    
    // Get all public methods (potential relationships)
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    foreach ($methods as $method) {
        if ($method->class !== $className) continue;
        if ($method->getNumberOfParameters() > 0) continue;
        
        $methodName = $method->getName();
        
        // Skip magic methods and getters
        if (strpos($methodName, '__') === 0) continue;
        if (strpos($methodName, 'get') === 0) continue;
        if (strpos($methodName, 'set') === 0) continue;
        if (strpos($methodName, 'scope') === 0) continue;
        if (in_array($methodName, ['save', 'delete', 'update', 'create', 'fill', 'push'])) continue;
        
        $allModels[$className]['methods'][] = $methodName;
        
        // Try to detect relationship type
        try {
            $docComment = $method->getDocComment();
            if ($docComment && (
                strpos($docComment, '@return') !== false && 
                (strpos($docComment, 'HasMany') !== false ||
                 strpos($docComment, 'BelongsTo') !== false ||
                 strpos($docComment, 'HasOne') !== false ||
                 strpos($docComment, 'BelongsToMany') !== false)
            )) {
                $allModels[$className]['relationships'][] = $methodName;
                echo "   🔗 Relationship: {$methodName}()\n";
            }
        } catch (Exception $e) {
            // Ignore
        }
    }
    
    echo "\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "        RELATIONSHIP VALIDATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Validate relationships by trying to call them
foreach ($allModels as $className => $data) {
    $modelName = class_basename($className);
    
    try {
        $instance = new $className;
        
        foreach ($data['methods'] as $method) {
            // Skip obvious non-relationships
            if (in_array($method, ['toArray', 'toJson', 'fresh', 'refresh'])) continue;
            
            try {
                $result = $instance->$method();
                
                // Check if it's a relationship
                if ($result instanceof Illuminate\Database\Eloquent\Relations\Relation) {
                    $relationType = class_basename(get_class($result));
                    $relatedModel = class_basename($result->getRelated());
                    echo "✅ {$modelName}::{$method}() → {$relationType} → {$relatedModel}\n";
                }
            } catch (Exception $e) {
                $relationshipIssues[] = [
                    'model' => $modelName,
                    'method' => $method,
                    'error' => $e->getMessage()
                ];
                echo "❌ {$modelName}::{$method}() → ERROR: {$e->getMessage()}\n";
            }
        }
    } catch (Exception $e) {
        echo "⚠️  Cannot analyze {$modelName}: {$e->getMessage()}\n";
    }
}

if (!empty($relationshipIssues)) {
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "        ISSUES FOUND\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    foreach ($relationshipIssues as $issue) {
        echo "🔴 {$issue['model']}::{$issue['method']}()\n";
        echo "   Error: {$issue['error']}\n\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "Analysis complete!\n";
echo "═══════════════════════════════════════════════════════════════\n";