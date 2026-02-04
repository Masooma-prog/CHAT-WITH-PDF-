import requests

try:
    response = requests.get('http://localhost:8001/health')
    print("Status Code:", response.status_code)
    print("Response:", response.json())
except Exception as e:
    print("Error:", e)
