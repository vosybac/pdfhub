<?php

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::get('/health', fn () => response('ok', 200));

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/api/network', [DashboardController::class, 'network'])->name('api.network');

Route::get('/upload', [DocumentController::class, 'uploadForm'])->name('documents.upload');
Route::post('/upload', [DocumentController::class, 'store'])->name('documents.store');
Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
Route::get('/documents/{document}/status', [DocumentController::class, 'status'])->name('documents.status');
Route::post('/documents/{document}/reprocess', [DocumentController::class, 'reprocess'])->name('documents.reprocess');
Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

Route::get('/authors', [AuthorController::class, 'index'])->name('authors.index');
Route::get('/authors/{author}', [AuthorController::class, 'show'])->name('authors.show');
