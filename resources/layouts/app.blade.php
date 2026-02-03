<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with your PDF</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f2f5;
            display: flex;
            height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #fff;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        .sidebar h2 {
            margin-top: 0;
            font-size: 1.2rem;
            color: #333;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin-bottom: auto;
        }
        .sidebar li {
            margin-bottom: 10px;
        }
        .sidebar a {
            text-decoration: none;
            color: #555;
            display: block;
            padding: 8px 12px;
            border-radius: 5px;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: #e0e7ff;
            color: #4361ee;
        }
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }
        .upload-form {
            padding-top: 20px;
            border-top: 1px solid #eee;
            margin-top: 20px;
            text-align: center;
        }
        .upload-form button {
            background-color: #4361ee;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>📚 Recent PDFs</h2>
        <ul>
            @if ($pdfs->isEmpty())
                <li>No PDFs uploaded yet.</li>
            @else
                @foreach ($pdfs as $item)
                    <li>
                        <a href="{{ route('pdf.show', $item->id) }}"
                           class="{{ isset($pdf) && $pdf->id === $item->id ? 'active' : '' }}">
                            {{ Str::limit($item->title, 25) }}
                        </a>
                    </li>
                @endforeach
            @endif
        </ul>

        <div class="upload-form">
            <form id="uploadForm" action="{{ route('pdf.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <label for="pdf_file">
                    <button type="button" onclick="document.getElementById('pdf_file').click();">Upload PDF</button>
                </label>
                <input type="file" name="pdf" id="pdf_file" style="display: none;" accept="application/pdf">
            </form>
            <p id="uploadStatus"></p>
        </div>
    </div>

    @yield('content')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.getElementById('uploadForm');
            const pdfFile = document.getElementById('pdf_file');
            const uploadStatus = document.getElementById('uploadStatus');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            pdfFile.addEventListener('change', function() {
                if (this.files.length > 0) {
                    uploadStatus.textContent = 'Uploading...';
                    const formData = new FormData(uploadForm);
                    fetch(uploadForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-CSRF-TOKEN': csrfToken }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            uploadStatus.textContent = 'Upload successful!';
                            window.location.href = `/pdfs/${data.pdf.id}`;
                        } else {
                            uploadStatus.textContent = 'Upload failed: ' + data.message;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        uploadStatus.textContent = 'Upload failed due to a network error.';
                    });
                }
            });
        });
    </script>
</body>
</html>
