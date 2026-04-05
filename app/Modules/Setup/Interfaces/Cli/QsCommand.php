<?php

declare(strict_types=1);

namespace QS\Modules\Setup\Interfaces\Cli;

use QS\Modules\Agents\Application\CommandHandler\ReindexContentHandler;
use QS\Modules\Agents\Infrastructure\N8n\ChatbotGateway;
use QS\Modules\Setup\Application\Command\SetupSiteCommand;
use QS\Modules\Setup\Application\CommandHandler\SetupSiteHandler;
use QS\Modules\Setup\Infrastructure\Wordpress\AgentStatusChecker;

final class QsCommand
{
    public function __construct(
        private readonly SetupSiteHandler $setupSiteHandler,
        private readonly ReindexContentHandler $reindexContentHandler,
        private readonly ChatbotGateway $chatbotGateway,
        private readonly AgentStatusChecker $agentStatusChecker
    ) {
    }

    /**
     * Configura el sitio base de QS.
     *
     * ## OPTIONS
     *
     * [--site-name=<name>]
     * : Nombre del sitio.
     *
     * [--site-description=<description>]
     * : Descripcion del sitio.
     *
     * [--permalink-structure=<structure>]
     * : Estructura de enlaces permanentes.
     *
     * [--menu-name=<name>]
     * : Nombre del menu principal.
     *
     * [--menu-location=<location>]
     * : Ubicacion del menu en el tema.
     *
     * [--front-page-slug=<slug>]
     * : Slug de la pagina que se fija como portada.
     *
     * [--force]
     * : Actualiza paginas existentes en vez de solo reutilizarlas.
     */
    public function setup(array $args, array $assocArgs): void
    {
        $command = SetupSiteCommand::fromInput($assocArgs, SetupSiteCommand::defaults());
        $result = $this->setupSiteHandler->handle($command);

        $this->render($result);
        \WP_CLI::success('QS setup ejecutado.');
    }

    /**
     * Reindexa posts y paginas hacia el pipeline RAG.
     *
     * ## OPTIONS
     *
     * [--post-types=<types>]
     * : Lista separada por comas. Default: post,page
     */
    public function reindex(array $args, array $assocArgs): void
    {
        $postTypes = isset($assocArgs['post-types'])
            ? array_values(array_filter(array_map('trim', explode(',', (string) $assocArgs['post-types']))))
            : ['post', 'page'];

        $result = $this->reindexContentHandler->handle($postTypes);

        $this->render($result);

        if (($result['failed'] ?? 0) > 0) {
            \WP_CLI::warning('La reindexacion termino con documentos fallidos.');
            return;
        }

        \WP_CLI::success('Reindexacion completada.');
    }

    /**
     * Envia un mensaje al chatbot.
     *
     * ## OPTIONS
     *
     * <message>
     * : Mensaje a enviar.
     *
     * [--session=<id>]
     * : Session id para el agente.
     */
    public function chat(array $args, array $assocArgs): void
    {
        $message = trim(implode(' ', $args));

        if ($message === '') {
            \WP_CLI::error('Debes enviar un mensaje. Ejemplo: wp qs chat "que servicios tienen?"');
        }

        $sessionId = trim((string) ($assocArgs['session'] ?? 'wp_cli'));
        $reply = $this->chatbotGateway->ask($message, $sessionId !== '' ? $sessionId : 'wp_cli');

        if (is_wp_error($reply)) {
            \WP_CLI::error($reply->get_error_message());
        }

        $this->render([
            'message' => $message,
            'session_id' => $sessionId !== '' ? $sessionId : 'wp_cli',
            'response' => $reply,
        ]);
        \WP_CLI::success('Respuesta del chatbot recibida.');
    }

    /**
     * Consulta el estado de n8n y Qdrant.
     */
    public function status(array $args, array $assocArgs): void
    {
        $status = $this->agentStatusChecker->check();

        $this->render($status);

        if (($status['overall_ok'] ?? false) === true) {
            \WP_CLI::success('Agentes disponibles.');
            return;
        }

        \WP_CLI::warning('Al menos un servicio de agentes no esta disponible.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function render(array $payload): void
    {
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        \WP_CLI::log(is_string($json) ? $json : print_r($payload, true));
    }
}
