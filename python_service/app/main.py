# Phase 5: Text Chunking Module
# Splits large text into smaller, overlapping chunks for better AI processing

import re
from typing import List, Dict

from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional, List
import uvicorn
import os
from dotenv import load_dotenv
from chunker import TextChunker
from embeddings import get_embedding_generator, generate_embeddings_batch, generate_embedding
from vector_store import get_vector_store
import json
import uuid

# Load environment variables
load_dotenv()

# Initialize FastAPI app
app = FastAPI(
    title="ChatPDF Python Service",
    description="ML/AI service for PDF processing, embeddings, and RAG",
    version="1.0.0"
)

# CORS middleware to allow Laravel to call this service
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, specify Laravel URL
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ========== PHASE 5: IN-MEMORY STORAGE ==========
# Store PDF chunks in memory (will be persisted to FAISS in Phase 7)
pdf_storage = {}  # {pdf_id: {'text': str, 'chunks': List[Dict], 'metadata': Dict}}

# Initialize chunker
chunker = TextChunker(chunk_size=1000, overlap=100)

# Phase 6: Initialize embedding generator (lazy loading)
embedding_generator = None

# Phase 7: Initialize vector store (lazy loading to avoid startup hang)
vector_store = None

def get_embeddings():
    """Get or initialize embedding generator."""
    global embedding_generator
    if embedding_generator is None:
        embedding_generator = get_embedding_generator()
    return embedding_generator

def get_vector_store_instance():
    """Get or initialize vector store (lazy loading)."""
    global vector_store
    if vector_store is None:
        vector_store = get_vector_store()
    return vector_store

# ========== PHASE 4: BASIC ENDPOINTS ==========

@app.get("/")
async def root():
    """Root endpoint - service info"""
    return {
        "service": "ChatPDF Python Service",
        "version": "1.0.0",
        "status": "running",
        "phase": 8,
        "endpoints": {
            "health": "/health",
            "chunk_text": "/api/chunk-text (Phase 5)",
            "search": "/api/search (Phase 7)",
            "chat": "/api/chat (Phase 8 - RAG with Groq)"
        }
    }

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "ok",
        "phase": 4,
        "message": "Python service is running",
        "openai_configured": bool(os.getenv("OPENAI_API_KEY"))
    }

# ========== PHASE 4: TEXT EXTRACTION (OCR) ==========

class TextExtractionResponse(BaseModel):
    success: bool
    text: str
    page_count: int
    method: str  # "pypdf" or "ocr"
    message: Optional[str] = None

