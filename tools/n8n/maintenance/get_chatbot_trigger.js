const http = require('http');
const fs = require('fs');
const path = require('path');

const envPath = path.resolve(__dirname, '..', '..', '.env');
const token = fs.readFileSync(envPath, 'utf8').split('\n').find(l => l.startsWith('N8N_CHATBOT_TOKEN')).split('=')[1].replace(/"/g, '').trim();

http.get({
  hostname: 'localhost', 
  port: 5678, 
  path: '/api/v1/workflows/PLtb0JLxgJ4Txwqg',
  headers: { 'X-N8n-Api-Key': token }
}, r => {
  let b = '';
  r.on('data', d => b += d);
  r.on('end', () => {
    let data = JSON.parse(b);
    let nodes = data.nodes ? data.nodes.filter(n => n.type.toLowerCase().includes('chat') || n.type.toLowerCase().includes('webhook')) : data;
    console.log(JSON.stringify(nodes, null, 2));
  });
});
