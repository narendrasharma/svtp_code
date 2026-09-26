<?php

namespace App\Http\Controllers;

use App\Mcp\McpReadAdapter;
use Illuminate\Http\Request;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

class McpServerController extends Controller
{
    public function __invoke(Request $request, McpReadAdapter $adapter): Response
    {
        $token = $request->attributes->get('mcp_token');
        $builder = Server::builder()->setServerInfo('triparo', '1.0.0', 'Triparo read-only marketplace tools');
        $builder->setSession(new FileSessionStore(storage_path('framework/cache/mcp-sessions/'.$token->id)));
        $adapter->register($builder, $token);

        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $allowedHosts = array_values(array_unique(array_filter([
            'localhost', '127.0.0.1', '[::1]', is_string($configuredHost) ? $configuredHost : null,
        ])));
        $transport = new StreamableHttpTransport(
            (new PsrHttpFactory)->createRequest($request),
            middleware: [new CorsMiddleware, new DnsRebindingProtectionMiddleware($allowedHosts)],
            maxBodyBytes: 65536,
        );

        $response = $builder->build()->run($transport);

        return (new HttpFoundationFactory)->createResponse(
            $response,
            str_contains($response->getHeaderLine('Content-Type'), 'text/event-stream'),
        );
    }
}
