const fs = require('fs');
const http = require('http');
const path = require('path');

const envPath = path.resolve(__dirname, '..', '..', '.env');
const envKeys = fs.readFileSync(envPath, 'utf8').split('\n').reduce((acc, line) => {
    let [key, ...val] = line.split('=');
    if (key && val.length) {
        key = key.trim();
        let value = val.join('=').trim().replace(/^"|"$/g, '');
        if (key === 'QGRANT_KEY') key = 'QDRANT_KEY';
        if (key === 'Cluster Endpoint') key = 'QDRANT_URL';
        acc[key] = value;
    }
    return acc;
}, {});

const token = envKeys.N8N_CHATBOT_TOKEN;

function apiRequest(method, endpoint, data = null) {
    return new Promise((resolve, reject) => {
        const options = {
            hostname: 'localhost',
            port: 5678,
            path: '/api/v1' + endpoint,
            method,
            headers: { 'X-N8n-Api-Key': token, 'Accept': 'application/json' }
        };
        if (data) {
            data = JSON.stringify(data);
            options.headers['Content-Type'] = 'application/json';
            options.headers['Content-Length'] = Buffer.byteLength(data);
        }

        const req = http.request(options, res => {
            let body = '';
            res.on('data', d => body += d);
            res.on('end', () => resolve({ code: res.statusCode, data: JSON.parse(body || '{}') }));
        });
        req.on('error', reject);
        if (data) req.write(data);
        req.end();
    });
}

(async () => {
    try {
        const creds = await apiRequest('GET', '/credentials');
        const credMap = {};
        if (creds.data && creds.data.data) {
            for (let c of creds.data.data) credMap[c.type] = c.id;
        }

        const wfPayload = {
            "name": "WP RAG Ingestion",
            "nodes": [
                {
                    "parameters": {
                        "httpMethod": "POST",
                        "path": "wp-ingest-rag",
                        "responseMode": "lastNode",
                        "options": {}
                    },
                    "name": "WP Data Receiver",
                    "type": "n8n-nodes-base.webhook",
                    "typeVersion": 1,
                    "position": [0, 0]
                },
                {
                    "parameters": {
                        "mode": "insert",
                        "collectionName": "wordpress_context",
                        "collection": "wordpress_context",
                        "qdrantCollection": "wordpress_context",
                        "options": {}
                    },
                    "name": "Qdrant Vector Store",
                    "type": "@n8n/n8n-nodes-langchain.vectorStoreQdrant",
                    "typeVersion": 1,
                    "position": [500, 0],
                    "credentials": credMap.qdrantApi ? { "qdrantApi": { "id": credMap.qdrantApi, "name": "Qdrant Cloud Key" } } : undefined
                },
                {
                    "parameters": {},
                    "name": "Hugging Face Embeddings",
                    "type": "@n8n/n8n-nodes-langchain.embeddingsHuggingFaceInference",
                    "typeVersion": 1,
                    "position": [500, 200],
                    "credentials": credMap.huggingFaceApi ? { "huggingFaceApi": { "id": credMap.huggingFaceApi, "name": "HF Token" } } : undefined
                },
                {
                    "parameters": {
                        "options": {}
                    },
                    "name": "Default Data Loader",
                    "type": "@n8n/n8n-nodes-langchain.documentDefaultDataLoader",
                    "typeVersion": 1,
                    "position": [300, 150]
                }
            ],
            "connections": {
                "WP Data Receiver": {
                    "main": [ [ { "node": "Qdrant Vector Store", "type": "main", "index": 0 } ] ]
                },
                "Default Data Loader": {
                    "ai_document": [ [ { "node": "Qdrant Vector Store", "type": "ai_document", "index": 0 } ] ]
                },
                "Hugging Face Embeddings": {
                    "ai_embedding": [ [ { "node": "Qdrant Vector Store", "type": "ai_embedding", "index": 0 } ] ]
                }
            },
            "settings": {}
        };

        // Inject expressions for Data Loader
        wfPayload.nodes[3].parameters.options = {
             metadata: `={
  "title": "{{ $json.body.title }}",
  "post_id": "{{ $json.body.post_id }}",
  "url": "{{ $json.body.url }}"
}`
        };
        wfPayload.nodes[3].parameters.text = "={{ $json.body.content }}";


        console.log("Creating Ingestion Workflow...");
        let createReq = await apiRequest('POST', '/workflows', wfPayload);
        
        if (createReq.code === 200 && createReq.data.id) {
            let wfId = createReq.data.id;
            console.log("Created successfully with ID: " + wfId);
            
            console.log("Activating...");
            let actReq = await apiRequest('POST', `/workflows/${wfId}/activate`);
            if (actReq.code === 200) {
                console.log("Workflow is ACTIVE! Ready to receive data on /webhook/wp-ingest-rag");
            } else {
                console.log("Activation failed:", actReq);
            }
        } else {
            console.log("Failed to create workflow:", createReq);
        }

    } catch (e) {
        console.error(e);
    }
})();
