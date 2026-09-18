<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NumberSeries;
use App\Services\NumberSeriesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Global number/reference series administration (Phase 11.5A).
 *
 * Configuration changes apply to FUTURE references only — historical
 * bookings, invoices, etc. are never renamed. Lowering a counter below
 * its current value is rejected to prevent duplicate references.
 */
class NumberSeriesController extends Controller
{
    public function index(NumberSeriesService $service): Response
    {
        $series = NumberSeries::orderBy('entity')
            ->get()
            ->map(fn (NumberSeries $row): array => [
                'entity' => $row->entity,
                'display_name' => $row->display_name,
                'prefix' => $row->prefix,
                'separator' => $row->separator,
                'include_year' => $row->include_year,
                'include_month' => $row->include_month,
                'padding' => $row->padding,
                'start_number' => $row->start_number,
                'next_number' => $row->next_number,
                'reset_cycle' => $row->reset_cycle,
                'last_period' => $row->last_period,
                'is_active' => $row->is_active,
                'description' => $row->description,
                'preview' => $service->preview($row),
                'updated_at' => $row->updated_at,
            ]);

        return Inertia::render('Admin/NumberSeries/Index', [
            'series' => $series,
            'resetCycles' => NumberSeries::resetCycles(),
        ]);
    }

    public function update(Request $request, string $entity, NumberSeriesService $service): RedirectResponse
    {
        $series = NumberSeries::where('entity', $entity)->firstOrFail();

        $validated = $request->validate([
            'prefix' => ['required', 'string', 'max:10'],
            'separator' => ['nullable', 'string', 'max:5'],
            'include_year' => ['sometimes', 'boolean'],
            'include_month' => ['sometimes', 'boolean'],
            'padding' => ['required', 'integer', 'min:3', 'max:10'],
            'start_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'next_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'reset_cycle' => ['required', 'string'],
        ]);

        try {
            $clean = $service->validateConfiguration($validated);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['prefix' => $exception->getMessage()]);
        }

        if ($clean['next_number'] < $series->next_number) {
            return back()->withErrors([
                'next_number' => "Next number cannot be lowered below the current counter ({$series->next_number}) — issued references must stay unique.",
            ]);
        }

        if ($clean['next_number'] < $clean['start_number']) {
            return back()->withErrors([
                'next_number' => 'Next number cannot be lower than the starting number.',
            ]);
        }

        $series->update($clean);

        return back()->with('flash', "Number series [{$series->display_name}] updated. Applies to future references only — history is unchanged.");
    }
}
