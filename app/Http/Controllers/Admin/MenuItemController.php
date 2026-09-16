<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddMenuPagesRequest;
use App\Http\Requests\ReorderMenuItemsRequest;
use App\Http\Requests\SaveMenuItemRequest;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MenuItemController extends Controller
{
    /**
     * Show a list of items for a given menu.
     */
    public function index(Menu $menu): Response
    {
        $items = $menu->items()
            ->with('page:id,title,slug,is_active')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Menus/Edit', [
            'menu' => $menu,
            'items' => $items,
            'pages' => Page::active()->orderBy('title')->get(['id', 'title', 'slug']),
            'menus' => Menu::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reorder(ReorderMenuItemsRequest $request, Menu $menu): RedirectResponse
    {
        DB::transaction(function () use ($request, $menu): void {
            Menu::whereKey($menu->id)->lockForUpdate()->firstOrFail();
            $request->validateHierarchy($menu->items()->pluck('id')->all());
            foreach ($request->validated('items') as $item) {
                $menu->items()->whereKey($item['id'])->update([
                    'parent_id' => $item['parent_id'],
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return back()->with('flash', 'Menu structure saved.');
    }

    public function addPages(AddMenuPagesRequest $request, Menu $menu): RedirectResponse
    {
        DB::transaction(function () use ($request, $menu): void {
            Menu::whereKey($menu->id)->lockForUpdate()->firstOrFail();
            $pages = Page::active()->whereIn('id', $request->validated('page_ids'))->get()->keyBy('id');
            if ($pages->count() !== count($request->validated('page_ids'))) {
                throw ValidationException::withMessages(['page_ids' => 'A selected page is no longer available.']);
            }
            $position = ($menu->items()->whereNull('parent_id')->max('sort_order') ?? -1) + 1;
            foreach ($request->validated('page_ids') as $pageId) {
                $page = $pages->get($pageId);
                $menu->items()->create([
                    'title' => $page->title,
                    'type' => 'page',
                    'page_id' => $page->id,
                    'target' => '_self',
                    'is_active' => true,
                    'sort_order' => $position++,
                ]);
            }
        });

        return back()->with('flash', 'Pages added to menu.');
    }

    public function create(Menu $menu): Response
    {
        // Items that can be selected as a parent (top‑level only)
        $menuItems = $menu->items()
            ->orderBy('sort_order')
            ->get()
            ->whereNull('parent_id');

        // Load active CMS pages for the Page dropdown
        $pages = Page::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'slug']);

        return Inertia::render('Admin/Menus/Form', [
            'menu' => $menu,
            'parentMenu' => $menu,
            'menuItems' => $menuItems,
            'pages' => $pages,
        ]);
    }

    public function store(SaveMenuItemRequest $request, Menu $menu): RedirectResponse
    {
        DB::transaction(function () use ($request, $menu): void {
            Menu::whereKey($menu->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            $data['sort_order'] = ($menu->items()->where('parent_id', $data['parent_id'] ?? null)->max('sort_order') ?? -1) + 1;
            $menu->items()->create($data);
        });

        return redirect()
            ->route('admin.menus.items.index', $menu)
            ->with('flash', 'Menu item created.');
    }

    public function edit(Menu $menu, MenuItem $menuItem): Response
    {
        abort_unless((int) $menuItem->menu_id === (int) $menu->id, 404);

        // Items that can be selected as a parent (exclude the current item & its descendants)
        $menuItems = $menu->items()
            ->orderBy('sort_order')
            ->get()
            ->whereNull('parent_id')
            ->where('id', '!=', $menuItem->id);

        // Load active CMS pages for the Page dropdown
        $pages = Page::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'slug']);

        return Inertia::render('Admin/Menus/Form', [
            'menu' => $menu,
            'item' => $menuItem,
            'parentMenu' => $menu,
            'menuItems' => $menuItems,
            'pages' => $pages,
        ]);
    }

    public function update(SaveMenuItemRequest $request, Menu $menu, MenuItem $menuItem): RedirectResponse
    {
        abort_unless((int) $menuItem->menu_id === (int) $menu->id, 404);

        DB::transaction(function () use ($request, $menu, $menuItem): void {
            Menu::whereKey($menu->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            $parents = $menu->items()->pluck('parent_id', 'id');
            $parentId = $data['parent_id'] ?? null;
            $visited = [$menuItem->id => true];
            while ($parentId !== null) {
                if (isset($visited[$parentId]) || ! $parents->has($parentId)) {
                    throw ValidationException::withMessages(['parent_id' => 'Invalid menu hierarchy.']);
                }
                $visited[$parentId] = true;
                $parentId = $parents->get($parentId);
            }
            $menuItem->update($data);
        });

        return redirect()
            ->route('admin.menus.items.index', $menu)
            ->with('flash', 'Menu item updated.');
    }

    public function destroy(Menu $menu, MenuItem $menuItem): RedirectResponse
    {
        abort_unless((int) $menuItem->menu_id === (int) $menu->id, 404);

        DB::transaction(function () use ($menu, $menuItem): void {
            Menu::whereKey($menu->id)->lockForUpdate()->firstOrFail();
            $menuItem->delete();
        });

        return back()->with('flash', 'Menu item removed.');
    }
}
