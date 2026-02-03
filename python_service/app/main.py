import os
import io
import time
import json
import logging
from typing import List, Dict, Any, Optional
import tempfile
from pathlib import Path

from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import uvicorn
from dotenv import load_dotenv

# Load environment variables from a .env file
load_dotenv()

# PDF and OCR libraries
import fitz  # PyMuPDF
import pytesseract
from PIL import Image
import pdfplumber

# AI and embeddings
import openai
from openai import OpenAI
import faiss
import numpy as np
from sentence_transformers import SentenceTransformer

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Initialize FastAPI app
app = FastAPI(
    title="ChatPDF Python Service",
    description="Microservice for PDF text extraction, OCR, and RAG functionality",
    version="1.0.0"
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, specify exact origins
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Global variables
client = None
embedding_model = None
vector_stores = {}  # Store FAISS indices per PDF

# Initialize OpenAI client
try:
    openai_api_key = os.getenv('OPENAI_API_KEY')
    if openai_api_key:
        client = OpenAI(api_key=openai_api_key)
        logger.info("OpenAI client initialized successfully.")
    else:
        logger.warning("OPENAI_API_KEY not found. OpenAI functionality will be disabled.")
except Exception as e:
    logger.error(f"Error initializing OpenAI client: {e}")
    client = None

# Initialize Sentence Transformer for local embeddings fallback
try:
    embedding_model = SentenceTransformer('all-MiniLM-L6-v2')
    logger.info("Sentence Transformer model (all-MiniLM-L6-v2) loaded.")
except Exception as e:
    logger.error(f"Error loading Sentence Transformer model: {e}")
    embedding_model = None


# --- Pydantic Models ---
class EmbedRequest(BaseModel):
    pdf_id: int
    chunks: List[str]
    pdf_meta: Dict[str, Any]

class AskRequest(BaseModel):
    pdf_id: int
    query: str
    chat_history: List[Dict[str, str]]
    pdf_meta: Dict[str, Any]

class GenerateQuestionsRequest(BaseModel):
    text: str

# --- Helper Functions --- (Keep all helper functions as they are)
def split_text_into_chunks(text: str, chunk_size: int = 1000, overlap: int = 200) -> List[str]:
    # ... (code is unchanged)
    if not text:
        return []
    chunks = []
    start = 0
    while start < len(text):
        end = start + chunk_size
        if end < len(text):
            sentence_end = text.rfind('.', start + chunk_size - 100, end)
            if sentence_end > start:
                end = sentence_end + 1
        chunk = text[start:end].strip()
        if chunk:
            chunks.append(chunk)
        start = end - overlap if end < len(text) else end
        if start >= len(text) or chunk_size <= overlap:
            break
    return chunks

def get_openai_embedding(text: str) -> Optional[List[float]]:
    # ... (code is unchanged)
    if not client:
        return None
    try:
        response = client.embeddings.create(
            model="text-embedding-ada-002",
            input=[text]
        )
        return response.data[0].embedding
    except Exception as e:
        logger.error(f"OpenAI embedding error: {e}")
        return None

def get_local_embedding(text: str) -> Optional[List[float]]:
    # ... (code is unchanged)
    if not embedding_model:
        return None
    try:
        return embedding_model.encode(text).tolist()
    except Exception as e:
        logger.error(f"Local embedding error: {e}")
        return None

def get_embedding(text: str) -> Optional[List[float]]:
    # ... (code is unchanged)
    embedding = get_openai_embedding(text)
    if embedding:
        return embedding
    logger.warning("OpenAI failed or not available, falling back to local Sentence Transformer.")
    return get_local_embedding(text)

def extract_text_and_pages_pypdfium(file_path: str) -> Dict[str, Any]:
    # ... (code is unchanged)
    full_text = []
    page_texts = {}
    page_count = 0
    try:
        with pdfplumber.open(file_path) as pdf:
            page_count = len(pdf.pages)
            for i, page in enumerate(pdf.pages):
                page_number = i + 1
                text = page.extract_text() or ""
                full_text.append(text)
                page_texts[page_number] = text
        if any(full_text):
            return {
                "success": True, "method": "pdfplumber", "text": "\n\n".join(full_text),
                "pages": page_texts, "page_count": page_count, "ocr_used": False
            }
    except Exception as e:
        logger.warning(f"pdfplumber extraction failed: {e}")
    return extract_text_with_ocr(file_path)

def extract_text_with_ocr(file_path: str) -> Dict[str, Any]:
    # ... (code is unchanged)
    full_text = []
    page_texts = {}
    page_count = 0
    try:
        pdf_document = fitz.open(file_path)
        page_count = pdf_document.page_count
        for i in range(page_count):
            page = pdf_document.load_page(i)
            pix = page.get_pixmap(matrix=fitz.Matrix(3, 3))
            img_data = pix.tobytes("ppm")
            img = Image.open(io.BytesIO(img_data))
            text = pytesseract.image_to_string(img)
            page_number = i + 1
            full_text.append(text)
            page_texts[page_number] = text
        pdf_document.close()
        return {
            "success": True, "method": "ocr", "text": "\n\n".join(full_text),
            "pages": page_texts, "page_count": page_count, "ocr_used": True
        }
    except Exception as e:
        logger.error(f"OCR extraction failed: {e}")
        return {
            "success": False, "method": "ocr", "text": None,
            "pages": {}, "page_count": page_count, "ocr_used": True, "error": str(e)
        }

def find_context(pdf_id: int, query: str, top_k: int = 5) -> List[Dict[str, Any]]:
    # ... (code is unchanged)
    if pdf_id not in vector_stores: return []
    vector_store = vector_stores[pdf_id]
    query_embedding = get_embedding(query)
    if not query_embedding:
        logger.error("Could not generate embedding for query.")
        return []
    query_vector = np.array([query_embedding]).astype('float32')
    index = vector_store['index']
    D, I = index.search(query_vector, top_k)
    results = []
    for i, doc_index in enumerate(I[0]):
        if doc_index != -1:
            chunk = vector_store['chunks'][doc_index]
            results.append({'chunk': chunk, 'score': float(D[0][i])})
    return results

def build_prompt(query: str, context: List[Dict[str, Any]], chat_history: List[Dict[str, str]], pdf_meta: Dict[str, Any]) -> str:
    # ... (code is unchanged)
    context_text = "\n\n---\n\n".join([r['chunk'] for r in context])
    history_text = "\n".join([f"{msg['sender'].capitalize()}: {msg['message']}" for msg in chat_history[-5:]])
    if history_text:
        history_text = "Previous Conversation History:\n" + history_text + "\n\n"
    prompt = f"""
    You are an AI assistant specialized in answering questions based ONLY on the provided document context.
    The document is titled "{pdf_meta.get('title', 'Unknown Document')}" and has {pdf_meta.get('pages', 'N/A')} pages.
    
    ---
    
    {history_text}
    
    ---
    
    Document Context (Chunks):
    {context_text}
    
    ---
    
    **Instructions:**
    1.  Answer the user's question ONLY using the text provided in the "Document Context" section.
    2.  If the answer cannot be found in the context, you MUST respond with: "I'm sorry, I could not find the answer to that question in the provided document." Do not use external knowledge.
    3.  If the question asks for a summary or key points, synthesize the answer from the context.
    4.  Maintain a concise, informative, and professional tone.
    
    User Question: {query}
    
    Your Answer:
    """
    return prompt

def generate_llm_response(prompt: str) -> str:
    # ... (code is unchanged)
    if not client: return "Error: OpenAI client is not configured."
    try:
        response = client.chat.completions.create(
            model="gpt-3.5-turbo",
            messages=[
                {"role": "system", "content": "You are a helpful assistant."},
                {"role": "user", "content": prompt}
            ],
            temperature=0.0
        )
        return response.choices[0].message.content.strip()
    except Exception as e:
        logger.error(f"OpenAI chat completion error: {e}")
        return f"Error communicating with the LLM: {e}"

# --- API Endpoints ---

# --- BUG FIX: Renamed endpoint from /health to /status to match Laravel app ---
@app.get("/status")
async def get_status():
    """Check the health and readiness of the service"""
    return {
        "status": "ok",
        "openai_client": bool(client),
        "local_embedding_model": bool(embedding_model),
        "stored_embeddings": len(vector_stores)
    }

# --- (All other endpoints: /extract, /embed, /ask, etc. remain unchanged) ---
@app.post("/extract")
async def extract_text(file: UploadFile = File(...)):
    # ... (code is unchanged)
    start_time = time.time()
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=".pdf") as tmp:
            tmp.write(await file.read())
            tmp_path = tmp.name
        extraction_result = extract_text_and_pages_pypdfium(tmp_path)
        os.unlink(tmp_path)
        if extraction_result.get("success"):
            return {
                "success": True, "method": extraction_result['method'], "full_text": extraction_result['text'],
                "page_texts": extraction_result['pages'], "page_count": extraction_result['page_count'],
                "ocr_used": extraction_result['ocr_used'], "processing_time": time.time() - start_time
            }
        else:
            raise HTTPException(status_code=500, detail=extraction_result.get("error", "Failed to extract text or perform OCR."))
    except Exception as e:
        logger.error(f"File upload/extraction error: {e}")
        if 'tmp_path' in locals() and os.path.exists(tmp_path):
            os.unlink(tmp_path)
        raise HTTPException(status_code=500, detail=f"Extraction service error: {e}")

