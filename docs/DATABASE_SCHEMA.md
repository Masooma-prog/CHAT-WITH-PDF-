# Database Schema - Chat with PDF

**Last Updated:** February 3, 2026  
**Status:** Phase 3 Complete ✅

---

## Overview

This document describes the complete database schema for the Chat with PDF application after Phase 3 implementation.

---

## Tables

### 1. **pdfs** (Main PDF Storage)

Stores uploaded PDF files and their metadata.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `id` | bigint | NO | PRI | auto_increment | Primary key |
| `user_id` | bigint | NO | MUL | - | Foreign key to users table |
| `title` | varchar | YES | MUL | NULL | PDF title (extracted from filename) |
| `original_name` | varchar | NO | - | - | Original uploaded filename |
| `file_path` | varchar | NO | - | - | Storage path (pdfs/xxx.pdf) |
| `size` | bigint | NO | - | 0 | File size in bytes |
| `status` | varchar | NO | - | 'uploaded' | Processing status |
| `text` | longtext | YES | - | NULL | Extracted text content |
| `pages` | int | NO | - | 0 | Number of pages |
| `meta` | json | YES | - | NULL | Additional metadata |
| `created_at` | timestamp | YES | MUL | NULL | Creation timestamp |
| `updated_at` | timestamp | YES | - | NULL | Last update timestamp |

**Status Values:**
- `uploaded` - Phase 2: File uploaded, not processed
- `text_extracted` - Phase 3: Text extracted successfully
- `processing` - Phase 4+: Being processed by Python service
- `ready` - Phase 9: Fully processed and ready for chat

**Meta JSON Structure:**
```json
{
  "uploaded_at": "2026-02-03T10:30:00Z",
  "phase": 3,
  "python_pdf_id": "abc123",
  "ocr_used": true,
  "questions_status": "completed"
}
```

---

### 2. **users** (User Authentication)

Stores user accounts.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `id` | bigint | NO | PRI | auto_increment | Primary key |
| `name` | varchar | NO | - | - | User's full name |
| `email` | varchar | NO | UNI | - | Email address (unique) |
| `email_verified_at` | timestamp | YES | - | NULL | Email verification timestamp |
| `password` | varchar | NO | - | - | Hashed password |
| `remember_token` | varchar | YES | - | NULL | Remember me token |
| `created_at` | timestamp | YES | - | NULL | Account creation timestamp |
| `updated_at` | timestamp | YES | - | NULL | Last update timestamp |

---

### 3. **chat_sessions** (Chat Conversations)

Stores chat sessions between users and PDFs.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `id` | bigint | NO | PRI | auto_increment | Primary key |
| `pdf_id` | bigint | YES | MUL | NULL | Foreign key to pdfs table |
| `title` | varchar | NO | - | - | Session title |
| `created_at` | timestamp | YES | MUL | NULL | Session start timestamp |
| `updated_at` | timestamp | YES | - | NULL | Last message timestamp |

---

### 4. **chat_messages** (Individual Messages)

Stores individual messages within chat sessions.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `id` | bigint | NO | PRI | auto_increment | Primary key |
| `session_id` | bigint | NO | MUL | - | Foreign key to chat_sessions |
| `sender` | enum | NO | MUL | - | 'user' or 'bot' |
| `message` | longtext | NO | - | - | Message content |
| `meta` | json | YES | - | NULL | Additional metadata |
| `created_at` | timestamp | YES | - | NULL | Message timestamp |
| `updated_at` | timestamp | YES | - | NULL | Last update timestamp |

**Sender Values:**
- `user` - Message from user
- `bot` - Response from AI assistant

**Meta JSON Structure:**
```json
{
  "sources": ["chunk_1", "chunk_2"],
  "confidence": 0.95,
  "processing_time": 2.3
}
```

---

### 5. **predefined_questions** (Quick Questions)

Stores AI-generated questions for each PDF.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `id` | bigint | NO | PRI | auto_increment | Primary key |
| `pdf_id` | bigint | NO | MUL | - | Foreign key to pdfs table |
| `title` | varchar | YES | MUL | NULL | Question title/category |
| `question` | text | NO | - | - | Question text |
| `source` | varchar | YES | - | NULL | Source/origin of question |
| `answer` | longtext | YES | - | NULL | Pre-computed answer |
| `created_at` | timestamp | YES | - | NULL | Creation timestamp |
| `updated_at` | timestamp | YES | - | NULL | Last update timestamp |

---

### 6. **sessions** (Laravel Sessions)

Stores user session data.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `id` | varchar | NO | PRI | - | Session ID |
| `user_id` | bigint | YES | MUL | NULL | Foreign key to users |
| `ip_address` | varchar | YES | - | NULL | User's IP address |
| `user_agent` | text | YES | - | NULL | Browser user agent |
| `payload` | longtext | NO | - | - | Session data |
| `last_activity` | int | NO | MUL | - | Last activity timestamp |

---

### 7. **cache** (Application Cache)

