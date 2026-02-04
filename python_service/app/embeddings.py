# Phase 6: Embeddings Module (HuggingFace Transformers - Python 3.13 Compatible)
# Converts text into vector embeddings using transformers directly

from transformers import AutoTokenizer, AutoModel
import torch
from typing import List
import numpy as np

# Configuration
MODEL_NAME = "sentence-transformers/all-MiniLM-L6-v2"
EMBEDDING_DIMENSION = 384

class EmbeddingGenerator:
    """
    Generates embeddings using HuggingFace transformers.
    Direct implementation without sentence-transformers library.
    """
    
    def __init__(self):
        """Initialize the model and tokenizer."""
        print(f"📥 Loading embedding model: {MODEL_NAME}")
        print("   (First time will download ~90MB)")
        
        self.tokenizer = AutoTokenizer.from_pretrained(MODEL_NAME)
        self.model = AutoModel.from_pretrained(MODEL_NAME)
        self.model.eval()  # Set to evaluation mode
        self.dimension = EMBEDDING_DIMENSION
        
        print(f"✅ Model loaded. Embedding dimension: {self.dimension}")
    
    def _mean_pooling(self, model_output, attention_mask):
        """Mean pooling to get sentence embeddings."""
        token_embeddings = model_output[0]
        input_mask_expanded = attention_mask.unsqueeze(-1).expand(token_embeddings.size()).float()
        return torch.sum(token_embeddings * input_mask_expanded, 1) / torch.clamp(input_mask_expanded.sum(1), min=1e-9)
    
    def generate_embedding(self, text: str) -> List[float]:
        """
        Generate embedding for a single text.
        
        Args:
            text: Text to embed
            
        Returns:
            List of floats representing the embedding vector
        """
        if not text or not text.strip():
            return [0.0] * self.dimension
        
        # Tokenize
        encoded_input = self.tokenizer([text], padding=True, truncation=True, return_tensors='pt', max_length=512)
        
        # Generate embeddings
        with torch.no_grad():
            model_output = self.model(**encoded_input)
        
        # Mean pooling
        embeddings = self._mean_pooling(model_output, encoded_input['attention_mask'])
        
        # Normalize
        embeddings = torch.nn.functional.normalize(embeddings, p=2, dim=1)
        
        return embeddings[0].numpy().tolist()
    
    def generate_embeddings_batch(self, texts: List[str]) -> List[List[float]]:
        """
        Generate embeddings for multiple texts at once (faster).
        
        Args:
            texts: List of texts to embed
            
        Returns:
            List of embedding vectors
        """
        if not texts:
            return []
        
        # Filter out empty texts
        valid_texts = [t if t and t.strip() else " " for t in texts]
        
        print(f"📊 Generating embeddings for {len(valid_texts)} texts...")
        
        # Tokenize all texts
        encoded_input = self.tokenizer(valid_texts, padding=True, truncation=True, return_tensors='pt', max_length=512)
        
        # Generate embeddings
        with torch.no_grad():
            model_output = self.model(**encoded_input)
        
        # Mean pooling
        embeddings = self._mean_pooling(model_output, encoded_input['attention_mask'])
        
        # Normalize
        embeddings = torch.nn.functional.normalize(embeddings, p=2, dim=1)
        
        print(f"✅ Generated {len(embeddings)} embeddings")
        
        return embeddings.numpy().tolist()


# Global embedding generator instance
_embedding_generator = None

def get_embedding_generator() -> EmbeddingGenerator:
    """
    Get or create the global embedding generator instance.
    Singleton pattern.
    """
    global _embedding_generator
    if _embedding_generator is None:
        _embedding_generator = EmbeddingGenerator()
    return _embedding_generator


# Convenience functions
def generate_embedding(text: str) -> List[float]:
    """Generate embedding for a single text."""
    generator = get_embedding_generator()
    return generator.generate_embedding(text)


def generate_embeddings_batch(texts: List[str]) -> List[List[float]]:
    """Generate embeddings for multiple texts."""
    generator = get_embedding_generator()
    return generator.generate_embeddings_batch(texts)
