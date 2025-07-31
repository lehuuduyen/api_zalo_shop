<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\Request;

class CorsApi extends Controller
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
{
     if ($request->getMethod() === "OPTIONS") {
        $response = response('', 200);
    } else {
        $response = $next($request);
    }

    $origin = $request->header('Origin');

    if ($origin && in_array($origin, [
        'http://pos.gsmilktea.vn',
        'http://web.local', // Nếu vẫn cần
    ])) {
        $response->header('Access-Control-Allow-Origin', $origin);
    }

    $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    $response->header('Access-Control-Allow-Credentials', 'true');

    return $response;
}
}
