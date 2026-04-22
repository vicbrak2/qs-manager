<?php

$envPath = dirname(__DIR__, 2) . '/.env';
$envLines = file_exists($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$envVars = [];
foreach ($envLines as $line) {
    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '"\'');
        // Handle QGRANT keys misspellings in .env
        if ($key === 'QGRANT_KEY') $key = 'QDRANT_KEY';
        if ($key === 'Cluster Endpoint') $key = 'QDRANT_URL';
        $envVars[$key] = $value;
    }
}

$token = $envVars['N8N_CHATBOT_TOKEN'] ?? '';
$baseUrl = 'http://localhost:5678/api/v1';

function apiRequest($method, $endpoint, $data = null) {
    global $token, $baseUrl;
    $ch = curl_init($baseUrl . $endpoint);
    $headers = [
        "X-N8n-Api-Key: $token",
        "Accept: application/json"
    ];
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = "Content-Type: application/json";
    }
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $httpCode, 'data' => json_decode($response, true) ?: $response];
}

// 1. Get credentials map
$credsRes = apiRequest('GET', '/credentials');
$existingCreds = [];
if ($credsRes['code'] == 200 && isset($credsRes['data']['data'])) {
    foreach ($credsRes['data']['data'] as $cred) {
        $existingCreds[$cred['type']] = $cred['id'];
    }
}

// 2. Ensure Groq exists
if (!isset($existingCreds['groqApi'])) {
    echo "Creating Groq Credential...\n";
    $res = apiRequest('POST', '/credentials', [
        'name' => 'Groq API Key',
        'type' => 'groqApi',
        'data' => ['apiKey' => $envVars['GROQ_N8N_API_KEY']]
    ]);
    if ($res['code'] == 200) $existingCreds['groqApi'] = $res['data']['id'];
}

// 3. Ensure Hugging Face exists
if (!isset($existingCreds['huggingFaceApi'])) {
    echo "Creating HF Credential...\n";
    $res = apiRequest('POST', '/credentials', [
        'name' => 'Hugging Face Token',
        'type' => 'huggingFaceApi',
        'data' => ['apiKey' => $envVars['HUGGING_FACE_API_KEY']]
    ]);
    if ($res['code'] == 200) $existingCreds['huggingFaceApi'] = $res['data']['id'];
}

// 4. Ensure Qdrant exists
if (!isset($existingCreds['qdrantApi'])) {
    echo "Creating Qdrant Credential...\n";
    $res = apiRequest('POST', '/credentials', [
        'name' => 'Qdrant Cloud Key',
        'type' => 'qdrantApi',
        'data' => [
            'url' => $envVars['QDRANT_URL'],
            'apiKey' => $envVars['QDRANT_KEY']
        ]
    ]);
    if ($res['code'] == 200) $existingCreds['qdrantApi'] = $res['data']['id'];
    else echo "Failed Qdrant Auth: " . print_r($res, true) . "\n";
}

// 5. Get workflow and update
$workflowId = 'PLtb0JLxgJ4Txwqg';
$wfRes = apiRequest('GET', "/workflows/$workflowId");

if ($wfRes['code'] == 200 && isset($wfRes['data']['nodes'])) {
    $nodes = $wfRes['data']['nodes'];
    foreach ($nodes as &$node) {
        if ($node['name'] === 'Groq Chat Model' && isset($existingCreds['groqApi'])) {
            $node['credentials'] = ['groqApi' => ['id' => $existingCreds['groqApi'], 'name' => 'Groq API Key']];
        }
        if ($node['name'] === 'Hugging Face Embeddings' && isset($existingCreds['huggingFaceApi'])) {
            $node['credentials'] = ['huggingFaceApi' => ['id' => $existingCreds['huggingFaceApi'], 'name' => 'Hugging Face Token']];
        }
        if ($node['name'] === 'Qdrant Vector Store Tool' && isset($existingCreds['qdrantApi'])) {
            $node['credentials'] = ['qdrantApi' => ['id' => $existingCreds['qdrantApi'], 'name' => 'Qdrant Cloud Key']];
            $node['parameters']['collectionName'] = 'wordpress_context';
        }
    }
    
    echo "Updating workflow...\n";
    $wfRes['data']['nodes'] = $nodes;
    $updateRes = apiRequest('PUT', "/workflows/$workflowId", $wfRes['data']);
    
    if ($updateRes['code'] == 200) {
        echo "Activating workflow...\n";
        $actRes = apiRequest('POST', "/workflows/$workflowId/activate");
        echo "Activate response: " . $actRes['code'] . "\n";
    } else {
        echo "Workflow update failed: " . json_encode($updateRes) . "\n";
    }
} else {
    echo "Failed to load workflow: " . json_encode($wfRes) . "\n";
}

echo "Done.\n";
