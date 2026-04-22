const http = require('http');

const postData = JSON.stringify({
  title: 'Test WordPress Post ' + new Date().getTime(),
  post_id: '9999',
  url: 'https://mysite.local/test-post',
  content: 'Esta es una prueba de integración del sistema RAG enviada desde el plugin de WordPress hacia el flujo de n8n. Contiene información valiosa sobre cómo testear la base de datos de vectores Qdrant.'
});

const options = {
  hostname: 'localhost',
  port: 5678,
  path: '/webhook/wp-ingest-rag',
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Content-Length': Buffer.byteLength(postData)
  }
};

console.log("Sending mock WordPress POST request to n8n webhook...");

const req = http.request(options, (res) => {
  console.log(`STATUS: ${res.statusCode}`);
  let body = '';
  res.on('data', (chunk) => { body += chunk; });
  res.on('end', () => {
    console.log(`BODY: ${body}`);
  });
});

req.on('error', (e) => {
  console.error(`Problem with request: ${e.message}`);
});

req.write(postData);
req.end();
