<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with your PDF</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <style>
        body { font-family: Inter, sans-serif; margin: 0; padding: 0; background-color: #f0f2f5; }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .status-badge { padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .status-processing { background: #dbeafe; color: #1e40af; }
        
        /* Progress modal styles */
        .modal-backdrop { 
            position: fixed; 
            inset: 0; 
            background: rgba(0,0,0,0.5); 
            z-index: 9998;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-backdrop.hidden {
            display: none !important;
        }
        .progress-modal {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            z-index: 9999;
        }
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #2563eb);
            transition: width 0.3s ease;
            border-radius: 9999px;
        }
    </style>
</head>
<body>

<!-- Upload Progress Modal -->
<div id="uploadProgressModal" class="modal-backdrop hidden">
    <div class="progress-modal">
        <div class="text-center mb-4">
            <svg class="w-16 h-16 mx-auto text-blue-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Processing Your PDF</h3>
            <p class="text-sm text-gray-600" id="uploadProgressMessage">Uploading PDF file...</p>
        </div>
        
        <div class="mb-4">
            <div class="progress-bar-container">
                <div id="uploadProgressBar" class="progress-bar" style="width: 0%"></div>
            </div>
            <div class="flex justify-between items-center mt-2">
                <span class="text-xs text-gray-600" id="uploadProgressPercent">0%</span>
                <span class="text-xs text-gray-500" id="uploadProgressTime">Elapsed: 0s</span>
            </div>
        </div>
        
        <div class="text-center">
            <div class="flex items-center justify-center space-x-2">
                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                <span class="text-sm text-gray-600">Please wait, this may take a few minutes...</span>
            </div>
        </div>
    </div>
</div>

<div class="h-screen flex gap-4 p-4">
    <!-- Left Sidebar -->
    <div class="w-1/5 bg-gray-50 rounded-lg border border-gray-200 flex flex-col h-full">
        <div class="p-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-800 mb-2">ChatPDF</h1>
            <p class="text-sm text-gray-600 truncate">Welcome, {{ Auth::user()->name }}</p>
        </div>
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Recent PDFs</h2>
        </div>
        <div id="pdfHistory" class="flex-1 overflow-y-auto p-2"></div>
        <div class="p-4 border-t border-gray-200">
            <form id="uploadForm" action="{{ route('pdf.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <label for="pdf_file_input">
                    <div id="uploadButton" class="bg-blue-600 hover:bg-blue-700 text-white text-center py-2 rounded-lg transition-colors cursor-pointer mb-3">
                        Upload PDF
                    </div>
                </label>
                <input type="file" name="pdf" id="pdf_file_input" style="display: none;" accept="application/pdf">
            </form>
            <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
               class="block bg-gray-200 hover:bg-gray-300 text-gray-800 text-center py-2 rounded-lg transition-colors text-sm">Logout</a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
        </div>
    </div>

    <!-- Main Content: PDF Viewer -->
    <div class="w-3/5 bg-white rounded-lg border border-gray-200 flex flex-col h-full">
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h2 id="pdfTitle" class="text-lg font-semibold text-gray-900 truncate">Select a PDF</h2>
                    <span id="processingBadge" class="status-badge hidden"></span>
                </div>
                <div id="pdfControls" style="display: none;" class="flex items-center space-x-2">
                    <button id="prevPage" class="p-2 text-gray-600 hover:text-gray-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <span id="pageInfo" class="text-sm text-gray-600"></span>
                    <button id="nextPage" class="p-2 text-gray-600 hover:text-gray-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-auto bg-gray-100" id="pdfViewContainer">
            <div id="welcomeMessage" class="flex items-center justify-center h-full text-center p-4">
                <div>
                    <svg class="w-24 h-24 mx-auto mb-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No PDF Selected</h3>
                    <p class="text-gray-600 mb-6">Upload a PDF or select one from the history</p>
                    <button onclick="document.getElementById('pdf_file_input').click()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg">
                        Upload Your First PDF
                    </button>
                </div>
            </div>
            <div id="pdfViewer" class="hidden justify-center p-4">
                <canvas id="pdfCanvas" class="border border-gray-300 rounded shadow-lg"></canvas>
            </div>
        </div>
    </div>

    <!-- Right Sidebar: AI Assistant -->
    <div class="w-1/5 bg-white rounded-lg border border-gray-200 flex flex-col h-full">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">AI Assistant</h2>
            <p class="text-sm text-gray-600" id="chatStatus">Ready to help</p>
        </div>

        <div class="p-4 border-b border-gray-200 flex-1 flex flex-col min-h-0">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">Quick Questions</h3>
                <button id="refreshQuestionsBtn" class="hidden text-xs text-blue-600 hover:text-blue-800" title="Refresh questions">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
            </div>
            <div id="predefinedQuestions" class="flex-1 overflow-y-auto flex flex-col gap-2">
                <p class="text-xs text-gray-400 w-full text-center">Select a PDF to see questions.</p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 min-h-0" id="chatMessagesContainer">
            <div id="chatMessages" class="space-y-4">
                <div class="flex animate-fade-in-up">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"></path></svg>
                        </div>
                    </div>
                    <div class="ml-3 flex-1"><div class="bg-gray-100 rounded-lg px-4 py-2"><p id="welcomeText" class="text-sm text-gray-700">Hello! Upload a PDF and I'll help analyze it.</p></div></div>
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-gray-200">
            <div class="flex space-x-2">
                <input type="text" id="chatInput" placeholder="Ask a question..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" disabled>
                <button id="sendMessage" class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg" disabled>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
            </div>
            <div id="typingIndicator" class="hidden mt-2 flex items-center text-sm text-gray-600">
                <div class="flex space-x-1">
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                </div>
                <span class="ml-2">AI is thinking...</span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentPdfId = null;
    let currentPdfData = null;
    let pdfDoc = null;
    let currentPageNum = 1;
    let pdfs = @json($pdfs ?? []);
    let uploadStartTime = null;

    const elements = {
        pdfHistory: document.getElementById('pdfHistory'),
        pdfTitle: document.getElementById('pdfTitle'),
        pdfViewer: document.getElementById('pdfViewer'),
        welcomeMessage: document.getElementById('welcomeMessage'),
        chatInput: document.getElementById('chatInput'),
        sendMessage: document.getElementById('sendMessage'),
        chatMessages: document.getElementById('chatMessages'),
        predefinedQuestions: document.getElementById('predefinedQuestions'),
        uploadForm: document.getElementById('uploadForm'),
        pdfFileInput: document.getElementById('pdf_file_input'),
        uploadButton: document.getElementById('uploadButton'),
        pageInfo: document.getElementById('pageInfo'),
        pdfControls: document.getElementById('pdfControls'),
        typingIndicator: document.getElementById('typingIndicator'),
        processingBadge: document.getElementById('processingBadge'),
        refreshQuestionsBtn: document.getElementById('refreshQuestionsBtn'),
        chatStatus: document.getElementById('chatStatus'),
        uploadProgressModal: document.getElementById('uploadProgressModal'),
        uploadProgressBar: document.getElementById('uploadProgressBar'),
        uploadProgressMessage: document.getElementById('uploadProgressMessage'),
        uploadProgressPercent: document.getElementById('uploadProgressPercent'),
        uploadProgressTime: document.getElementById('uploadProgressTime')
    };

    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';

    // PHASE 2 FIX: Ensure modal is ALWAYS hidden on page load
    function hideUploadModal() {
        if (elements.uploadProgressModal) {
            elements.uploadProgressModal.classList.add('hidden');
        }
    }
    
    // Hide immediately
    hideUploadModal();
    
    // Hide after delays to catch any auto-triggers
    setTimeout(hideUploadModal, 50);
    setTimeout(hideUploadModal, 100);
    setTimeout(hideUploadModal, 500);

    // ========== UPLOAD HANDLING WITH PROGRESS ==========
    
    elements.pdfFileInput.addEventListener('change', async (event) => {
        if (event.target.files.length === 0) return;
        
        const file = event.target.files[0];
        const formData = new FormData(elements.uploadForm);
        
        // Show progress modal
        elements.uploadProgressModal.classList.remove('hidden');
        uploadStartTime = Date.now();
        updateUploadProgress(10, 'Uploading PDF file...');
        startTimeCounter();
        
        try {
            // Step 1: Upload PDF
            const uploadResponse = await fetch(elements.uploadForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            });

            if (!uploadResponse.ok) {
                const errorData = await uploadResponse.json().catch(() => ({ message: 'Upload failed' }));
                throw new Error(errorData.message || 'Upload failed');
            }

            const uploadData = await uploadResponse.json();
            
            if (!uploadData.success) {
                throw new Error(uploadData.message || 'Upload failed');
            }

            const pdfId = uploadData.pdf_id;
            updateUploadProgress(30, 'PDF uploaded! Generating questions...');
            
            // Step 2: Poll for questions (no timeout limit)
            await pollForQuestions(pdfId);
            
        } catch (error) {
            console.error('Upload error:', error);
            updateUploadProgress(0, `Error: ${error.message}`, true);
            
            setTimeout(() => {
                elements.uploadProgressModal.classList.add('hidden');
            }, 3000);
        } finally {
            elements.pdfFileInput.value = '';
        }
    });

    async function pollForQuestions(pdfId) {
        let attempt = 0;
        const maxAttempts = 20; // 10 seconds max (20 * 500ms)
        const pollInterval = 500; // 500ms - fast polling since backend is instant
        
        while (attempt < maxAttempts) {
            attempt++;
            
            // Calculate progress (30% to 95%)
            const progress = 30 + ((attempt / maxAttempts) * 65);
            const elapsedSeconds = Math.floor((Date.now() - uploadStartTime) / 1000);
            const message = `Generating questions... (${elapsedSeconds}s elapsed)`;
            
            updateUploadProgress(Math.min(progress, 95), message);

            try {
                const response = await fetch(`/pdfs/${pdfId}/auto-fetch-questions`, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    console.warn('Polling request failed:', response.status);
                    await sleep(pollInterval);
                    continue;
                }

                const data = await response.json();
                console.log(`Poll attempt ${attempt}:`, data);

                if (data.status === 'ready' && data.questions && data.questions.length > 0) {
                    // Success!
                    updateUploadProgress(100, `✓ Generated ${data.questions.length} questions!`);
                    
                    await sleep(1500);
                    window.location.href = `/pdfs/${pdfId}`;
                    return;
                }

                if (data.status === 'failed') {
                    throw new Error('Question generation failed');
                }

                // Still processing, continue polling
                await sleep(pollInterval);

            } catch (error) {
                console.error(`Poll attempt ${attempt} error:`, error);
                
                // Don't give up on network errors
                if (attempt < maxAttempts) {
                    await sleep(pollInterval);
                    continue;
                }
                
                throw error;
            }
        }

        // Timeout reached - redirect anyway
        updateUploadProgress(90, 'Taking longer than expected. Opening PDF...');
        await sleep(2000);
        window.location.href = `/pdfs/${pdfId}`;
    }

    function updateUploadProgress(percent, message, isError = false) {
        elements.uploadProgressBar.style.width = percent + '%';
        elements.uploadProgressPercent.textContent = Math.round(percent) + '%';
        elements.uploadProgressMessage.textContent = message;
        
        if (isError) {
            elements.uploadProgressBar.style.background = '#ef4444';
        } else if (percent === 100) {
            elements.uploadProgressBar.style.background = 'linear-gradient(90deg, #10b981, #059669)';
        }
    }

    function startTimeCounter() {
        const interval = setInterval(() => {
            if (elements.uploadProgressModal.classList.contains('hidden')) {
                clearInterval(interval);
                return;
            }
            
            const elapsed = Math.floor((Date.now() - uploadStartTime) / 1000);
            elements.uploadProgressTime.textContent = `Elapsed: ${elapsed}s`;
        }, 1000);
    }

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // ========== PDF HISTORY ==========
    
    function renderPdfHistory() {
        elements.pdfHistory.innerHTML = '';
        if (pdfs.length > 0) {
            const ul = document.createElement('ul');
            pdfs.forEach(pdf => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = `/pdfs/${pdf.id}`;
                a.textContent = pdf.title;
                a.className = `block px-4 py-2 cursor-pointer hover:bg-gray-200 rounded-lg transition-colors text-sm ${currentPdfId == pdf.id ? 'bg-blue-100 text-blue-700 font-semibold' : ''}`;
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    window.location.href = a.href;
                });
                li.appendChild(a);
                ul.appendChild(li);
            });
            elements.pdfHistory.appendChild(ul);
        } else {
            elements.pdfHistory.innerHTML = '<div class="p-4 text-center text-gray-500 text-sm">No PDFs uploaded yet.</div>';
        }
    }

    // ========== STATUS CHECKING ==========
    
    async function checkProcessingStatus(pdfId) {
        try {
            const response = await fetch(`/pdfs/${pdfId}/status`);
            const data = await response.json();
            
            if (data.success) {
                const badge = elements.processingBadge;
                badge.classList.remove('hidden', 'status-pending', 'status-completed', 'status-failed', 'status-processing');
                
                if (data.questions_status === 'completed') {
                    badge.textContent = '✓ Ready';
                    badge.classList.add('status-completed');
                } else if (data.questions_status === 'failed') {
                    badge.textContent = '✗ Failed';
                    badge.classList.add('status-failed');
                } else if (data.questions_status === 'processing') {
                    badge.textContent = '⏳ Generating Questions';
                    badge.classList.add('status-processing');
                    // Auto-poll for questions if still processing
                    setTimeout(() => pollQuestionsOnViewPage(pdfId), 10000);
                } else {
                    badge.textContent = '⏳ Processing';
                    badge.classList.add('status-pending');
                }
                
                if (data.python_pdf_id) {
                    elements.chatStatus.textContent = `Python ID: ${data.python_pdf_id.slice(-8)}`;
                }
            }
        } catch (e) {
            console.error('Status check failed:', e);
        }
    }

    async function pollQuestionsOnViewPage(pdfId) {
        try {
            const response = await fetch(`/pdfs/${pdfId}/questions`);
            const data = await response.json();
            
            if (data.success && data.questions && data.questions.length > 0) {
                // Questions are ready! Reload the page
                window.location.reload();
            } else if (data.status === 'processing') {
                // Still processing, check again in 10 seconds
                setTimeout(() => pollQuestionsOnViewPage(pdfId), 10000);
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }

    // ========== PDF SELECTION AND RENDERING ==========
    
    async function selectPdf(pdf) {
        if (!pdf || !pdf.id) return;
        currentPdfId = pdf.id;
        currentPdfData = pdf;
        renderPdfHistory();
        resetChatUI(pdf.title);
        elements.pdfTitle.textContent = pdf.title;
        elements.welcomeMessage.classList.add('hidden');
        elements.pdfViewer.classList.remove('hidden');
        elements.pdfControls.style.display = 'flex';
        elements.refreshQuestionsBtn.classList.remove('hidden');
        
        checkProcessingStatus(pdf.id);
        
        try {
            const loadingTask = pdfjsLib.getDocument(`/storage/${pdf.file_path}`);
            pdfDoc = await loadingTask.promise;
            currentPageNum = 1;
            renderPage(currentPageNum);
            elements.chatInput.disabled = false;
            elements.sendMessage.disabled = false;
            fetchPredefinedQuestions(pdf.id);
        } catch (error) {
            console.error('Error loading PDF:', error);
            elements.pdfTitle.textContent = 'Error loading PDF';
        }
    }

    async function renderPage(num) {
        if (!pdfDoc) return;
        const page = await pdfDoc.getPage(num);
        const viewport = page.getViewport({ scale: 1.5 });
        const canvas = document.getElementById('pdfCanvas');
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        elements.pageInfo.textContent = `Page ${num} / ${pdfDoc.numPages}`;
    }

    // ========== QUESTIONS HANDLING ==========
    
    async function fetchPredefinedQuestions(pdfId, isRetry = false) {
        elements.predefinedQuestions.innerHTML = '<p class="text-xs text-gray-400 w-full text-center">Loading questions...</p>';
        
        try {
            const response = await fetch(`/pdfs/${pdfId}/questions`);
            if (!response.ok) throw new Error('Network error');
            const data = await response.json();
            
            elements.predefinedQuestions.innerHTML = '';
            
            if (data.questions && data.questions.length > 0) {
                data.questions.forEach(q => {
                    const button = document.createElement('button');
                    // Show full question text, truncate if too long
                    const questionText = q.question.length > 80 
                        ? q.question.substring(0, 80) + '...' 
                        : q.question;
                    button.textContent = questionText;
                    button.title = q.question; // Full question on hover
                    button.classList.add('bg-gray-100', 'hover:bg-gray-200', 'text-gray-800', 'px-3', 'py-2', 'rounded-lg', 'text-xs', 'transition-colors', 'text-left', 'w-full', 'whitespace-normal', 'leading-relaxed');
                    button.onclick = () => {
                        elements.chatInput.value = q.question;
                        elements.sendMessage.click();
                    };
                    elements.predefinedQuestions.appendChild(button);
                });
            } else {
                let msg = 'No questions yet.';
                if (data.status === 'processing') {
                    msg = '⏳ Questions are being generated...';
                } else if (!isRetry) {
                    msg += ' <button id="retryQuestionsBtn" class="text-blue-600 underline">Generate now</button>';
                }
                elements.predefinedQuestions.innerHTML = `<p class="text-xs text-gray-400 w-full text-center">${msg}</p>`;
                
                const retryBtn = document.getElementById('retryQuestionsBtn');
                if (retryBtn) {
                    retryBtn.onclick = () => regenerateQuestions(pdfId);
                }
            }
        } catch (error) {
            console.error('Failed to fetch questions:', error);
            elements.predefinedQuestions.innerHTML = '<p class="text-xs text-red-500 w-full text-center">Could not load questions.</p>';
        }
    }

    async function regenerateQuestions(pdfId) {
        elements.predefinedQuestions.innerHTML = '<p class="text-xs text-gray-400 w-full text-center">⏳ Generating questions...</p>';
        
        try {
            const response = await fetch(`/pdfs/${pdfId}/regenerate-questions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Start polling for questions
                setTimeout(() => fetchPredefinedQuestions(pdfId, true), 5000);
            } else {
                elements.predefinedQuestions.innerHTML = `<p class="text-xs text-red-500 w-full text-center">${data.message}</p>`;
            }
        } catch (error) {
            console.error('Regeneration failed:', error);
            elements.predefinedQuestions.innerHTML = '<p class="text-xs text-red-500 w-full text-center">Failed to regenerate.</p>';
        }
    }
    
    elements.refreshQuestionsBtn.addEventListener('click', () => {
        if (currentPdfId) regenerateQuestions(currentPdfId);
    });

    // ========== CHAT FUNCTIONALITY ==========
    
    function appendMessage(sender, message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'flex mb-4 animate-fade-in-up';
        const sanitized = message.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, '<br>');

        if (sender === 'user') {
            messageDiv.classList.add('justify-end');
            messageDiv.innerHTML = `<div class="bg-blue-500 text-white rounded-lg px-4 py-2 max-w-xs break-words">${sanitized}</div>`;
        } else {
            messageDiv.innerHTML = `
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"></path></svg>
                    </div>
                </div>
                <div class="ml-3 flex-1"><div class="bg-gray-100 rounded-lg px-4 py-2 max-w-xs break-words">${sanitized}</div></div>`;
        }
        elements.chatMessages.appendChild(messageDiv);
        elements.chatMessages.parentElement.scrollTop = elements.chatMessages.parentElement.scrollHeight;
    }

    function resetChatUI(pdfTitle = null) {
        while (elements.chatMessages.children.length > 1) {
            elements.chatMessages.removeChild(elements.chatMessages.lastChild);
        }
        if (pdfTitle) {
            document.getElementById('welcomeText').textContent = `Hello! I'm ready to help with "${pdfTitle}".`;
        }
        elements.chatInput.disabled = true;
        elements.sendMessage.disabled = true;
        elements.predefinedQuestions.innerHTML = '<p class="text-xs text-gray-400 w-full text-center">Select a PDF to see questions.</p>';
        elements.processingBadge.classList.add('hidden');
        elements.refreshQuestionsBtn.classList.add('hidden');
    }

    elements.sendMessage.addEventListener('click', async () => {
        const message = elements.chatInput.value.trim();
        if (!message || !currentPdfId) return;
        
        appendMessage('user', message);
        elements.chatInput.value = '';
        elements.typingIndicator.classList.remove('hidden');
        elements.sendMessage.disabled = true;
        elements.chatInput.disabled = true;

        try {
            const response = await fetch(`/chat/${currentPdfId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ message })
            });
            
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server error response:', errorText);
                throw new Error('Server error');
            }
            
            const data = await response.json();
            console.log('Chat response:', data);
            
            if (data.answer) {
                appendMessage('bot', data.answer);
            } else {
                console.error('No answer in response:', data);
                appendMessage('bot', 'Sorry, I received an invalid response. Please try again.');
            }
        } catch (error) {
            console.error('Error:', error);
            appendMessage('bot', 'Sorry, I encountered an error. Please try again.');
        } finally {
            elements.typingIndicator.classList.add('hidden');
            elements.sendMessage.disabled = false;
            elements.chatInput.disabled = false;
            elements.chatInput.focus();
        }
    });
    
    elements.chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !elements.sendMessage.disabled) elements.sendMessage.click();
    });

    // ========== PDF NAVIGATION ==========
    
    document.getElementById('prevPage').addEventListener('click', () => {
        if (currentPageNum > 1) { currentPageNum--; renderPage(currentPageNum); }
    });

    document.getElementById('nextPage').addEventListener('click', () => {
        if (pdfDoc && currentPageNum < pdfDoc.numPages) { currentPageNum++; renderPage(currentPageNum); }
    });

    // ========== INITIALIZE ==========
    
    renderPdfHistory();
    @if(isset($pdf))
        selectPdf(@json($pdf));
    @endif
});
</script>
</body>
</html>