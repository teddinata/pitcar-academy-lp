<?php

namespace App\Mcp\Tools;

use App\Services\Ai\BusinessTools;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * One BusinessTools tool exposed over MCP. The same name, description and
 * input schema the admin AI uses, so both stay in step.
 */
class BusinessMcpTool extends Tool
{
    /** @param  array<string, mixed>  $inputSchema */
    public function __construct(private string $toolName, private string $toolDescription, private array $inputSchema) {}

    public function name(): string
    {
        return $this->toolName;
    }

    public function title(): string
    {
        return str_replace('_', ' ', ucfirst($this->toolName));
    }

    public function description(): string
    {
        return $this->toolDescription;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'inputSchema' => $this->inputSchema,
            'annotations' => ['title' => $this->title(), 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
        ];
    }

    public function handle(Request $request, BusinessTools $tools): Response
    {
        return Response::text($tools->run($this->toolName, $request->all()));
    }
}
