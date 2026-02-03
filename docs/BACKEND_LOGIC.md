# Chat with PDF - Backend Logic Documentation

## System Overview

The Chat with PDF system allows users to interact with uploaded PDF documents using natural language queries. The system uses AI/ML technologies including OCR, embeddings, vector databases, and Large Language Models (LLMs) to provide accurate, context-aware responses.

---

## Architecture Flow

```
User → Upload PDF → Laravel Backend → Text Extraction → Python Service
                                                              ↓
                                                    Text Processing & Chunking
                                                              ↓
                                                    Generate Embeddings
                                                              ↓
                                                    Store in Vector Database
                                                              ↓
User Question → Laravel → Python Service → Similarity Search → Retrieve Chunks
                                                              ↓
                                                    Send to OpenAI GPT-4
                                                              ↓
                                                    Generate Response
                                                              ↓
                                            Laravel ← Response ← Python Service
                                                              ↓
                                                    Display to User
```

---

## Detailed Backend Logic

### **1. PDF Upload & Storage**

**Process:**
- User uploads PDF file through web interface
- Laravel validates file (type, size, format)
- Store PDF in `storage/app/public/pdfs` directory
- Save metadata to MySQL `pdfs` table:
  - `id` (primary key)
  - `user_id` (foreign key)
  - `filename` (original name)
  - `path` (storage path)
  - `size` (file size in bytes)
  - `upload_date` (timestamp)
  - `status` (processing/completed/failed)

**Laravel Controller:**
- `PdfController@upload()`
- Handles file validation and storage
- Creates database record

---

### **2. Text Extraction**

**Two Scenarios:**

#### **A. Text-Based PDF**
- Use PHP library: `smalot/pdfparser`
- Extract text directly from PDF
- Fast and accurate for digital documents

#### **B. Scanned/Image-Based PDF**
- Send PDF to Python microservice
- Use **Tesseract OCR** to convert images to text
- Process each page individually
- Combine extracted text from all pages

**Storage:**
- Save extracted text in `pdfs.text` column (TEXT/LONGTEXT)
- Update `status` to 'text_extracted'

---

### **3. Text Processing & Chunking**

**Python Service Process:**

1. **Receive extracted text** from Laravel
2. **Clean text:**
   - Remove extra whitespaces
   - Fix line breaks
   - Remove special characters (if needed)
   - Normalize encoding

3. **Split into chunks:**
   - Chunk size: 500-1000 tokens
   - Maintain context overlap (50-100 tokens)
   - Preserve sentence boundaries
   - Keep paragraphs together when possible

4. **Why chunking?**
   - LLMs have token limits
   - Improves retrieval accuracy
   - Faster similarity search
   - Better context management

**Example:**
```
Original: 5000 tokens
Chunks: 
  - Chunk 1: tokens 0-1000
  - Chunk 2: tokens 900-1900 (100 token overlap)
  - Chunk 3: tokens 1800-2800
  - ...
```

---

### **4. Generate Embeddings**

**Embedding Models (Choose One):**

#### **Option A: Sentence-BERT (Local)**
- Model: `sentence-transformers/all-MiniLM-L6-v2`
- Output: 384-dimensional vectors
- Free, runs locally
- Good quality

#### **Option B: OpenAI Embeddings (API)**
- Model: `text-embedding-ada-002`
- Output: 1536-dimensional vectors
- Cost: ~$0.0001 per 1K tokens
- Best quality

**Process:**
1. For each text chunk, generate embedding vector
2. Vector captures semantic meaning
3. Similar text = similar vectors

**Example:**
```python
chunk = "The capital of France is Paris."
embedding = model.encode(chunk)
# Result: [0.234, -0.567, 0.891, ..., 0.123] (384 or 1536 numbers)
```

---

### **5. Store in Vector Database**

**Vector Database Options:**

#### **FAISS (Facebook AI Similarity Search)**
- Fast similarity search
- Stores vectors in memory or disk
- Good for millions of vectors

#### **ChromaDB**
- Persistent storage
- Built-in metadata support
- Easy to use

