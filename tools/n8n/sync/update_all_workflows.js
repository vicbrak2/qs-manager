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

        if (!credMap.groqApi) {
            let r = await apiRequest('POST', '/credentials', { name: 'Groq API Key', type: 'groqApi', data: { apiKey: envKeys.GROQ_N8N_API_KEY }});
            if (r.code === 200) credMap.groqApi = r.data.id;
        }
        if (!credMap.huggingFaceApi) {
            let r = await apiRequest('POST', '/credentials', { name: 'HF Token', type: 'huggingFaceApi', data: { apiKey: envKeys.HUGGING_FACE_API_KEY }});
            if (r.code === 200) credMap.huggingFaceApi = r.data.id;
        }
        if (!credMap.qdrantApi) {
            let r = await apiRequest('POST', '/credentials', { name: 'Qdrant Cloud Key', type: 'qdrantApi', data: { url: envKeys.QDRANT_URL, apiKey: envKeys.QDRANT_KEY }});
            if (r.code === 200) credMap.qdrantApi = r.data.id;
        }

        const updateWf = async (wfId) => {
            const wf = await apiRequest('GET', `/workflows/${wfId}`);
            if (wf.code === 200 && wf.data.nodes) {
                wf.data.nodes.forEach(n => {
                    if (n.name === 'Groq Chat Model' && credMap.groqApi) n.credentials = { groqApi: { id: credMap.groqApi, name: 'Groq API Key' } };
                    if (n.name === 'Hugging Face Embeddings' && credMap.huggingFaceApi) n.credentials = { huggingFaceApi: { id: credMap.huggingFaceApi, name: 'HF Token' } };
                    if ((n.name === 'Qdrant Vector Store Tool' || n.name === 'Qdrant Vector Store') && credMap.qdrantApi) {
                        n.credentials = { qdrantApi: { id: credMap.qdrantApi, name: 'Qdrant Cloud Key' } };
                        n.parameters.collectionName = 'wordpress_context';
                        n.parameters.collection = 'wordpress_context';
                        n.parameters.qdrantCollection = 'wordpress_context';
                    }
                });

                console.log(`Updating workflow ${wfId}...`);
                let updatedWf = { nodes: wf.data.nodes, connections: wf.data.connections, settings: {}, name: wf.data.name };
                let upd = await apiRequest('PUT', `/workflows/${wfId}`, updatedWf);
                if (upd.code === 200) {
                    let act = await apiRequest('POST', `/workflows/${wfId}/activate`);
                    if (act.code === 200) console.log(`Activated ${wfId}!`);
                    else console.log(`Activation Failed for ${wfId}:`, act);
                } else {
                    console.log(`Update failed for ${wfId}:`, upd);
                }
            } else {
                console.log(`Failed to fetch workflow ${wfId}`);
            }
        };

        await updateWf('PLtb0JLxgJ4Txwqg');
        await updateWf('nIRvK40bEdRwSReg');
        
    } catch (e) {
        console.error(e);
    }
})();
