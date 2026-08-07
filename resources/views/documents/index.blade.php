@extends('layouts.app')

@section('title', 'Documents — PDFHub')
@section('page-title', 'Upload History')
@section('topbar-actions')
    <a href="{{ route('documents.upload') }}" class="btn btn-sm btn-primary">+ Upload</a>
@endsection

@section('content')
    @if ($documents->isEmpty())
        <div class="card empty">
            <div class="icon">🗂</div>
            <h3>No documents yet</h3>
            <p class="muted">Upload your first PDF to start building the author network.</p>
            <a href="{{ route('documents.upload') }}" class="btn btn-primary" style="margin-top: 8px;">Upload a PDF</a>
        </div>
    @else
        <div class="card" style="padding: 8px 12px;">
            <table>
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Size</th>
                        <th>Pages</th>
                        <th>Authors</th>
                        <th>Status</th>
                        <th>Uploaded</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $doc)
                        <tr>
                            <td>
                                <a href="{{ route('documents.show', $doc) }}" class="filename">{{ $doc->original_filename }}</a>
                            </td>
                            <td class="muted">{{ round($doc->file_size / 1024) }} KB</td>
                            <td class="muted">{{ $doc->page_count ?? '—' }}</td>
                            <td>
                                @if ($doc->status === 'processed')
                                    <span class="muted strong">{{ $doc->authors_count }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-{{ $doc->status }}">
                                    @switch($doc->status)
                                        @case('pending') Pending @break
                                        @case('processing') Processing @break
                                        @case('processed') Processed @break
                                        @case('failed') Failed @break
                                        @default {{ $doc->status }}
                                    @endswitch
                                </span>
                            </td>
                            <td class="muted small">{{ $doc->created_at->format('M j, H:i') }}</td>
                            <td style="white-space: nowrap;">
                                <a href="{{ route('documents.show', $doc) }}" class="btn btn-sm">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($documents->hasPages())
            <div class="pager">
                {{ $documents->links() }}
            </div>
        @endif
    @endif
@endsection
