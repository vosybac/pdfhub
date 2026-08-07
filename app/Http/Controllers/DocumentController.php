<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPdfDocument;
use App\Models\PdfDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function uploadForm()
    {
        return view('documents.upload');
    }

    public function store(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|mimes:pdf|max:51200',
        ], [
            'files.required' => 'Please choose at least one PDF file.',
            'files.*.mimes' => 'Only PDF files are allowed.',
            'files.*.max' => 'Each file must be smaller than 50MB.',
        ]);

        $created = [];
        $queue = [];

        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $storedPath = $file->store('pdfs', 'local');

            $document = PdfDocument::create([
                'original_filename' => $originalName,
                'stored_path' => $storedPath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
                'status' => 'pending',
            ]);

            $created[] = $document;
            $queue[] = $document->id;

            ProcessPdfDocument::dispatch($document->id)->onQueue('default');
        }

        return response()->json([
            'message' => 'Uploaded ' . count($created) . ' file(s). Processing in background...',
            'documents' => collect($created)->map(fn ($d) => [
                'id' => $d->id,
                'original_filename' => $d->original_filename,
                'status' => $d->status,
            ])->all(),
            'queued' => $queue,
        ]);
    }

    public function index()
    {
        $documents = PdfDocument::query()
            ->withCount('authors')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('documents.index', compact('documents'));
    }

    public function show(PdfDocument $document)
    {
        $document->load(['authors' => fn ($q) => $q->withCount('documents')]);

        return view('documents.show', compact('document'));
    }

    public function download(PdfDocument $document)
    {
        if (! Storage::disk('local')->exists($document->stored_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function status(PdfDocument $document)
    {
        return response()->json([
            'id' => $document->id,
            'status' => $document->status,
            'error' => $document->error,
            'page_count' => $document->page_count,
            'authors_count' => $document->authors()->count(),
            'title' => $document->title,
        ]);
    }

    public function reprocess(PdfDocument $document)
    {
        $document->status = 'pending';
        $document->error = null;
        $document->save();

        ProcessPdfDocument::dispatch($document->id);

        return redirect()->route('documents.show', $document)->with('status', 'Re-queued for processing.');
    }

    public function destroy(PdfDocument $document)
    {
        Storage::disk('local')->delete($document->stored_path);
        $document->delete();

        return redirect()->route('documents.index')->with('status', 'Document deleted.');
    }
}
