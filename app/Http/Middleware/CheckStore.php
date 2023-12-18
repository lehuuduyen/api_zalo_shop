<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\Request;

class CheckStore extends Controller
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
            $token = request()->bearerToken();
            $dataToken = $this->decodeData($token);
            $data = json_decode($dataToken);
            $timeNow = time();
            if ($data) {
                if($data->expired_in >= $timeNow || empty($data->expired_in) )
                $request['data_reponse'] = $data;
                $this->connectDb($data->databaseStore);
                return $next($request);
            }
        } catch (\Throwable $th) {
            //throw $th;

        }
        return $this->returnError(new \stdClass, "Token không đúng hoặc hết hạn");
    }
}
