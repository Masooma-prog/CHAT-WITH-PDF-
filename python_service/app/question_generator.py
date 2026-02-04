# Intelligent Question Generator using Groq LLM
# Generates smart, context-aware questions from PDF content

from typing import List, Dict
import re
import os

def generate_questions_from_text(text: str, max_questions: int = 5) -> List[Dict]:
    """
    Generate intelligent questions from PDF text using Groq LLM.
    Falls back to templates if API fails.
    
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
        
        # Try to generate intelligent questions using Groq
        groq_questions = generate_smart_questions_with_groq(text, max_questions)
        if groq_questions:
            return groq_questions
        
        # Fallback to template-based questions
        print("⚠️ Falling back to template questions")
        return generate_template_questions(text, max_questions)
        
    except Exception as e:
        print(f"Error generating questions: {e}")
        return get_default_questions()[:max_questions]


def generate_smart_questions_with_groq(text: str, max_questions: int) -> List[Dict]:
    """
    Use Groq LLM to generate intelligent, context-aware questions.
    
    Args:
        text: PDF text content
        max_questions: Number of questions to generate
    
    Returns:
        List of question dictionaries or None if failed
    """
    try:
        from groq import Groq
        
        # Check for API key
        groq_api_key = os.getenv("GROQ_API_KEY")
        if not groq_api_key:
            print("⚠️ GROQ_API_KEY not found")
            return None
        
        client = Groq(api_key=groq_api_key)
        
        # Truncate text if too long (keep first 2000 chars)
        text_sample = text[:2000] if len(text) > 2000 else text
        
        # Create prompt for question generation
        prompt = f"""Based on the following document excerpt, generate {max_questions} intelligent, diverse questions that someone might ask about this content.

Document excerpt:
{text_sample}

Requirements:
- Generate exactly {max_questions} questions
- Make questions specific to the content
- Vary the question types (what, how, why, explain, describe, etc.)
- Keep questions clear and concise
- Make them interesting and useful

Format your response as a JSON array with this structure:
[
  {{"title": "Brief Topic", "question": "Full question?"}},
  {{"title": "Brief Topic", "question": "Full question?"}}
]

Only return the JSON array, nothing else."""

        # Call Groq API
        response = client.chat.completions.create(
            model="llama-3.3-70b-versatile",
            messages=[{"role": "user", "content": prompt}],
            temperature=0.7,  # Higher temperature for more creative questions
            max_tokens=500
        )
        
        answer = response.choices[0].message.content.strip()
        
        # Parse JSON response
        import json
        
        # Extract JSON from response (in case there's extra text)
        json_match = re.search(r'\[.*\]', answer, re.DOTALL)
        if json_match:
            questions_data = json.loads(json_match.group())
            
            # Validate and format questions
            questions = []
            for q in questions_data[:max_questions]:
                if isinstance(q, dict) and 'title' in q and 'question' in q:
                    questions.append({
                        'title': q['title'][:50],  # Limit title length
                        'question': q['question'][:200]  # Limit question length
                    })
            
            if len(questions) >= 3:  # At least 3 questions
                print(f"✅ Generated {len(questions)} intelligent questions using Groq")
                return questions
        
        print("⚠️ Failed to parse Groq response")
        return None
        
    except Exception as e:
        print(f"❌ Groq question generation error: {e}")
        return None


def generate_template_questions(text: str, max_questions: int) -> List[Dict]:
    """
    Generate template-based questions as fallback.
    
    Args:
        text: PDF text content
        max_questions: Number of questions
    
    Returns:
        List of question dictionaries
    """
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


def get_default_questions() -> List[Dict]:
    """Fallback default questions"""
    return [
        {"title": "Summary", "question": "What is this document about?"},
        {"title": "Main Points", "question": "What are the main points?"},
        {"title": "Details", "question": "Can you provide more details?"}
    ]
