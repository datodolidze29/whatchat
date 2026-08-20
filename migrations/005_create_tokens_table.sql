-- the tokens table: opaque login tokens (one user can have many - phone + laptop = 2 rows)
CREATE TABLE tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,             -- single-column PK is fine here (one column is already unique)
    user_id BIGINT NOT NULL,                          -- who owns the token. repeats (many tokens per user) so it's just a plain FK
    token VARCHAR(64) UNIQUE NOT NULL,                -- 64 hex chars. UNIQUE -> also gives a fast index for the per-request lookup
    expires_at DATETIME NOT NULL,                     -- when it stops working (checked with expires_at > NOW())
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
