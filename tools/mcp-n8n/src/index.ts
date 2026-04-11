import fs from 'node:fs/promises';
import path from 'node:path';

import { McpServer, StdioServerTransport } from '@modelcontextprotocol/server';
import * as z from 'zod/v4';

type JsonObject = Record<string, unknown>;

type WorkflowSummary = {
    id: string;
    name: string;
    active: boolean;
    isArchived: boolean;
    updatedAt?: string;
};

const REQUEST_TIMEOUT_MS = 45_000;

const n8nBaseUrl = (process.env.N8N_BASE_URL ?? '').trim().replace(/\/+$/, '');
const n8nApiKey = (process.env.N8N_API_KEY ?? '').trim();

if (n8nBaseUrl === '' || n8nApiKey === '') {
    console.error('Missing required environment variables: N8N_BASE_URL and/or N8N_API_KEY.');
    process.exit(1);
}

const server = new McpServer(
    {
        name: 'n8n-direct',
        version: '0.1.0',
    },
    {
        instructions:
            'Use n8n tools to inspect or deploy workflows. Prefer upsert by file path and activate after successful update.',
    },
);

server.registerTool(
    'n8n_list_workflows',
    {
        description: 'List workflows available in n8n.',
        inputSchema: z.object({
            limit: z.number().int().min(1).max(200).optional(),
            includeArchived: z.boolean().optional(),
        }),
    },
    async ({ limit, includeArchived }) => {
        try {
            const workflows = await listWorkflows();
            const filtered = includeArchived === true ? workflows : workflows.filter((item) => !item.isArchived);
            const limited = typeof limit === 'number' ? filtered.slice(0, limit) : filtered;

            return {
                content: [
                    {
                        type: 'text',
                        text: `Found ${limited.length} workflow(s).`,
                    },
                ],
                structuredContent: {
                    workflows: limited,
                },
            };
        } catch (error) {
            return {
                isError: true,
                content: [
                    {
                        type: 'text',
                        text: formatError(error),
                    },
                ],
            };
        }
    },
);

server.registerTool(
    'n8n_upsert_workflow_file',
    {
        description:
            'Create or update a workflow in n8n using a local JSON file. Matches existing workflows by exact name.',
        inputSchema: z.object({
            filePath: z.string().min(1),
            activate: z.boolean().default(true),
        }),
    },
    async ({ filePath, activate }) => {
        try {
            const absolutePath = toAbsolutePath(filePath);
            const workflow = await loadWorkflowFromFile(absolutePath);
            const result = await upsertWorkflowByName(workflow, activate);

            return {
                content: [
                    {
                        type: 'text',
                        text: `Workflow ${result.action}: ${result.name} (${result.id})`,
                    },
                ],
                structuredContent: result,
            };
        } catch (error) {
            return {
                isError: true,
                content: [
                    {
                        type: 'text',
                        text: formatError(error),
                    },
                ],
            };
        }
    },
);

server.registerTool(
    'n8n_activate_workflow',
    {
        description: 'Activate a workflow by workflow ID.',
        inputSchema: z.object({
            workflowId: z.string().min(1),
        }),
    },
    async ({ workflowId }) => {
        try {
            await n8nRequest('POST', `/workflows/${encodeURIComponent(workflowId)}/activate`);

            return {
                content: [
                    {
                        type: 'text',
                        text: `Workflow activated: ${workflowId}`,
                    },
                ],
            };
        } catch (error) {
            return {
                isError: true,
                content: [
                    {
                        type: 'text',
                        text: formatError(error),
                    },
                ],
            };
        }
    },
);

async function upsertWorkflowByName(workflow: JsonObject, activate: boolean): Promise<{
    action: 'created' | 'updated';
    id: string;
    name: string;
    activated: boolean;
}> {
    const workflowName = asString(workflow.name).trim();

    if (workflowName === '') {
        throw new Error('Workflow JSON must contain a non-empty "name".');
    }

    const candidates = (await listWorkflows()).filter((item) => item.name === workflowName);
    const existing = pickPreferredWorkflow(candidates);
    const payload = normalizeWorkflow(workflow);

    if (existing !== null) {
        await n8nRequest('PUT', `/workflows/${encodeURIComponent(existing.id)}`, payload);

        if (activate) {
            await n8nRequest('POST', `/workflows/${encodeURIComponent(existing.id)}/activate`);
        }

        return {
            action: 'updated',
            id: existing.id,
            name: workflowName,
            activated: activate,
        };
    }

    const created = await n8nRequest('POST', '/workflows', payload);
    const createdId = resolveWorkflowId(created);

    if (createdId === '') {
        throw new Error('n8n did not return a workflow ID after creation.');
    }

    if (activate) {
        await n8nRequest('POST', `/workflows/${encodeURIComponent(createdId)}/activate`);
    }

    return {
        action: 'created',
        id: createdId,
        name: workflowName,
        activated: activate,
    };
}

