<?php

namespace App\Mcp;

use App\AI\DTOs\AIExecutionContext;
use App\AI\Support\AIToolAction;
use App\AI\Support\AIToolGuardrail;
use App\AI\Support\AIToolRegistry;
use App\AI\Tools\MarketplaceReadTool;
use App\Models\McpAccessToken;
use App\Services\ActivityLogger;
use App\Support\ModuleManager;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Mcp\Server\Builder;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;
use Throwable;

class McpReadAdapter
{
    private const READ_TOOLS = [
        'search_tours', 'get_tour', 'search_hotels', 'get_hotel',
        'search_tour_bookings', 'search_hotel_bookings', 'search_taxi_bookings',
        'marketplace_overview', 'search_geography', 'search_knowledge',
    ];

    public function __construct(
        private readonly AIToolRegistry $registry,
        private readonly AIToolGuardrail $guardrail,
        private readonly ModuleManager $modules,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function register(Builder $builder, McpAccessToken $token): void
    {
        $context = $this->context($token);

        foreach ($this->registry->metadata() as $metadata) {
            $name = $metadata['name'];
            if (! in_array($name, self::READ_TOOLS, true) || $metadata['action'] !== AIToolAction::Read->value) {
                continue;
            }

            $tool = $this->registry->allowed($name, $context, $this->guardrail);
            if (! $tool || ($tool instanceof MarketplaceReadTool && ! $tool->available($this->modules))) {
                continue;
            }

            $builder->add(
                new Tool($name, null, $tool->inputSchema(), $tool->description(), null),
                new class($this, $token, $name) implements ToolHandlerInterface
                {
                    public function __construct(
                        private readonly McpReadAdapter $adapter,
                        private readonly McpAccessToken $token,
                        private readonly string $name,
                    ) {}

                    public function execute(array $arguments, ClientGateway $gateway): mixed
                    {
                        return $this->adapter->call($this->token, $this->name, $arguments);
                    }
                },
            );
        }
    }

    /** @param array<string, mixed> $arguments */
    public function call(McpAccessToken $token, string $name, array $arguments): CallToolResult
    {
        $user = $token->user?->fresh();
        if (! $user || ! $user->isAdmin() || ! $user->hasStaffPermission('ai.assistant.use')) {
            return $this->failure($token, $name, 'Forbidden.');
        }

        $token->setRelation('user', $user);
        $context = $this->context($token);
        $tool = in_array($name, self::READ_TOOLS, true)
            ? $this->registry->allowed($name, $context, $this->guardrail) : null;
        if (! $tool || $tool->action() !== AIToolAction::Read
            || ($tool instanceof MarketplaceReadTool && ! $tool->available($this->modules))) {
            return $this->failure($token, $name, 'Tool unavailable or forbidden.');
        }

        try {
            $result = $tool->execute($context, $arguments);
            $available = $result['available'] ?? true;
            $this->audit($token, $name, $available ? 'success' : 'knowledge_unavailable');

            return new CallToolResult([new TextContent($result)], $available === false, $result);
        } catch (InvalidArgumentException) {
            return $this->failure($token, $name, 'Invalid tool arguments.');
        } catch (Throwable $exception) {
            Log::warning('MCP read tool failed', ['tool' => $name, 'exception' => $exception::class]);

            return $this->failure($token, $name, 'Internal tool failure.');
        }
    }

    private function context(McpAccessToken $token): AIExecutionContext
    {
        return new AIExecutionContext(
            userId: $token->user_id,
            role: $token->user?->role,
            permissions: $token->user?->staffPermissionNames() ?? [],
        );
    }

    private function failure(McpAccessToken $token, string $name, string $message): CallToolResult
    {
        $this->audit($token, $name, 'failed');

        return CallToolResult::error([new TextContent($message)]);
    }

    private function audit(McpAccessToken $token, string $name, string $outcome): void
    {
        $this->activityLogger->log('mcp.tool.call', 'mcp', 'MCP READ tool call', $token, null, [
            'token_id' => $token->id,
            'token_label' => $token->label,
            'tool' => $name,
            'outcome' => $outcome,
        ], $token->user);
    }
}
