const fs = require('fs');
const path = require('path');
const http = require('http');
const https = require('https');

const WORKFLOW_FILES = [
    'infrastructure/n8n/chatbot_rag_workflow.json',
    'infrastructure/n8n/wp_rag_ingestion_workflow.json',
];

const CREDENTIAL_REQUIREMENTS = {
    qdrantApi: {
        envName: 'N8N_QDRANT_CREDENTIAL_NAME',
        defaultNames: ['Qdrant account', 'Qdrant local'],
    },
    huggingFaceApi: {
        envName: 'N8N_HUGGING_FACE_CREDENTIAL_NAME',
        defaultNames: ['Hugging Face direct', 'Hugging Face account'],
    },
    openRouterApi: {
        envName: 'N8N_OPENROUTER_CREDENTIAL_NAME',
        defaultNames: ['OpenRouter account'],
    },
    groqApi: {
        envName: 'N8N_GROQ_CREDENTIAL_NAME',
        defaultNames: ['Groq account'],
    },
};

const NODE_CREDENTIAL_MAP = {
    '@n8n/n8n-nodes-langchain.vectorStoreQdrant': 'qdrantApi',
    '@n8n/n8n-nodes-langchain.embeddingsHuggingFaceInference': 'huggingFaceApi',
    '@n8n/n8n-nodes-langchain.embeddingsHuggingFace': 'huggingFaceApi',
    '@n8n/n8n-nodes-langchain.lmChatOpenRouter': 'openRouterApi',
    '@n8n/n8n-nodes-langchain.lmChatGroq': 'groqApi',
};

const rootDir = path.resolve(__dirname, '..', '..');
const dotEnv = loadDotEnv(path.join(rootDir, '.env'));
const env = {
    ...dotEnv,
    ...process.env,
};

const config = {
    baseUrl: (env.N8N_BASE_URL || 'https://n8n.qamilunastudio.com').trim().replace(/\/+$/, ''),
    apiKey: (env.N8N_API_KEY || env.N8N_CHATBOT_TOKEN || '').trim(),
    dryRun: /^(1|true|yes)$/i.test(String(env.N8N_DRY_RUN || '')),
};

if (config.apiKey === '') {
    throw new Error('Missing N8N_API_KEY. Set it in the environment or GitHub Actions secret.');
}

main().catch((error) => {
    console.error(error.message);
    process.exitCode = 1;
});

async function main() {
    const credentialsResponse = await apiRequest('GET', '/credentials');
    const workflowsResponse = await apiRequest('GET', '/workflows');
    const credentials = credentialsResponse.data || [];
    const workflows = workflowsResponse.data || [];
    const credentialMap = resolveCredentialMap(credentials);

    for (const relativeFile of WORKFLOW_FILES) {
        const absoluteFile = path.join(rootDir, relativeFile);
        const workflow = JSON.parse(fs.readFileSync(absoluteFile, 'utf8'));
        const payload = injectCredentials(normalizeWorkflow(workflow), credentialMap);
        await upsertWorkflow(payload, workflows);
    }
}

async function upsertWorkflow(payload, existingWorkflows) {
    const existing = pickExistingWorkflow(existingWorkflows, payload.name);
    const action = existing ? 'Updating' : 'Creating';
    console.log(`${action} workflow "${payload.name}"`);

    if (config.dryRun) {
        console.log(`DRY RUN: skipped ${action.toLowerCase()} "${payload.name}"`);
        return;
    }

    if (existing) {
        await apiRequest('PUT', `/workflows/${existing.id}`, payload);
        await apiRequest('POST', `/workflows/${existing.id}/activate`);
        console.log(`Activated workflow "${payload.name}" (${existing.id})`);
        return;
    }

    const created = await apiRequest('POST', '/workflows', payload);
    const workflowId = created.id || (created.data && created.data.id);

    if (!workflowId) {
        throw new Error(`n8n did not return a workflow id when creating "${payload.name}"`);
    }

    await apiRequest('POST', `/workflows/${workflowId}/activate`);
    console.log(`Created and activated workflow "${payload.name}" (${workflowId})`);
}

function pickExistingWorkflow(workflows, workflowName) {
    const candidates = workflows
        .filter((workflow) => workflow.name === workflowName)
        .sort((left, right) => {
            if (left.isArchived !== right.isArchived) {
                return left.isArchived ? 1 : -1;
            }

            if (left.active !== right.active) {
                return left.active ? -1 : 1;
            }

            return Date.parse(right.updatedAt || 0) - Date.parse(left.updatedAt || 0);
        });

    return candidates[0] || null;
}

