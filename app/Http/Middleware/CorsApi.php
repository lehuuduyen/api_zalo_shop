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
        try {
              $codeApp = $request->header('origin');
            
              if($codeApp == "GSMILKTEA"){
                return $next($request);

              }
              

        } catch (\Throwable $th) {
            //throw $th;

        }
        return $this->returnError(new \stdClass, "Token không đúng hoặc hết hạn");
    }
}
