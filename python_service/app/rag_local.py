# Local RAG Implementation (No APIs)
# Generates answers using only retrieved chunks without external LLM

from typing import List, Dict
import os

def generate_local_rag_response(question: str, context_chunks: List[Dict]) -> Dict:
    """
    Generate RAG response using only retrieved chunks (no external API).
    Uses simple template-based approach.
    
    Args:
        question: User's question
        context_chunks: List of relevant chunks from vector search
    
    Returns:
        Dictionary with answer and metadata
    """
    try:
        if not context_chunks:
            return {
                "answer": "I couldn't find relevant information in the document to answer your question.",
                "method": "local_rag",
                "chunks_used": 0
            }
        
        # Build context from chunks
        context_text = "\n\n".join([
            f"[Excerpt {i+1}]\n{chunk['text']}"
            for i, chunk in enumerate(context_chunks)
        ])
        
        # Simple template-based response
        answer = f"""Based on the document, here are the relevant excerpts:

{context_text}

---

Summary: The document discusses the topics mentioned in the excerpts above. The most relevant section (similarity score: {context_chunks[0].get('similarity_score', 0):.2f}) provides information related to your question: "{question}"
"""
        
        return {
            "answer": answer,
            "method": "local_rag_template",
            "chunks_used": len(context_chunks),
            "top_similarity": context_chunks[0].get('similarity_score', 0)
        }
        
    except Exception as e:
        print(f"❌ Local RAG error: {e}")
        return {
            "answer": f"Error generating response: {str(e)}",
            "method": "error",
            "chunks_used": 0
        }


def generate_smart_answer_with_groq(question: str, context_chunks: List[Dict]) -> Dict:
    """
    Smart RAG using Groq LLM to generate intelligent answers.
    
    Args:
        question: User's question
        context_chunks: List of relevant chunks
    
    Returns:
        Dictionary with answer
    """
    try:
        if not context_chunks:
            return {
                "answer": "I couldn't find relevant information in the document to answer your question.",
                "method": "groq_rag",
                "chunks_used": 0
            }
        
        # Import Groq client
        from groq import Groq
        
        # Initialize Groq
        groq_api_key = os.getenv("GROQ_API_KEY")
        if not groq_api_key:
            print("⚠️ GROQ_API_KEY not found, falling back to extractive")
            return generate_extractive_answer(question, context_chunks)
        
        client = Groq(api_key=groq_api_key)
        
        # Build context from top 3 chunks
        context_text = "\n\n".join([
            f"Section {i+1}:\n{chunk['text']}"
            for i, chunk in enumerate(context_chunks[:3])
        ])
        
        # Create smart prompt
        prompt = f"""You are a helpful AI assistant. Answer the question based ONLY on the context provided below. 

Context from the document:
{context_text}

Question: {question}

Instructions:
- Provide a clear, concise answer
- Use information ONLY from the context above
- If the context doesn't contain the answer, say "I don't have enough information to answer that question."
- Be direct and helpful
- Don't mention "the document" or "the context" - just answer naturally

Answer:"""

        # Call Groq API
        response = client.chat.completions.create(
            model="llama-3.3-70b-versatile",
            messages=[{"role": "user", "content": prompt}],
            temperature=0.3,
            max_tokens=500
        )
        
        answer = response.choices[0].message.content.strip()
        tokens_used = response.usage.total_tokens
        
        return {
            "answer": answer,
            "method": "groq_rag",
            "chunks_used": len(context_chunks[:3]),
            "tokens_used": tokens_used,
            "model": "llama-3.3-70b-versatile"
        }
        
    except Exception as e:
        print(f"❌ Groq RAG error: {e}")
        # Fallback to extractive
        return generate_extractive_answer(question, context_chunks)


def generate_extractive_answer(question: str, context_chunks: List[Dict]) -> Dict:
    """
    Extractive QA: Return the most relevant chunk as the answer.
    Pure RAG without any generation.
    
    Args:
        question: User's question
        context_chunks: List of relevant chunks
    
    Returns:
        Dictionary with answer
    """
    try:
        if not context_chunks:
            return {
                "answer": "No relevant information found in the document.",
                "method": "extractive",
                "chunks_used": 0
            }
        
        # Return top 3 most relevant chunks
        top_chunks = context_chunks[:3]
        
        answer_parts = []
        for i, chunk in enumerate(top_chunks, 1):
            similarity = chunk.get('similarity_score', 0)
            text = chunk['text']
            answer_parts.append(f"**Relevant Section {i}** (Relevance: {similarity:.1%}):\n{text}")
        
        answer = "\n\n---\n\n".join(answer_parts)
        
        return {
            "answer": answer,
            "method": "extractive_rag",
            "chunks_used": len(top_chunks),
            "top_similarity": top_chunks[0].get('similarity_score', 0)
        }
        
    except Exception as e:
        print(f"❌ Extractive QA error: {e}")
        return {
            "answer": f"Error: {str(e)}",
            "method": "error",
            "chunks_used": 0
        }
