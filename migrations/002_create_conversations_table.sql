-- the conversations table: one row per chat (both 1-on-1 AND groups are just conversations)
CREATE TABLE conversations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    name VARCHAR(100),                        -- nullable: a direct chat has no name, a group does
    type ENUM('direct', 'group') NOT NULL     -- ENUM = db itself rejects anything that isn't one of these two (no typos get in)
);
