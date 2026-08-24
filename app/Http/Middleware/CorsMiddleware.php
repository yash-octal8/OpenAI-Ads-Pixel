<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
            $this->addCorsHeaders($response, $request);
            return $response;
        }

        $response = $next($request);
        $this->addCorsHeaders($response, $request);

        return $response;
    }

    protected function addCorsHeaders($response, Request $request): void
    {
        $origin = $request->header('Origin');

        if (empty($origin) || $origin === 'null') {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, ngrok-skip-browser-warning, Authorization, Accept, Origin, User-Agent');
        $response->headers->set('Access-Control-Max-Age', '86400');
    }
}