@app.post("/embed")
async def embed_chunks(request: EmbedRequest):
    # ... (code is unchanged)
    start_time = time.time()
    pdf_id = request.pdf_id
    chunks = request.chunks
    if not client and not embedding_model:
        raise HTTPException(status_code=503, detail="Embedding service not available (OpenAI key missing and local model failed).")
    try:
        embeddings = [emb for chunk in chunks if (emb := get_embedding(chunk))]
        if not embeddings: raise Exception("Failed to generate any embeddings.")
        embedding_dim = len(embeddings[0])
        np_embeddings = np.array(embeddings).astype('float32')
        index = faiss.IndexFlatL2(embedding_dim)
        index.add(np_embeddings)
        vector_stores[pdf_id] = {
            'index': index, 'chunks': chunks, 'model': 'text-embedding-ada-002' if client else 'all-MiniLM-L6-v2',
            'created_at': time.time(), 'chunk_count': len(chunks)
        }
        return {
            "success": True, "pdf_id": pdf_id, "chunk_count": len(chunks),
            "model": vector_stores[pdf_id]['model'], "processing_time": time.time() - start_time
        }
    except Exception as e:
        logger.error(f"Embedding error for PDF {pdf_id}: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to create embeddings: {e}")

@app.post("/ask")
async def ask_with_rag(request: AskRequest):
    # ... (code is unchanged)
    start_time = time.time()
    pdf_id = request.pdf_id
    query = request.query
    if pdf_id not in vector_stores:
        raise HTTPException(status_code=404, detail=f"No embeddings found for PDF ID: {pdf_id}. Please run the /embed endpoint first.")
    if not client:
        raise HTTPException(status_code=503, detail="OpenAI LLM service is not configured.")
    try:
        context_results = find_context(pdf_id, query, top_k=5)
        if not context_results:
            return {
                "response": "I'm sorry, I couldn't find any relevant information in the document for your question.",
                "sources": [], "confidence": 0.0, "processing_time": time.time() - start_time
            }
        prompt = build_prompt(query, context_results, request.chat_history, request.pdf_meta)
        response_text = generate_llm_response(prompt)
        sources = [{'chunk': r['chunk'], 'score': r['score']} for r in context_results]
        avg_score = np.mean([r['score'] for r in context_results])
        return {
            "response": response_text, "sources": sources, "confidence": float(avg_score),
            "processing_time": time.time() - start_time
        }
    except Exception as e:
        logger.error(f"RAG query error for PDF {pdf_id}: {e}")
        raise HTTPException(status_code=500, detail=f"RAG service error: {e}")