function resolveCredentialMap(credentials) {
    const requiredTypes = new Set(Object.values(NODE_CREDENTIAL_MAP));
    const resolved = {};

    for (const type of requiredTypes) {
        resolved[type] = resolveCredential(credentials, type);
    }

    return resolved;
}

function resolveCredential(credentials, type) {
    const requirement = CREDENTIAL_REQUIREMENTS[type];

    if (!requirement) {
        throw new Error(`No credential resolution rule configured for type "${type}"`);
    }

    const matches = credentials.filter((credential) => credential.type === type);

    if (matches.length === 0) {
        throw new Error(`No n8n credential found for type "${type}"`);
    }

    const preferredNames = [env[requirement.envName], ...requirement.defaultNames].filter(Boolean);

    for (const name of preferredNames) {
        const match = matches.find((credential) => credential.name === name);
        if (match) {
            return {
                id: match.id,
                name: match.name,
            };
        }
    }

    if (matches.length === 1) {
        return {
            id: matches[0].id,
            name: matches[0].name,
        };
    }

    const availableNames = matches.map((credential) => credential.name).join(', ');
    throw new Error(`Multiple "${type}" credentials found. Set ${requirement.envName}. Available: ${availableNames}`);
}

function normalizeWorkflow(workflow) {
    return {
        name: workflow.name,
        nodes: (workflow.nodes || []).map((node) => ({
            parameters: node.parameters || {},
            name: node.name,
            type: node.type,
            typeVersion: node.typeVersion,
            position: node.position,
        })),
        connections: workflow.connections || {},
        settings: sanitizeSettings(workflow.settings || {}),
    };
}

function sanitizeSettings(settings) {
    const allowedKeys = ['executionOrder'];
    const sanitized = {};

    for (const key of allowedKeys) {
        if (settings[key] !== undefined) {
            sanitized[key] = settings[key];
        }
    }

    return sanitized;
}

function injectCredentials(workflow, credentialMap) {
    return {
        ...workflow,
        nodes: workflow.nodes.map((node) => {
            const credentialType = NODE_CREDENTIAL_MAP[node.type];

            if (!credentialType) {
                return node;
            }

            return {
                ...node,
                credentials: {
                    [credentialType]: credentialMap[credentialType],
                },
            };
        }),
    };
}

function loadDotEnv(filePath) {
    if (!fs.existsSync(filePath)) {
        return {};
    }

    const entries = {};
    const lines = fs.readFileSync(filePath, 'utf8').split(/\r?\n/);

    for (const line of lines) {
        if (!line || line.trim().startsWith('#')) {
            continue;
        }

        const separatorIndex = line.indexOf('=');

        if (separatorIndex === -1) {
            continue;
        }

        const key = line.slice(0, separatorIndex).trim();
        const value = line.slice(separatorIndex + 1).trim().replace(/^"|"$/g, '');
        entries[key] = value;
    }

    return entries;
}

function apiRequest(method, endpoint, body) {
    return new Promise((resolve, reject) => {
        const targetUrl = buildApiUrl(endpoint);
        const transport = targetUrl.protocol === 'https:' ? https : http;
        const payload = body === undefined ? null : JSON.stringify(body);

        const request = transport.request(
            {
                protocol: targetUrl.protocol,
                hostname: targetUrl.hostname,
                port: targetUrl.port || undefined,
                path: `${targetUrl.pathname}${targetUrl.search}`,
                method,
                headers: {
                    Accept: 'application/json',
                    'X-N8N-API-KEY': config.apiKey,
                    ...(payload
                        ? {
                              'Content-Type': 'application/json',
                              'Content-Length': Buffer.byteLength(payload),
                          }
                        : {}),
                },
            },
            (response) => {
                let responseBody = '';

                response.on('data', (chunk) => {
                    responseBody += chunk;
                });

                response.on('end', () => {
                    const parsedBody = responseBody ? safeJsonParse(responseBody) : null;

                    if (response.statusCode >= 200 && response.statusCode < 300) {
                        resolve(parsedBody);
                        return;
                    }

                    const errorMessage =
                        (parsedBody && parsedBody.message) ||
                        `${method} ${targetUrl.pathname} failed with status ${response.statusCode}`;

                    reject(new Error(errorMessage));
                });
            },
        );

        request.on('error', reject);

        if (payload) {
            request.write(payload);
        }

        request.end();
    });
}

function buildApiUrl(endpoint) {
    const base = new URL(config.baseUrl);
    const apiPath = `${base.pathname.replace(/\/$/, '')}/api/v1${endpoint}`;
    return new URL(`${base.protocol}//${base.host}${apiPath}`);
}

function safeJsonParse(value) {
    try {
        return JSON.parse(value);
    } catch (error) {
        return {
            message: value,
        };
    }
}
