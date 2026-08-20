-- the messages table: one row per message sent
CREATE TABLE messages (
    sender_id BIGINT NOT NULL,                         -- who sent it (FK to users), NOT auto-increment
    conversation_id BIGINT NOT NULL,                   -- which chat it belongs to. FK lives on the "many" side (many messages per convo)
    message_id BIGINT AUTO_INCREMENT PRIMARY KEY,      -- own id + doubles as the ORDER column (always increasing, never ties)
    content TEXT,                                       -- TEXT = long variable text. nullable so an image can have no caption
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,    -- for DISPLAY only, not for ordering (timestamps can tie)
    file_path VARCHAR(600),                            -- path to the file, NOT the bytes. nullable (text messages have no file)
    type ENUM('text', 'image') NOT NULL,               -- so we know how to render it
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (sender_id) REFERENCES users(id)
);
