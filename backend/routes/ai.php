<?php

use App\Http\Middleware\AuthenticateMcpToken;
use App\Mcp\Servers\PitcarAcademyServer;
use Laravel\Mcp\Facades\Mcp;

// Read-only business data for Claude Desktop, Claude Code and other MCP clients.
Mcp::web('/mcp', PitcarAcademyServer::class)->middleware([AuthenticateMcpToken::class, 'throttle:60,1']);
