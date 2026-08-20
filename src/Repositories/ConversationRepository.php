<?php
namespace App\Repositories;

use PDO;
use Throwable;

class ConversationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // $participantIds already includes the creator; $name is null for direct chats
    public function create(string $type, ?string $name, array $participantIds): int
    {
        $this->pdo->beginTransaction();
        try {
            // 1. INSERT the conversation row (type, name); get its id via lastInsertId()
            $stmt = $this->pdo->prepare("INSERT INTO conversations (type, name) VALUES (?, ?)");
            $stmt->execute([$type, $name]);
            $conversationId = (int) $this->pdo->lastInsertId();

            // 2. prepare the participant INSERT once, then loop over $participantIds
            //    and execute it for each (conversation_id, user_id)

            $stmt = $this->pdo->prepare(
                "INSERT INTO conversation_participants (user_id, conversation_id) VALUES (?, ?)",
            );
            foreach ($participantIds as $pi) {
                $stmt->execute([$pi, $conversationId]);
            }

            // 3. commit and return the conversation id
            $this->pdo->commit();
            return $conversationId;
        } catch (Throwable $e) {
            $this->pdo->rollBack(); // undo EVERYTHING on any failure
            throw $e; // re-throw so the controller can respond
        }
    }
}