@app.post("/generate-questions")
async def generate_questions_endpoint(request: GenerateQuestionsRequest):
    # ... (code is unchanged)
    if not client:
        raise HTTPException(status_code=503, detail="OpenAI service is not available.")
    try:
        prompt = """
You are an expert AI... (prompt unchanged) ...Produce smart, exam-style questions that demonstrate a deep understanding of the uploaded document.
"""
        response = client.chat.completions.create(
            model="gpt-3.5-turbo",
            messages=[
                {"role": "system", "content": prompt},
                {"role": "user", "content": f"Here is the document text:\n\n---\n\n{request.text}"}
            ],
            response_format={"type": "json_object"}
        )
        content = response.choices[0].message.content
        if content is None:
            raise ValueError("The response from the model was empty.")
        questions_json = json.loads(content)
        return questions_json
    except json.JSONDecodeError:
        logger.error(f"Failed to decode JSON from OpenAI response: {content}")
        raise HTTPException(status_code=500, detail="Failed to parse questions from AI response.")
    except Exception as e:
        logger.error(f"Error generating questions: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to generate questions: {e}")

@app.delete("/embeddings/{pdf_id}")
async def delete_embeddings(pdf_id: int):
    # ... (code is unchanged)
    if pdf_id in vector_stores:
        del vector_stores[pdf_id]
        return {"success": True, "message": f"Embeddings for PDF {pdf_id} deleted"}
    else:
        return {"success": False, "message": f"No embeddings found for PDF {pdf_id}"}

@app.get("/embeddings")
async def list_embeddings():
    # ... (code is unchanged)
    return {
        "stored_pdfs": list(vector_stores.keys()),
        "total_count": len(vector_stores)
    }

# Entry point for running the application
if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8001)
