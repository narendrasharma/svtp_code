<?php

namespace App\Http\Controllers;

use App\AI\Support\AIException;
use App\Http\Requests\ContentCopilotRequest;
use App\Models\Destination;
use App\Models\Page;
use App\Models\Place;
use App\Models\Property;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\ContentCopilotService;
use App\Support\HotelSettings;
use App\Support\ModuleManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ContentCopilotController extends Controller
{
    public function generate(ContentCopilotRequest $request, ContentCopilotService $copilot): JsonResponse
    {
        $data = $request->validated();
        $type = $data['content_type'];
        $field = $data['field'];
        $action = $data['action'];
        $source = trim($data['source'] ?? '');
        $context = $data['context'] ?? [];

        if (strlen(json_encode($context, JSON_INVALID_UTF8_SUBSTITUTE)) > 12000) {
            throw ValidationException::withMessages(['context' => 'Reduce the context before asking AI.']);
        }

        $expectedField = match ($type) {
            'tour' => 'overview',
            'page' => 'content',
            default => 'description',
        };

        if (($action === 'seo' && $field !== 'seo')
            || ($action === 'itinerary' && ($type !== 'tour' || $field !== 'itinerary'))
            || (! in_array($action, ['seo', 'itinerary'], true) && $field !== $expectedField)) {
            throw ValidationException::withMessages(['field' => 'This AI action is unavailable for the selected content field.']);
        }

        if ($source === '' && $context === []) {
            throw ValidationException::withMessages(['source' => 'Add content or page context before asking AI.']);
        }

        if ($source === '' && ! in_array($action, ['generate', 'seo', 'itinerary'], true)) {
            throw ValidationException::withMessages(['source' => 'Add content before choosing this writing action.']);
        }

        $this->authorizeScope($request->user(), $type, $data['entity_id'] ?? null);

        try {
            return response()->json($copilot->draft($type, $field, $action, $source, $context, app()->getLocale(), $data['output_format'] ?? 'html'));
        } catch (AIException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        }
    }

    private function authorizeScope(User $user, string $type, ?int $entityId): void
    {
        $module = match ($type) {
            'tour', 'destination', 'place' => 'tours',
            'hotel' => 'hotels',
            default => null,
        };

        if ($module !== null) {
            abort_unless(app(ModuleManager::class)->isEnabled($module), 404);
        }

        if ($user->isAdmin()) {
            $permission = match ($type) {
                'tour', 'destination', 'place' => $entityId ? 'tours.update' : 'tours.create',
                'hotel' => 'hotel.properties.manage',
                default => 'content.pages',
            };
            abort_unless($user->hasStaffPermission($permission), 403);

            if ($entityId !== null) {
                $model = match ($type) {
                    'tour' => TourPackage::class,
                    'hotel' => Property::class,
                    'destination' => Destination::class,
                    'place' => Place::class,
                    default => Page::class,
                };
                $model::findOrFail($entityId);
            }

            return;
        }

        abort_unless(in_array($type, ['tour', 'hotel'], true), 403);
        $profile = $user->vendorProfile;
        abort_unless($profile && ($type !== 'hotel' || $profile->is_active), 403);

        if ($type === 'tour') {
            if ($entityId !== null) {
                $tour = TourPackage::query()->where('vendor_profile_id', $profile->id)->findOrFail($entityId);
                Gate::authorize('update', $tour);
            } else {
                Gate::authorize('create', TourPackage::class);
            }

            return;
        }

        if ($entityId !== null) {
            Property::query()->where('vendor_profile_id', $profile->id)->findOrFail($entityId);
        } else {
            abort_unless(HotelSettings::enabled('hotel.vendor_can_create_properties'), 403);
        }
    }
}
