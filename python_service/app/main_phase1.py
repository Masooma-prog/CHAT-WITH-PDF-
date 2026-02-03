# PHASE 1: Minimal Python Service
# Only basic health check - no PDF processing yet

from fastapi import FastAPI
import uvicorn
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Initialize FastAPI app
app = FastAPI(
    title="ChatPDF Python Service - Phase 1",
    description="Basic service setup",
    version="1.0.0"
)

# Health check endpoint
@app.get("/health")
async def health_check():
    """Check if service is running"""
    return {
        "status": "ok",
        "phase": 1,
        "message": "Python service is running"
    }

@app.get("/")
async def root():
    """Root endpoint"""
    return {
        "service": "ChatPDF Python Service",
        "phase": 1,
        "status": "ready"
    }

# Run the application
if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8001)
