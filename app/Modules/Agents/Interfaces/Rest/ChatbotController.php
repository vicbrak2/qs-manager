<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Interfaces\Rest;

use QS\Core\Logging\Logger;
use QS\Modules\Agents\Infrastructure\N8n\ChatbotGateway;
use QS\Modules\Agents\Infrastructure\Persistence\WpdbChatLogRepository;
use QS\Modules\Agents\Infrastructure\Wordpress\ChatbotFallbackResponder;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ChatbotController
{
    public function __construct(
        private readonly ChatbotGateway $gateway,
        private readonly ChatbotFallbackResponder $fallbackResponder,
        private readonly WpdbChatLogRepository $chatLogRepository,
        private readonly Logger $logger
    ) {
    }

    public function chat(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $message = trim((string) $request->get_param('message'));

        if ($message === '') {
            return new WP_Error('missing_message', 'El mensaje no puede estar vacío.', ['status' => 400]);
        }

        $sessionId = $this->resolveSessionId($request);

        $reply = $this->gateway->ask($message, $sessionId);

        if (is_wp_error($reply)) {
            if ($this->shouldUseFallback($reply)) {
                $payload = $this->fallbackResponder->unavailableResponse($reply);
                $turnMeta = $this->logTurn(
                    $sessionId,
                    $message,
                    (string) $payload['response'],
                    true,
                    (string) $payload['fallback_reason']
                );

                return new WP_REST_Response(array_merge($payload, $turnMeta), 200);
            }

            return $reply;
        }

        $turnMeta = $this->logTurn($sessionId, $message, $reply, false, null);

        return new WP_REST_Response([
            'success'  => true,
            'response' => $reply,
            'session_id' => $sessionId,
            'turn_index' => $turnMeta['turn_index'],
        ], 200);
    }

    public function feedback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sessionId = $this->sanitizeSessionId((string) $request->get_param('session_id'));
        $turnIndex = (int) $request->get_param('turn_index');
        $rating = trim((string) $request->get_param('rating'));

        if ($sessionId === '') {
            return new WP_Error('missing_session_id', 'Debes enviar un session_id valido.', ['status' => 400]);
        }

        if ($turnIndex <= 0) {
            return new WP_Error('missing_turn_index', 'Debes enviar un turn_index valido.', ['status' => 400]);
        }

        if (! in_array($rating, ['good', 'bad'], true)) {
            return new WP_Error('invalid_rating', 'El rating debe ser good o bad.', ['status' => 400]);
        }

        try {
            $stored = $this->chatLogRepository->recordFeedback($sessionId, $turnIndex, $rating);
        } catch (\Throwable $exception) {
            $this->logger->warning('QS chatbot feedback failed: ' . $exception->getMessage());

            return new WP_Error('feedback_storage_error', 'No se pudo guardar el feedback.', ['status' => 500]);
        }

        if (! $stored) {
            return new WP_Error('feedback_turn_not_found', 'No se encontro el turno indicado.', ['status' => 404]);
        }

        return new WP_REST_Response([
            'success' => true,
            'session_id' => $sessionId,
            'turn_index' => $turnIndex,
            'rating' => $rating,
        ], 200);
    }

    private function shouldUseFallback(WP_Error $error): bool
    {
        if ($error->get_error_code() === 'n8n_connection_error') {
            return true;
        }

        $data = $error->get_error_data();

        if (! is_array($data) || ! isset($data['status']) || ! is_numeric($data['status'])) {
            return false;
        }

        return (int) $data['status'] >= 500;
    }

    /**
     * @return array{session_id: string, turn_index: int|null}
     */
    private function logTurn(
        string $sessionId,
        string $message,
        string $response,
        bool $isFallback,
        ?string $fallbackReason
    ): array {
        try {
            $turn = $this->chatLogRepository->appendTurn($sessionId, $message, $response, $isFallback, $fallbackReason);

            return [
                'session_id' => $sessionId,
                'turn_index' => $turn['turn_index'],
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('QS chatbot turn log failed: ' . $exception->getMessage());

            return [
                'session_id' => $sessionId,
                'turn_index' => null,
            ];
        }
    }

    private function resolveSessionId(WP_REST_Request $request): string
    {
        $requestedSessionId = $this->sanitizeSessionId((string) $request->get_param('session_id'));

        if ($requestedSessionId !== '') {
            return $requestedSessionId;
        }

        if (is_user_logged_in()) {
            return 'wp_user_' . get_current_user_id();
        }

        return 'anon_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    private function sanitizeSessionId(string $sessionId): string
    {
        $sessionId = trim($sessionId);

        if ($sessionId === '') {
            return '';
        }

        $sessionId = preg_replace('/[^a-zA-Z0-9_\-:.]/', '_', $sessionId);

        if (! is_string($sessionId)) {
            return '';
        }

        return substr($sessionId, 0, 120);
    }
}
