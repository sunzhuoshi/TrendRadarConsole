<?php
/**
 * TrendRadarConsole - Database Connection Class
 */

class Database
{
    private static $instance = null;
    private $pdo;
    
    // Whitelist of allowed table names
    private static $allowedTables = [
        'users',
        'configurations',
        'platforms',
        'keywords',
        'webhooks',
        'settings',
        'operation_logs'
    ];
    
    private function __construct()
    {
        $configFile = __DIR__ . '/../config/config.php';
        if (!file_exists($configFile)) {
            throw new Exception('Configuration file not found. Please copy config/config.example.php to config/config.php');
        }
        
        $config = require $configFile;
        $db = $config['db'];
        
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['database'],
            $db['charset']
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $this->pdo = new PDO($dsn, $db['username'], $db['password'], $options);
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection()
    {
        return $this->pdo;
    }
    
    /**
     * Validate table name against whitelist
     */
    private function validateTableName($table)
    {
        if (!in_array($table, self::$allowedTables, true)) {
            throw new InvalidArgumentException('Invalid table name: ' . $table);
        }
        return $table;
    }
    
    /**
     * Validate column names (alphanumeric and underscore only)
     */
    private function validateColumnName($column)
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('Invalid column name: ' . $column);
        }
        return $column;
    }
    
    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchOne($sql, $params = [])
    {
        return $this->query($sql, $params)->fetch();
    }
    
    public function insert($table, $data)
    {
        $table = $this->validateTableName($table);
        
        // Validate column names
        $columns = [];
        foreach (array_keys($data) as $column) {
            $columns[] = $this->validateColumnName($column);
        }
        
        $columnStr = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columnStr}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = [])
    {
        $table = $this->validateTableName($table);
        
        // Validate column names and build SET clause
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = $this->validateColumnName($column) . ' = ?';
        }
        $set = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        
        $params = array_merge(array_values($data), $whereParams);
        return $this->query($sql, $params)->rowCount();
    }
    
    public function delete($table, $where, $params = [])
    {
        $table = $this->validateTableName($table);
        
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params)->rowCount();
    }
}
