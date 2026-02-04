print("Testing imports...")

print("1. Testing faiss...")
try:
    import faiss
    print("   ✓ faiss imported")
except Exception as e:
    print(f"   ✗ faiss failed: {e}")

print("2. Testing sentence-transformers...")
try:
    from sentence_transformers import SentenceTransformer
    print("   ✓ sentence-transformers imported")
except Exception as e:
    print(f"   ✗ sentence-transformers failed: {e}")

print("3. Testing FastAPI...")
try:
    from fastapi import FastAPI
    print("   ✓ FastAPI imported")
except Exception as e:
    print(f"   ✗ FastAPI failed: {e}")

print("\nAll imports tested!")
