<?php

namespace App\AI\Tools;

use App\AI\Contracts\AIActionToolInterface;
use App\AI\DTOs\AIExecutionContext;
use App\AI\Support\ActionException;
use App\AI\Support\AIToolAction;
use App\Enums\TourModerationStatus;
use App\Models\AiActionProposal;
use App\Models\Destination;
use App\Models\TourPackage;
use App\Support\ModuleManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class MarketplaceContentActionTool implements AIActionToolInterface
{
    public const NAMES = ['set_tour_featured', 'update_destination_excerpt'];

    public function __construct(private readonly string $toolName)
    {
        if (! in_array($toolName, self::NAMES, true)) {
            throw new InvalidArgumentException('Unknown content action.');
        }
    }

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return match ($this->toolName) {
            'set_tour_featured' => 'Prepare a proposal to set the featured status of exactly one Tour by ID. This tool does not change or publish the Tour; explicit human confirmation is required before execution.',
            default => 'Prepare a proposal to replace the short excerpt of exactly one Destination by ID, up to 500 plain-text characters. This tool does not change the Destination; explicit human confirmation is required before execution.',
        };
    }

    public function inputSchema(): array
    {
        return match ($this->toolName) {
            'set_tour_featured' => ['type' => 'object', 'properties' => [
                'tour_id' => ['type' => 'integer'], 'featured' => ['type' => 'boolean'],
            ], 'required' => ['tour_id', 'featured'], 'additionalProperties' => false],
            default => ['type' => 'object', 'properties' => [
                'destination_id' => ['type' => 'integer'], 'excerpt' => ['type' => 'string', 'maxLength' => 500],
            ], 'required' => ['destination_id', 'excerpt'], 'additionalProperties' => false],
        };
    }

    public function action(): AIToolAction
    {
        return AIToolAction::Write;
    }

    public function riskLevel(): string
    {
        return 'low';
    }

    public function additionalPermissions(): array
    {
        return [];
    }

    public function requiredPermission(): string
    {
        return $this->toolName === 'set_tour_featured' ? 'tours.update' : 'locations.destinations.manage';
    }

    public function available(ModuleManager $modules): bool
    {
        return $modules->isEnabled('tours');
    }

    public function execute(AIExecutionContext $context, array $arguments): array
    {
        throw ActionException::denied();
    }

    public function preview(AIExecutionContext $context, array $arguments): array
    {
        $this->assertContext($context);

        if ($this->toolName === 'set_tour_featured') {
            if (array_diff(array_keys($arguments), ['tour_id', 'featured']) !== []
                || Validator::make($arguments, ['tour_id' => 'required|integer|min:1', 'featured' => 'required|boolean:strict'])->fails()) {
                throw ActionException::invalid();
            }

            $tour = TourPackage::query()->find((int) $arguments['tour_id']);

            if (! $tour) {
                throw ActionException::missing();
            }

            $featured = (bool) $arguments['featured'];

            if ($featured && (! $tour->is_active || $tour->moderation_status !== TourModerationStatus::Approved)) {
                throw ActionException::invalid();
            }

            if ($tour->is_featured === $featured) {
                throw ActionException::invalid();
            }

            return [
                'target_type' => 'tour', 'target_id' => $tour->id,
                'target_label' => mb_substr($tour->title, 0, 200), 'field_label' => 'Featured',
                'summary' => ($featured ? 'Mark Tour as featured: ' : 'Remove featured status from Tour: ').mb_substr($tour->title, 0, 180),
                'validated_arguments' => ['tour_id' => $tour->id, 'featured' => $featured],
                'before_snapshot' => ['is_featured' => $tour->is_featured],
                'proposed_changes' => ['is_featured' => $featured],
            ];
        }

        if (array_diff(array_keys($arguments), ['destination_id', 'excerpt']) !== []
            || Validator::make($arguments, ['destination_id' => 'required|integer|min:1', 'excerpt' => 'required|string|max:500'])->fails()) {
            throw ActionException::invalid();
        }

        $destination = Destination::query()->find((int) $arguments['destination_id']);

        if (! $destination) {
            throw ActionException::missing();
        }

        $excerpt = trim(strip_tags($arguments['excerpt']));

        if ($excerpt === '' || mb_strlen($excerpt) > 500 || $destination->excerpt === $excerpt) {
            throw ActionException::invalid();
        }

        return [
            'target_type' => 'destination', 'target_id' => $destination->id,
            'target_label' => mb_substr($destination->name, 0, 200), 'field_label' => 'Short excerpt',
            'summary' => 'Replace Destination excerpt: '.mb_substr($destination->name, 0, 180),
            'validated_arguments' => ['destination_id' => $destination->id, 'excerpt' => $excerpt],
            'before_snapshot' => ['excerpt' => $destination->excerpt],
            'proposed_changes' => ['excerpt' => $excerpt],
        ];
    }

    public function executeConfirmed(AIExecutionContext $context, AiActionProposal $proposal): array
    {
        $this->assertContext($context);

        if (! $proposal->exists || $proposal->status !== 'confirmed' || $proposal->confirmed_at === null
            || $proposal->expires_at->isPast() || $proposal->user_id !== $context->userId
            || $proposal->tool_name !== $this->toolName) {
            throw ActionException::denied();
        }

        if ($this->toolName === 'set_tour_featured') {
            $tour = TourPackage::query()->whereKey($proposal->target_id)->lockForUpdate()->first();

            if (! $tour) {
                throw ActionException::missing();
            }

            $next = $proposal->proposed_changes['is_featured'] ?? null;

            if (! is_bool($next) || ($next && (! $tour->is_active || $tour->moderation_status !== TourModerationStatus::Approved))
                || $tour->is_featured !== ($proposal->before_snapshot['is_featured'] ?? null)) {
                throw ActionException::stale();
            }

            $tour->update(['is_featured' => $next]);
            Cache::forget('home.featured_packages');

            return ['target_type' => 'tour', 'target_id' => $tour->id, 'target_label' => mb_substr($tour->title, 0, 200), 'is_featured' => $tour->fresh()->is_featured];
        }

        $destination = Destination::query()->whereKey($proposal->target_id)->lockForUpdate()->first();

        if (! $destination) {
            throw ActionException::missing();
        }

        $next = $proposal->proposed_changes['excerpt'] ?? null;

        if (! is_string($next) || $next === '' || mb_strlen($next) > 500
            || $destination->excerpt !== ($proposal->before_snapshot['excerpt'] ?? null)) {
            throw ActionException::stale();
        }

        $destination->update(['excerpt' => $next]);
        Cache::forget('home.destinations.autocomplete');

        return ['target_type' => 'destination', 'target_id' => $destination->id, 'target_label' => mb_substr($destination->name, 0, 200), 'excerpt' => $destination->fresh()->excerpt];
    }

    private function assertContext(AIExecutionContext $context): void
    {
        if ($context->role !== 'admin' || $context->userId === null
            || ! in_array('ai.assistant.use', $context->permissions, true)
            || ! in_array($this->requiredPermission(), $context->permissions, true)
            || ! $this->available(app(ModuleManager::class))) {
            throw ActionException::denied();
        }
    }
}
