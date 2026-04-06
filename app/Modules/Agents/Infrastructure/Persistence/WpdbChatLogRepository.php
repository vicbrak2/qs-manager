<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\Persistence;

use RuntimeException;

final class WpdbChatLogRepository
{
    private readonly string $tableName;

    private ?bool $tableExists = null;

    public function __construct(private readonly \wpdb $wpdb)
    {
        $this->tableName = $this->wpdb->prefix . 'qs_chat_log';
    }

    /**
     * @return array{id: int, session_id: string, turn_index: int}
     */
    public function appendTurn(
        string $sessionId,
        string $userMessage,
        string $botResponse,
        bool $isFallback = false,
        ?string $fallbackReason = null
    ): array {
        if (! $this->tableExists()) {
            throw new RuntimeException('QS chat log table is not available.');
        }

        $turnIndex = $this->nextTurnIndex($sessionId);
        $timestamp = gmdate('Y-m-d H:i:s');

        $inserted = $this->wpdb->insert(
            $this->tableName,
            [
                'session_id' => $sessionId,
                'turn_index' => $turnIndex,
                'user_message' => $userMessage,
                'bot_response' => $botResponse,
                'feedback_rating' => null,
                'is_fallback' => $isFallback ? 1 : 0,
                'fallback_reason' => $fallbackReason,
                'feedback_updated_at' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            throw new RuntimeException('Could not insert chatbot turn log.');
        }

        return [
            'id' => (int) $this->wpdb->insert_id,
            'session_id' => $sessionId,
            'turn_index' => $turnIndex,
        ];
    }

    public function recordFeedback(string $sessionId, int $turnIndex, string $rating): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        $rowId = $this->findRowId($sessionId, $turnIndex);

        if ($rowId === null) {
            return false;
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        $updated = $this->wpdb->update(
            $this->tableName,
            [
                'feedback_rating' => $rating,
                'feedback_updated_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'id' => $rowId,
            ],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            throw new RuntimeException(sprintf('Could not update feedback for chat row %d.', $rowId));
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentTurns(int $limit = 50, ?string $rating = null): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        $limit = max(1, min($limit, 200));

        if ($rating !== null && $rating !== '') {
            /** @var literal-string $sql */
            $sql = "SELECT id, session_id, turn_index, user_message, bot_response, feedback_rating, is_fallback, fallback_reason, created_at, updated_at
                FROM {$this->tableName}
                WHERE feedback_rating = %s
                ORDER BY created_at DESC, id DESC
                LIMIT %d";

            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $rating, $limit),
                ARRAY_A
            );
        } else {
            /** @var literal-string $sql */
            $sql = "SELECT id, session_id, turn_index, user_message, bot_response, feedback_rating, is_fallback, fallback_reason, created_at, updated_at
                FROM {$this->tableName}
                ORDER BY created_at DESC, id DESC
                LIMIT %d";

            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $limit),
                ARRAY_A
            );
        }

        if (! is_array($rows)) {
            return [];
        }

        return array_values(
            array_filter($rows, static fn (mixed $row): bool => is_array($row))
        );
    }

    private function nextTurnIndex(string $sessionId): int
    {
        /** @var literal-string $sql */
        $sql = "SELECT COALESCE(MAX(turn_index), 0) FROM {$this->tableName} WHERE session_id = %s";

        return ((int) $this->wpdb->get_var(
            $this->wpdb->prepare($sql, $sessionId)
        )) + 1;
    }

    private function findRowId(string $sessionId, int $turnIndex): ?int
    {
        /** @var literal-string $sql */
        $sql = "SELECT id FROM {$this->tableName} WHERE session_id = %s AND turn_index = %d LIMIT 1";
        $rowId = $this->wpdb->get_var(
            $this->wpdb->prepare($sql, $sessionId, $turnIndex)
        );

        return is_numeric($rowId) ? (int) $rowId : null;
    }

    private function tableExists(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $this->tableName)
        );

        $this->tableExists = $existing === $this->tableName;

        return $this->tableExists;
    }
}