Stores cached data for performance.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `key` | varchar | NO | PRI | - | Cache key |
| `value` | mediumtext | NO | - | - | Cached value |
| `expiration` | int | NO | - | - | Expiration timestamp |

---

### 8. **cache_locks** (Cache Locking)

Prevents race conditions in cache operations.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `key` | varchar | NO | PRI | - | Lock key |
| `owner` | varchar | NO | - | - | Lock owner |
| `expiration` | int | NO | - | - | Lock expiration |

---

### 9. **jobs** (Queue Jobs)

Stores queued background jobs.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `id` | bigint | NO | PRI | auto_increment | Primary key |
| `queue` | varchar | NO | MUL | - | Queue name |
| `payload` | longtext | NO | - | - | Job data |
| `attempts` | tinyint | NO | - | - | Attempt count |
| `reserved_at` | int | YES | - | NULL | Reserved timestamp |
| `available_at` | int | NO | - | - | Available timestamp |
| `created_at` | int | NO | - | - | Creation timestamp |

---

### 10. **failed_jobs** (Failed Queue Jobs)

Stores jobs that failed processing.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `id` | bigint | NO | PRI | auto_increment | Primary key |
| `uuid` | varchar | NO | UNI | - | Unique job identifier |
| `connection` | text | NO | - | - | Queue connection |
| `queue` | text | NO | - | - | Queue name |
| `payload` | longtext | NO | - | - | Job data |
| `exception` | longtext | NO | - | - | Exception details |
| `failed_at` | timestamp | NO | - | CURRENT_TIMESTAMP | Failure timestamp |

---

### 11. **job_batches** (Batch Job Tracking)

Tracks batches of related jobs.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `id` | varchar | NO | PRI | - | Batch ID |
| `name` | varchar | NO | - | - | Batch name |
| `total_jobs` | int | NO | - | - | Total jobs in batch |
| `pending_jobs` | int | NO | - | - | Pending jobs count |
| `failed_jobs` | int | NO | - | - | Failed jobs count |
| `failed_job_ids` | longtext | NO | - | - | IDs of failed jobs |
| `options` | mediumtext | YES | - | NULL | Batch options |
| `cancelled_at` | int | YES | - | NULL | Cancellation timestamp |
| `created_at` | int | NO | - | - | Creation timestamp |
| `finished_at` | int | YES | - | NULL | Completion timestamp |

---

### 12. **migrations** (Migration Tracking)

Tracks applied database migrations.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `id` | int | NO | PRI | auto_increment | Primary key |
| `migration` | varchar | NO | - | - | Migration filename |
| `batch` | int | NO | - | - | Migration batch number |

---

### 13. **password_reset_tokens** (Password Resets)

Stores password reset tokens.

| Column | Type | Nullable | Key | Default | Description |
|--------|------|----------|-----|---------|-------------|
| `email` | varchar | NO | PRI | - | User email |
| `token` | varchar | NO | - | - | Reset token |
| `created_at` | timestamp | YES | - | NULL | Token creation timestamp |

---

## Relationships

### Foreign Keys

```
users (1) ──< (N) pdfs
  └─ user_id

pdfs (1) ──< (N) chat_sessions
  └─ pdf_id

pdfs (1) ──< (N) predefined_questions
  └─ pdf_id

chat_sessions (1) ──< (N) chat_messages
  └─ session_id

users (1) ──< (N) sessions
  └─ user_id
```

---

## Indexes

### pdfs table
- PRIMARY KEY: `id`
- INDEX: `user_id`
- INDEX: `title`
- INDEX: `created_at`

### users table
- PRIMARY KEY: `id`
- UNIQUE: `email`

### chat_sessions table
- PRIMARY KEY: `id`
- INDEX: `pdf_id`
- INDEX: `created_at`

### chat_messages table
- PRIMARY KEY: `id`
- INDEX: `session_id`
- INDEX: `sender`

### predefined_questions table
- PRIMARY KEY: `id`
- INDEX: `pdf_id`
- INDEX: `title`

---

## Storage Locations

### File Storage
- **PDFs:** `storage/app/public/pdfs/`
- **Public Access:** `public/storage/pdfs/` (symlinked)

### Database
- **Host:** 127.0.0.1
- **Port:** 3306
- **Database:** chatpdf
- **User:** root

---

## Phase Implementation Status

| Phase | Status | Tables Modified |
|-------|--------|-----------------|
| Phase 1 | ✅ Complete | All tables created |
| Phase 2 | ✅ Complete | pdfs (upload) |
| Phase 3 | ✅ Complete | pdfs (text, pages, status) |
| Phase 4 | 🔄 Pending | - |
| Phase 5+ | ⏳ Not Started | - |

---

## Notes

- All timestamps use MySQL `timestamp` type with automatic timezone handling
- JSON columns use MySQL native JSON type for efficient querying
- File paths are relative to `storage/app/public/`
- Maximum PDF size: 10MB (configurable in .env)
- Text extraction supports both digital and scanned PDFs (via OCR)

---

**End of Database Schema Documentation**
