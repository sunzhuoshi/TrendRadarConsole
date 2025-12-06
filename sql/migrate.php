#!/usr/bin/env php
<?php
/**
 * TrendRadarConsole - Database Migration Script
 * 
 * This script runs database migrations automatically.
 * Usage: php migrate.php [--dry-run] [--status] [--verify]
 * 
 * Options:
 *   --dry-run  Show what migrations would be run without executing them
 *   --status   Show the current migration status
 *   --verify   Verify that all migrations have been applied correctly
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
$verifyOnly = in_array('--verify', $argv);

/**
 * Verify that a migration was applied successfully
 * Returns true if verification passes, false otherwise
 */
function verifyMigration($pdo, $migrationName) {
    $verifications = [
        '001_create_operation_logs_table.sql' => function($pdo) {
            // Check if operation_logs table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'operation_logs'");
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Table operation_logs does not exist'];
            }
            
            // Verify table structure
            $stmt = $pdo->query("DESCRIBE operation_logs");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $requiredColumns = ['id', 'user_id', 'action', 'target_type', 'target_id', 'details', 'created_at'];
            
            foreach ($requiredColumns as $col) {
                if (!in_array($col, $columns)) {
                    return ['success' => false, 'message' => "Missing column: {$col}"];
                }
            }
            
            // Verify indexes exist
            $stmt = $pdo->query("SHOW INDEX FROM operation_logs");
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $indexNames = array_unique(array_column($indexes, 'Key_name'));
            
            $requiredIndexes = ['PRIMARY', 'idx_user_id', 'idx_action', 'idx_created_at'];
            foreach ($requiredIndexes as $idx) {
                if (!in_array($idx, $indexNames)) {
                    return ['success' => false, 'message' => "Missing index: {$idx}"];
                }
            }
            
            // Verify foreign key exists
            $stmt = $pdo->query("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'operation_logs' 
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ");
            $fkNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('fk_operation_logs_user', $fkNames)) {
                return ['success' => false, 'message' => 'Missing foreign key: fk_operation_logs_user'];
            }
            
            return ['success' => true, 'message' => 'All verifications passed'];
        },
        '002_add_dev_mode_to_users.sql' => function($pdo) {
            // Verify dev_mode or advanced_mode column exists in users table
            // (advanced_mode exists if migration 005 has been applied)
            $stmt = $pdo->query("DESCRIBE users");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('dev_mode', $columns) && !in_array('advanced_mode', $columns)) {
                return ['success' => false, 'message' => 'Neither dev_mode nor advanced_mode column exists in users table'];
            }
            
            return ['success' => true, 'message' => 'All verifications passed'];
        },
        '005_rename_dev_mode_to_advanced_mode.sql' => function($pdo) {
            // Verify advanced_mode column exists in users table
            $stmt = $pdo->query("DESCRIBE users");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('advanced_mode', $columns)) {
                return ['success' => false, 'message' => 'Column advanced_mode does not exist in users table'];
            }
            
            // Verify dev_mode column no longer exists
            if (in_array('dev_mode', $columns)) {
                return ['success' => false, 'message' => 'Column dev_mode still exists in users table'];
            }
            
            return ['success' => true, 'message' => 'All verifications passed'];
        }
    ];
    
    if (isset($verifications[$migrationName])) {
        return $verifications[$migrationName]($pdo);
    }
    
    // No specific verification defined, just check if recorded in migrations table
    return ['success' => true, 'message' => 'No specific verification defined'];
}

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
    
    // Verify mode - check all applied migrations
    if ($verifyOnly) {
        echo "\n🔍 Verifying applied migrations...\n";
        $allPassed = true;
        
        if (empty($appliedMigrations)) {
            echo "   ⚠️  No migrations have been applied yet.\n";
            exit(0);
        }
        
        foreach ($appliedMigrations as $migration) {
            echo "\n   📋 Verifying: {$migration}\n";
            $result = verifyMigration($pdo, $migration);
            if ($result['success']) {
                echo "      ✅ {$result['message']}\n";
            } else {
                echo "      ❌ {$result['message']}\n";
                $allPassed = false;
            }
        }
        
        echo "\n";
        if ($allPassed) {
            echo "🎉 All migrations verified successfully!\n";
            exit(0);
        } else {
            echo "❌ Some migrations failed verification.\n";
            exit(1);
        }
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
            
            try {
                // Remove SQL comments (lines starting with --)
                $sqlLines = explode("\n", $sql);
                $cleanLines = array_filter($sqlLines, function($line) {
                    $trimmed = trim($line);
                    return !empty($trimmed) && strpos($trimmed, '--') !== 0;
                });
                $cleanSql = implode("\n", $cleanLines);
                
                // Split by semicolon and filter empty statements
                $statements = array_filter(
                    array_map('trim', explode(';', $cleanSql)),
                    function($s) { return !empty($s); }
                );
                
                foreach ($statements as $statement) {
                    if (!empty(trim($statement))) {
                        $pdo->exec($statement);
                    }
                }
                
                echo "      ✅ SQL executed successfully\n";
                
                // Verify the migration BEFORE recording it
                echo "      🔍 Verifying migration...\n";
                $verifyResult = verifyMigration($pdo, $migration);
                if ($verifyResult['success']) {
                    echo "      ✅ Verification passed: {$verifyResult['message']}\n";
                    
                    // Only record the migration after verification passes
                    $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
                    $stmt->execute([$migration, $nextBatch]);
                    echo "      ✅ Migration recorded\n";
                    
                    $migratedCount++;
                } else {
                    echo "      ❌ Verification failed: {$verifyResult['message']}\n";
                    echo "      ⚠️  Migration NOT recorded. Please check and retry.\n";
                    exit(1);
                }
                
            } catch (Exception $e) {
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
