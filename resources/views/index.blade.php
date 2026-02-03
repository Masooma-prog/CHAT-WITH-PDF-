<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'ChatPDF' }} - AI-Powered PDF Chat</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <!-- Tailwind CSS and Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="font-sans antialiased bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-2xl font-bold text-gray-900">
                            <span class="text-blue-600">Chat</span>PDF
                        </h1>
                    </div>
                    <div class="ml-4 text-sm text-gray-600">
                        AI-Powered PDF Analysis
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Upload button -->
                    <button id="uploadBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <span>Upload PDF</span>
                    </button>
                    
                    <!-- Hidden file input -->
                    <input type="file" id="pdfFileInput" accept=".pdf" style="display: none;">
                    
                    <!-- Status indicator -->
                    <div id="statusIndicator" class="hidden">
                        <div class="flex items-center space-x-2">
                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                            <span class="text-sm text-gray-600">Processing...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <main class="min-h-screen">
        @yield('content')
    </main>

    <!-- Upload Modal -->
    <div id="uploadModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4">Upload PDF</h3>
            <div id="uploadProgress" class="hidden mb-4">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="progressBar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <p class="text-sm text-gray-600 mt-2" id="progressText">Preparing upload...</p>
            </div>
            <div id="uploadResult" class="hidden">
                <p class="text-green-600 mb-4">PDF uploaded successfully!</p>
            </div>
            <div class="flex justify-end space-x-3">
                <button id="cancelUpload" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">Cancel</button>
                <button id="confirmUpload" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">Upload</button>
            </div>
        </div>
    </div>

    <!-- Error Toast -->
    <div id="errorToast" class="hidden fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span id="errorMessage"></span>
        </div>
    </div>

    <!-- Success Toast -->
    <div id="successToast" class="hidden fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span id="successMessage"></span>
        </div>
    </div>

    <!-- Global JavaScript variables -->
    <script>
        window.chatPDF = {
            csrfToken: '{{ csrf_token() }}',
            apiUrl: '{{ url('/api') }}',
            currentPdfId: {{ isset($pdf) ? $pdf->id : 'null' }},
            currentSessionId: null,
        };
    </script>
</body>
</html>