<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PdfDocument extends Model
{
    protected $fillable = [
        'original_filename',
        'stored_path',
        'file_size',
        'mime_type',
        'page_count',
        'title',
        'status',
        'error',
        'extracted_text',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'file_size' => 'integer',
        'page_count' => 'integer',
    ];

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'document_author', 'document_id', 'author_id')
            ->withPivot(['author_order', 'affiliation', 'email'])
            ->orderBy('document_author.author_order');
    }

    public function filePath(): string
    {
        return storage_path('app/private/' . $this->stored_path);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