**Storage Structure:**
```
{
  "chunk_id": "pdf_123_chunk_5",
  "pdf_id": 123,
  "chunk_index": 5,
  "text": "Original chunk text...",
  "embedding": [0.234, -0.567, ...],
  "metadata": {
    "page": 3,
    "position": "middle"
  }
}
```

**Indexing:**
- Create index for fast similarity search
- Support for cosine similarity or L2 distance

---

### **6. User Asks Question**

**Flow:**
1. User types question in chat interface
2. Frontend sends AJAX request to Laravel
3. Laravel API endpoint: `POST /api/chat`
4. Request includes:
   - `pdf_id`
   - `question`
   - `session_id` (for conversation tracking)

---

### **7. Query Processing & Similarity Search**

**Python Service:**

1. **Generate query embedding:**
   ```python
   question = "What is the main topic?"
   query_embedding = model.encode(question)
   ```

2. **Perform similarity search:**
   - Compare query embedding with all chunk embeddings
   - Calculate similarity scores (cosine similarity)
   - Retrieve top 3-5 most relevant chunks

3. **Ranking:**
   ```
   Chunk 1: 0.92 similarity
   Chunk 2: 0.87 similarity
   Chunk 3: 0.81 similarity
   Chunk 4: 0.76 similarity
   Chunk 5: 0.72 similarity
   ```

4. **Return top chunks** with their original text

---

### **8. RAG (Retrieval-Augmented Generation)**

**Process:**

1. **Prepare context:**
   ```
   Context = Chunk 1 + Chunk 2 + Chunk 3
   ```

2. **Create prompt for GPT-4:**
   ```
   You are a helpful assistant. Answer the question based ONLY on the following context.
   
   Context:
   [Chunk 1 text]
   [Chunk 2 text]
   [Chunk 3 text]
   
   Question: {user_question}
   
   Answer:
   ```

3. **Send to OpenAI API:**
   - Model: `gpt-4` or `gpt-3.5-turbo`
   - Temperature: 0.3 (for factual responses)
   - Max tokens: 500

4. **Receive response:**
   - LLM generates answer based on context
   - Reduces hallucinations
   - Ensures accuracy

---

### **9. Response Display & Storage**

**Laravel Backend:**

1. **Receive response** from Python service

2. **Save to MySQL:**
   
   **chat_sessions table:**
   ```sql
   id, user_id, pdf_id, created_at, updated_at
   ```
   
   **chat_messages table:**
   ```sql
   id, session_id, role (user/assistant), message, created_at
   ```

3. **Store conversation:**
   ```
   Message 1: role=user, message="What is this about?"
   Message 2: role=assistant, message="This document discusses..."
   ```

4. **Return JSON response:**
   ```json
   {
     "success": true,
     "answer": "This document discusses...",
     "sources": ["Chunk 1", "Chunk 2"],
     "session_id": 456
   }
   ```

5. **Frontend displays** response in chat interface

---

### **10. Follow-up Questions & Conversation Context**

**Features:**

1. **Maintain conversation history:**
   - Load previous messages from `chat_messages`
   - Include in context for follow-up questions

2. **Contextual understanding:**
   - "What about the second point?" (refers to previous answer)
   - System knows which PDF and conversation

3. **Multi-turn dialogue:**
   ```
   User: "What is the main topic?"
   AI: "The main topic is climate change."
   
   User: "What are the solutions mentioned?"
   AI: "The document mentions renewable energy, carbon capture..."
   ```

---

## Technology Stack

### **Backend (Laravel - PHP)**
- User authentication & authorization
- File upload handling
- API endpoints
- MySQL database management
- Session management
- Response formatting

### **Python Microservice**
- **OCR:** Tesseract
- **PDF Processing:** PyPDF2, pdf2image
- **Embeddings:** sentence-transformers or OpenAI API
- **Vector Database:** FAISS or ChromaDB
- **LLM Integration:** OpenAI API (GPT-4/3.5)
- **Framework:** FastAPI or Flask

