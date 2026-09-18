<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunicationTemplate;
use App\Services\Comms\TemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reusable message templates. Bodies are rendered by a whitelist
 * placeholder parser — unknown placeholders are rejected at save, and
 * rendering itself can never execute code.
 */
class TemplateController extends Controller
{
    public function __construct(protected TemplateService $templates) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Templates/Index', [
            'templates' => CommunicationTemplate::orderBy('key')->get(),
            'placeholders' => CommunicationTemplate::allowedPlaceholders(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        CommunicationTemplate::create($validated + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('flash', 'Template created.');
    }

    public function update(Request $request, CommunicationTemplate $template): RedirectResponse
    {
        $validated = $this->validated($request, $template->id);

        $template->update($validated + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('flash', 'Template updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?int $ignore = null): array
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', Rule::unique('communication_templates', 'key')->ignore($ignore)],
            'name' => ['required', 'string', 'max:150'],
            'email_subject' => ['nullable', 'string', 'max:255'],
            'email_body' => ['nullable', 'string', 'max:10000'],
            'sms_body' => ['nullable', 'string', 'max:500'],
            'whatsapp_body' => ['nullable', 'string', 'max:4000'],
            'in_app_title' => ['nullable', 'string', 'max:255'],
            'in_app_body' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->templates->assertPlaceholdersValid([
            $validated['email_subject'] ?? null,
            $validated['email_body'] ?? null,
            $validated['sms_body'] ?? null,
            $validated['whatsapp_body'] ?? null,
            $validated['in_app_title'] ?? null,
            $validated['in_app_body'] ?? null,
        ]);

        return $validated;
    }
}
