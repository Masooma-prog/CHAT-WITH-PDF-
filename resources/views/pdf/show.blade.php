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

<!-- Normal View -->
<div id="normalView" class="h-screen flex gap-4 p-4">
    <!-- Left Sidebar -->
    <div class="w-1/5 bg-gray-50 rounded-lg border border-gray-200 flex flex-col h-full">
        <div class="p-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-800 mb-2">ChatPDF</h1>
            <p class="text-sm text-gray-600 truncate">Welcome, {{ Auth::user()->name }}</p>
        </div>
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-lg font-semibold text-gray-900">Recent PDFs</h2>
            </div>
            <!-- Comparison Mode Toggle / Exit Button -->
            <button id="toggleCompareMode" class="w-full mt-2 px-3 py-2 text-sm bg-gradient-to-r from-purple-50 to-purple-100 hover:from-purple-100 hover:to-purple-200 text-purple-700 rounded-lg border border-purple-200 transition-all flex items-center justify-center gap-2">
                <svg id="compareModeIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span id="compareModeText">Compare Mode</span>
            </button>
            <div id="compareInfo" class="hidden mt-2 p-2 bg-purple-50 rounded text-xs text-purple-700 border border-purple-200">
                <span id="selectedCount">0 PDFs selected</span>
                <button id="startCompare" class="hidden mt-1 w-full px-2 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-xs">
                    Start Comparing
                </button>
            </div>
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
                <div class="flex items-center gap-2">
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
            
            <!-- Single PDF Viewer -->
            <div id="pdfViewer" class="hidden justify-center p-4">
                <canvas id="pdfCanvas" class="border border-gray-300 rounded shadow-lg"></canvas>
            </div>
            
            <!-- Split PDF Viewer (for comparison) -->
            <div id="splitPdfViewer" class="hidden flex gap-4 p-4 h-full">
                <!-- PDF 1 -->
                <div class="flex-1 flex flex-col bg-white rounded-lg border-2 border-blue-400 shadow-lg">
                    <div class="p-2 bg-blue-50 border-b border-blue-200 flex items-center justify-between">
                        <span id="pdf1Title" class="text-sm font-semibold text-blue-900 truncate">PDF 1</span>
                        <div class="flex items-center gap-1">
                            <button id="pdf1Prev" class="p-1 text-blue-600 hover:bg-blue-100 rounded">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <span id="pdf1Page" class="text-xs text-blue-700 px-2">1/1</span>
                            <button id="pdf1Next" class="p-1 text-blue-600 hover:bg-blue-100 rounded">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-auto bg-gray-50 flex items-center justify-center">
                        <canvas id="pdf1Canvas" class="max-w-full max-h-full"></canvas>
                    </div>
                </div>
                
                <!-- PDF 2 -->
                <div class="flex-1 flex flex-col bg-white rounded-lg border-2 border-green-400 shadow-lg">
                    <div class="p-2 bg-green-50 border-b border-green-200 flex items-center justify-between">
                        <span id="pdf2Title" class="text-sm font-semibold text-green-900 truncate">PDF 2</span>
                        <div class="flex items-center gap-1">
                            <button id="pdf2Prev" class="p-1 text-green-600 hover:bg-green-100 rounded">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <span id="pdf2Page" class="text-xs text-green-700 px-2">1/1</span>
                            <button id="pdf2Next" class="p-1 text-green-600 hover:bg-green-100 rounded">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-auto bg-gray-50 flex items-center justify-center">
                        <canvas id="pdf2Canvas" class="max-w-full max-h-full"></canvas>
                    </div>
                </div>
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
            <!-- Quick Action Buttons -->
            <div id="quickActionsContainer" class="hidden mb-3">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs text-gray-500 font-medium">Quick Actions:</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="quick-action-btn px-3 py-1.5 text-xs bg-gradient-to-r from-blue-50 to-blue-100 hover:from-blue-100 hover:to-blue-200 text-blue-700 rounded-lg border border-blue-200 transition-all" data-question="Summarize this document in 3-4 sentences">
                        <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Summarize
                    </button>
                    <button class="quick-action-btn px-3 py-1.5 text-xs bg-gradient-to-r from-purple-50 to-purple-100 hover:from-purple-100 hover:to-purple-200 text-purple-700 rounded-lg border border-purple-200 transition-all" data-question="What are the key points or main ideas?">
                        <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        Key Points
                    </button>
                    <button class="quick-action-btn px-3 py-1.5 text-xs bg-gradient-to-r from-green-50 to-green-100 hover:from-green-100 hover:to-green-200 text-green-700 rounded-lg border border-green-200 transition-all" data-question="Explain this in simple terms">
                        <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        Explain
                    </button>
                    <button class="quick-action-btn px-3 py-1.5 text-xs bg-gradient-to-r from-orange-50 to-orange-100 hover:from-orange-100 hover:to-orange-200 text-orange-700 rounded-lg border border-orange-200 transition-all" data-question="What are the important details I should know?">
                        <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Details
                    </button>
                    <button class="quick-action-btn px-3 py-1.5 text-xs bg-gradient-to-r from-pink-50 to-pink-100 hover:from-pink-100 hover:to-pink-200 text-pink-700 rounded-lg border border-pink-200 transition-all" data-question="What questions should I ask about this?">
                        <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Questions
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between mb-2">
                <button id="clearHistoryBtn" class="hidden text-xs text-gray-500 hover:text-red-600 flex items-center gap-1" title="Clear chat history">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Clear history
                </button>
                <span id="messageCount" class="hidden text-xs text-gray-400"></span>
            </div>
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

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentPdfId = null;
    let currentPdfData = null;
    let pdfDoc = null;
    let currentPageNum = 1;
    let pdfs = @json($pdfs ?? []);
    let uploadStartTime = null;
    
    // Comparison mode state
    let isCompareMode = @json($isComparison ?? false);
    let selectedPdfIds = [];
    let selectedPdfs = [];
    
    // Split-screen state
    let pdf1Doc = null;
    let pdf2Doc = null;
    let pdf1CurrentPage = 1;
    let pdf2CurrentPage = 1;
    let pdf1Data = @json($pdf1 ?? null);
    let pdf2Data = @json($pdf2 ?? null);

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
        clearHistoryBtn: document.getElementById('clearHistoryBtn'),
        messageCount: document.getElementById('messageCount'),
        quickActionsContainer: document.getElementById('quickActionsContainer'),
        toggleCompareMode: document.getElementById('toggleCompareMode'),
        compareModeText: document.getElementById('compareModeText'),
        compareInfo: document.getElementById('compareInfo'),
        selectedCount: document.getElementById('selectedCount'),
        startCompare: document.getElementById('startCompare'),
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
    
    function togglePdfSelection(pdf) {
        const index = selectedPdfIds.indexOf(pdf.id);
        if (index > -1) {
            selectedPdfIds.splice(index, 1);
            selectedPdfs = selectedPdfs.filter(p => p.id !== pdf.id);
        } else {
            // Limit to 2 PDFs
            if (selectedPdfIds.length >= 2) {
                alert('You can only compare 2 PDFs at a time. Please deselect one first.');
                return;
            }
            selectedPdfIds.push(pdf.id);
            selectedPdfs.push(pdf);
        }
        
        updateComparisonUI();
        renderPdfHistory();
    }
    
    function updateComparisonUI() {
        const count = selectedPdfIds.length;
        
        if (count === 0) {
            elements.selectedCount.textContent = 'Select 2 PDFs to compare';
        } else if (count === 1) {
            elements.selectedCount.textContent = '1 PDF selected (select 1 more)';
        } else if (count === 2) {
            elements.selectedCount.textContent = '2 PDFs selected - Ready!';
        }
        
        if (count === 2) {
            elements.startCompare.classList.remove('hidden');
        } else {
            elements.startCompare.classList.add('hidden');
        }
    }
    
    function renderPdfHistory() {
        elements.pdfHistory.innerHTML = '';
        if (pdfs.length > 0) {
            const ul = document.createElement('ul');
            pdfs.forEach(pdf => {
                const li = document.createElement('li');
                li.className = 'relative';
                
                // Only show checkboxes if in compare mode AND not currently viewing a comparison
                if (isCompareMode && currentPdfId !== 'compare') {
                    // Comparison mode: show checkboxes
                    const label = document.createElement('label');
                    label.className = 'flex items-center px-4 py-2 cursor-pointer hover:bg-gray-200 rounded-lg transition-colors text-sm';
                    
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'mr-3 w-4 h-4 text-purple-600';
                    checkbox.checked = selectedPdfIds.includes(pdf.id);
                    checkbox.addEventListener('change', () => togglePdfSelection(pdf));
                    
                    const span = document.createElement('span');
                    span.textContent = pdf.title;
                    span.className = selectedPdfIds.includes(pdf.id) ? 'font-semibold text-purple-700' : '';
                    
                    label.appendChild(checkbox);
                    label.appendChild(span);
                    li.appendChild(label);
                } else {
                    // Normal mode: clickable links
                    const a = document.createElement('a');
                    a.href = `/pdfs/${pdf.id}`;
                    a.textContent = pdf.title;
                    a.className = `block px-4 py-2 cursor-pointer hover:bg-gray-200 rounded-lg transition-colors text-sm ${currentPdfId == pdf.id ? 'bg-blue-100 text-blue-700 font-semibold' : ''}`;
                    a.addEventListener('click', (e) => {
                        e.preventDefault();
                        window.location.href = a.href;
                    });
                    li.appendChild(a);
                }
                
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
            elements.quickActionsContainer.classList.remove('hidden');
            fetchPredefinedQuestions(pdf.id);
            
            // Load chat history for this PDF
            await loadChatHistory(pdf.id);
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

    async function loadChatHistory(pdfId) {
        try {
            const response = await fetch(`/chat/${pdfId}/history`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (!response.ok) {
                console.warn('Failed to load chat history:', response.status);
                return;
            }

            const data = await response.json();
            
            if (data.success && data.messages && data.messages.length > 0) {
                console.log(`Loading ${data.messages.length} previous messages`);
                
                // Clear existing messages except welcome message
                while (elements.chatMessages.children.length > 1) {
                    elements.chatMessages.removeChild(elements.chatMessages.lastChild);
                }
                
                // Add all previous messages
                data.messages.forEach(msg => {
                    appendMessage(msg.role === 'user' ? 'user' : 'bot', msg.message);
                });
                
                // Show message count and clear button
                const userMessages = data.messages.filter(m => m.role === 'user').length;
                elements.messageCount.textContent = `${userMessages} question${userMessages !== 1 ? 's' : ''} asked`;
                elements.messageCount.classList.remove('hidden');
                elements.clearHistoryBtn.classList.remove('hidden');
                
                console.log('Chat history loaded successfully');
            } else {
                console.log('No previous chat history found');
                elements.messageCount.classList.add('hidden');
                elements.clearHistoryBtn.classList.add('hidden');
            }
        } catch (error) {
            console.error('Error loading chat history:', error);
        }
    }

    function updateMessageCount() {
        // Count user messages (excluding welcome message)
        const userMessages = Array.from(elements.chatMessages.children)
            .filter(msg => msg.classList.contains('justify-end')).length;
        
        if (userMessages > 0) {
            elements.messageCount.textContent = `${userMessages} question${userMessages !== 1 ? 's' : ''} asked`;
            elements.messageCount.classList.remove('hidden');
            elements.clearHistoryBtn.classList.remove('hidden');
        }
    }

    async function sendComparisonMessage(message) {
        appendMessage('user', message);
        elements.chatInput.value = '';
        elements.typingIndicator.classList.remove('hidden');
        elements.sendMessage.disabled = true;
        elements.chatInput.disabled = true;

        try {
            // Use pdf1Data and pdf2Data IDs for comparison
            const pdfIds = [pdf1Data.id, pdf2Data.id];
            
            console.log('Sending comparison request:', { message, pdfIds });
            
            const response = await fetch('/chat/compare', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ 
                    message: message,
                    pdf_ids: pdfIds
                })
            });
            
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server error response:', errorText);
                throw new Error('Server error: ' + response.status);
            }
            
            const data = await response.json();
            console.log('Response data:', data);
            
            if (data.success && data.answer) {
                appendMessage('bot', data.answer);
                updateMessageCount();
            } else {
                appendMessage('bot', data.answer || 'Sorry, I received an invalid response. Please try again.');
            }
        } catch (error) {
            console.error('Comparison error:', error);
            appendMessage('bot', 'Error: ' + error.message);
        } finally {
            elements.typingIndicator.classList.add('hidden');
            elements.sendMessage.disabled = false;
            elements.chatInput.disabled = false;
            elements.chatInput.focus();
        }
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
        elements.quickActionsContainer.classList.add('hidden');
        elements.predefinedQuestions.innerHTML = '<p class="text-xs text-gray-400 w-full text-center">Select a PDF to see questions.</p>';
        elements.processingBadge.classList.add('hidden');
        elements.refreshQuestionsBtn.classList.add('hidden');
    }

    elements.sendMessage.addEventListener('click', async () => {
        const message = elements.chatInput.value.trim();
        if (!message) return;
        
        // Check if in comparison mode
        if (currentPdfId === 'compare' && selectedPdfIds.length >= 2) {
            await sendComparisonMessage(message);
            return;
        }
        
        if (!currentPdfId) return;
        
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
                
                // Update message count and show clear button
                updateMessageCount();
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

    // Clear chat history button
    elements.clearHistoryBtn.addEventListener('click', async () => {
        if (!currentPdfId) return;
        
        if (!confirm('Are you sure you want to clear the chat history for this PDF? This cannot be undone.')) {
            return;
        }
        
        try {
            const response = await fetch(`/chat/${currentPdfId}/clear`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                // Clear UI
                while (elements.chatMessages.children.length > 1) {
                    elements.chatMessages.removeChild(elements.chatMessages.lastChild);
                }
                elements.messageCount.classList.add('hidden');
                elements.clearHistoryBtn.classList.add('hidden');
                
                console.log('Chat history cleared');
            } else {
                alert('Failed to clear chat history. Please try again.');
            }
        } catch (error) {
            console.error('Error clearing history:', error);
            alert('Error clearing chat history. Please try again.');
        }
    });

    // Quick action buttons
    document.querySelectorAll('.quick-action-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const question = btn.getAttribute('data-question');
            if (question && !elements.sendMessage.disabled) {
                elements.chatInput.value = question;
                elements.sendMessage.click();
            }
        });
    });

    // Toggle comparison mode
    elements.toggleCompareMode.addEventListener('click', () => {
        // If we're on a comparison page, exit to home
        if (currentPdfId === 'compare') {
            window.location.href = '/';
            return;
        }
        
        isCompareMode = !isCompareMode;
        
        if (isCompareMode) {
            elements.compareModeText.textContent = 'Exit Compare Mode';
            elements.compareInfo.classList.remove('hidden');
            elements.toggleCompareMode.classList.remove('from-purple-50', 'to-purple-100');
            elements.toggleCompareMode.classList.add('from-purple-600', 'to-purple-700', 'text-white');
        } else {
            elements.compareModeText.textContent = 'Compare Mode';
            elements.compareInfo.classList.add('hidden');
            elements.toggleCompareMode.classList.add('from-purple-50', 'to-purple-100');
            elements.toggleCompareMode.classList.remove('from-purple-600', 'to-purple-700', 'text-white');
            selectedPdfIds = [];
            selectedPdfs = [];
        }
        
        renderPdfHistory();
    });

    // Start comparison
    elements.startCompare.addEventListener('click', async () => {
        if (selectedPdfIds.length !== 2) {
            alert('Please select exactly 2 PDFs to compare');
            return;
        }
        
        // Navigate to comparison page (like clicking a PDF)
        const pdf1Id = selectedPdfIds[0];
        const pdf2Id = selectedPdfIds[1];
        window.location.href = `/compare/${pdf1Id}/${pdf2Id}`;
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
    
    // Check if in comparison mode
    @if(isset($isComparison) && $isComparison && isset($pdf1) && isset($pdf2))
        // Auto-load comparison mode
        (async () => {
            elements.welcomeMessage.classList.add('hidden');
            elements.pdfViewer.classList.add('hidden');
            elements.pdfControls.style.display = 'none';
            
            document.getElementById('splitPdfViewer').classList.remove('hidden');
            document.getElementById('splitPdfViewer').classList.add('flex');
            
            // Update title with "Combined" format
            elements.pdfTitle.textContent = `Combined: ${pdf1Data.title} + ${pdf2Data.title}`;
            
            // Change sidebar button to "Exit Comparison"
            elements.compareModeText.textContent = 'Exit Comparison';
            elements.toggleCompareMode.classList.remove('from-purple-50', 'to-purple-100', 'text-purple-700');
            elements.toggleCompareMode.classList.add('from-red-50', 'to-red-100', 'text-red-700', 'border-red-200');
            const icon = document.getElementById('compareModeIcon');
            if (icon) {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>';
            }
            
            await loadSplitPdfs();
            
            elements.chatInput.disabled = false;
            elements.sendMessage.disabled = false;
            elements.quickActionsContainer.classList.remove('hidden');
            
            document.getElementById('welcomeText').textContent = `Comparing 2 PDFs. Ask questions to compare them!`;
            
            currentPdfId = 'compare';
            selectedPdfIds = [pdf1Data.id, pdf2Data.id];
        })();
    @elseif(isset($pdf))
        selectPdf(@json($pdf));
    @endif
    
    async function loadSplitPdfs() {
        try {
            document.getElementById('pdf1Title').textContent = pdf1Data.title;
            const task1 = pdfjsLib.getDocument(`/storage/${pdf1Data.file_path}`);
            pdf1Doc = await task1.promise;
            pdf1CurrentPage = 1;
            await renderPdf1(pdf1CurrentPage);
            
            document.getElementById('pdf2Title').textContent = pdf2Data.title;
            const task2 = pdfjsLib.getDocument(`/storage/${pdf2Data.file_path}`);
            pdf2Doc = await task2.promise;
            pdf2CurrentPage = 1;
            await renderPdf2(pdf2CurrentPage);
        } catch (error) {
            console.error('Error loading PDFs:', error);
            alert('Error loading PDFs');
        }
    }

    async function renderPdf1(num) {
        if (!pdf1Doc) return;
        const page = await pdf1Doc.getPage(num);
        const viewport = page.getViewport({ scale: 1.0 });
        const canvas = document.getElementById('pdf1Canvas');
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        document.getElementById('pdf1Page').textContent = `${num}/${pdf1Doc.numPages}`;
    }

    async function renderPdf2(num) {
        if (!pdf2Doc) return;
        const page = await pdf2Doc.getPage(num);
        const viewport = page.getViewport({ scale: 1.0 });
        const canvas = document.getElementById('pdf2Canvas');
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        document.getElementById('pdf2Page').textContent = `${num}/${pdf2Doc.numPages}`;
    }

    // PDF navigation for split view
    document.getElementById('pdf1Prev').addEventListener('click', () => {
        if (pdf1CurrentPage > 1) { pdf1CurrentPage--; renderPdf1(pdf1CurrentPage); }
    });
    document.getElementById('pdf1Next').addEventListener('click', () => {
        if (pdf1Doc && pdf1CurrentPage < pdf1Doc.numPages) { pdf1CurrentPage++; renderPdf1(pdf1CurrentPage); }
    });
    document.getElementById('pdf2Prev').addEventListener('click', () => {
        if (pdf2CurrentPage > 1) { pdf2CurrentPage--; renderPdf2(pdf2CurrentPage); }
    });
    document.getElementById('pdf2Next').addEventListener('click', () => {
        if (pdf2Doc && pdf2CurrentPage < pdf2Doc.numPages) { pdf2CurrentPage++; renderPdf2(pdf2CurrentPage); }
    });
});
</script>
</body>
</html>