<?php

declare(strict_types=1);

namespace QS\Modules\Agents;

use function DI\autowire;
use function DI\factory;

use QS\Core\Contracts\ModuleServiceProviderInterface;
use QS\Modules\Agents\Application\CommandHandler\ReindexContentHandler;
use QS\Modules\Agents\Infrastructure\Chatbot\ChatbotProfile;
use QS\Modules\Agents\Infrastructure\Chatbot\QuickReplyMatcher;
use QS\Modules\Agents\Infrastructure\N8n\ChatbotGateway;
use QS\Modules\Agents\Infrastructure\N8n\IngestGateway;
use QS\Modules\Agents\Infrastructure\N8n\WhatsAppGateway;
use QS\Modules\Agents\Infrastructure\Persistence\WpdbChatLogRepository;
use QS\Modules\Agents\Infrastructure\Qdrant\QdrantGateway;
use QS\Modules\Agents\Infrastructure\Wordpress\ChatbotFallbackResponder;
use QS\Modules\Agents\Infrastructure\Wordpress\ChatbotShortcode;
use QS\Modules\Agents\Infrastructure\Wordpress\ReindexAdminPage;
use QS\Modules\Agents\Interfaces\Rest\ChatbotController;
use QS\Modules\Agents\Interfaces\Rest\WhatsAppOptionsController;

final class AgentsServiceProvider implements ModuleServiceProviderInterface
{
    public static function definitions(): array
    {
        return [
            ChatbotProfile::class => factory(static fn (): ChatbotProfile => ChatbotProfile::resolveDefault()),
            QuickReplyMatcher::class => autowire(),
            ChatbotGateway::class => autowire(),
            IngestGateway::class => autowire(),
            WhatsAppGateway::class => autowire(),
            WpdbChatLogRepository::class => autowire(),
            QdrantGateway::class => autowire(),
            ChatbotFallbackResponder::class => autowire(),
            ReindexContentHandler::class => autowire(),
            ReindexAdminPage::class => autowire(),
            ChatbotShortcode::class => autowire(),
            ChatbotController::class => autowire(),
            WhatsAppOptionsController::class => autowire(),
        ];
    }

    public static function commandHandlers(): array
    {
        return [];
    }

    public static function queryHandlers(): array
    {
        return [];
    }

    public static function hookables(): array
    {
        return [
            ReindexAdminPage::class,
            ChatbotShortcode::class,
        ];
    }

    public static function activationHooks(): array
    {
        return [];
    }
}
