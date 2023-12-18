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
            $allowedReferers = [
                'https://h5.zdn.vn/zapps/3709056269145890941',
                'zbrowser://h5.zdn.vn/zapps/3709056269145890941'
              ];
              $referer = $request->header('referer');
              $origin = $request->header('origin');
             
              $allowedCors = false;
              
              foreach ($allowedReferers as $element) {
                if (strpos($referer, $element) === 0) {
                  $allowedCors = true;
                  break;
                }
              }
              if($allowedCors){
                header('Access-Control-Allow-Origin: '.$origin );
                header('Access-Control-Allow-Headers: Content-Type, Authorization');
              }
              return $next($request);

        } catch (\Throwable $th) {
            //throw $th;

        }
        return $this->returnError(new \stdClass, "Token không đúng hoặc hết hạn");
    }
}
