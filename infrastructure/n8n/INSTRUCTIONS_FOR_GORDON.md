# N8N Deployment Context for Gordon (Docker AI)

Hello Gordon,

The user is trying to deploy an **n8n** container using Docker Compose. We have already generated the necessary configuration files, but the image extraction/pull process seems to be taking longer than expected or the user needs assistance verifying the network and deployment status on their machine.

## Project Context
- **Objective:** Deploy n8n to act as the AI Orchestrator (RAG Chatbot) behind a WordPress API.
- **Port Mapping:** `5678:5678`
- **Volume Mounts:** 
  - `n8n_data:/home/node/.n8n`
  - `./:/import` (to securely import the JSON workflow)
- **Desired Action:** Deploy the container, ensure it is healthy, and subsequently run the import command for the workflow.

## Existing Files in this Directory (`infrastructure/n8n/`)
1. `docker-compose.yml` (The stack definition for n8n)
2. `chatbot_rag_workflow.json` (The n8n Langchain workflow to be imported)

## Commands to Execute
If the container is not up yet, please help the user verify their Docker Desktop daemon and network, and then execute:
```bash
# 1. Start the container in detached mode
docker compose up -d

# 2. Verify it is running and healthy
docker ps | findstr n8n

# 3. Once running, import the pre-built workflow into n8n
docker compose exec n8n n8n import:workflow --input=/import/chatbot_rag_workflow.json
```

## Troubleshooting
Please check if the `docker.n8n.io/n8nio/n8n:latest` image is stuck pulling. If they have network restrictions, suggest pulling `n8nio/n8n` from Docker Hub instead as an alternative.
