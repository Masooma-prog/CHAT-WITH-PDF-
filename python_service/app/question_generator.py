# Simple Question Generator (No API needed)
# Generates questions based on PDF content using templates

from typing import List, Dict
import re

def generate_questions_from_text(text: str, max_questions: int = 5) -> List[Dict]:
    """
    Generate simple questions from PDF text without using any API.
    Uses keyword extraction and templates.
    
    Args:
        text: PDF text content
        max_questions: Maximum number of questions (3-7)
    
    Returns:
        List of question dictionaries
    """
    try:
        # Limit questions based on text length
        text_length = len(text)
        if text_length < 500:
            max_questions = 3
        elif text_length < 2000:
            max_questions = 5
        else:
            max_questions = min(max_questions, 7)
        
        questions = []
        
        # Extract first sentence for context
        sentences = re.split(r'[.!?]+', text)
        sentences = [s.strip() for s in sentences if len(s.strip()) > 20]
        
        if not sentences:
            return get_default_questions()[:max_questions]
        
        # Generate questions based on templates
        templates = [
            {
                "title": "Summary",
                "question": "What is this document about?"
            },
            {
                "title": "Main Points",
                "question": "What are the main points discussed in this document?"
            },
            {
                "title": "Key Information",
                "question": "What are the key takeaways from this document?"
            },
            {
                "title": "Details",
                "question": "Can you explain the details mentioned in this document?"
            },
            {
                "title": "Purpose",
                "question": "What is the purpose of this document?"
            },
            {
                "title": "Context",
                "question": "What context or background information is provided?"
            },
            {
                "title": "Conclusion",
                "question": "What conclusions can be drawn from this document?"
            }
        ]
        
        return templates[:max_questions]
        
    except Exception as e:
        print(f"Error generating questions: {e}")
        return get_default_questions()[:max_questions]


def get_default_questions() -> List[Dict]:
    """Fallback default questions"""
    return [
        {"title": "Summary", "question": "What is this document about?"},
        {"title": "Main Points", "question": "What are the main points?"},
        {"title": "Details", "question": "Can you provide more details?"}
    ]
