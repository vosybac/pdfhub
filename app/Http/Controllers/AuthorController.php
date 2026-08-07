<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Services\NetworkService;

class AuthorController extends Controller
{
    public function index()
    {
        $authors = Author::query()
            ->withCount('documents')
            ->orderByDesc('documents_count')
            ->paginate(30);

        return view('authors.index', compact('authors'));
    }

    public function show(Author $author)
    {
        $author->load('documents');
        $collaborators = $author->collaborators();
        $graph = $this->egoGraph($author);

        return view('authors.show', compact('author', 'collaborators', 'graph'));
    }

    private function egoGraph(Author $author): array
    {
        $service = app(NetworkService::class);
        $full = $service->graphData();
        $id = $author->id;

        $neighborIds = $author->collaborators()->pluck('id')->all();
        $neighborIds[] = $id;
        $keep = array_fill_keys($neighborIds, true);

        $nodes = array_values(array_filter($full['nodes'], fn ($n) => isset($keep[$n['id']])));
        $links = array_values(array_filter($full['links'], function ($l) use ($id, $keep) {
            return $l['source'] === $id || $l['target'] === $id
                || (isset($keep[$l['source']]) && isset($keep[$l['target']]));
        }));

        return ['nodes' => $nodes, 'links' => $links];
    }
}
