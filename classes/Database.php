<?php
/**
 * Database Connection and Operations Class
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

class Database {
    private static $instance = null;
    private $connection;
    private $host;
    private $port;
    private $database;
    private $username;
    private $password;
    private $charset;
    
    /**
     * Constructor - Initialize database parameters
     */
    private function __construct() {
        $this->host = DB_HOST;
        $this->port = DB_PORT;
        $this->database = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->charset = DB_CHARSET;
        
        $this->connect();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create database connection
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}",
                PDO::ATTR_PERSISTENT => false
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            $this->logError("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        // Check if connection is still alive
        try {
            $this->connection->query('SELECT 1');
        } catch (PDOException $e) {
            $this->connect(); // Reconnect if connection is lost
        }
        
        return $this->connection;
    }
    
    /**
     * Execute SELECT query
     */
    public function select($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError("SELECT query failed: " . $e->getMessage() . " Query: " . $query);
            throw new Exception("Failed to retrieve data from database.");
        }
    }
    
    /**
     * Execute SELECT query and return single row
     */
    public function selectOne($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->logError("SELECT query failed: " . $e->getMessage() . " Query: " . $query);
            throw new Exception("Failed to retrieve data from database.");
        }
    }
    
    /**
     * Execute INSERT query
     */
    public function insert($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                return $this->connection->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            $this->logError("INSERT query failed: " . $e->getMessage() . " Query: " . $query);
            throw new Exception("Failed to insert data into database.");
        }
    }
    
    /**
     * Execute UPDATE query
     */
    public function update($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError("UPDATE query failed: " . $e->getMessage() . " Query: " . $query);
            throw new Exception("Failed to update data in database.");
        }
    }
    
    /**
     * Execute DELETE query
     */
    public function delete($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError("DELETE query failed: " . $e->getMessage() . " Query: " . $query);
            throw new Exception("Failed to delete data from database.");
        }
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Execute custom query
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError("Custom query failed: " . $e->getMessage() . " Query: " . $query);
            throw new Exception("Failed to execute database query.");
        }
    }
    
    /**
     * Get total count for pagination
     */
    public function getCount($table, $where = '', $params = []) {
        try {
            $query = "SELECT COUNT(*) as total FROM {$table}";
            if (!empty($where)) {
                $query .= " WHERE {$where}";
            }
            
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return (int)$result['total'];
        } catch (PDOException $e) {
            $this->logError("COUNT query failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Build WHERE clause from array
     */
    public function buildWhereClause($conditions) {
        if (empty($conditions)) {
            return ['', []];
        }
        
        $whereClause = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                // Handle IN operator
                $placeholders = str_repeat('?,', count($value) - 1) . '?';
                $whereClause[] = "{$field} IN ({$placeholders})";
                $params = array_merge($params, $value);
            } else {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
        }
        
        return [implode(' AND ', $whereClause), $params];
    }
    
    /**
     * Escape string for safe SQL usage
     */
    public function escape($string) {
        return $this->connection->quote($string);
    }
    
    /**
     * Log database errors
     */
    private function logError($message) {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] DATABASE ERROR: " . $message . PHP_EOL;
        file_put_contents(ROOT_PATH . 'logs/database_errors.log', $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {}
    
    /**
     * Close connection on destruction
     */
    public function __destruct() {
        $this->connection = null;
    }
}

/**
 * Helper class for common database operations
 */
class DatabaseHelper {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get user by NIK
     */
    public function getUserByNik($nik) {
        $query = "SELECT u.*, d.name as division_name, d.code as division_code 
                  FROM users u 
                  LEFT JOIN divisions d ON u.division_id = d.id 
                  WHERE u.nik = ? AND u.status = 'active'";
        return $this->db->selectOne($query, [$nik]);
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($id) {
        $query = "SELECT u.*, d.name as division_name, d.code as division_code 
                  FROM users u 
                  LEFT JOIN divisions d ON u.division_id = d.id 
                  WHERE u.id = ?";
        return $this->db->selectOne($query, [$id]);
    }
    
    /**
     * Get active loan for user
     */
    public function getActiveLoanByUserId($userId) {
        $query = "SELECT * FROM active_loans WHERE user_id = ? AND status = 'active'";
        return $this->db->selectOne($query, [$userId]);
    }
    
    /**
     * Get savings account for user
     */
    public function getSavingsAccountByUserId($userId) {
        $query = "SELECT * FROM savings_accounts WHERE user_id = ? AND status = 'active'";
        return $this->db->selectOne($query, [$userId]);
    }
    
    /**
     * Get system setting
     */
    public function getSystemSetting($key) {
        $query = "SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?";
        $result = $this->db->selectOne($query, [$key]);
        
        if ($result) {
            $value = $result['setting_value'];
            switch ($result['setting_type']) {
                case 'number':
                    return (float)$value;
                case 'boolean':
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                case 'json':
                    return json_decode($value, true);
                default:
                    return $value;
            }
        }
        
        return null;
    }
    
    /**
     * Create or update a system setting (upsert).
     */
    public function setSystemSetting($key, $value, $type = 'string', $description = null, $updatedBy = null) {
        // Normalize value for storage
        if ($type === 'boolean') {
            $storedValue = $value ? '1' : '0';
        } elseif ($type === 'json') {
            $storedValue = json_encode($value);
        } else {
            $storedValue = (string)$value;
        }
        
        $existing = $this->db->selectOne(
            "SELECT id FROM system_settings WHERE setting_key = ?",
            [$key]
        );
        
        if ($existing) {
            $query = "UPDATE system_settings 
                      SET setting_value = ?, setting_type = ?, updated_by = ?
                      WHERE setting_key = ?";
            return $this->db->update($query, [$storedValue, $type, $updatedBy, $key]);
        }
        
        $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type, description, updated_by)
                  VALUES (?, ?, ?, ?, ?)";
        return $this->db->insert($query, [$key, $storedValue, $type, $description, $updatedBy]);
    }
    
    /**
     * Generate unique reference number
     */
    public function generateReferenceNumber($prefix, $table, $column) {
        $date = date('ymd');
        $query = "SELECT MAX(CAST(SUBSTRING({$column}, -4) AS UNSIGNED)) as max_num 
                  FROM {$table} 
                  WHERE {$column} LIKE ?";
        
        $pattern = $prefix . $date . '%';
        $result = $this->db->selectOne($query, [$pattern]);
        
        $nextNumber = ($result['max_num'] ?? 0) + 1;
        return $prefix . $date . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Log user activity
     */
    public function logActivity($userId, $action, $entityType = null, $entityId = null, $oldValues = null, $newValues = null) {
        $query = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $userId,
            $action,
            $entityType,
            $entityId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        return $this->db->insert($query, $params);
    }
    
    /**
     * Create notification
     */
    public function createNotification($userId, $title, $message, $type = 'info', $category = 'system', $actionUrl = null, $expiresAt = null) {
        $query = "INSERT INTO notifications (user_id, title, message, type, category, action_url, expires_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [$userId, $title, $message, $type, $category, $actionUrl, $expiresAt];
        return $this->db->insert($query, $params);
    }
}

?>
