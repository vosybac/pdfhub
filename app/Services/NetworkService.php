<?php

namespace App\Services;

use App\Models\Author;
use Illuminate\Support\Facades\DB;

class NetworkService
{
    public function graphData(): array
    {
        $pairs = DB::table('document_author as da1')
            ->join('document_author as da2', 'da1.document_id', '=', 'da2.document_id')
            ->join('authors as a1', 'a1.id', '=', 'da1.author_id')
            ->join('authors as a2', 'a2.id', '=', 'da2.author_id')
            ->whereColumn('da1.author_id', '<', 'da2.author_id')
            ->select('a1.id as a', 'a1.full_name as a_name', 'a2.id as b', 'a2.full_name as b_name')
            ->distinct()
            ->get();

        $weights = [];
        foreach ($pairs as $pair) {
            $key = $pair->a . '-' . $pair->b;
            $weights[$key] = ($weights[$key] ?? 0) + 1;
        }

        $links = [];
        foreach ($weights as $key => $weight) {
            [$a, $b] = explode('-', $key);
            $links[] = [
                'source' => (int) $a,
                'target' => (int) $b,
                'weight' => $weight,
            ];
        }

        $nodes = [];
        foreach (Author::query()->get() as $author) {
            $nodes[] = [
                'id' => $author->id,
                'name' => $author->full_name,
                'email' => $author->email,
                'affiliation' => $author->affiliation,
                'papers' => $author->documents()->count(),
                'collaborators' => count($author->collaborators()),
            ];
        }

        return ['nodes' => $nodes, 'links' => $links];
    }

    public function stats(): array
    {
        return [
            'documents' => DB::table('pdf_documents')->count(),
            'processed' => DB::table('pdf_documents')->where('status', 'processed')->count(),
            'pending' => DB::table('pdf_documents')->whereIn('status', ['pending', 'processing'])->count(),
            'failed' => DB::table('pdf_documents')->where('status', 'failed')->count(),
            'authors' => DB::table('authors')->count(),
            'papers_with_authors' => DB::table('document_author')->select('document_id')->distinct()->count(),
            'collaborations' => DB::table('document_author as da1')
                ->join('document_author as da2', function ($join) {
                    $join->on('da1.document_id', '=', 'da2.document_id')
                        ->on('da1.author_id', '<', 'da2.author_id');
                })
                ->distinct()
                ->count(),
        ];
    }
}
