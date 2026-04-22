const http = require('http');
const fs = require('fs');
const path = require('path');

const envPath = path.resolve(__dirname, '..', '..', '.env');
const token = fs.readFileSync(envPath, 'utf8').split('\n').find(l => l.startsWith('N8N_CHATBOT_TOKEN')).split('=')[1].replace(/"/g, '').trim();

const req = http.request({
    hostname: 'localhost', port: 5678, path: '/api/v1/executions?limit=1', // get latest execution
    method: 'GET', headers: { 'X-N8n-Api-Key': token, 'Accept': 'application/json' }
}, res => {
    let body = '';
    res.on('data', d => body += d);
    res.on('end', () => {
        let ex = JSON.parse(body);
        if (ex.data && ex.data.length > 0) {
            let latest = ex.data[0];
            console.log("Execution ID:", latest.id);
            console.log("Status:", latest.status);
            // Request full execution details to see the error
            http.request({
                hostname: 'localhost', port: 5678, path: `/api/v1/executions/${latest.id}?includeData=true`,
                method: 'GET', headers: { 'X-N8n-Api-Key': token, 'Accept': 'application/json' }
            }, res2 => {
                 let b2 = '';
                 res2.on('data', d => b2 += d);
                 res2.on('end', () => {
                     let dat = JSON.parse(b2);
                     console.log(JSON.stringify(dat.data?.resultData?.error || dat.data || dat, null, 2));
                 });
            }).end();
        } else {
            console.log("No executions found");
        }
    });
});
req.end();
