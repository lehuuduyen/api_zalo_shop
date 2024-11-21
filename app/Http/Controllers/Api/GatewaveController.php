<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class GatewaveController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function randomEmail($length = 5)
    {
        $time = time();
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString . "_" . $time . "@gmail.com";
    }
    public function checkFollow(Request $request)
    {
        try {
            //code...
            $validator = Validator::make($request->all(), [
                'store' => 'required',
                'appid' => 'required',
            ], [
                'store.required' => "Vui lòng nhập store",
                'appid.required' => "Vui lòng nhập appid",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                $store = DB::table('website')->where('db_name', $request['store'])->select('*')->first();
                $databaseStore = $request['store'];
                $this->connectDb($databaseStore);
                $prefixTable = $this->getPrefixTableFirst();
                $this->_PRFIX_TABLE = $prefixTable;
                $listFollow = $this->getOptionsMeta('follow');
                $arr = [];
                if ($listFollow) {
                    $listFollow = json_decode($listFollow);
                    if (in_array($request['appid'], $listFollow)) {
                        return $this->returnSuccess([
                            'follow' => true
                        ]);
                    } else {
                        $listFollow[] = $request['appid'];
                        $option = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_options')->updateOrInsert(
                            array(
                                'option_name' => 'follow'
                            ),
                            array(
                                'option_value' => json_encode($listFollow),
                            )
                        );
                        return $this->returnSuccess([
                            'follow' => false
                        ]);
                    }
                } else {
                    $arr[] = $request['appid'];
                    $option = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_options')->updateOrInsert(
                        array(
                            'option_name' => 'follow'
                        ),
                        array(
                            'option_value' => json_encode($arr),
                        )
                    );
                    return $this->returnSuccess([
                        'follow' => false
                    ]);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sdt' => 'required',
                'name' => 'required'
            ], [
                'sdt.required' => "Vui lòng nhập sdt",
                'name.required' => "Vui lòng nhập name"
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {

                $databaseStore = env('DB_DATABASE');
                $this->connectDb($databaseStore);
                $prefixTable = $this->getPrefixTableFirst();
              
                $this->_PRFIX_TABLE = $prefixTable;
                $user = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_users')->where('user_login', $request['sdt'])->first();
                // wp_wc_customer_lookup
                
                if (!$user) {
                    $email = $this->randomEmail();
                    $insertGetId = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_users')->insertGetId(
                        array(
                            'user_login'     =>   $request['sdt'],
                            'user_pass'     =>   "appid",
                            'user_email'     =>   $email,
                            'user_nicename'     =>   $request['name'],
                            'display_name'     =>   $request['name'],
                            'user_registered'     =>   date('Y-m-d H:i:s'),
                        )
                    );
                   
                    $insertMetaUser = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                        array(
                            'user_id' => $insertGetId,
                            'meta_key' => 'last_name'
                        ),
                        array('meta_value' => $request['name'])
                    );
                    $insertMetaUser = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                        array(
                            'user_id' => $insertGetId,
                            'meta_key' => 'wp_capabilities'
                        ),
                        array('meta_value' => 'a:1:{s:10:"subscriber";b:1;}')
                    );
                } else {
                    $email = $user->user_email;
                    $insertGetId = $user->ID;

                }
            
                $customer = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_wc_customer_lookup')->where('user_id', $insertGetId)->first();
                if (!$customer) {
                    $insertCus = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_wc_customer_lookup')->insert(
                        array(
                            'customer_id'     =>   $insertGetId,
                            'username'     =>   $request['sdt'],
                            'first_name'     =>  '',
                            'last_name'     =>  $request['name'],
                            'user_id'     =>   $insertGetId,
                            'email'     =>   $email,


                        )
                    );
                }
                $hash = $this->getToken($request['store'], $request['sdt'], $databaseStore, $request['name'], $insertGetId, $prefixTable);
                $this->woo_logs('gateway', $hash, 3);

                return $this->returnSuccess([
                    'token' => $hash
                ]);
            }
        } catch (\Throwable $th) {
            $this->woo_logs('gateway', $th->getMessage());

            return $this->returnError(new \stdClass, $th->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
