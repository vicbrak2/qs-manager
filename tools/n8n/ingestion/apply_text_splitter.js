const http = require('http');
const fs = require('fs');
const path = require('path');

const envPath = path.resolve(__dirname, '..', '..', '.env');
const token = fs.readFileSync(envPath, 'utf8').split('\n').find(l => l.startsWith('N8N_CHATBOT_TOKEN')).split('=')[1].replace(/"/g, '').trim();

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
        const wfId = 'nIRvK40bEdRwSReg';
        const wf = await apiRequest('GET', `/workflows/${wfId}`);
        if (wf.code === 200 && wf.data.nodes) {
            
            // Check if text splitter already exists
            const hasSplitter = wf.data.nodes.some(n => n.name === 'Recursive Character Text Splitter');
            if (!hasSplitter) {
                // Add the text splitter node
                wf.data.nodes.push({
                    "parameters": {
                        "chunkSize": 1000,
                        "chunkOverlap": 100
                    },
                    "name": "Recursive Character Text Splitter",
                    "type": "@n8n/n8n-nodes-langchain.textSplitterRecursiveCharacterTextSplitter",
                    "typeVersion": 1,
                    "position": [300, 300]
                });

                // In the connections, we connect the Text Splitter to the Default Data Loader
                if (!wf.data.connections["Recursive Character Text Splitter"]) {
                    wf.data.connections["Recursive Character Text Splitter"] = {
                        "ai_textSplitter": [
                            [
                                {
                                    "node": "Default Data Loader",
                                    "type": "ai_textSplitter",
                                    "index": 0
                                }
                            ]
                        ]
                    };
                }

                console.log(`Updating workflow ${wfId} with Text Splitter...`);
                let updatedWf = { nodes: wf.data.nodes, connections: wf.data.connections, settings: {}, name: wf.data.name };
                let upd = await apiRequest('PUT', `/workflows/${wfId}`, updatedWf);
                
                if (upd.code === 200) {
                    await apiRequest('POST', `/workflows/${wfId}/activate`);
                    console.log("Successfully added the text splitter to WP RAG Ingestion");
                } else {
                    console.log("Failed to update:", upd);
                }
            } else {
                console.log("Text Splitter already exists.");
            }
        }
    } catch (e) {
        console.error(e);
    }
})();
