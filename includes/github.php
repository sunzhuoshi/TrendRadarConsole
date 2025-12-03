<?php
/**
 * TrendRadarConsole - GitHub API Integration
 */

class GitHub
{
    private $token;
    private $owner;
    private $repo;
    private $apiBase = 'https://api.github.com';
    
    public function __construct($token, $owner, $repo)
    {
        $this->token = $token;
        $this->owner = $owner;
        $this->repo = $repo;
    }
    
    /**
     * Make an API request to GitHub
     */
    private function request($method, $endpoint, $data = null)
    {
        $url = $this->apiBase . $endpoint;
        
        $headers = [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $this->token,
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: TrendRadarConsole'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST' || $method === 'PATCH' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $message = isset($decoded['message']) ? $decoded['message'] : 'Unknown error';
            throw new Exception('GitHub API error (' . $httpCode . '): ' . $message);
        }
        
        return [
            'code' => $httpCode,
            'data' => $decoded
        ];
    }
    
    /**
     * Test connection and get repository info
     */
    public function testConnection()
    {
        $result = $this->request('GET', "/repos/{$this->owner}/{$this->repo}");
        return $result['data'];
    }
    
    /**
     * Get a repository variable
     */
    public function getVariable($name)
    {
        try {
            $result = $this->request('GET', "/repos/{$this->owner}/{$this->repo}/actions/variables/{$name}");
            return $result['data']['value'] ?? null;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                return null; // Variable doesn't exist
            }
            throw $e;
        }
    }
    
    /**
     * Set a repository variable (create or update)
     */
    public function setVariable($name, $value)
    {
        // Check if variable exists
        $exists = false;
        try {
            $this->request('GET', "/repos/{$this->owner}/{$this->repo}/actions/variables/{$name}");
            $exists = true;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '404') === false) {
                throw $e;
            }
        }
        
        if ($exists) {
            // Update existing variable
            $this->request('PATCH', "/repos/{$this->owner}/{$this->repo}/actions/variables/{$name}", [
                'value' => $value
            ]);
        } else {
            // Create new variable
            $this->request('POST', "/repos/{$this->owner}/{$this->repo}/actions/variables", [
                'name' => $name,
                'value' => $value
            ]);
        }
        
        return true;
    }
    
    /**
     * Get CONFIG_YAML variable
     */
    public function getConfigYaml()
    {
        return $this->getVariable('CONFIG_YAML');
    }
    
    /**
     * Set CONFIG_YAML variable
     */
    public function setConfigYaml($content)
    {
        return $this->setVariable('CONFIG_YAML', $content);
    }
    
    /**
     * Get FREQUENCY_WORDS variable
     */
    public function getFrequencyWords()
    {
        return $this->getVariable('FREQUENCY_WORDS');
    }
    
    /**
     * Set FREQUENCY_WORDS variable
     */
    public function setFrequencyWords($content)
    {
        return $this->setVariable('FREQUENCY_WORDS', $content);
    }
    
    /**
     * Dispatch a workflow
     */
    public function dispatchWorkflow($workflowId, $ref = 'main', $inputs = [])
    {
        $data = [
            'ref' => $ref
        ];
        
        if (!empty($inputs)) {
            $data['inputs'] = $inputs;
        }
        
        $result = $this->request('POST', "/repos/{$this->owner}/{$this->repo}/actions/workflows/{$workflowId}/dispatches", $data);
        return $result['code'] === 204;
    }
}
