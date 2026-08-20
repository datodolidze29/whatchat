-- the users table: one row per person who signs up
CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,              -- own id, auto-generated. BIGINT so we never run out
    email VARCHAR(255) UNIQUE,                         -- optional (no NOT NULL), but if set it must be unique
    password_hash VARCHAR(255) NOT NULL,               -- store the bcrypt HASH, never the real password. 255 = room for the hash
    phone VARCHAR(20) UNIQUE NOT NULL,                 -- text not a number! phones have +, leading 0s. unique = one account per phone
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,    -- db fills "now" automatically on insert
    username VARCHAR(30) UNIQUE NOT NULL               -- required + unique handle
);
