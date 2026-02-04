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

# ========== PHASE 4: BASIC ENDPOINTS ==========

@app.get("/")
async def root():
    """Root endpoint - service info"""
    return {
        "service": "ChatPDF Python Service",
        "version": "1.0.0",
        "status": "running",
        "phase": 4,
        "endpoints": {
            "health": "/health",
            "extract_text": "/api/extract-text (Phase 4)",
            "upload": "/api/upload (Phase 5)",
            "embeddings": "/api/embeddings (Phase 6)",
            "search": "/api/search (Phase 7)",
            "chat": "/api/chat (Phase 8-9)"
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
    Phase 5: Receive text from Laravel and chunk it
    This is called after Laravel extracts text in Phase 3
    """
    try:
        pdf_id = request.pdf_id
        text = request.text
        
        if not text.strip():
            raise HTTPException(status_code=400, detail="Text is empty")
        
        # Phase 5: Chunk the text
        chunks = chunker.chunk_text(text, pdf_id=pdf_id)
        chunk_stats = chunker.get_chunk_stats(chunks)
        
        # Store in memory
        pdf_storage[pdf_id] = {
            'filename': request.filename,
            'text': text,
            'chunks': chunks,
            'page_count': text.count('\f') + 1,  # Rough page count
            'metadata': {
                'uploaded_at': str(uuid.uuid4()),
                'chunk_stats': chunk_stats
            }
        }
        
        return UploadResponse(
            success=True,
            pdf_id=pdf_id,
            message=f"Text chunked successfully. {len(chunks)} chunks created.",
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
async def get_chunks(pdf_id: str):
    """
    Phase 5: Retrieve chunks for a specific PDF
    """
    # Debug: Log what we're looking for and what we have
    print(f"Looking for PDF ID: {pdf_id} (type: {type(pdf_id)})")
    print(f"Available PDF IDs: {list(pdf_storage.keys())}")
    
    if pdf_id not in pdf_storage:
        raise HTTPException(
            status_code=404, 
            detail=f"PDF not found. Available IDs: {list(pdf_storage.keys())}"
        )
    
    pdf_data = pdf_storage[pdf_id]
    
    return ChunksResponse(
        success=True,
        pdf_id=pdf_id,
        chunks=pdf_data['chunks'],
        total_chunks=len(pdf_data['chunks']),
        message=f"Retrieved {len(pdf_data['chunks'])} chunks"
    )

# ========== PHASE 5: LIST ALL STORED PDFS (DEBUG) ==========

@app.get("/api/pdfs")
async def list_pdfs():
    """
    Debug endpoint: List all PDFs stored in memory
    """
    return {
        "success": True,
        "total_pdfs": len(pdf_storage),
        "pdf_ids": list(pdf_storage.keys()),
        "pdfs": {
            pdf_id: {
                "filename": data["filename"],
                "chunks_count": len(data["chunks"]),
                "page_count": data["page_count"]
            }
            for pdf_id, data in pdf_storage.items()
        }
    }

# ========== PHASE 6: EMBEDDINGS (PLACEHOLDER) ==========

class EmbeddingRequest(BaseModel):
    text: str
    pdf_id: str

class EmbeddingResponse(BaseModel):
    success: bool
    message: str

@app.post("/api/embeddings", response_model=EmbeddingResponse)
async def generate_embeddings(request: EmbeddingRequest):
    """
    Phase 6: Generate embeddings for text chunks
    This will be implemented in Phase 6
    """
    return EmbeddingResponse(
        success=False,
        message="Phase 6: Not yet implemented"
    )

# ========== PHASE 7: VECTOR SEARCH (PLACEHOLDER) ==========

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
    This will be implemented in Phase 7
    """
    return SearchResponse(
        success=False,
        results=[],
        message="Phase 7: Not yet implemented"
    )

# ========== PHASE 8-9: RAG CHAT (PLACEHOLDER) ==========

class ChatRequest(BaseModel):
    question: str
    pdf_id: str

class ChatResponse(BaseModel):
    success: bool
    answer: str
    sources: List[dict]
    message: Optional[str] = None

@app.post("/api/chat", response_model=ChatResponse)
async def chat_with_pdf(request: ChatRequest):
    """
    Phase 8-9: Chat with PDF using RAG (Retrieval-Augmented Generation)
    This will be implemented in Phases 8-9
    """
    return ChatResponse(
        success=False,
        answer="",
        sources=[],
        message="Phase 8-9: Not yet implemented"
    )

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
