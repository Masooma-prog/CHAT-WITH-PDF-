# Chat with PDF - Backend Implementation Phases

**IMPORTANT: NO FRONTEND CHANGES - Keep all existing UI/design as-is**

---

## Overview

This document provides a step-by-step implementation guide for building the Chat with PDF backend. Each phase is independent and can be tested before moving to the next.

---

## Phase 1: Environment Setup & Configuration

### **Goal:** Set up all required services and API keys

### **Tasks:**

1. **Install Python dependencies:**
   ```bash
   cd python_service
   pip install -r requirements.txt
   ```

2. **Update `.env` file:**
   ```env
   # OpenAI API
   OPENAI_API_KEY=your_openai_api_key_here
   
   # Python Service
   PYTHON_SERVICE_URL=http://localhost:8001
   
   # Database (already configured)
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=chatpdf
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

3. **Verify installations:**
   - PHP 8.1+ ✓
   - Composer ✓
   - Python 3.9+ ✓
   - MySQL 8.0+ ✓
   - Node.js ✓

### **Testing:**
```bash
php -v
python --version
composer --version
mysql --version
```

### **Deliverables:**
- ✓ All dependencies installed
- ✓ Environment variables configured
- ✓ Services ready to start

---

## Phase 2: PDF Upload & Storage (Laravel)

### **Goal:** Handle PDF uploads and store metadata

### **Files to modify:**
- `app/Http/Controllers/PdfController.php`
- `app/Models/Pdf.php`

### **Implementation:**


**PdfController@upload():**
- Validate PDF file (max 50MB, type: application/pdf)
- Store in `storage/app/public/pdfs`
- Save metadata to database
- Return success response

**Database fields:**
- filename, path, size, user_id, status, created_at

### **Testing:**
1. Upload a PDF via frontend
2. Check `storage/app/public/pdfs` folder
3. Verify database record in `pdfs` table
4. Status should be 'uploaded'

### **Deliverables:**
- ✓ PDF files stored successfully
- ✓ Database records created
- ✓ No frontend changes

---

## Phase 3: Text Extraction (PHP)

### **Goal:** Extract text from text-based PDFs

### **Files to modify:**
- `app/Services/PdfExtractorService.php`

### **Implementation:**

**Use existing library:** `smalot/pdfparser` (already in composer.json)

**PdfExtractorService:**
```php
public function extractText($pdfPath)
{
    // Use smalot/pdfparser
    // Extract text from PDF
    // Return plain text
}
```

**Update PdfController:**
- After upload, call `PdfExtractorService->extractText()`
- Save extracted text to `pdfs.text` column
- Update status to 'text_extracted'

### **Testing:**
1. Upload a text-based PDF
2. Check `pdfs.text` column in database
3. Verify text is extracted correctly

### **Deliverables:**
- ✓ Text extraction working
- ✓ Text stored in database
- ✓ Status updated

---

## Phase 4: Python Service Setup

### **Goal:** Create Python FastAPI service for ML operations

### **Files to create/modify:**
- `python_service/app/main.py`
- `python_service/requirements.txt`

### **Implementation:**

**requirements.txt:**
```
fastapi==0.104.1
uvicorn==0.24.0
sentence-transformers==2.2.2
faiss-cpu==1.7.4
openai==1.3.0
PyPDF2==3.0.1
python-multipart==0.0.6
```

**main.py structure:**
```python
from fastapi import FastAPI
app = FastAPI()

@app.get("/health")
def health_check():
    return {"status": "ok"}

@app.post("/extract-text")
def extract_text(file):
    # OCR for scanned PDFs
    pass

@app.post("/generate-embeddings")
def generate_embeddings(text):
    # Create embeddings
    pass

@app.post("/search")
def search_similar(query, pdf_id):
    # Vector similarity search
    pass

@app.post("/chat")
def chat(question, pdf_id):
    # RAG implementation
    pass
```

### **Testing:**
```bash
cd python_service
uvicorn app.main:app --reload --port 8001
```

Visit: `http://localhost:8001/health`

### **Deliverables:**
- ✓ Python service running
- ✓ Health check endpoint working
- ✓ Ready for ML integration

---

## Phase 5: Text Chunking

### **Goal:** Split extracted text into manageable chunks

