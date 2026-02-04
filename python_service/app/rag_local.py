# Local RAG Implementation (No APIs)
# Generates answers using only retrieved chunks without external LLM

from typing import List, Dict

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
