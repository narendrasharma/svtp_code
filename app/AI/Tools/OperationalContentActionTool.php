<?php

namespace App\AI\Tools;

use App\AI\Contracts\AIActionToolInterface;
use App\AI\DTOs\AIExecutionContext;
use App\AI\Support\ActionException;
use App\AI\Support\AIToolAction;
use App\Enums\PropertyStatus;
use App\Http\Requests\SavePageRequest;
use App\Http\Requests\SavePlaceRequest;
use App\Models\AiActionProposal;
use App\Models\Page;
use App\Models\Place;
use App\Models\Property;
use App\Models\User;
use App\Services\HotelPropertyService;
use App\Support\ModuleManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OperationalContentActionTool implements AIActionToolInterface
{
    public const NAMES = ['set_hotel_featured', 'update_place_excerpt', 'update_page_meta_description'];

    public function __construct(private readonly string $toolName, private readonly HotelPropertyService $hotels)
    {
        if (! in_array($toolName, self::NAMES, true)) {
            throw new InvalidArgumentException('Unknown operational content action.');
        }
    }

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return match ($this->toolName) {
            'set_hotel_featured' => 'Prepare a proposal to feature or unfeature exactly one Hotel property by ID. Only published properties may be featured. This tool does not change the property; explicit human confirmation is required before execution.',
            'update_place_excerpt' => 'Prepare a proposal to replace the plain-text excerpt of exactly one Place by ID, at most 500 characters. This tool does not change the Place; explicit human confirmation is required before execution.',
            default => 'Prepare a proposal to replace the plain-text SEO meta description of exactly one CMS Page by ID, at most 255 characters. This tool does not change the Page; explicit human confirmation is required before execution.',
        };
    }

    public function inputSchema(): array
    {
        return match ($this->toolName) {
            'set_hotel_featured' => ['type' => 'object', 'properties' => [
                'property_id' => ['type' => 'integer'], 'featured' => ['type' => 'boolean'],
            ], 'required' => ['property_id', 'featured'], 'additionalProperties' => false],
            'update_place_excerpt' => ['type' => 'object', 'properties' => [
                'place_id' => ['type' => 'integer'], 'excerpt' => ['type' => 'string', 'maxLength' => 500],
            ], 'required' => ['place_id', 'excerpt'], 'additionalProperties' => false],
            default => ['type' => 'object', 'properties' => [
                'page_id' => ['type' => 'integer'], 'meta_description' => ['type' => 'string', 'maxLength' => 255],
            ], 'required' => ['page_id', 'meta_description'], 'additionalProperties' => false],
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

    public function requiredPermission(): string
    {
        return match ($this->toolName) {
            'set_hotel_featured' => 'hotel.properties.manage',
            'update_place_excerpt' => 'tours.update',
            default => 'content.pages',
        };
    }

    public function additionalPermissions(): array
    {
        return $this->toolName === 'set_hotel_featured' ? ['hotel.properties.publish'] : [];
    }

    public function available(ModuleManager $modules): bool
    {
        return match ($this->toolName) {
            'set_hotel_featured' => $modules->isEnabled('hotels'),
            'update_place_excerpt' => $modules->isEnabled('tours'),
            default => true,
        };
    }

    public function execute(AIExecutionContext $context, array $arguments): array
    {
        throw ActionException::denied();
    }

    public function preview(AIExecutionContext $context, array $arguments): array
    {
        $this->assertContext($context);
        [$modelClass, $idKey, $field, $targetType, $fieldLabel] = $this->definition();
        $valueKey = $field === 'is_featured' ? 'featured' : $field;

        if (array_diff(array_keys($arguments), [$idKey, $valueKey]) !== []
            || ($field !== 'is_featured' && ! is_string($arguments[$valueKey] ?? null))
            || Validator::make($arguments, [
                $idKey => ['required', 'integer', 'min:1'],
                $valueKey => $field === 'is_featured' ? ['required', 'boolean:strict'] : $this->textRules($field),
            ])->fails()) {
            throw ActionException::invalid();
        }

        $target = $modelClass::query()->find((int) $arguments[$idKey]);

        if (! $target) {
            throw ActionException::missing();
        }

        $next = $field === 'is_featured' ? (bool) $arguments[$valueKey] : trim(strip_tags($arguments[$valueKey]));

        if (($field !== 'is_featured' && ($next === '' || Validator::make([$field => $next], [$field => $this->textRules($field)])->fails()))
            || ($field === 'is_featured' && $next && $target->status !== PropertyStatus::Published->value)
            || $target->{$field} === $next) {
            throw ActionException::invalid();
        }

        $label = mb_substr($target->{$targetType === 'page' ? 'title' : 'name'}, 0, 200);

        return [
            'target_type' => $targetType, 'target_id' => $target->id, 'target_label' => $label,
            'field_label' => $fieldLabel,
            'summary' => 'Change '.$fieldLabel.' for '.ucfirst($targetType).': '.mb_substr($label, 0, 180),
            'validated_arguments' => [$idKey => $target->id, $valueKey => $next],
            'before_snapshot' => [$field => $target->{$field}],
            'proposed_changes' => [$field => $next],
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

        [$modelClass, , $field, $targetType] = $this->definition();
        $target = $modelClass::query()->whereKey($proposal->target_id)->lockForUpdate()->first();

        if (! $target || $proposal->target_type !== $targetType) {
            throw ActionException::missing();
        }

        $next = $proposal->proposed_changes[$field] ?? null;

        if ($target->{$field} !== ($proposal->before_snapshot[$field] ?? null)
            || ($field === 'is_featured' && (! is_bool($next) || ($next && $target->status !== PropertyStatus::Published->value)))
            || ($field !== 'is_featured' && (! is_string($next) || $next === '' || trim(strip_tags($next)) !== $next
                || Validator::make([$field => $next], [$field => $this->textRules($field)])->fails()))) {
            throw ActionException::stale();
        }

        if ($target instanceof Property) {
            $actor = User::query()->find($context->userId);

            if (! $actor) {
                throw ActionException::denied();
            }

            try {
                $this->hotels->setFeatured($target, $next, $actor);
            } catch (ValidationException) {
                throw ActionException::stale();
            }
        } else {
            $target->update([$field => $next]);
        }

        return [
            'target_type' => $targetType, 'target_id' => $target->id,
            'target_label' => $proposal->target_label, $field => $target->fresh()->{$field},
        ];
    }

    /** @return array{class-string<Model>, string, string, string, string} */
    private function definition(): array
    {
        return match ($this->toolName) {
            'set_hotel_featured' => [Property::class, 'property_id', 'is_featured', 'hotel', 'Featured'],
            'update_place_excerpt' => [Place::class, 'place_id', 'excerpt', 'place', 'Short excerpt'],
            default => [Page::class, 'page_id', 'meta_description', 'page', 'SEO meta description'],
        };
    }

    /** @return array<int, mixed> */
    private function textRules(string $field): array
    {
        return match ($field) {
            'excerpt' => (new SavePlaceRequest)->rules()['excerpt'],
            default => (new SavePageRequest)->rules()['meta_description'],
        };
    }

    private function assertContext(AIExecutionContext $context): void
    {
        if ($context->role !== 'admin' || $context->userId === null
            || ! in_array('ai.assistant.use', $context->permissions, true)
            || ! in_array($this->requiredPermission(), $context->permissions, true)
            || count(array_diff($this->additionalPermissions(), $context->permissions)) !== 0
            || ! $this->available(app(ModuleManager::class))) {
            throw ActionException::denied();
        }
    }
}
