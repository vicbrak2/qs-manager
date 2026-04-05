const fs = require('fs');
const http = require('http');
const path = require('path');

const envPath = path.resolve(__dirname, '..', '..', '.env');
const envKeys = fs.readFileSync(envPath, 'utf8').split('\n').reduce((acc, line) => {
    let [key, ...val] = line.split('=');
    if (key && val.length) {
        key = key.trim();
        let value = val.join('=').trim().replace(/^"|"$/g, '');
        acc[key] = value;
    }
    return acc;
}, {});

const token = envKeys.N8N_CHATBOT_TOKEN;

const options = {
    hostname: 'localhost',
    port: 5678,
    path: '/api/v1/workflows',
    method: 'GET',
    headers: { 'X-N8n-Api-Key': token, 'Accept': 'application/json' }
};

http.get(options, res => {
    let body = '';
    res.on('data', d => body += d);
    res.on('end', () => console.log(JSON.stringify(JSON.parse(body), null, 2)));
});
