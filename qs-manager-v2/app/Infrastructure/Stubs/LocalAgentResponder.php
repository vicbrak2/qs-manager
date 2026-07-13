<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Stubs;

use QSManager\Domain\Agents\AgentResponder;

final class LocalAgentResponder implements AgentResponder
{
    public function reply(string $message): string
    {
        if (trim($message) === '') {
            return 'Stub local activo. Envia un mensaje para simular una respuesta sin LLM ni internet.';
        }

        return 'Stub local activo. Recibi tu mensaje, pero Agents V2 aun no llama LLMs, Qdrant ni WhatsApp.';
    }
}