### **Database (MySQL)**
- **users:** User accounts
- **pdfs:** PDF metadata and extracted text
- **chat_sessions:** Conversation tracking
- **chat_messages:** Question-answer pairs
- **predefined_questions:** Optional quick questions

### **Vector Database**
- **FAISS:** In-memory/disk vector storage
- **ChromaDB:** Persistent vector database

### **External APIs**
- **OpenAI API:** GPT-4/3.5 for responses, embeddings
- **Alternative:** Local LLMs (Llama 2, Mistral) for offline use

---

## Data Flow Summary

```
1. PDF Upload → Laravel → Storage
2. Text Extraction → PHP/Python → MySQL
3. Chunking → Python → Memory
4. Embeddings → Python → Vector DB
5. User Question → Laravel → Python
6. Similarity Search → Vector DB → Top Chunks
7. RAG → OpenAI API → Response
8. Save & Display → MySQL → User
```

---

## Performance Considerations

### **Optimization:**
- Cache embeddings for frequently accessed PDFs
- Use async processing for large PDFs
- Implement queue system for background jobs
- Index vector database for faster search

### **Scalability:**
- Separate Python service can scale independently
- Use Redis for caching
- Load balancing for multiple Python workers
- CDN for static assets

---

## Security Considerations

1. **File Upload:**
   - Validate file types (only PDF)
   - Limit file size (e.g., 50MB max)
   - Scan for malware

2. **API Security:**
   - Authentication tokens
   - Rate limiting
   - CORS configuration

3. **Data Privacy:**
   - User-specific PDF access
   - Encrypted storage for sensitive documents
   - Secure API key management

---

## Cost Estimation (Monthly)

### **OpenAI API Usage:**
- **Embeddings:** ~$0.0001 per 1K tokens
- **GPT-4:** ~$0.03 per 1K tokens (input), ~$0.06 per 1K tokens (output)

### **Example:**
- 100 PDFs (avg 10 pages each)
- 1000 questions per month
- **Estimated cost:** $50-150/month

### **Free Alternative:**
- Use Sentence-BERT (local embeddings)
- Use Llama 2 or Mistral (local LLM)
- Requires GPU server (~$100-300/month)

---

## Implementation Phases

### **Phase 1: Basic Setup**
- Laravel project setup
- User authentication
- PDF upload functionality
- MySQL database schema

### **Phase 2: Text Extraction**
- PHP PDF parser integration
- Python OCR service
- Text storage

### **Phase 3: Embeddings & Vector DB**
- Python embedding generation
- FAISS/ChromaDB setup
- Vector storage

### **Phase 4: RAG Implementation**
- OpenAI API integration
- Similarity search
- Response generation

### **Phase 5: Chat Interface**
- Frontend chat UI
- Real-time messaging
- Conversation history

### **Phase 6: Optimization**
- Caching
- Performance tuning
- Error handling

---

## Constraints & Requirements

### **Must Have:**
- OpenAI API key (for GPT-4/3.5)
- Python 3.9+ environment
- MySQL 8.0+
- Minimum 8GB RAM (16GB recommended)
- Internet connection (for API calls)

### **Cannot Work Without:**
- OpenAI API (or alternative local LLM)
- Python for ML models
- Vector database for embeddings

### **Limitations:**
- PDF size: ~50MB maximum
- Processing time: 2-10 seconds per query
- Not real-time streaming
- Requires dedicated server/VPS

---

## Conclusion

This backend logic implements a complete RAG (Retrieval-Augmented Generation) system for Chat with PDF functionality. The system combines Laravel's robust backend capabilities with Python's ML/AI ecosystem to deliver accurate, context-aware responses based on PDF content.

**Key Success Factors:**
- Proper text chunking for better retrieval
- High-quality embeddings for accurate similarity search
- Well-crafted prompts for GPT-4 to reduce hallucinations
- Efficient vector database indexing for fast queries
- Comprehensive error handling and logging

---

**Document Version:** 1.0  
**Last Updated:** February 3, 2026  
**Author:** AI Assistant
