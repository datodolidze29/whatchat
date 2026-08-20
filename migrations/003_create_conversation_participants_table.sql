-- join table: links users <-> conversations (many-to-many). one row = "this user is in this chat"
CREATE TABLE conversation_participants (
    user_id BIGINT NOT NULL,
    conversation_id BIGINT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_message_id BIGINT,                       -- read-receipt pointer: "read up to here". nullable (new member read nothing)
    role ENUM('member', 'admin'),                      -- per-person role in the chat
    PRIMARY KEY (user_id, conversation_id),            -- COMPOSITE key: the PAIR is unique (also blocks adding same user twice)
    FOREIGN KEY (user_id) REFERENCES users(id),        -- must point to a real user
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) -- must point to a real conversation
);
