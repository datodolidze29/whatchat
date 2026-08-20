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

    public function create(string $type, ?string $name, array $participantIds): int
    {
        //get convo type E>g only two or many like DM or group
        $this->pdo->beginTransaction(); //starts an explicit transaction in SQL, grouping multiple queries into a single, atomic unit of work (google)
        try {
            $stmt = $this->pdo->prepare("INSERT INTO conversations (type, name) VALUES (?, ?)"); //prepares conversation table insert
            $stmt->execute([$type, $name]); //executing
            $conversationId = (int) $this->pdo->lastInsertId(); //gets the auto increment ID of last row it inserted

            $stmt = $this->pdo->prepare(
                "INSERT INTO conversation_participants (user_id, conversation_id) VALUES (?, ?)", //prepares convo participants table
            );
            foreach ($participantIds as $pi) {
                $stmt->execute([$pi, $conversationId]); // insert one, two or many participants as user ids into the conversation
            }

            $this->pdo->commit(); // Commits a transaction, returning the database connection to autocommit mode until the next call to PDO::beginTransaction() starts a new transaction. (php docs)
            return $conversationId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // returns the list of chats this user is in, each with: last message preview, its time, and how many I haven't read
    public function findForUser(int $userId): array
    {
        // big query but read it top-to-bottom in this order: FROM -> JOINs -> WHERE -> the SELECT columns
        $sql = "SELECT
            c.id,                              -- the conversation's own columns (c = alias for 'conversations')
            c.type,
            c.name,
            c.created_at,

            lm.content    AS last_message,     -- content of the newest message (lm = the latest message row, joined below)
            lm.created_at AS last_message_at,  -- when that newest message was sent

            (
                -- CORRELATED SUBQUERY: runs once PER conversation row (it uses c.id from the outer query).
                -- counts messages in THIS conversation that I haven't read yet = my unread badge number
                SELECT COUNT(*)
                FROM messages m
                WHERE m.conversation_id = c.id                          -- only messages in this conversation
                  AND m.sender_id <> ?                                  -- <> means != ; don't count my OWN messages
                  AND m.message_id > COALESCE(cp.last_read_message_id, 0) -- only messages newer than where I've read.
                                                                         -- COALESCE(x,0): if last_read is NULL (read nothing) use 0 -> everything counts
            ) AS unread_count

        FROM conversations c                    -- start from the conversations table, call it 'c'

        JOIN conversation_participants cp        -- INNER JOIN: keep only conversations that have a matching participant row...
            ON cp.conversation_id = c.id         -- ...matched on the shared key

        LEFT JOIN messages lm                    -- LEFT JOIN so a chat with NO messages still shows up (lm just becomes NULL)
            ON lm.message_id = (                 -- join the ONE latest message: the row whose id = the max id in this conversation
                SELECT MAX(message_id)
                FROM messages
                WHERE conversation_id = c.id
            )

        WHERE cp.user_id = ?                     -- AUTHORIZATION: only conversations where the participant is ME. can't leak others' chats

        ORDER BY lm.created_at DESC;"; //-- most recently active chats first (like WhatsApp's list)

        $stmt = $this->pdo->prepare($sql);
        // TWO ? placeholders, order matters: 1st is inside the unread subquery (sender_id <> ?), 2nd is WHERE cp.user_id = ?
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(); // returns all rows (empty array if I'm in no conversations)
    }

    // authorization check: is this user a member of this conversation? (used before sending/reading messages)
    public function isParticipant(int $userId, int $conversationId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM conversation_participants WHERE user_id = ? AND conversation_id = ?", // SELECT 1 = I only care IF a row exists
        );
        $stmt->execute([$userId, $conversationId]);
        return (bool) $stmt->fetch(); // row exists -> true (member), no row -> false
    }

    // move my "read up to here" pointer forward for this conversation (this is how read receipts / unread counts work)
    public function markRead(int $userId, int $conversationId, int $lastReadMessageId): void
    {
        $stmt = $this->pdo->prepare(
            // UPDATE (row already exists) the one participant row identified by the composite key
            "UPDATE conversation_participants SET last_read_message_id = ? WHERE user_id = ? AND conversation_id = ?",
        );
        $stmt->execute([$lastReadMessageId, $userId, $conversationId]);
    }
}
