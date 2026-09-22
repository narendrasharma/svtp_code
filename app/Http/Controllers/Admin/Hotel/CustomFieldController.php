<?php

namespace App\Http\Controllers\Admin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelCustomFieldDefinition;
use App\Models\PropertyType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin custom field definition management (12B.2.1).
 *
 * Admins own the marketplace schema; vendors only fill values.
 * No hard delete — deactivation preserves stored values.
 */
class CustomFieldController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['entity_type', 'is_active', 'group_name', 'search']);

        $query = HotelCustomFieldDefinition::with(['propertyTypes:id,name'])->withCount('values')->latest('id');

        if (! empty($filters['entity_type']) && in_array($filters['entity_type'], HotelCustomFieldDefinition::ENTITIES, true)) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['group_name'])) {
            $query->where('group_name', mb_substr(trim((string) $filters['group_name']), 0, 80));
        }

        if (! empty($filters['search'])) {
            $search = '%'.mb_substr(trim((string) $filters['search']), 0, 80).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('key', 'like', $search));
        }

        return Inertia::render('Admin/Hotel/CustomFields/Index', [
            'definitions' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'entities' => HotelCustomFieldDefinition::ENTITIES,
            'fieldTypes' => HotelCustomFieldDefinition::TYPES,
            'groups' => HotelCustomFieldDefinition::whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name'),
            'propertyTypes' => PropertyType::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['key'] = $this->uniqueKey($data['key'] ?? null, $data['name'], $data['entity_type']);
        $definition = HotelCustomFieldDefinition::create($data);
        $definition->propertyTypes()->sync($data['property_type_ids'] ?? []);

        return back()->with('flash', 'Custom field created.');
    }

    public function update(Request $request, HotelCustomFieldDefinition $definition): RedirectResponse
    {
        $data = $this->validated($request, $definition->id);

        // Key stability: values link by definition_id, but keys should stay
        // stable once values exist to protect integrations/exports.
        if ($definition->values()->exists() && ! empty($data['key']) && $data['key'] !== $definition->key) {
            return back()->withErrors(['key' => 'Key is locked while values exist; edit the label instead.']);
        }

        $data['key'] = $this->uniqueKey($data['key'] ?? $definition->key, $data['name'] ?? $definition->name, $data['entity_type'] ?? $definition->entity_type, $definition->id);
        $definition->update($data);
        $definition->propertyTypes()->sync($data['property_type_ids'] ?? []);

        return back()->with('flash', 'Custom field updated.');
    }

    public function toggle(HotelCustomFieldDefinition $definition): RedirectResponse
    {
        $definition->update(['is_active' => ! $definition->is_active]);

        return back()->with('flash', $definition->is_active
            ? 'Custom field reactivated; previous values are visible again.'
            : 'Custom field deactivated; stored values are preserved.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'entity_type' => ['required', 'string', Rule::in(HotelCustomFieldDefinition::ENTITIES)],
            'name' => ['required', 'string', 'max:100'],
            'key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'field_type' => ['required', 'string', Rule::in(HotelCustomFieldDefinition::TYPES)],
            'group_name' => ['nullable', 'string', 'max:80'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'placeholder' => ['nullable', 'string', 'max:150'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'show_on_frontend' => ['sometimes', 'boolean'],
            'show_label' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'options' => ['nullable', 'array', 'max:50'],
            'options.*.value' => ['required_with:options', 'string', 'max:80'],
            'options.*.label' => ['nullable', 'string', 'max:100'],
            'property_type_ids' => ['nullable', 'array', 'max:50'],
            'property_type_ids.*' => ['integer', 'exists:property_types,id'],
        ]);

        if (in_array($data['field_type'], [HotelCustomFieldDefinition::TYPE_SELECT, HotelCustomFieldDefinition::TYPE_MULTISELECT], true)
            && empty($data['options'])) {
            throw ValidationException::withMessages(['options' => 'Select fields need at least one option.']);
        }

        if (! in_array($data['field_type'], [HotelCustomFieldDefinition::TYPE_SELECT, HotelCustomFieldDefinition::TYPE_MULTISELECT], true)) {
            $data['options'] = null;
        }

        // Applicability only constrains property fields; room-type fields
        // stay entity-level in this phase.
        if (($data['entity_type'] ?? null) !== HotelCustomFieldDefinition::ENTITY_PROPERTY) {
            $data['property_type_ids'] = [];
        }

        return $data;
    }

    protected function uniqueKey(?string $desired, string $name, string $entityType, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim($desired !== null && trim($desired) !== '' ? $desired : $name), '_');
        $base = mb_substr(preg_replace('/[^a-z0-9_]/', '', $base) ?: 'field', 0, 60);
        $key = $base !== '' ? $base : 'field';
        $counter = 2;

        while (HotelCustomFieldDefinition::where('entity_type', $entityType)->where('key', $key)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->withTrashed()->exists()) {
            $key = mb_substr($base, 0, 70).'_'.$counter;
            $counter++;
        }

        return $key;
    }
}
