#!/usr/bin/env php
<?php
/**
 * TrendRadarConsole - Database Migration Script
 * 
 * This script runs database migrations automatically.
 * Usage: php migrate.php [--dry-run] [--status]
 * 
 * Options:
 *   --dry-run  Show what migrations would be run without executing them
 *   --status   Show the current migration status
 */

// Determine the base path
$basePath = dirname(__DIR__);

// Check if config exists
$configFile = $basePath . '/config/config.php';
if (!file_exists($configFile)) {
    echo "❌ Error: Configuration file not found at {$configFile}\n";
    echo "   Please ensure the application is properly installed.\n";
    exit(1);
}

// Load configuration
$config = require $configFile;
$db = $config['db'];

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv);
$statusOnly = in_array('--status', $argv);

try {
    // Connect to database
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['database'],
        $db['charset']
    );
    
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Connected to database: {$db['database']}\n";
    
    // Ensure migrations table exists
    // Note: VARCHAR(191) is used instead of VARCHAR(255) to stay within MySQL 5.6's
    // 767-byte index limit when using utf8mb4 charset (191 * 4 = 764 bytes)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(191) NOT NULL UNIQUE COMMENT 'Migration filename',
            `batch` INT NOT NULL COMMENT 'Batch number for grouping migrations',
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Database migrations tracking'
    ");
    
    // Get list of applied migrations
    $stmt = $pdo->query("SELECT migration FROM migrations ORDER BY migration");
    $appliedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get list of migration files
    $migrationsPath = $basePath . '/sql/migrations';
    if (!is_dir($migrationsPath)) {
        echo "📁 No migrations directory found at {$migrationsPath}\n";
        exit(0);
    }
    
    $migrationFiles = glob($migrationsPath . '/*.sql');
    sort($migrationFiles);
    
    // Extract just filenames
    $allMigrations = array_map('basename', $migrationFiles);
    
    // Find pending migrations
    $pendingMigrations = array_diff($allMigrations, $appliedMigrations);
    
    // Show status
    echo "\n📊 Migration Status:\n";
    echo "   Total migrations: " . count($allMigrations) . "\n";
    echo "   Applied: " . count($appliedMigrations) . "\n";
    echo "   Pending: " . count($pendingMigrations) . "\n";
    
    if ($statusOnly) {
        if (!empty($appliedMigrations)) {
            echo "\n✅ Applied migrations:\n";
            foreach ($appliedMigrations as $m) {
                echo "   - {$m}\n";
            }
        }
        if (!empty($pendingMigrations)) {
            echo "\n⏳ Pending migrations:\n";
            foreach ($pendingMigrations as $m) {
                echo "   - {$m}\n";
            }
        }
        exit(0);
    }
    
    // Run pending migrations
    if (empty($pendingMigrations)) {
        echo "\n✨ Nothing to migrate. Database is up to date.\n";
        exit(0);
    }
    
    // Get next batch number
    $stmt = $pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations");
    $nextBatch = (int) $stmt->fetch()['next_batch'];
    
    echo "\n🚀 Running " . count($pendingMigrations) . " migration(s) (batch {$nextBatch})...\n";
    
    if ($dryRun) {
        echo "\n⚠️  DRY RUN - No changes will be made\n";
    }
    
    $migratedCount = 0;
    foreach ($pendingMigrations as $migration) {
        $migrationFile = $migrationsPath . '/' . $migration;
        
        echo "\n   📦 Migrating: {$migration}\n";
        
        if (!$dryRun) {
            // Read and execute migration SQL
            $sql = file_get_contents($migrationFile);
            
            // Skip empty files
            if (empty(trim($sql))) {
                echo "      ⚠️  Skipped (empty file)\n";
                continue;
            }
            
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Execute the migration
                // Note: Multi-statement execution requires handling
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    function($s) { return !empty($s) && !preg_match('/^--/', $s); }
                );
                
                foreach ($statements as $statement) {
                    if (!empty(trim($statement))) {
                        $pdo->exec($statement);
                    }
                }
                
                // Record the migration
                $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
                $stmt->execute([$migration, $nextBatch]);
                
                $pdo->commit();
                echo "      ✅ Migrated successfully\n";
                $migratedCount++;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo "      ❌ Failed: " . $e->getMessage() . "\n";
                exit(1);
            }
        } else {
            echo "      📋 Would execute: " . substr(file_get_contents($migrationFile), 0, 100) . "...\n";
            $migratedCount++;
        }
    }
    
    echo "\n🎉 Migration complete! {$migratedCount} migration(s) " . ($dryRun ? "would be " : "") . "executed.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