async function loadWorkflowFromFile(absolutePath: string): Promise<JsonObject> {
    const raw = await fs.readFile(absolutePath, 'utf8');
    const parsed = JSON.parse(raw) as unknown;

    if (!isRecord(parsed)) {
        throw new Error('Workflow file must contain a JSON object.');
    }

    return parsed;
}

function toAbsolutePath(filePath: string): string {
    if (path.isAbsolute(filePath)) {
        return filePath;
    }

    return path.resolve(process.cwd(), filePath);
}

async function listWorkflows(): Promise<WorkflowSummary[]> {
    const response = await n8nRequest('GET', '/workflows');
    const rawList = isRecord(response) && Array.isArray(response.data) ? response.data : [];
    const normalized: WorkflowSummary[] = [];

    for (const item of rawList) {
        if (!isRecord(item)) {
            continue;
        }

        const id = asString(item.id);
        const name = asString(item.name);

        if (id === '' || name === '') {
            continue;
        }

        normalized.push({
            id,
            name,
            active: Boolean(item.active),
            isArchived: Boolean(item.isArchived),
            updatedAt: asString(item.updatedAt) || undefined,
        });
    }

    return normalized;
}

function pickPreferredWorkflow(candidates: WorkflowSummary[]): WorkflowSummary | null {
    if (candidates.length === 0) {
        return null;
    }

    const sorted = [...candidates].sort((left, right) => {
        if (left.isArchived !== right.isArchived) {
            return left.isArchived ? 1 : -1;
        }

        if (left.active !== right.active) {
            return left.active ? -1 : 1;
        }

        const leftTime = left.updatedAt ? Date.parse(left.updatedAt) : 0;
        const rightTime = right.updatedAt ? Date.parse(right.updatedAt) : 0;

        return rightTime - leftTime;
    });

    return sorted[0] ?? null;
}

function normalizeWorkflow(workflow: JsonObject): JsonObject {
    const nodes = Array.isArray(workflow.nodes) ? workflow.nodes : [];

    return {
        name: asString(workflow.name),
        nodes: nodes.map((node) => normalizeNode(node)),
        connections: isRecord(workflow.connections) ? workflow.connections : {},
        settings: sanitizeSettings(workflow.settings),
    };
}

function normalizeNode(node: unknown): JsonObject {
    if (!isRecord(node)) {
        return {
            parameters: {},
            name: '',
            type: '',
            typeVersion: 1,
            position: [0, 0],
        };
    }

    const credentials = isRecord(node.credentials) ? node.credentials : undefined;

    const normalized: JsonObject = {
        parameters: isRecord(node.parameters) ? node.parameters : {},
        name: asString(node.name),
        type: asString(node.type),
        typeVersion: asNumber(node.typeVersion, 1),
        position: normalizePosition(node.position),
    };

    if (credentials !== undefined) {
        normalized.credentials = credentials;
    }

    return normalized;
}

function sanitizeSettings(settings: unknown): JsonObject {
    if (!isRecord(settings)) {
        return {};
    }

    const allowedKeys = ['executionOrder'];
    const result: JsonObject = {};

    for (const key of allowedKeys) {
        if (key in settings) {
            result[key] = settings[key];
        }
    }

    return result;
}

function normalizePosition(position: unknown): [number, number] {
    if (!Array.isArray(position) || position.length < 2) {
        return [0, 0];
    }

    const first = typeof position[0] === 'number' ? position[0] : 0;
    const second = typeof position[1] === 'number' ? position[1] : 0;

    return [first, second];
}

function resolveWorkflowId(response: unknown): string {
    if (!isRecord(response)) {
        return '';
    }

    const direct = asString(response.id);

    if (direct !== '') {
        return direct;
    }

    if (isRecord(response.data)) {
        return asString(response.data.id);
    }

    return '';
}

async function n8nRequest(method: string, endpoint: string, body?: unknown): Promise<unknown> {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

    try {
        const response = await fetch(`${n8nBaseUrl}/api/v1${endpoint}`, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-N8N-API-KEY': n8nApiKey,
            },
            body: body === undefined ? undefined : JSON.stringify(body),
            signal: controller.signal,
        });

        const responseText = await response.text();
        const parsed = safeJsonParse(responseText);

        if (!response.ok) {
            const details = typeof parsed === 'string' ? parsed : JSON.stringify(parsed);
            throw new Error(`${method} ${endpoint} failed with status ${response.status}: ${details}`);
        }

        return parsed;
    } finally {
        clearTimeout(timeout);
    }
}

function safeJsonParse(raw: string): unknown {
    if (raw.trim() === '') {
        return {};
    }

    try {
        return JSON.parse(raw) as unknown;
    } catch {
        return raw;
    }
}

function asString(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function asNumber(value: unknown, fallback: number): number {
    return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
}

function isRecord(value: unknown): value is JsonObject {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function formatError(error: unknown): string {
    if (error instanceof Error) {
        return error.message;
    }

    return String(error);
}

async function main(): Promise<void> {
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error('n8n-direct MCP server running on stdio');
}

main().catch((error) => {
    console.error('Fatal error in n8n-direct MCP server:', formatError(error));
    process.exit(1);
});
