import requests
import json

# Test search endpoint
response = requests.post('http://localhost:8001/api/search', json={
    "query": "what is this document about?",
    "pdf_id": "14",
    "top_k": 3
})

print("Status:", response.status_code)
print("\nResults:")
print(json.dumps(response.json(), indent=2))