@app.post("/api/extract-text", response_model=TextExtractionResponse)
async def extract_text(file: UploadFile = File(...)):
    """
    Phase 4: Extract text from PDF (including OCR for scanned PDFs)
    This is a fallback when Laravel's PHP extraction fails
    """
    try:
        # Save uploaded file temporarily
        temp_path = f"/tmp/{file.filename}"
        with open(temp_path, "wb") as f:
            content = await file.read()
            f.write(content)
        
        # Try PyPDF2 first (faster for text-based PDFs)
        try:
            import PyPDF2
            with open(temp_path, 'rb') as pdf_file:
                pdf_reader = PyPDF2.PdfReader(pdf_file)
                text = ""
                for page in pdf_reader.pages:
                    text += page.extract_text() + "\n"
                
                if text.strip():
                    # Clean up
                    os.remove(temp_path)
                    return TextExtractionResponse(
                        success=True,
                        text=text,
                        page_count=len(pdf_reader.pages),
                        method="pypdf",
                        message="Text extracted successfully"
                    )
        except Exception as e:
            print(f"PyPDF2 extraction failed: {e}")
        
        # Fallback to OCR (for scanned PDFs) - Phase 4+
        # TODO: Implement OCR in next phase
        
        # Clean up
        if os.path.exists(temp_path):
            os.remove(temp_path)
        
        return TextExtractionResponse(
            success=False,
            text="",
            page_count=0,
            method="none",
            message="Could not extract text. OCR not yet implemented."
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# ========== PHASE 5: PDF UPLOAD & CHUNKING ==========

class UploadResponse(BaseModel):
    success: bool
    pdf_id: str
    message: str
    chunks_count: int
    chunk_stats: Dict

@app.post("/api/upload", response_model=UploadResponse)
async def upload_pdf(file: UploadFile = File(...)):
    """
    Phase 5: Upload PDF, extract text, and chunk it
    """
    try:
        # Generate unique PDF ID
        pdf_id = str(uuid.uuid4())
        
        # Create temp directory if it doesn't exist
        import tempfile
        temp_dir = tempfile.gettempdir()
        temp_path = os.path.join(temp_dir, f"{pdf_id}_{file.filename}")
        
        # Save uploaded file temporarily
        with open(temp_path, "wb") as f:
            content = await file.read()
            f.write(content)
        
        # Extract text using PyPDF2
        import PyPDF2
        text = ""
        page_count = 0
        
        try:
            with open(temp_path, 'rb') as pdf_file:
                pdf_reader = PyPDF2.PdfReader(pdf_file)
                page_count = len(pdf_reader.pages)
                
                for page in pdf_reader.pages:
                    text += page.extract_text() + "\n"
        except Exception as e:
            if os.path.exists(temp_path):
                os.remove(temp_path)
            raise HTTPException(status_code=400, detail=f"Failed to extract text: {str(e)}")
        
        # Clean up temp file
        if os.path.exists(temp_path):
            os.remove(temp_path)
        
        if not text.strip():
            raise HTTPException(status_code=400, detail="No text could be extracted from PDF")
        
        # Phase 5: Chunk the text
        chunks = chunker.chunk_text(text, pdf_id=pdf_id)
        chunk_stats = chunker.get_chunk_stats(chunks)
        
        # Store in memory
        pdf_storage[pdf_id] = {
            'filename': file.filename,
            'text': text,
            'chunks': chunks,
            'page_count': page_count,
            'metadata': {
                'uploaded_at': str(uuid.uuid4()),  # Timestamp placeholder
                'chunk_stats': chunk_stats
            }
        }
        
        return UploadResponse(
            success=True,
            pdf_id=pdf_id,
            message=f"PDF uploaded and chunked successfully. {len(chunks)} chunks created.",
            chunks_count=len(chunks),
            chunk_stats=chunk_stats
        )
        
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# ========== PHASE 5: CHUNK TEXT ENDPOINT (FOR LARAVEL) ==========

class ChunkTextRequest(BaseModel):
    text: str
    pdf_id: str
    filename: str

@app.post("/api/chunk-text", response_model=UploadResponse)
async def chunk_text_endpoint(request: ChunkTextRequest):
    """
    Phase 5-7: Receive text from Laravel, chunk it, generate embeddings, and save to FAISS
    """
    try:
        pdf_id = request.pdf_id
        text = request.text
        
        if not text.strip():
            raise HTTPException(status_code=400, detail="Text is empty")
        
        # Phase 5: Chunk the text
        chunks = chunker.chunk_text(text, pdf_id=pdf_id)
        chunk_stats = chunker.get_chunk_stats(chunks)
        
        # Phase 6: Generate embeddings for all chunks
        print(f"📊 Phase 6: Generating embeddings for {len(chunks)} chunks...")
        chunk_texts = [chunk['text'] for chunk in chunks]
        embeddings = generate_embeddings_batch(chunk_texts)
        
        # Add embeddings to chunks
        for i, chunk in enumerate(chunks):
            chunk['embedding'] = embeddings[i]
        
        print(f"✅ Phase 6: Generated {len(embeddings)} embeddings")
        
        # Phase 7: Save to FAISS vector store
        print(f"💾 Phase 7: Saving to vector store...")
        vs = get_vector_store_instance()
        success = vs.add_pdf(pdf_id, chunks, embeddings)
        
        if not success:
            raise HTTPException(status_code=500, detail="Failed to save to vector store")
        
        # Also keep in memory for backward compatibility
        pdf_storage[pdf_id] = {
            'filename': request.filename,
            'text': text,
            'chunks': chunks,
            'page_count': text.count('\f') + 1,
            'metadata': {
                'uploaded_at': str(uuid.uuid4()),
                'chunk_stats': chunk_stats,
                'embeddings_generated': True,
                'embedding_dimension': len(embeddings[0]) if embeddings else 0,
                'saved_to_faiss': success
            }
        }
        
        return UploadResponse(
            success=True,
            pdf_id=pdf_id,
            message=f"Text chunked, embedded, and saved to vector store. {len(chunks)} chunks created.",
            chunks_count=len(chunks),
            chunk_stats=chunk_stats
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# ========== PHASE 5: GET CHUNKS ENDPOINT ==========

class ChunksResponse(BaseModel):
    success: bool
    pdf_id: str
    chunks: List[Dict]
    total_chunks: int
    message: str

@app.get("/api/chunks/{pdf_id}", response_model=ChunksResponse)
async def get_chunks(pdf_id: str, include_embeddings: bool = False):
    """
    Phase 5-7: Retrieve chunks for a specific PDF from vector store
    Set include_embeddings=true to get full embeddings (large response)
    """
    try:
        # Get from vector store (Phase 7)
        vs = get_vector_store_instance()
        pdf_info = vs.get_pdf_info(pdf_id)
        
        if not pdf_info:
            # Fallback to in-memory storage
            if pdf_id not in pdf_storage:
                available_ids = vs.list_pdfs()
                raise HTTPException(
                    status_code=404, 
                    detail=f"PDF not found. Available IDs: {available_ids}"
                )
            
            # Return from memory
            pdf_data = pdf_storage[pdf_id]
            chunks = pdf_data['chunks']
        else:
            # Get chunks from vector store
            chunks = vs.chunks_data.get(pdf_id, [])
        
        # Remove embeddings from response if not requested
        if not include_embeddings:
            chunks = [
                {k: v for k, v in chunk.items() if k != 'embedding'}
                for chunk in chunks
            ]
        
        return ChunksResponse(
            success=True,
            pdf_id=pdf_id,
            chunks=chunks,
            total_chunks=len(chunks),
            message=f"Retrieved {len(chunks)} chunks" + (" (without embeddings)" if not include_embeddings else " (with embeddings)")
        )
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# ========== PHASE 5: LIST ALL STORED PDFS (DEBUG) ==========

@app.get("/api/pdfs")
async def list_pdfs():
    """
    Debug endpoint: List all PDFs (from vector store and memory)
    """
    vs = get_vector_store_instance()
    vector_store_pdfs = vs.list_pdfs()
    
    # Combine data from both sources
    all_pdfs = {}
    
    # From vector store
    for pdf_id in vector_store_pdfs:
        info = vs.get_pdf_info(pdf_id)
        all_pdfs[pdf_id] = {
            "source": "vector_store",
            "chunks_count": info['chunks_count'],
            "dimension": info['dimension'],
            "filename": pdf_storage.get(pdf_id, {}).get('filename', 'unknown')
        }
    
    # From memory (if not in vector store)
    for pdf_id, data in pdf_storage.items():
        if pdf_id not in all_pdfs:
            all_pdfs[pdf_id] = {
                "source": "memory_only",
                "filename": data["filename"],
                "chunks_count": len(data["chunks"]),
                "page_count": data["page_count"]
            }
    
    return {
        "success": True,
        "total_pdfs": len(all_pdfs),
        "pdf_ids": list(all_pdfs.keys()),
        "pdfs": all_pdfs
    }

# ========== PHASE 6: EMBEDDINGS ==========

class EmbeddingRequest(BaseModel):
    text: str
    pdf_id: str

class EmbeddingResponse(BaseModel):
    success: bool
    embedding: List[float]
    dimension: int
    message: str

@app.post("/api/embeddings", response_model=EmbeddingResponse)
async def generate_embeddings_endpoint(request: EmbeddingRequest):
    """
    Phase 6: Generate embedding for a single text
    """
    try:
        from embeddings import generate_embedding
        
        embedding = generate_embedding(request.text)
        
        return EmbeddingResponse(
            success=True,
            embedding=embedding,
            dimension=len(embedding),
            message="Embedding generated successfully"
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# ========== PHASE 7: VECTOR SEARCH ==========

class SearchRequest(BaseModel):
    query: str
    pdf_id: str
    top_k: int = 5

class SearchResponse(BaseModel):
    success: bool
    results: List[dict]
    message: str

@app.post("/api/search", response_model=SearchResponse)
async def search_similar(request: SearchRequest):
    """
    Phase 7: Search for similar text chunks using vector similarity
    """
    try:
        # Generate embedding for query
        query_embedding = generate_embedding(request.query)
        
        # Search in vector store
        vs = get_vector_store_instance()
        results = vs.search(request.pdf_id, query_embedding, request.top_k)
        
        if not results:
            return SearchResponse(
                success=False,
                results=[],
                message=f"No results found for PDF {request.pdf_id}"
            )
        
        return SearchResponse(
            success=True,
            results=results,
            message=f"Found {len(results)} similar chunks"
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# ========== PHASE 8: RAG CHAT WITH GROQ ==========

class ChatRequest(BaseModel):
    question: str
    pdf_id: str
    chat_history: list = []  # Optional: for follow-up questions

class ChatResponse(BaseModel):
    success: bool
    answer: str
    sources: List[dict]
    model: str
    tokens_used: int
    message: Optional[str] = None

@app.post("/api/chat", response_model=ChatResponse)
async def chat_with_pdf(request: ChatRequest):
    """
    Phase 8: Chat with PDF using LOCAL RAG (No external APIs)
    
    Flow:
    1. Generate embedding for user's question
    2. Search vector store for relevant chunks
    3. Return chunks as answer (pure RAG)
    """
    try:
        from rag_local import generate_extractive_answer
        
        print(f"💬 Chat request for PDF {request.pdf_id}")
        print(f"   Question: {request.question}")
        
        # Step 1: Generate embedding for question
        query_embedding = generate_embedding(request.question)
        
        # Step 2: Search vector store for relevant chunks
        vs = get_vector_store_instance()
        similar_chunks = vs.search(request.pdf_id, query_embedding, top_k=5)
        
        if not similar_chunks:
            return ChatResponse(
                success=False,
                answer="",
                sources=[],
                model="local_rag",
                tokens_used=0,
                message=f"No content found for PDF {request.pdf_id}"
            )
        
        print(f"   Found {len(similar_chunks)} relevant chunks")
        
        # Step 3: Generate answer using LOCAL RAG (no API)
        rag_result = generate_extractive_answer(
            question=request.question,
            context_chunks=similar_chunks
        )
        
        print(f"   ✅ Answer generated using {rag_result['method']}")
        
        # Step 4: Return response
        return ChatResponse(
            success=True,
            answer=rag_result["answer"],
            sources=similar_chunks,
            model=rag_result["method"],
            tokens_used=0,  # No tokens used (local processing)
            message="Answer generated using local RAG (no external API)"
        )
        
    except Exception as e:
        print(f"❌ Chat error: {e}")
        raise HTTPException(status_code=500, detail=str(e))

# ========== RUN SERVER ==========

if __name__ == "__main__":
    port = int(os.getenv("PORT", 8001))
    uvicorn.run(
        "main:app",
        host="0.0.0.0",
        port=port,
        reload=True,
        log_level="info"
    )
