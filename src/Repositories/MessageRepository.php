<?php

namespace App\Repositories;

use PDO;

class MessageRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $conversationId, int $senderId, ?string $content, string $type, ?string $filePath): int
    {
        // prepare + execute an INSERT into messages (conversation_id, sender_id, content, type, file_path)
        $stmt = $this->pdo->prepare(
            "INSERT INTO messages (conversation_id, sender_id, content, type, file_path) VALUES (?,?,?,?,?)",
        );
        $stmt->execute([$conversationId, $senderId, $content, $type, $filePath]);
        // return the new message_id via lastInsertId()
        return (int) $this->pdo->lastInsertId();
    }

    public function findForConversation(int $conversationId, ?int $beforeId, int $limit): array
    {
        $sql = "SELECT message_id, conversation_id, sender_id, content, type, file_path, created_at
                FROM messages
                WHERE conversation_id = ?";
        if ($beforeId !== null) {
            $sql .= " AND message_id < ?";
        }
        $sql .= " ORDER BY message_id DESC LIMIT ?";

        $stmt = $this->pdo->prepare($sql);

        $pos = 1;
        $stmt->bindValue($pos++, $conversationId, PDO::PARAM_INT);
        if ($beforeId !== null) {
            $stmt->bindValue($pos++, $beforeId, PDO::PARAM_INT);
        }
        $stmt->bindValue($pos++, $limit, PDO::PARAM_INT); // the one that MUST be PARAM_INT

        $stmt->execute(); // no array here — we bound each value above

        $rows = $stmt->fetchAll();
        return array_reverse($rows);
    }
}
