<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload PDF</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Inter Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <h1 class="text-3xl font-bold mb-6 text-center text-gray-800">Upload a PDF</h1>

        <!-- File Upload Form -->
        <form id="uploadForm" action="{{ route('pdf.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-6">
                <label for="pdf_file" class="block text-sm font-medium text-gray-700 mb-2">
                    Select PDF file:
                </label>
                <div id="drop-zone" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                    <div class="space-y-1 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-gray-600">
                            <label for="pdf_file" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                <span>Upload a file</span>
                                <input id="pdf_file" name="pdf_file" type="file" class="sr-only" accept=".pdf">
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p id="fileName" class="text-xs text-gray-500"></p>
                    </div>
                </div>
            </div>

            <div class="flex justify-center">
                <button type="submit" id="submitBtn" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-300 ease-in-out">
                    Upload
                </button>
            </div>
        </form>

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="hidden mt-4">
            <div class="flex items-center justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900 mr-3"></div>
                <span class="text-gray-700">Uploading...</span>
            </div>
            <p id="uploadMessage" class="mt-2 text-center text-sm text-gray-600"></p>
        </div>

        <!-- Success/Error Message -->
        <div id="statusMessage" class="hidden mt-4 p-3 rounded-md text-center"></div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const uploadForm = document.getElementById('uploadForm');
            const fileInput = document.getElementById('pdf_file');
            const fileNameDisplay = document.getElementById('fileName');
            const loadingIndicator = document.getElementById('loadingIndicator');
            const statusMessage = document.getElementById('statusMessage');
            const submitBtn = document.getElementById('submitBtn');
            const dropZone = document.getElementById('drop-zone');

            // Handle file selection from input
            fileInput.addEventListener('change', (event) => {
                if (event.target.files.length > 0) {
                    fileNameDisplay.textContent = event.target.files[0].name;
                } else {
                    fileNameDisplay.textContent = '';
                }
            });

            // Handle drag-and-drop
            dropZone.addEventListener('dragover', (event) => {
                event.preventDefault();
                event.stopPropagation();
                dropZone.classList.add('border-indigo-500', 'bg-indigo-50');
            });

            dropZone.addEventListener('dragleave', (event) => {
                event.preventDefault();
                event.stopPropagation();
                dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
            });

            dropZone.addEventListener('drop', (event) => {
                event.preventDefault();
                event.stopPropagation();
                dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');

                const files = event.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    fileNameDisplay.textContent = files[0].name;
                }
            });

            // Handle form submission
            uploadForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (fileInput.files.length === 0) {
                    displayStatusMessage('Please select a file to upload.', 'error');
                    return;
                }

                showLoadingState();
                
                try {
                    const formData = new FormData(uploadForm);
                    const response = await fetch(uploadForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        }
                    });

                    // Check if the response is valid JSON
                    if (!response.headers.get('content-type')?.includes('application/json')) {
                         throw new Error('Server response was not JSON. Status: ' + response.status);
                    }

                    const data = await response.json();

                    if (response.ok) {
                        displayStatusMessage(data.message || 'Upload successful!', 'success');
                        // Redirect to the newly created PDF's show page
                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        } else {
                            // If no redirect URL, just show success and stay on page
                            console.log('Upload successful, but no redirect URL provided.');
                        }
                    } else {
                        // Handle server-side errors
                        displayStatusMessage(data.message || 'An unexpected error occurred during upload.', 'error');
                        console.error('Server error:', response.status, data.message);
                    }
                } catch (error) {
                    // Handle network or JSON parsing errors
                    console.error('Fetch or parsing error:', error);
                    displayStatusMessage('Failed to connect to the server. Please check your network and try again.', 'error');
                } finally {
                    // This block always runs, whether the try or catch block succeeds
                    hideLoadingState();
                }
            });

            function showLoadingState() {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                loadingIndicator.classList.remove('hidden');
                statusMessage.classList.add('hidden');
            }

            function hideLoadingState() {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                loadingIndicator.classList.add('hidden');
            }

            function displayStatusMessage(message, type) {
                statusMessage.textContent = message;
                statusMessage.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700');
                if (type === 'success') {
                    statusMessage.classList.add('bg-green-100', 'text-green-700');
                } else {
                    statusMessage.classList.add('bg-red-100', 'text-red-700');
                }
            }
        });
    </script>
</body>
</html>
