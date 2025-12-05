<?php
/**
 * TrendRadarConsole - SSH Helper Class
 * Provides SSH connection functionality for Docker worker communication
 */

class SSHHelper
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $connection;
    private $lastError;
    
    /**
     * Constructor
     */
    public function __construct($host, $username, $password, $port = 22)
    {
        $this->host = $host;
        $this->port = (int)$port;
        $this->username = $username;
        $this->password = $password;
        $this->connection = null;
        $this->lastError = '';
    }
    
    /**
     * Connect to SSH server
     */
    public function connect()
    {
        if (!function_exists('ssh2_connect')) {
            $this->lastError = 'PHP SSH2 extension is not installed. Please install php-ssh2.';
            return false;
        }
        
        // Connect to SSH server
        $this->connection = @ssh2_connect($this->host, $this->port);
        if (!$this->connection) {
            $this->lastError = 'Failed to connect to SSH server: ' . $this->host . ':' . $this->port;
            return false;
        }
        
        // Authenticate
        if (!@ssh2_auth_password($this->connection, $this->username, $this->password)) {
            $this->lastError = 'SSH authentication failed. Please check username and password.';
            $this->connection = null;
            return false;
        }
        
        return true;
    }
    
    /**
     * Execute a command via SSH
     */
    public function exec($command)
    {
        if (!$this->connection) {
            if (!$this->connect()) {
                return [
                    'success' => false,
                    'output' => '',
                    'error' => $this->lastError
                ];
            }
        }
        
        // Execute command
        $stream = @ssh2_exec($this->connection, $command);
        if (!$stream) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'Failed to execute command'
            ];
        }
        
        // Get stdout and stderr streams
        $stderrStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        
        // Enable blocking for both streams
        stream_set_blocking($stream, true);
        stream_set_blocking($stderrStream, true);
        
        // Read output
        $stdout = stream_get_contents($stream);
        $stderr = stream_get_contents($stderrStream);
        
        // Close streams
        fclose($stream);
        fclose($stderrStream);
        
        // Return result
        return [
            'success' => empty($stderr),
            'output' => $stdout,
            'error' => $stderr
        ];
    }
    
    /**
     * Disconnect from SSH server
     */
    public function disconnect()
    {
        if ($this->connection) {
            // SSH2 doesn't have a disconnect function, connection is closed when object is destroyed
            $this->connection = null;
        }
    }
    
    /**
     * Get the last error message
     */
    public function getLastError()
    {
        return $this->lastError;
    }
    
    /**
     * Check if connected
     */
    public function isConnected()
    {
        return $this->connection !== null;
    }
    
    /**
     * Test SSH connection
     */
    public function testConnection()
    {
        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => $this->lastError
            ];
        }
        
        // Try executing a simple command
        $result = $this->exec('echo "Connection successful"');
        
        if ($result['success'] && strpos($result['output'], 'Connection successful') !== false) {
            return [
                'success' => true,
                'message' => 'SSH connection successful'
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['error'] ?: 'Failed to execute test command'
        ];
    }
    
    /**
     * Check if Docker is available on remote server
     */
    public function checkDocker()
    {
        $result = $this->exec('docker --version');
        
        if ($result['success'] && strpos($result['output'], 'Docker') !== false) {
            return [
                'success' => true,
                'version' => trim($result['output'])
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Docker is not available on the remote server: ' . ($result['error'] ?: 'Unknown error')
        ];
    }
}