### **Files to modify:**
- `python_service/app/main.py`
- Add `python_service/app/chunker.py`

### **Implementation:**

**chunker.py:**
```python
def chunk_text(text, chunk_size=1000, overlap=100):
    # Split text into chunks
    # Maintain overlap for context
    # Return list of chunks
    pass
```

**Chunking logic:**
- Chunk size: 500-1000 tokens
- Overlap: 50-100 tokens
- Preserve sentence boundaries

### **Testing:**
1. Send text to `/generate-embeddings`
2. Verify chunks are created
3. Check overlap between chunks

### **Deliverables:**
- ✓ Text chunking working
- ✓ Proper overlap maintained
- ✓ Chunks ready for embedding

---

## Phase 6: Generate Embeddings

### **Goal:** Convert text chunks to vector embeddings

### **Files to modify:**
- `python_service/app/main.py`
- Add `python_service/app/embeddings.py`

### **Implementation:**

**Option A: Sentence-BERT (Free, Local)**
```python
from sentence_transformers import SentenceTransformer

model = SentenceTransformer('all-MiniLM-L6-v2')

def generate_embedding(text):
    return model.encode(text)
```

**Option B: OpenAI (Paid, Better Quality)**
```python
import openai

def generate_embedding(text):
    response = openai.Embedding.create(
        model="text-embedding-ada-002",
        input=text
    )
    return response['data'][0]['embedding']
```

### **Testing:**
1. Send chunk to embedding endpoint
2. Verify vector output (384 or 1536 dimensions)
3. Test with multiple chunks

### **Deliverables:**
- ✓ Embeddings generated successfully
- ✓ Correct vector dimensions
- ✓ Ready for vector storage

---

## Phase 7: Vector Database Setup

### **Goal:** Store and search embeddings efficiently

### **Files to modify:**
- `python_service/app/main.py`
- Add `python_service/app/vector_db.py`

### **Implementation:**

**Using FAISS:**
```python
import faiss
import numpy as np

class VectorDB:
    def __init__(self):
        self.dimension = 384  # or 1536 for OpenAI
        self.index = faiss.IndexFlatL2(self.dimension)
        self.chunks = {}
    
    def add_vectors(self, pdf_id, chunks, embeddings):
        # Add to FAISS index
        # Store chunk mapping
        pass
    
    def search(self, query_embedding, k=5):
        # Find top k similar chunks
        pass
```

### **Testing:**
1. Add embeddings to vector DB
2. Perform similarity search
3. Verify top results are relevant

### **Deliverables:**
- ✓ Vector database initialized
- ✓ Embeddings stored
- ✓ Search working

---

## Phase 8: OpenAI Integration

### **Goal:** Connect to OpenAI API for chat responses

### **Files to modify:**
- `python_service/app/main.py`
- Add `python_service/app/openai_client.py`

### **Implementation:**

**openai_client.py:**
```python
import openai

def chat_completion(context, question):
    prompt = f"""
    You are a helpful assistant. Answer based ONLY on the context below.
    
    Context:
    {context}
    
    Question: {question}
    
    Answer:
    """
    
    response = openai.ChatCompletion.create(
        model="gpt-4",
        messages=[{"role": "user", "content": prompt}],
        temperature=0.3,
        max_tokens=500
    )
    
    return response.choices[0].message.content
```

### **Testing:**
1. Test with sample context and question
2. Verify response quality
3. Check API usage/costs

### **Deliverables:**
- ✓ OpenAI API connected
- ✓ Responses generated
- ✓ Error handling in place

---

## Phase 9: RAG Implementation

### **Goal:** Complete Retrieval-Augmented Generation flow

### **Files to modify:**
- `python_service/app/main.py`
- `app/Services/RAGService.php` (Laravel)

### **Implementation:**

**Python `/chat` endpoint:**
```python
@app.post("/chat")
def chat(question: str, pdf_id: int):
    # 1. Generate query embedding
    query_emb = generate_embedding(question)
    
    # 2. Search vector DB
    similar_chunks = vector_db.search(query_emb, k=5)
    
    # 3. Prepare context
    context = "\n\n".join([chunk['text'] for chunk in similar_chunks])
    
    # 4. Call OpenAI
    answer = chat_completion(context, question)
    
    return {"answer": answer, "sources": similar_chunks}
```

