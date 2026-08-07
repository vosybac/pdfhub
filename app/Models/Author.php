<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Author extends Model
{
    protected $fillable = [
        'full_name',
        'normalized_name',
        'first_name',
        'last_name',
        'email',
        'affiliation',
        'slug',
    ];

    public static function normalizeName(string $name): string
    {
        $name = Str::lower($name);
        $name = Str::ascii($name);
        $name = preg_replace('/[^a-z0-9 ]/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name;
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(PdfDocument::class, 'document_author', 'author_id', 'document_id')
            ->withPivot(['author_order', 'affiliation', 'email'])
            ->orderBy('pdf_documents.created_at', 'desc');
    }

    /**
     * Authors who share at least one paper with this author.
     * Returns collection of Author models with papers_count attribute.
     */
    public function collaborators()
    {
        $rows = $this->documents()
            ->join('document_author as da2', 'pdf_documents.id', '=', 'da2.document_id')
            ->where('da2.author_id', '!=', $this->id)
            ->selectRaw('da2.author_id as collab_id, COUNT(*) as papers_count')
            ->groupBy('da2.author_id')
            ->orderByDesc('papers_count')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->collab_id => $row->papers_count]);

        $collabs = self::query()->whereIn('id', $rows->keys())->get();

        return $collabs->map(function ($author) use ($rows) {
            $author->papers_count = $rows[$author->id];

            return $author;
        })->sortByDesc('papers_count')->values();
    }

    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim($this->full_name));
        $initials = '';
        foreach ($words as $word) {
            $initials .= mb_substr($word, 0, 1);
        }

        return strtoupper($initials);
    }
}
