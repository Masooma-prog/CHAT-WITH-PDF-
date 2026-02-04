import requests
import json

# Test Phase 8: RAG Chat
print("Testing Phase 8: RAG Chat with Groq\n")

# Test 1: Ask a question about the PDF
response = requests.post('http://localhost:8001/api/chat', json={
    "question": "What is artificial intelligence?",
    "pdf_id": "14"
})

print("Status:", response.status_code)
print("\nResponse:")
result = response.json()

if result['success']:
    print(f"\n✅ Answer:\n{result['answer']}")
    print(f"\n📊 Model: {result['model']}")
    print(f"📊 Tokens used: {result['tokens_used']}")
    print(f"📊 Sources: {result['sources'][0]['text'][:100]}...")
else:
    print(f"\n❌ Error: {result.get('message', 'Unknown error')}")

# Test 2: Ask another question
print("\n" + "="*60 + "\n")
response2 = requests.post('http://localhost:8001/api/chat', json={
    "question": "What are some applications of AI mentioned in the document?",
    "pdf_id": "14"
})

result2 = response2.json()
if result2['success']:
    print(f"✅ Answer:\n{result2['answer']}")
    print(f"\n📊 Tokens used: {result2['tokens_used']}")