**Laravel RAGService:**
```php
public function chat($pdfId, $question)
{
    // Call Python service
    $response = Http::post(env('PYTHON_SERVICE_URL') . '/chat', [
        'pdf_id' => $pdfId,
        'question' => $question
    ]);
    
    return $response->json();
}
```

### **Testing:**
1. Upload PDF
2. Ask question via chat
3. Verify accurate response
4. Check response time

### **Deliverables:**
- ✓ Full RAG pipeline working
- ✓ Accurate responses
- ✓ Sources tracked

---

## Phase 10: Chat History & Sessions

### **Goal:** Save conversations to database

### **Files to modify:**
- `app/Http/Controllers/ChatController.php`
- `app/Models/ChatSession.php`
- `app/Models/ChatMessage.php`

### **Implementation:**

**ChatController:**
```php
public function sendMessage(Request $request)
{
    // 1. Get or create session
    $session = ChatSession::firstOrCreate([
        'user_id' => auth()->id(),
        'pdf_id' => $request->pdf_id
    ]);
    
    // 2. Save user message
    ChatMessage::create([
        'session_id' => $session->id,
        'role' => 'user',
        'message' => $request->question
    ]);
    
    // 3. Get AI response
    $response = $this->ragService->chat($request->pdf_id, $request->question);
    
    // 4. Save AI message
    ChatMessage::create([
        'session_id' => $session->id,
        'role' => 'assistant',
        'message' => $response['answer']
    ]);
    
    return response()->json($response);
}
```

### **Testing:**
1. Send multiple messages
2. Check database records
3. Verify conversation continuity

### **Deliverables:**
- ✓ Chat history saved
- ✓ Sessions tracked
- ✓ Conversation context maintained

---

## Phase 11: Error Handling & Logging

### **Goal:** Robust error handling and debugging

### **Files to modify:**
- All controllers
- Python service endpoints

### **Implementation:**

**Laravel:**
```php
try {
    // Process PDF
} catch (\Exception $e) {
    Log::error('PDF processing failed: ' . $e->getMessage());
    return response()->json(['error' => 'Processing failed'], 500);
}
```

**Python:**
```python
@app.exception_handler(Exception)
async def global_exception_handler(request, exc):
    logger.error(f"Error: {str(exc)}")
    return JSONResponse(
        status_code=500,
        content={"error": str(exc)}
    )
```

### **Testing:**
1. Test with invalid PDFs
2. Test with network errors
3. Verify error messages

### **Deliverables:**
- ✓ Comprehensive error handling
- ✓ Detailed logging
- ✓ User-friendly error messages

---

## Phase 12: Optimization & Caching

### **Goal:** Improve performance

### **Implementation:**

**Caching strategies:**
1. Cache embeddings for frequently accessed PDFs
2. Cache OpenAI responses for common questions
3. Use Redis for session storage

**Optimization:**
1. Async PDF processing
2. Queue system for large files
3. Database indexing

### **Testing:**
1. Measure response times
2. Test with large PDFs
3. Monitor memory usage

### **Deliverables:**
- ✓ Faster response times
- ✓ Reduced API costs
- ✓ Better scalability

---

## Implementation Order

**Week 1:**
- Phase 1: Setup ✓
- Phase 2: PDF Upload ✓
- Phase 3: Text Extraction ✓

**Week 2:**
- Phase 4: Python Service ✓
- Phase 5: Text Chunking ✓
- Phase 6: Embeddings ✓

**Week 3:**
- Phase 7: Vector DB ✓
- Phase 8: OpenAI Integration ✓
- Phase 9: RAG Implementation ✓

**Week 4:**
- Phase 10: Chat History ✓
- Phase 11: Error Handling ✓
- Phase 12: Optimization ✓

---

## Testing Checklist

After each phase:
- [ ] Unit tests pass
- [ ] Manual testing successful
- [ ] No frontend changes
- [ ] Logs are clean
- [ ] Documentation updated

---

## Ready to Start?

**Current Status:** Phase 1 (Environment Setup)

**Next Steps:**
1. Verify all dependencies installed
2. Configure `.env` file
3. Get OpenAI API key
4. Start Phase 2

---

**Document Version:** 1.0  
**Last Updated:** February 3, 2026  
**Status:** Ready for Implementation
