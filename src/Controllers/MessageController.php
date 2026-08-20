<?php

namespace App\Controllers;

use App\Repositories\MessageRepository;
use App\Repositories\ConversationRepository;

class MessageController extends Controller
{
    private MessageRepository $messages;
    private ConversationRepository $conversations;

    public function __construct(MessageRepository $messages, ConversationRepository $conversations)
    {
        $this->messages = $messages;
        $this->conversations = $conversations;
    }

    // $userId from token, $conversationId from the URL
    public function store(int $userId, int $conversationId): void
    {
        // 1. AUTHORIZATION: if !isParticipant($userId, $conversationId) -> 403 forbidden, return
        if (!$this->conversations->isParticipant($userId, $conversationId)) {
            $this->json(403, ["error" => "forbidden"]);
            return;
        }
        // 2. read + guard JSON body
        $data = json_decode(file_get_contents("php://input"), true);
        // 3. pull content, type (default 'text'), file_path (?? null)
        $type = $data["type"] ?? "text";
        $filePath = $data["file_path"] ?? null;
        $content = $data["content"] ?? null;
        // 4. validate: type is 'text' or 'image'; a text message must have non-empty content
        if (!in_array($type, ["text", "image"], true)) {
            $this->json(400, ["error" => "invalid type"]);
            return;
        }
        if ($type === "text" && empty($content)) {
            $this->json(400, ["error" => "text message needs content"]);
            return;
        }
        // 5. insert via messages->create(...) with sender_id = $userId (from token, NOT body!)
        $messageId = $this->messages->create($conversationId, $userId, $content, $type, $filePath);
        // 6. 201 with the new message_id
        $this->json(201, ["success" => true, "message_id" => $messageId]);
    }

    public function index(int $userId, int $conversationId): void
    {
        // 1. authorization (same gate as sending)
        if (!$this->conversations->isParticipant($userId, $conversationId)) {
            $this->json(403, ["error" => "forbidden"]);
            return;
        }

        // 2. read pagination params from the query string
        $beforeId = isset($_GET["before_id"]) ? (int) $_GET["before_id"] : null;
        $limit = isset($_GET["limit"]) ? (int) $_GET["limit"] : 30;

        // 3. cap the limit — never let a client request unbounded rows
        $limit = min(max($limit, 1), 100);

        // 4. fetch + return
        $messages = $this->messages->findForConversation($conversationId, $beforeId, $limit);
        $this->json(200, ["messages" => $messages]);
    }
}
