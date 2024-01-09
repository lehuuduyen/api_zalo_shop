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
    public function randomEmail($length = 5){
        $time = time();
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString."_".$time."@gmail.com";
    }
    public function index(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'store' => 'required',
                'sdt' => 'required',
                'user_id' => 'required',
                'name' => 'required'
            ],[
                'store.required' => "Vui lòng nhập store",
                'sdt.required' => "Vui lòng nhập sdt",
                'user_id.required' => "Vui lòng nhập id",
                'name.required' => "Vui lòng nhập name"
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass,$validator->errors()->first());
            }else{
                $store = DB::table('website')->where('db_name',$request['store'])->select('*')->first();
                if($store){
                    $databaseStore = $request['store'];
                    $this->connectDb($databaseStore);
                    $prefixTable = $this->getPrefixTableFirst();
                    $this->_PRFIX_TABLE = $prefixTable;
                    $user = DB::connection('mysql_external')->table($this->_PRFIX_TABLE.'_users')->where('user_login', $request['sdt'])->first();

                    $insertLog = DB::connection('mysql_external')->table($this->_PRFIX_TABLE.'_actionscheduler_logs')->insert(
                        array(
                            'action_id'     =>   0,
                            'message'     =>   "Log access app ".json_encode($user),
                        )
                    );
                    // wp_wc_customer_lookup

                    if (!$user) {
                        $email = $this->randomEmail();
                        $insert = DB::connection('mysql_external')->table($this->_PRFIX_TABLE.'_users')->insert(
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
                        $insertMetaUser = DB::connection('mysql_external')->table($this->_PRFIX_TABLE.'_usermeta')->insert(
                            array(
                                'meta_key'     =>   "last_name",
                                'meta_value'     =>  $request['name'],
                                'user_id'     =>   $request['user_id'],

                            )
                        );

                    }else{
                        $email = $user->user_email;
                    }
                    $customer = DB::connection('mysql_external')->table($this->_PRFIX_TABLE.'_wc_customer_lookup')->where('user_id', $request['user_id'])->first();
                    if(!$customer){
                        $insertCus = DB::connection('mysql_external')->table($this->_PRFIX_TABLE.'_wc_customer_lookup')->insert(
                            array(
                                'customer_id'     =>   $request['user_id'],
                                'username'     =>   $request['sdt'],
                                'first_name'     =>  '',
                                'last_name'     =>  $request['name'],
                                'user_id'     =>   $request['user_id'],
                                'email'     =>   $email,


                            )
                        );
                    }
                    $hash = $this->getToken($request['store'],$request['sdt'],$databaseStore,$store->domain,$request['name'],$request['user_id'],$prefixTable);
                    return $this->returnSuccess([
                        'token'=> $hash
                    ]);
                }else{
                    return $this->returnError(new \stdClass,'Store không tồn tại');
                }
            }
        } catch (\Throwable $th) {
            return $this->returnError(new \stdClass,$th->getMessage());
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
