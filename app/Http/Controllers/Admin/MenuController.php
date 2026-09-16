<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMenuRequest;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Menus/Index', [
            'menus' => Menu::orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Menus/Form');
    }

    public function store(SaveMenuRequest $request): RedirectResponse
    {
        $menu = Menu::create($request->validated());

        return redirect()
            ->route('admin.menus.items.index', $menu)
            ->with('flash', 'Menu created.');
    }

    public function edit(Menu $menu): RedirectResponse
    {
        return redirect()->route('admin.menus.items.index', $menu);
    }

    public function update(SaveMenuRequest $request, Menu $menu): RedirectResponse
    {
        $menu->update($request->validated());

        return redirect()
            ->route('admin.menus.items.index', $menu)
            ->with('flash', 'Menu updated.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $menu->delete();

        return back()->with('flash', 'Menu removed.');
    }
}
