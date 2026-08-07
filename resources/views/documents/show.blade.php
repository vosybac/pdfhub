@extends('layouts.app')

@section('title', $document->original_filename . ' — PDFHub')
@section('page-title', 'Document detail')

@section('content')
    <div class="grid" style="grid-template-columns: 1fr 1fr; align-items: start; gap: 16px;">
        <div class="grid" style="gap: 16px;">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
                    <div>
                        <h2 style="margin: 0 0 4px;">{{ $document->original_filename }}</h2>
                        <div class="muted small">{{ round($document->file_size / 1024) }} KB
                            @if ($document->page_count) · {{ $document->page_count }} pages @endif
                            · uploaded {{ $document->created_at->format('M j, Y H:i') }}
                        </div>
                    </div>
                    <span class="badge badge-{{ $document->status }}">
                        @switch($document->status)
                            @case('pending') Pending @break
                            @case('processing') Processing @break
                            @case('processed') Processed @break
                            @case('failed') Failed @break
                            @default {{ $document->status }}
                        @endswitch
                    </span>
                </div>

                @if ($document->title)
                    <p class="muted" style="margin: 14px 0 0;"><strong class="strong">Detected title:</strong> {{ $document->title }}</p>
                @endif

                @if ($document->error)
                    <div class="alert alert-danger" style="margin: 14px 0 0;">{{ $document->error }}</div>
                @endif

                <div style="display: flex; gap: 10px; margin-top: 18px; flex-wrap: wrap;">
                    <a href="{{ route('documents.download', $document) }}" class="btn">⬇ Download PDF</a>
                    <form method="POST" action="{{ route('documents.reprocess', $document) }}">
                        @csrf
                        <button class="btn">⟳ Re-process</button>
                    </form>
                    <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Delete this document and its author links?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top: 0;">Extracted authors ({{ $document->authors->count() }})</h3>
                @if ($document->authors->isEmpty())
                    <p class="muted">No authors were detected.
                        @if ($document->status === 'processed')
                            The parser could not find an author block — try re-processing or check if the PDF is a scanned document.
                        @endif
                    </p>
                @else
                    <div class="grid" style="gap: 10px;">
                        @foreach ($document->authors as $author)
                            <div class="author-card" style="padding: 10px 12px; background: var(--card-2); border-radius: 10px; border: 1px solid var(--border);">
                                <div class="avatar">{{ $author->initials }}</div>
                                <div style="min-width: 0;">
                                    <a href="{{ route('authors.show', $author) }}" class="filename">{{ $author->full_name }}</a>
                                    @if ($author->pivot->affiliation)
                                        <div class="muted small" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">🏛 {{ $author->pivot->affiliation }}</div>
                                    @endif
                                    @if ($author->pivot->email)
                                        <div class="muted small">✉ {{ $author->pivot->email }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top: 0;">Raw extracted text <span class="muted small">(first page header)</span></h3>
            @if ($document->extracted_text)
                <pre class="raw-text">{{ $document->extracted_text }}</pre>
            @else
                <p class="muted">No extracted text yet.</p>
            @endif
        </div>
    </div>
@endsection

@push('head')
<style>
    .raw-text {
        background: #0d1020;
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 14px;
        font-size: 12px;
        line-height: 1.5;
        white-space: pre-wrap;
        word-break: break-word;
        max-height: 70vh;
        overflow: auto;
        color: #c9d0ec;
        margin: 0;
    }
    @media (max-width: 1000px) {
        .grid { grid-template-columns: 1fr !important; }
    }
</style>
@endpush
