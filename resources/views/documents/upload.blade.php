@extends('layouts.app')

@section('title', 'Upload PDFs — PDFHub')
@section('page-title', 'Upload PDFs')
@section('topbar-actions')
    <a href="{{ route('documents.index') }}" class="btn btn-sm">View history</a>
@endsection

@section('content')
    <div class="grid" style="max-width: 760px;">
        <div class="card">
            <form id="upload-form" method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
                @csrf
                <div id="dropzone" class="dropzone">
                    <div class="icon">📄</div>
                    <h3>Drag & drop PDF files here</h3>
                    <p class="muted">or click to browse. Multiple files allowed.</p>
                    <input type="file" name="files[]" id="file-input" accept="application/pdf" multiple hidden>
                </div>
                <div style="margin-top: 16px; display: flex; align-items: center; gap: 12px;">
                    <button type="submit" id="upload-btn" class="btn btn-primary" disabled>Upload & queue for processing</button>
                    <span class="muted small" id="file-count"></span>
                </div>
            </form>
            <div id="upload-list" class="upload-list"></div>
        </div>

        <div class="card">
            <h3 style="margin-top: 0;">How it works</h3>
            <ol class="prose" style="margin: 0; padding-left: 20px;">
                <li><strong>Upload</strong> PDFs — they are stored and the upload history is recorded.</li>
                <li>A <strong>background process</strong> parses each PDF and extracts authors, affiliations and emails.</li>
                <li>Each author gets a <strong>profile page</strong> with their papers and collaborators.</li>
                <li>The <strong>dashboard</strong> visualizes the co-authorship network as a 2D / 3D graph.</li>
            </ol>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const dropzone = document.getElementById('dropzone');
    const input = document.getElementById('file-input');
    const countEl = document.getElementById('file-count');
    const btn = document.getElementById('upload-btn');
    const list = document.getElementById('upload-list');
    let files = [];

    dropzone.addEventListener('click', () => input.click());
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('drag'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag');
        files = [...e.dataTransfer.files];
        renderSelected();
    });
    input.addEventListener('change', () => {
        files = [...input.files];
        renderSelected();
    });

    function renderSelected() {
        const pdfs = files.filter(f => f.type === 'application/pdf' || f.name.toLowerCase().endsWith('.pdf'));
        countEl.textContent = `${pdfs.length} PDF selected`;
        btn.disabled = pdfs.length === 0;
    }

    const form = document.getElementById('upload-form');
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = new FormData();
        const pdfs = files.filter(f => f.type === 'application/pdf' || f.name.toLowerCase().endsWith('.pdf'));
        pdfs.forEach(f => data.append('files[]', f));
        btn.disabled = true;
        btn.textContent = 'Uploading...';
        try {
            const res = await fetch(form.action, { method: 'POST', body: data });
            const json = await res.json();
            if (!res.ok) {
                const msg = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'Upload failed');
                throw new Error(msg);
            }
            (json.documents || []).forEach(doc => addUploadItem(doc));
            files = [];
            input.value = '';
            renderSelected();
            btn.textContent = 'Upload & queue for processing';
            btn.disabled = true;
            pollAll();
        } catch (err) {
            alert('Error: ' + err.message);
            btn.textContent = 'Upload & queue for processing';
            btn.disabled = false;
        }
    });

    function addUploadItem(doc) {
        const el = document.createElement('div');
        el.className = 'upload-item';
        el.id = 'up-' + doc.id;
        el.innerHTML = `
            <div class="spinner"></div>
            <div style="flex:1; min-width:0;">
                <div class="filename" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(doc.original_filename)}</div>
                <div class="muted small status-text">Queued…</div>
            </div>
        `;
        list.prepend(el);
    }

    function updateUploadItem(id, status, error) {
        const el = document.getElementById('up-' + id);
        if (!el) return;
        const statusEl = el.querySelector('.status-text');
        const labels = {
            pending: 'Pending in queue…',
            processing: 'Processing…',
            processed: 'Done ✓ — view below',
            failed: 'Failed: ' + (error || 'unknown error'),
        };
        const spinner = el.querySelector('.spinner');
        if (status === 'processed') spinner.replaceWith(document.createElement('span'));
        if (status === 'failed') spinner.remove();
        statusEl.textContent = labels[status] || status;
        el.style.opacity = (status === 'processed' || status === 'failed') ? '0.55' : '1';
    }

    function pollAll() {
        document.querySelectorAll('[id^="up-"]').forEach(el => {
            const id = el.id.slice(3);
            poll(id);
        });
    }

    function poll(id) {
        fetch(`/documents/${id}/status`).then(r => r.json()).then(data => {
            updateUploadItem(id, data.status, data.error);
            if (data.status === 'pending' || data.status === 'processing') {
                setTimeout(() => poll(id), 2500);
            }
        }).catch(() => setTimeout(() => poll(id), 4000));
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
</script>
@endpush
