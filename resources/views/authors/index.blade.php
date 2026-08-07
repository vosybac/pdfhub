@extends('layouts.app')

@section('title', 'Authors — PDFHub')
@section('page-title', 'Authors')
@section('topbar-actions')
    <a href="{{ route('documents.upload') }}" class="btn btn-sm btn-primary">+ Upload more PDFs</a>
@endsection

@section('content')
    @if ($authors->isEmpty())
        <div class="card empty">
            <div class="icon">✦</div>
            <h3>No authors yet</h3>
            <p class="muted">Upload PDFs and the background process will build author profiles automatically.</p>
            <a href="{{ route('documents.upload') }}" class="btn btn-primary" style="margin-top: 8px;">Upload a PDF</a>
        </div>
    @else
        <div class="stat-grid">
            <div class="stat">
                <div class="label">Authors</div>
                <div class="value value-accent">{{ $authors->total() }}</div>
            </div>
        </div>

        <div class="card" style="padding: 8px 12px;">
            <table>
                <thead>
                    <tr>
                        <th>Author</th>
                        <th>Affiliation</th>
                        <th>Email</th>
                        <th>Papers</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($authors as $author)
                        <tr>
                            <td>
                                <div class="author-card">
                                    <div class="avatar">{{ $author->initials }}</div>
                                    <div>
                                        <a href="{{ route('authors.show', $author) }}" class="filename">{{ $author->full_name }}</a>
                                        <div class="muted small">{{ $author->documents_count }} paper(s)</div>
                                    </div>
                                </div>
                            </td>
                            <td class="muted small" style="max-width: 320px;">{{ $author->affiliation ?? '—' }}</td>
                            <td class="muted small">{{ $author->email ?? '—' }}</td>
                            <td>
                                <span class="badge badge-processed">{{ $author->documents_count }}</span>
                            </td>
                            <td>
                                <a href="{{ route('authors.show', $author) }}" class="btn btn-sm">Profile</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($authors->hasPages())
            <div class="pager">
                {{ $authors->links() }}
            </div>
        @endif
    @endif
@endsection
