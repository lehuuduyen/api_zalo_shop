<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\Request;
use stdClass;

class GetData extends Controller
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
                if ($data->expired_in >= $timeNow || empty($data->expired_in))
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

                // Get the host (domain)
                $host = $_SERVER['HTTP_HOST'];

                // Combine protocol and host to get the full domain
                $fullDomain = $protocol . \env('APP_URL_BACKEND');
                $data->domain = $fullDomain;
                $request['data_reponse'] = $data;
                $this->connectDb($data->databaseStore);
            } else {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

                // Get the host (domain)
                $host = $_SERVER['HTTP_HOST'];

                // Combine protocol and host to get the full domain
                $fullDomain = $protocol . \env('APP_URL_BACKEND');
                $json = new stdClass();
                $json->domain = $fullDomain;
                $json->prefixTable = 'wp';

                $request['data_reponse'] = $json;
            }

            return $next($request);
        } catch (\Throwable $th) {
            //throw $th;

        }
        return $this->returnError(new \stdClass, "Token không đúng hoặc hết hạn");
    }
}
