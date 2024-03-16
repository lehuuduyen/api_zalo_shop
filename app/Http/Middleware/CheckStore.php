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
            $data = $request->all();
            if(isset($data['sdt']) && isset($data['name']) && isset($data['user_id'])){
                $user = DB::table($this->_PRFIX_TABLE.'_users')->where('user_login', $request['sdt'])->first();
                if (!$user) {
                    $email = $this->randomEmail();
                    $userId = DB::table($this->_PRFIX_TABLE.'_users')->insertGetId(
                        array(
                            'user_login'     =>   $request['sdt'],
                            'user_pass'     =>   "appid",
                            'user_email'     =>   $email,
                            'user_nicename'     =>   $request['name'],
                            'display_name'     =>   $request['name'],
                            'user_registered'     =>   date('Y-m-d H:i:s'),
                            'ID'   =>   $request['user_id']
                        )
                    );
                    $insertMetaUser = DB::table($this->_PRFIX_TABLE.'_usermeta')->insert(
                        array(
                            'meta_key'     =>   "last_name",
                            'meta_value'     =>  $request['name'],
                            'user_id'     =>   $request['user_id'],

                        )
                    );
                    $user = DB::table($this->_PRFIX_TABLE.'_users')->find($userId);
                }
                $request['user']=$user;
            }
            
            return $next($request);

        } catch (\Throwable $th) {
            //throw $th;
            return $this->returnError(new \stdClass, $th->getMessage());

        }
        return $this->returnError(new \stdClass, "Token không đúng hoặc hết hạn");
    }
}
