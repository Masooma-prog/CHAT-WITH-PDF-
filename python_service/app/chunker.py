# Phase 5: Text Chunking Module
# Splits large text into smaller, overlapping chunks for better AI processing

import re
from typing import List, Dict

class TextChunker:
    """
    Splits text into chunks with overlap to maintain context.
    Preserves sentence boundaries for better semantic coherence.
    """
    
    def __init__(self, chunk_size: int = 1000, overlap: int = 100):
        """
        Initialize chunker with size parameters.
        
        Args:
            chunk_size: Target size of each chunk in characters
            overlap: Number of characters to overlap between chunks
        """
        self.chunk_size = chunk_size
        self.overlap = overlap
    
    def chunk_text(self, text: str, pdf_id: str = None) -> List[Dict]:
        """
        Split text into overlapping chunks.
        
        Args:
            text: The full text to chunk
            pdf_id: Optional PDF identifier for tracking
            
        Returns:
            List of chunk dictionaries with metadata
        """
        if not text or not text.strip():
            return []
        
        # Clean the text
        text = self._clean_text(text)
        
        # Split into sentences
        sentences = self._split_into_sentences(text)
        
        # Build chunks from sentences
        chunks = self._build_chunks(sentences)
        
        # Add metadata to chunks
        chunk_list = []
        for i, chunk_text in enumerate(chunks):
            chunk_list.append({
                'chunk_id': i,
                'text': chunk_text,
                'char_count': len(chunk_text),
                'word_count': len(chunk_text.split()),
                'pdf_id': pdf_id,
                'position': i
            })
        
        return chunk_list
    
    def _clean_text(self, text: str) -> str:
        """Clean and normalize text."""
        # Remove excessive whitespace
        text = re.sub(r'\s+', ' ', text)
        # Remove special characters that might cause issues
        text = re.sub(r'[\x00-\x08\x0b-\x0c\x0e-\x1f\x7f-\x9f]', '', text)
        return text.strip()
    
    def _split_into_sentences(self, text: str) -> List[str]:
        """
        Split text into sentences while preserving meaning.
        Uses simple sentence boundary detection.
        """
        # Split on common sentence endings
        sentences = re.split(r'(?<=[.!?])\s+', text)
        
        # Filter out empty sentences
        sentences = [s.strip() for s in sentences if s.strip()]
        
        return sentences
    
    def _build_chunks(self, sentences: List[str]) -> List[str]:
        """
        Build chunks from sentences with overlap.
        Ensures chunks don't exceed chunk_size while maintaining context.
        """
        chunks = []
        current_chunk = []
        current_size = 0
        
        for sentence in sentences:
            sentence_size = len(sentence)
            
            # If adding this sentence exceeds chunk_size, save current chunk
            if current_size + sentence_size > self.chunk_size and current_chunk:
                chunks.append(' '.join(current_chunk))
                
                # Start new chunk with overlap
                # Keep last few sentences for context
                overlap_text = ' '.join(current_chunk)
                if len(overlap_text) > self.overlap:
                    # Find sentences that fit in overlap
                    overlap_sentences = []
                    overlap_size = 0
                    for s in reversed(current_chunk):
                        if overlap_size + len(s) <= self.overlap:
                            overlap_sentences.insert(0, s)
                            overlap_size += len(s)
                        else:
                            break
                    current_chunk = overlap_sentences
                    current_size = overlap_size
                else:
                    current_chunk = []
                    current_size = 0
            
            # Add sentence to current chunk
            current_chunk.append(sentence)
            current_size += sentence_size
        
        # Add the last chunk
        if current_chunk:
            chunks.append(' '.join(current_chunk))
        
        return chunks
    
    def get_chunk_stats(self, chunks: List[Dict]) -> Dict:
        """
        Get statistics about the chunks.
        
        Args:
            chunks: List of chunk dictionaries
            
        Returns:
            Dictionary with chunk statistics
        """
        if not chunks:
            return {
                'total_chunks': 0,
                'avg_chunk_size': 0,
                'min_chunk_size': 0,
                'max_chunk_size': 0,
                'total_characters': 0
            }
        
        sizes = [c['char_count'] for c in chunks]
        
        return {
            'total_chunks': len(chunks),
            'avg_chunk_size': sum(sizes) // len(sizes),
            'min_chunk_size': min(sizes),
            'max_chunk_size': max(sizes),
            'total_characters': sum(sizes),
            'avg_words_per_chunk': sum(c['word_count'] for c in chunks) // len(chunks)
        }


# Utility function for quick chunking
def chunk_text_simple(text: str, chunk_size: int = 1000, overlap: int = 100) -> List[str]:
    """
    Simple function to chunk text without metadata.
    
    Args:
        text: Text to chunk
        chunk_size: Size of each chunk
        overlap: Overlap between chunks
        
    Returns:
        List of text chunks
    """
    chunker = TextChunker(chunk_size=chunk_size, overlap=overlap)
    chunks = chunker.chunk_text(text)
    return [c['text'] for c in chunks]
