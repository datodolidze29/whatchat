CREATE TABLE messages (
    sender_id BIGINT NOT NULL,
    conversation_id BIGINT NOT NULL,
    message_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(600),
    type ENUM('text', 'image') NOT NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (sender_id) REFERENCES users(id)
);
