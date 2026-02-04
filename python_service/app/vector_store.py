# Phase 7: Vector Store Module
# Manages FAISS vector database for persistent storage and similarity search

import faiss
import numpy as np
import json
import os
from typing import List, Dict, Tuple, Optional
from pathlib import Path

class VectorStore:
    """
    Manages FAISS vector database for storing and searching embeddings.
    Provides persistent storage and fast similarity search.
    """
    
    def __init__(self, storage_dir: str = "vector_store"):
        """
        Initialize vector store.
        
        Args:
            storage_dir: Directory to store FAISS indexes and metadata
        """
        self.storage_dir = Path(storage_dir)
        self.storage_dir.mkdir(exist_ok=True)
        
        # Store FAISS indexes per PDF
        self.indexes = {}  # {pdf_id: faiss.Index}
        self.chunks_data = {}  # {pdf_id: List[Dict]}
        
        print(f"📁 Vector store initialized at: {self.storage_dir}")
        
        # Load existing indexes on startup
        self._load_all_indexes()
    
    def add_pdf(self, pdf_id: str, chunks: List[Dict], embeddings: List[List[float]]) -> bool:
        """
        Add a PDF's chunks and embeddings to the vector store.
        
        Args:
            pdf_id: Unique PDF identifier
            chunks: List of chunk dictionaries
            embeddings: List of embedding vectors
            
        Returns:
            True if successful
        """
        try:
            if not embeddings:
                print(f"⚠️ No embeddings provided for PDF {pdf_id}")
                return False
            
            # Convert embeddings to numpy array
            embeddings_array = np.array(embeddings, dtype=np.float32)
            
            # Validate shape
            if len(embeddings_array.shape) != 2:
                print(f"❌ Invalid embeddings shape: {embeddings_array.shape}")
                return False
            
            dimension = embeddings_array.shape[1]
            print(f"📐 Embedding dimension: {dimension}")
            
            # Create FAISS index (L2 distance)
            index = faiss.IndexFlatL2(dimension)
            
            # Add embeddings to index
            index.add(embeddings_array)
            
            # Store in memory
            self.indexes[pdf_id] = index
            self.chunks_data[pdf_id] = chunks
            
            # Save to disk
            self._save_index(pdf_id, index, chunks)
            
            print(f"✅ Added PDF {pdf_id} to vector store ({len(chunks)} chunks)")
            return True
            
        except Exception as e:
            print(f"❌ Error adding PDF {pdf_id}: {e}")
            return False
    
    def search(self, pdf_id: str, query_embedding: List[float], top_k: int = 5) -> List[Dict]:
        """
        Search for similar chunks using vector similarity.
        
        Args:
            pdf_id: PDF to search in
            query_embedding: Query vector
            top_k: Number of results to return
            
        Returns:
            List of matching chunks with similarity scores
        """
        try:
            if pdf_id not in self.indexes:
                print(f"⚠️ PDF {pdf_id} not found in vector store")
                return []
            
            index = self.indexes[pdf_id]
            chunks = self.chunks_data[pdf_id]
            
            # Convert query to numpy array
            query_array = np.array([query_embedding], dtype=np.float32)
            
            # Search FAISS index
            distances, indices = index.search(query_array, min(top_k, len(chunks)))
            
            # Build results
            results = []
            for i, (distance, idx) in enumerate(zip(distances[0], indices[0])):
                if idx < len(chunks):
                    chunk = chunks[idx].copy()
                    chunk['similarity_score'] = float(1 / (1 + distance))  # Convert distance to similarity
                    chunk['rank'] = i + 1
                    results.append(chunk)
            
            return results
            
        except Exception as e:
            print(f"❌ Search error for PDF {pdf_id}: {e}")
            return []
    
    def get_pdf_info(self, pdf_id: str) -> Optional[Dict]:
        """
        Get information about a stored PDF.
        
        Args:
            pdf_id: PDF identifier
            
        Returns:
            Dictionary with PDF info or None
        """
        if pdf_id not in self.indexes:
            return None
        
        return {
            'pdf_id': pdf_id,
            'chunks_count': len(self.chunks_data[pdf_id]),
            'dimension': self.indexes[pdf_id].d,
            'stored': True
        }
    
    def list_pdfs(self) -> List[str]:
        """Get list of all stored PDF IDs."""
        return list(self.indexes.keys())
    
    def delete_pdf(self, pdf_id: str) -> bool:
        """
        Delete a PDF from the vector store.
        
        Args:
            pdf_id: PDF to delete
            
        Returns:
            True if successful
        """
        try:
            if pdf_id in self.indexes:
                del self.indexes[pdf_id]
                del self.chunks_data[pdf_id]
                
                # Delete files
                index_file = self.storage_dir / f"{pdf_id}.index"
                chunks_file = self.storage_dir / f"{pdf_id}_chunks.json"
                
                if index_file.exists():
                    index_file.unlink()
                if chunks_file.exists():
                    chunks_file.unlink()
                
                print(f"🗑️ Deleted PDF {pdf_id} from vector store")
                return True
            
            return False
            
        except Exception as e:
            print(f"❌ Error deleting PDF {pdf_id}: {e}")
            return False
    
    def _save_index(self, pdf_id: str, index: faiss.Index, chunks: List[Dict]):
        """Save FAISS index and chunks to disk."""
        try:
            # Save FAISS index
            index_file = str(self.storage_dir / f"{pdf_id}.index")
            faiss.write_index(index, index_file)
            
            # Save chunks (without embeddings to save space)
            chunks_file = self.storage_dir / f"{pdf_id}_chunks.json"
            chunks_without_embeddings = [
                {k: v for k, v in chunk.items() if k != 'embedding'}
                for chunk in chunks
            ]
            
            with open(chunks_file, 'w', encoding='utf-8') as f:
                json.dump(chunks_without_embeddings, f, ensure_ascii=False, indent=2)
            
            print(f"💾 Saved index and chunks for PDF {pdf_id}")
            
        except Exception as e:
            print(f"❌ Error saving PDF {pdf_id}: {e}")
    
    def _load_all_indexes(self):
        """Load all existing indexes from disk on startup."""
        try:
            index_files = list(self.storage_dir.glob("*.index"))
            
            if not index_files:
                print("📂 No existing indexes found")
                return
            
            print(f"📂 Loading {len(index_files)} existing indexes...")
            
            for index_file in index_files:
                pdf_id = index_file.stem  # Filename without extension
                
                try:
                    # Load FAISS index
                    index = faiss.read_index(str(index_file))
                    
                    # Load chunks
                    chunks_file = self.storage_dir / f"{pdf_id}_chunks.json"
                    if chunks_file.exists():
                        with open(chunks_file, 'r', encoding='utf-8') as f:
                            chunks = json.load(f)
                        
                        self.indexes[pdf_id] = index
                        self.chunks_data[pdf_id] = chunks
                        
                        print(f"  ✅ Loaded PDF {pdf_id} ({len(chunks)} chunks)")
                    else:
                        print(f"  ⚠️ Chunks file missing for PDF {pdf_id}")
                        
                except Exception as e:
                    print(f"  ❌ Error loading PDF {pdf_id}: {e}")
            
            print(f"✅ Loaded {len(self.indexes)} PDFs from disk")
            
        except Exception as e:
            print(f"❌ Error loading indexes: {e}")


# Global vector store instance
_vector_store = None

def get_vector_store() -> VectorStore:
    """
    Get or create the global vector store instance.
    Singleton pattern.
    """
    global _vector_store
    if _vector_store is None:
        _vector_store = VectorStore()
    return _vector_store
