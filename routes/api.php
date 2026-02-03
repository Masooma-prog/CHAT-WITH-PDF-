<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PredefinedQuestionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// --- PDF Management Endpoints ---
Route::post('/upload', [PdfController::class, 'upload'])->name('api.upload');
Route::get('/pdfs', [PdfController::class, 'apiIndex'])->name('api.pdfs.index');
Route::get('/pdfs/{pdf}/pages', [PdfController::class, 'getPages'])->name('api.pdfs.pages');
Route::post('/pdfs/{pdf}/extract', [PdfController::class, 'extract'])->name('api.pdfs.extract');

// NEW: Endpoint to trigger AI question generation based on PDF content
// This route should accept the PDF ID and return suggested questions.
Route::post('/pdfs/{pdf}/generate-questions', [PredefinedQuestionController::class, 'generateQuestions'])->name('api.pdfs.generate_questions');


// --- Chat Session Endpoints ---
Route::post('/sessions', [ChatController::class, 'createSession'])->name('api.sessions.create');
Route::get('/sessions/{session}/messages', [ChatController::class, 'getMessages'])->name('api.sessions.messages');
Route::post('/sessions/{session}/message', [ChatController::class, 'postMessage'])->name('api.sessions.message');

// --- Predefined Questions Endpoints ---
// Removed the old 'ask' route as the new route above handles content-based generation
Route::get('/predefined-questions', [PredefinedQuestionController::class, 'index'])->name('api.questions.index');
