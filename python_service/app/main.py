# Phase 4: Python FastAPI Service for Chat with PDF
# This service handles ML operations that Laravel/PHP cannot do

from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional, List
import uvicorn
import os
from dotenv import load_dotenv

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

# ========== PHASE 5: PDF UPLOAD & STORAGE (PLACEHOLDER) ==========

class UploadResponse(BaseModel):
    success: bool
    pdf_id: str
    message: str

@app.post("/api/upload", response_model=UploadResponse)
async def upload_pdf(file: UploadFile = File(...)):
    """
    Phase 5: Upload PDF and prepare for processing
    This will be implemented in Phase 5
    """
    return UploadResponse(
        success=False,
        pdf_id="",
        message="Phase 5: Not yet implemented"
    )

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
