<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /**
     * Show a public CMS page.
     */
    public function show(string $slug): Response
    {
        // Only fetch active pages; 404 if not found or inactive.
        $page = Page::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Pass only the fields needed by the frontend.
        return Inertia::render('Cms/Page', [
            'page' => [
                'title' => $page->title,
                'content' => $page->content,
                'template' => $page->template ?? 'default',
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'excerpt' => $page->excerpt,
                'slug' => $page->slug,
            ],
        ]);
    }
}
