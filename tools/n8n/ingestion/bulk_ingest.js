const fs = require('fs');
const http = require('http');
const path = require('path');

const defaultDocumentsPath = path.join(__dirname, 'documents.json');

function readDocuments(filePath) {
  const raw = fs.readFileSync(filePath, 'utf8');
  const parsed = JSON.parse(raw);

  if (!Array.isArray(parsed)) {
    throw new Error('El archivo de documentos debe contener un array JSON.');
  }

  return parsed;
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function postJson(hostname, port, pathname, payload) {
  return new Promise((resolve, reject) => {
    const data = JSON.stringify(payload);
    const request = http.request(
      {
        hostname,
        port,
        path: pathname,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': Buffer.byteLength(data),
        },
      },
      (response) => {
        let body = '';

        response.on('data', (chunk) => {
          body += chunk;
        });

        response.on('end', () => {
          resolve({
            statusCode: response.statusCode ?? 0,
            body,
          });
        });
      },
    );

    request.on('error', reject);
    request.write(data);
    request.end();
  });
}

function getJson(hostname, port, pathname) {
  return new Promise((resolve, reject) => {
    const request = http.request(
      {
        hostname,
        port,
        path: pathname,
        method: 'GET',
      },
      (response) => {
        let body = '';

        response.on('data', (chunk) => {
          body += chunk;
        });

        response.on('end', () => {
          try {
            resolve({
              statusCode: response.statusCode ?? 0,
              data: JSON.parse(body || '{}'),
            });
          } catch (error) {
            reject(error);
          }
        });
      },
    );

    request.on('error', reject);
    request.end();
  });
}

async function ingestDocument(document) {
  const result = await postJson('localhost', 5678, '/webhook/wp-ingest-rag', document);
  const label = document.title ?? `post_id=${document.post_id ?? 'sin-id'}`;

  if (result.statusCode >= 400) {
    throw new Error(`Falló ${label}: ${result.statusCode} ${result.body}`);
  }

  console.log(`✓ ${label} -> ${result.statusCode}`);
}

async function printCollectionStats() {
  const result = await getJson('localhost', 6333, '/collections/wordpress_context');

  if (result.statusCode >= 400) {
    throw new Error(`No pude leer la colección wordpress_context: ${result.statusCode}`);
  }

  const collection = result.data.result ?? {};
  console.log('');
  console.log('Colección wordpress_context');
  console.log(`- points_count: ${collection.points_count ?? 'n/a'}`);
  console.log(`- indexed_vectors_count: ${collection.indexed_vectors_count ?? 'n/a'}`);
  console.log(`- status: ${collection.status ?? 'n/a'}`);
}

async function main() {
  const filePath = process.argv[2]
    ? path.resolve(process.argv[2])
    : defaultDocumentsPath;
  const pauseMs = Number(process.argv[3] ?? 500);
  const documents = readDocuments(filePath);

  console.log(`Ingestando ${documents.length} documentos desde ${filePath}`);

  for (const document of documents) {
    await ingestDocument(document);
    await delay(pauseMs);
  }

  await printCollectionStats();
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
