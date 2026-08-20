# WhatsApp-Style Chat Backend — Study Notes

A short reference of the design decisions and the *why* behind each. PHP + MySQL (+ Redis).

---

## 1. Core data model

Three tables:

- **`users`**
- **`conversations`** — has a `type` (direct vs group) and `created_at`.
- **`conversation_participants`** — the join table (who is in what chat).

**Key ideas**
- A **many-to-many** relationship (users ↔ conversations) needs a **join table**. Each row = one membership fact ("user X is in conversation Y"). 50 members = 50 rows.
- A 1-on-1 is **just a conversation with two participants** — no separate table. One model, three behaviors (self / 1-on-1 / group).
- `type` is stored on the conversation because it's an **independent fact decided at creation**, not something to recompute from the member count.
- Join table primary key = **composite `(user_id, conversation_id)`** — because neither column alone is unique, but the *pair* is. This also stops the same user being added twice.
- Rule: **a primary key enforces uniqueness — put it on whatever is actually unique.**

---

## 2. Messages

Columns: `message_id` (PK, auto-increment), `conversation_id` (FK), `sender_id` (FK), `content`, `created_at`.

**Key ideas**
- A message points to its conversation via a **foreign key**. In a one-to-many, the **FK lives on the "many" side** (a message has one home; a conversation has many messages).
- Test for FK direction: say both sentences, keep the true one. "One conversation has many messages" ✓ → messages is the many side → FK goes there.
- **Ordering is a read-time problem, not a write-time one.** Don't sort by `created_at` (two messages can share a timestamp = ties). Sort by the **auto-increment `message_id`** — always increasing, never ties. Keep `created_at` only for *display*.

---

## 3. Pagination (keyset / cursor)

**Never use `LIMIT ... OFFSET`** for deep scroll — `OFFSET` makes the DB *walk and discard* all skipped rows; cost grows the deeper you go, and new messages shift positions (duplicates/skips).

**Keyset pagination instead:** remember the id of the last message shown, then:
```
messages WHERE conversation_id = ? AND message_id < <cursor>
ORDER BY message_id DESC
LIMIT 30
```
- Uses the index to **jump** straight to the spot → same cost on page 2 or page 2000.
- Fetch **descending** (nearest-older 30), then **reverse in memory** for display (humans read oldest→newest).
- Lesson: the order that's *efficient to query* ≠ the order that's *meaningful to display*.

---

## 4. Read receipts

- **Don't** put `is_read` on messages — one boolean can't hold "who read it" for a 50-person group (that's many facts).
- Store **one integer per participant**: `last_read_message_id` on the `conversation_participants` row.
- Because messages are ordered by id, "read up to 4003" implies everything ≤ 4003 is read. One number = whole read history.
- **Unread count:** `COUNT(messages WHERE conversation_id = ? AND message_id > last_read AND sender_id != me)`.
- **Group "seen by":** a participant read message N if their `last_read_message_id >= N`.
- Lesson: one small, well-chosen piece of state answers many questions.

---

## 5. Presence — online status & typing (Redis, not MySQL)

Match storage to the *nature* of the data. Presence is **ephemeral** (short-lived, current-value-only) → use **Redis (in-memory)**, not disk-based MySQL.
- Losing a message on restart = disaster (→ MySQL, durable). Losing "is typing" on restart = fine (→ Redis, disposable).

**Use TTL (time-to-live) auto-expiry — never send a "stopped" signal.**
- **Typing:** write `typing:conv42:dato` with a ~5s TTL; the app refreshes it while typing. Stop typing (or crash) → refreshes stop → key expires → indicator vanishes. Absence of a refresh *is* the stop.
- **Online:** app sends a heartbeat (~30s); write `online:dato` with a **longer** TTL (~60s). Key exists → online. Key gone → offline. You never store "offline" — it's just the key's absence.
- TTL > heartbeat interval so a slightly-late heartbeat doesn't falsely flip the user offline.
- Lesson: prefer designs where the correct end-state happens automatically (expiry) over ones that depend on a cleanup message arriving.

---

## 6. Image uploads & file storage

**Never store file bytes in the database.** Big blobs bloat every query, backup, and the cache.

- File bytes → **file/object storage** (S3, or a files dir for learning).
- Database → stores only a **path/URL string** + metadata (`type`, sender, size). Coat in the back room; ticket in your wallet.
- Message row for an image: add a `type` (`'text'` / `'image'`) and a **path** column; caption reuses `content`.
- **Upload order (forced by data dependency):** receive bytes → save to storage → get path back → *then* insert the row with that path. (You can't store the path before you know it.)
- Same principle, three costumes: point at rows, point at conversations, point at files. **Store the heavy thing once, reference it with something small.**

---

## 7. Message search

- **`LIKE '%word%'` = full table scan** (reads every message). A normal index is sorted by the whole text, so it can't find a word in the *middle*.
- Use a **full-text index** (an **inverted index**: maps *word → messages*, like the index at the back of a book). Start with **MySQL `FULLTEXT`**; only graduate to Elasticsearch at real scale.
- **Authorization is part of the query:** match the word **AND** restrict to the user's own conversations (via `conversation_participants`). Never let someone search all messages.
- Trade-off: indexes make **reads fast but writes slightly slower** (index must stay updated).

---

## 8. API layer (REST)

Organize around **things (nouns)**, acted on by **HTTP methods**. Use plural nouns, nest by ownership.

- `GET /conversations` — list my chats
- `GET /conversations/42/messages?before_id=1000&limit=30` — page of messages (cursor + limit are **query params**)
- `POST /conversations/42/messages` — send a message (**create**)
- `PATCH .../read` (or POST) — mark as read (**update** existing state)

Rules: **path = identity, query string = options.** Method carries meaning: `GET` read, `POST` create, `PATCH`/`PUT` update, `DELETE` remove. A good API is **predictable** — see 3 endpoints, guess the rest.

---

## 9. Security (think like an attacker)

- **Authentication ≠ Authorization.** Logged-in ≠ allowed. Auth once at login; **check permission on *every* request** (does a `conversation_participants` row exist for this user + chat?). Skipping this = IDOR / broken access control (just changing the id in the URL).
- **SQL injection:** never concatenate user input into SQL. Use **prepared statements / parameterized queries** (placeholders) — data travels in a separate channel and is never run as commands. PHP: PDO or mysqli.
- **XSS:** user text rendered raw in a browser can run as code. **Escape output** when displaying user content.
- Master rule: **never trust input, never trust output.** All user input is untrusted, everywhere it enters.

---

## 10. Performance

- **Measure, don't guess.** Find the actually-slow query with real data.
- Villain: the **full table scan** (cheap on 1k rows, deadly on 50M). Hero: the **index** (lets the DB *jump*, not scan).
- Use **`EXPLAIN`** to see if a query uses an index or scans.
- Indexes aren't free: **slower writes + more disk.** Index only the columns you filter/sort/join on.
- Watch for the **N+1 query** (1 list query + N per-row queries). Fetch in bulk instead.
- Cache hot results in **Redis** to avoid hitting MySQL.
- **Avoid premature optimization** — build correct first, measure under load, then fix the specific slow thing.

---

### Recurring principles (the real lessons)
- A column can't hold a list → point at it with something small (FK / path / cursor).
- Match the storage to the data's nature (durable vs ephemeral).
- Store one well-chosen fact that answers many questions.
- Efficient-to-query order ≠ meaningful-to-display order.
- Trust nothing from the user; check permission every time.
- Measure before optimizing; reach for big tools only when small ones actually break.

*Next step: start coding Milestone 1 (the schema) — bring it back for a real code review.*
