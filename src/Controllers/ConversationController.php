<?php

namespace App\Controllers;

use App\Repositories\ConversationRepository;

// handles creating conversations (the HTTP side). the route in index.php authenticates first and passes me the user id
class ConversationController extends Controller
{
    private ConversationRepository $conversations;

    public function __construct(ConversationRepository $conversations)
    {
        $this->conversations = $conversations;
    }

    // $userId comes from the token (verified in index.php), NOT from the body -> can't be faked
    public function create(int $userId): void
    {
        $data = json_decode(file_get_contents("php://input"), true);
        // validate: type present + is one of the allowed enum values, and participant_ids is a non-empty array
        if (
            empty($data["type"]) ||
            empty($data["participant_ids"]) ||
            !in_array($data["type"], ["direct", "group"], true) || // true = strict comparison (type + value)
            !is_array($data["participant_ids"])
        ) {
            $this->json(400, ["error" => "not enough credentials"]);
            return;
        }

        $type = $data["type"] ?? null;
        $name = $data["name"] ?? null; // null for direct chats, a name for groups
        $participantIds = $data["participant_ids"] ?? null;

        $participantIds[] = $userId; // ALWAYS add the creator - you're a member of the chat you make
        $participantIds = array_values(array_unique($participantIds)); // drop dupes (in case they listed themselves) + reindex

        try {
            $id = $this->conversations->create($type, $name, $participantIds); // repo does the transaction
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            // e.g. a participant id that doesn't exist -> FK fails -> repo rolls back -> lands here
            $this->json(400, ["error" => "could not create conversation"]);
            return;
        }

        $this->json(201, ["success" => true, "conversation_id" => $id]); // send back the new id so client can use it
    }

    public function index(int $userId): void
    {
        $conversations = $this->conversations->findForUser($userId);
        $this->json(200, ["conversations" => $conversations]);
    }

    public function markRead(int $userId, int $conversationId): void
    {
        // 1. authorization gate
        if (!$this->conversations->isParticipant($userId, $conversationId)) {
            $this->json(403, ["error" => "forbidden"]);
            return;
        }
        // 2. read + validate the message id from the body
        $data = json_decode(file_get_contents("php://input"), true);
        $messageId = $data["last_read_message_id"] ?? null;
        if (!is_int($messageId) || $messageId < 1) {
            $this->json(400, ["error" => "last_read_message_id must be a positive integer"]);
            return;
        }
        // 3. update + respond
        $this->conversations->markRead($userId, $conversationId, $messageId);
        $this->json(200, ["success" => true]);
    }
}
