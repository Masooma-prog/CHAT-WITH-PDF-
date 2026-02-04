# Phase 8: LLM Client Module (Groq)
# Handles chat completion using Groq API

from groq import Groq
import os
from typing import List, Dict

# Global Groq client
_groq_client = None

def get_groq_client() -> Groq:
    """
    Get or initialize Groq client.
    Singleton pattern.
    """
    global _groq_client
    if _groq_client is None:
        api_key = os.getenv("GROQ_API_KEY")
        if not api_key:
            raise ValueError("GROQ_API_KEY not found in environment")
        _groq_client = Groq(api_key=api_key)
        print("✅ Groq client initialized")
    return _groq_client


def generate_rag_response(question: str, context_chunks: List[Dict], model: str = "meta-llama/llama-4-scout-17b-16e-instruct") -> Dict:
    """
    Generate RAG response using Groq.
    
    Args:
        question: User's question
        context_chunks: List of relevant chunks from vector search
        model: Groq model to use (default: Llama 4 Scout 17B - fast and good quality)
               Available models:
               - meta-llama/llama-4-scout-17b-16e-instruct (recommended: fast, cheap)
               - meta-llama/llama-4-maverick-17b-128e-instruct (better quality, slower)
               - qwen/qwen3-32b (good alternative)
    
    Returns:
        Dictionary with answer and metadata
    """
    try:
        client = get_groq_client()
        
        # Build context from chunks
        context = "\n\n".join([
            f"[Chunk {i+1}]\n{chunk['text']}"
            for i, chunk in enumerate(context_chunks)
        ])
        
        # Create RAG prompt
        system_prompt = """You are a helpful AI assistant that answers questions based on provided document content.

IMPORTANT RULES:
1. Answer ONLY based on the context provided below
2. If the answer is not in the context, say "I cannot find this information in the document"
3. Be concise and accurate
4. Quote relevant parts when helpful
5. Do not make up information"""

        user_prompt = f"""Context from document:
{context}

Question: {question}

Answer:"""

        # Call Groq API
        print(f"🤖 Calling Groq API with model: {model}")
        
        response = client.chat.completions.create(
            model=model,
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt}
            ],
            temperature=0.3,  # Lower = more focused, higher = more creative
            max_tokens=1000,
            top_p=0.9
        )
        
        answer = response.choices[0].message.content
        
        # Extract metadata
        usage = response.usage
        
        print(f"✅ Generated response ({usage.total_tokens} tokens)")
        
        return {
            "answer": answer,
            "model": model,
            "tokens_used": usage.total_tokens,
            "prompt_tokens": usage.prompt_tokens,
            "completion_tokens": usage.completion_tokens,
            "chunks_used": len(context_chunks)
        }
        
    except Exception as e:
        print(f"❌ Groq API error: {e}")
        raise


def chat_with_history(question: str, context_chunks: List[Dict], chat_history: List[Dict] = None, model: str = "meta-llama/llama-4-scout-17b-16e-instruct") -> Dict:
    """
    Generate response with chat history for follow-up questions.
    
    Args:
        question: Current question
        context_chunks: Relevant chunks
        chat_history: Previous messages [{"role": "user/assistant", "content": "..."}]
        model: Groq model
    
    Returns:
        Dictionary with answer and metadata
    """
    try:
        client = get_groq_client()
        
        # Build context
        context = "\n\n".join([
            f"[Chunk {i+1}]\n{chunk['text']}"
            for i, chunk in enumerate(context_chunks)
        ])
        
        # Build messages with history
        messages = [
            {"role": "system", "content": f"""You are a helpful AI assistant answering questions about a document.

Context from document:
{context}

Answer based ONLY on this context. If information is not in the context, say so."""}
        ]
        
        # Add chat history
        if chat_history:
            messages.extend(chat_history[-6:])  # Last 3 exchanges (6 messages)
        
        # Add current question
        messages.append({"role": "user", "content": question})
        
        print(f"🤖 Calling Groq with {len(messages)} messages")
        
        response = client.chat.completions.create(
            model=model,
            messages=messages,
            temperature=0.3,
            max_tokens=1000,
            top_p=0.9
        )
        
        answer = response.choices[0].message.content
        usage = response.usage
        
        print(f"✅ Generated response ({usage.total_tokens} tokens)")
        
        return {
            "answer": answer,
            "model": model,
            "tokens_used": usage.total_tokens,
            "chunks_used": len(context_chunks)
        }
        
    except Exception as e:
        print(f"❌ Groq API error: {e}")
        raise
