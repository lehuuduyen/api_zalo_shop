<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Hautelook\Phpass\PasswordHash;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;

class GatewaveController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

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
                        $option = DB::table($this->_PRFIX_TABLE . '_options')->updateOrInsert(
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
                    $option = DB::table($this->_PRFIX_TABLE . '_options')->updateOrInsert(
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

    public function call_otp(Request $request)
    {
        $getAccessToken = $this->getOptionsMeta('access_token_zalo');
        $getRefreshToken = $this->getOptionsMeta('refresh_token_zalo');
        if (!$getAccessToken) {
            $getAccessToken = \env('ACCESS_TOKEN_ZALO');
            $getRefreshToken = \env('REFRESH_TOKEN_ZALO');
        }
        try {
            $validator = Validator::make($request->all(), [
                'sdt' => 'required',
            ], [
                'sdt.required' => "Vui lòng nhập sdt",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                $param = $request->all();

                $rand = rand(100000, 999999);
                $data = [
                    "mode" => \env('MODE_ZALO'),
                    "phone" => $param['sdt'],
                    "template_id" => "398993",
                    "template_data" => [
                        "otp" => $rand
                    ],
                    "tracking_id" => "123456"
                ];
                $client = new Client();
                $response = $client->post('https://business.openapi.zalo.me/message/template', [
                    'json' => $data, // Dữ liệu được gửi dưới dạng JSON
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'access_token' => $getAccessToken // Thêm nếu cần token
                    ]
                ]);

                $body = $response->getBody()->getContents();
                $body = json_decode($body);

                if ($body->message == "Success") {
                    $insertGetId = DB::table($this->_PRFIX_TABLE . '_otp_code')->insertGetId(
                        array(
                            'sdt'     =>   $data['phone'],
                            'otp'     =>   $rand,
                            'time'     =>   $body->data->sent_time,

                        )
                    );
                } elseif ($body->message = 'Access token invalid') {

                    $client = new Client();
                    $data = [
                        "app_id" => '3294166732429448932',
                        "grant_type" => 'refresh_token',
                        "refresh_token" => $getRefreshToken,
                    ];
                    $response = $client->post('https://oauth.zaloapp.com/v4/oa/access_token', [
                        'form_params' => $data, // Dữ liệu được gửi dưới dạng JSON
                        'headers' => [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                            'secret_key' => 'N5vp2EXU3yP21BRj5N2x' // Thêm nếu cần token
                        ]
                    ]);

                    $body = $response->getBody()->getContents();
                    $body = json_decode($body);

                    if (isset($body->access_token)) {
                        $this->saveOptionsMeta('access_token_zalo', $body->access_token);
                        $this->saveOptionsMeta('refresh_token_zalo', $body->refresh_token);

                        return $this->call_otp($request);
                    }
                }

                return $body;
            }
        } catch (\Throwable $th) {
            $this->woo_logs('gateway', $th->getMessage());

            return $this->returnError(new \stdClass, $th->getMessage());
        }
    }
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sdt' => 'required',
                'name' => 'required',
                'pass' => 'required',
                // 'otp' => 'required',
            ], [
                'sdt.required' => "Vui lòng nhập sdt",
                'name.required' => "Vui lòng nhập name",
                'pass.required' => "Vui lòng nhập mật khẩu",
                // 'otp.required' => "Vui lòng nhập otp",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                //check otp 
                var_dump(isset($data['referrer_code']) && !empty($data['referrer_code'])   &&  $data['referrer_code'] != '77777777' && $request['sdt'] != $data['referrer_code']);die;

                $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $request['sdt'])->first();

                if (!isset($request['otp'])) {
                    if ($user) {
                        return $this->returnError(new \stdClass, "User đã tồn tại");
                    }
                    return true;
                }
                $otpRecord = DB::table($this->_PRFIX_TABLE . '_otp_code')
                    ->where('otp', $request['otp'])
                    ->where('sdt', $request['sdt'])
                    ->where('status', 1)
                    ->where('time', '>=', Carbon::now()->subMinutes(10)->valueOf())
                    ->first();

                if ($otpRecord) {
                    DB::table($this->_PRFIX_TABLE . '_otp_code')
                        ->where('id', $otpRecord->id) // Dựa trên ID của OTP
                        ->update(['status' => 2]);
                    $databaseStore = env('DB_DATABASE');
                    $this->connectDb($databaseStore);
                    $prefixTable = $this->getPrefixTableFirst();

                    $this->_PRFIX_TABLE = $prefixTable;
                    // wp_wc_customer_lookup

                    if (!$user) {
                      

                        $email = $this->randomEmail();
                        $insertGetId = DB::table($this->_PRFIX_TABLE . '_users')->insertGetId(
                            array(
                                'user_login'     =>   $request['sdt'],
                                'user_pass'     =>   $this->createPass($request['pass']),
                                'user_email'     =>   $email,
                                'user_nicename'     =>   $request['name'],
                                'display_name'     =>   $request['name'],
                                'user_registered'     =>   date('Y-m-d H:i:s'),
                            )
                        );

                        $insertMetaUser = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                            array(
                                'user_id' => $insertGetId,
                                'meta_key' => 'last_name'
                            ),
                            array('meta_value' => $request['name'])
                        );
                        $insertMetaUser = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                            array(
                                'user_id' => $insertGetId,
                                'meta_key' => 'wp_capabilities'
                            ),
                            array('meta_value' => 'a:1:{s:10:"subscriber";b:1;}')
                        );
                                                  
                        if (isset($data['referrer_code']) && !empty($data['referrer_code'])   &&  $data['referrer_code'] != '77777777' && $request['sdt'] != $data['referrer_code'] ) {
                      
                            $this->woo_logs('user_parent_save', $data['referrer_code'] . '-' . $userId);

                            $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->insert(
                                array(
                                    'user_id' => $insertGetId,
                                    'meta_key' => 'user_parent',
                                    'meta_value' => $data['referrer_code']
                                ),
                            );
                            $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->insert(
                                array(
                                    'user_id' => $insertGetId,
                                    'meta_key' => 'user_parent_created',
                                    'meta_value' => date("d/m/Y")
                                ),
                            );
                     
                        }   
                    } else {
                        return $this->returnError(new \stdClass, "User đã tồn tại");
                    }

                    $customer = DB::table($this->_PRFIX_TABLE . '_wc_customer_lookup')->where('user_id', $insertGetId)->first();
                    if (!$customer) {
                        $insertCus = DB::table($this->_PRFIX_TABLE . '_wc_customer_lookup')->insert(
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
                    $hash = $this->getToken($request['store'], $request['sdt'], $databaseStore, $request['name'], $insertGetId, $email, $prefixTable);
                    $this->woo_logs('gateway', $hash, 3);

                    return $this->returnSuccess([
                        'token' => $hash
                    ]);
                } else {
                    return $this->returnError(new \stdClass, "OTP không đúng hoặc đã hết hạn");
                }
            }
        } catch (\Throwable $th) {
            $this->woo_logs('gateway', $th->getMessage());

            return $this->returnError(new \stdClass, $th->getMessage());
        }
    }
    public function reset_pass(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sdt' => 'required',
                'pass' => 'required',
                // 'otp' => 'required',
            ], [
                'sdt.required' => "Vui lòng nhập sdt",
                'pass.required' => "Vui lòng nhập mật khẩu",
                // 'otp.required' => "Vui lòng nhập otp",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                //check otp 
                $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $request['sdt'])->first();

                if (!$user) {
                    return $this->returnError(new \stdClass, "User không tồn tại");
                }
                
                $otpRecord = DB::table($this->_PRFIX_TABLE . '_otp_code')
                    ->where('otp', $request['otp'])
                    ->where('sdt', $request['sdt'])
                    ->where('status', 1)
                    ->where('time', '>=', Carbon::now()->subMinutes(10)->valueOf())
                    ->first();

                if ($otpRecord) {
                    DB::table($this->_PRFIX_TABLE . '_otp_code')
                        ->where('id', $otpRecord->id) // Dựa trên ID của OTP
                        ->update(['status' => 2]);
                    $databaseStore = env('DB_DATABASE');
                    $this->connectDb($databaseStore);
                    $prefixTable = $this->getPrefixTableFirst();

                    $this->_PRFIX_TABLE = $prefixTable;
                    // wp_wc_customer_lookup

                    DB::table($this->_PRFIX_TABLE . '_users')
                        ->where('id', $user->ID) // Dựa trên ID của OTP
                        ->update(['user_pass' =>  $this->createPass($request['pass'])]);
                    $hash = $this->getToken($request['store'], $request['sdt'], $databaseStore, $request['name'], $user->ID, $user->user_email, $prefixTable);
                    $this->woo_logs('gateway', $hash, 3);
                    return $this->returnSuccess([
                        'token' => $hash
                    ]);
                } else {
                    return $this->returnError(new \stdClass, "OTP không đúng hoặc đã hết hạn");
                }
            }
        } catch (\Throwable $th) {
            $this->woo_logs('gateway', $th->getMessage());

            return $this->returnError(new \stdClass, $th->getMessage());
        }
    }
    public function loginPos(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sdt' => 'required',
                'pass' => 'required'
            ], [
                'sdt.required' => "Vui lòng nhập sdt",
                'pass.required' => "Vui lòng nhập pass"
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {

                $databaseStore = env('DB_DATABASE');
                $this->connectDb($databaseStore);
                $prefixTable = $this->getPrefixTableFirst();

                $this->_PRFIX_TABLE = $prefixTable;

                $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $request['sdt'])->first();
                // wp_wc_customer_lookup

                if (!$user) {
                    return $this->returnError(new \stdClass, "Tài khoản nhân viên không đúng");
                }
                $checkPass = $this->check_user_credentials($request['pass'], $user->user_pass);
                if (!$checkPass) {
                    return $this->returnError(new \stdClass, "User hoặc mật khẩu không đúng");
                }
                $role = $this->getUserMeta($user->ID, 'wp_capabilities');

                $nameRole = array_key_first(unserialize($role));
                if ($nameRole == "shop_manager" || $nameRole == "contributor" || $nameRole == "administrator") {
                    $hash = $this->getToken($nameRole, $request['sdt'], $databaseStore, $request['name'], $user->ID, $user->user_email, $prefixTable);
                    $this->woo_logs('gateway', $hash, 3);

                    return $this->returnSuccess([
                        'token' => $hash
                    ]);
                }
                return $this->returnError(new \stdClass, "Không có quyền");
            }
        } catch (\Throwable $th) {
            $this->woo_logs('gateway', $th->getMessage());

            return $this->returnError(new \stdClass, $th->getMessage());
        }
    }
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sdt' => 'required',
                'pass' => 'required'
            ], [
                'sdt.required' => "Vui lòng nhập sdt",
                'pass.required' => "Vui lòng nhập pass"
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {

                $databaseStore = env('DB_DATABASE');
                $this->connectDb($databaseStore);
                $prefixTable = $this->getPrefixTableFirst();

                $this->_PRFIX_TABLE = $prefixTable;

                $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $request['sdt'])->first();
                // wp_wc_customer_lookup

                if (!$user) {
                    return $this->returnError(new \stdClass, "Tài khoản nhân viên không đúng");
                }
                $checkPass = $this->check_user_credentials($request['pass'], $user->user_pass);
                if (!$checkPass) {
                    return $this->returnError(new \stdClass, "User hoặc mật khẩu không đúng");
                }
                $role = $this->getUserMeta($user->ID, 'wp_capabilities');

                $nameRole = array_key_first(unserialize($role));
                $hash = $this->getToken($nameRole, $request['sdt'], $databaseStore, $user->display_name, $user->ID, $user->user_email, $prefixTable);
                $this->woo_logs('gateway', $hash, 3);

                return $this->returnSuccess([
                    'token' => $hash
                ]);
                return $this->returnError(new \stdClass, "Không có quyền");
            }
        } catch (\Throwable $th) {
            $this->woo_logs('gateway', $th->getMessage());

            return $this->returnError(new \stdClass, $th->getMessage());
        }
    }
    public function createPass($user_pass)
    {
        // Cấu hình giống WordPress
        $hash_cost_log2 = 8; // Cost mặc định là 8
        $portable_hashes = true; // Portable hashes để có định dạng $P$...

        // Khởi tạo PasswordHash
        $hasher = new PasswordHash($hash_cost_log2, $portable_hashes);

        // Mật khẩu cần băm
        $password = $user_pass;

        // Băm mật khẩu
        // Kiểm tra mật khẩu
        $passwordHash = $hasher->HashPassword($password);


        return $passwordHash;
    }
    public function check_user_credentials($user_pass, $hashedPassword)
    {
        // Cấu hình giống WordPress
        $hash_cost_log2 = 8; // Cost mặc định là 8
        $portable_hashes = true; // Portable hashes để có định dạng $P$...

        // Khởi tạo PasswordHash
        $hasher = new PasswordHash($hash_cost_log2, $portable_hashes);

        // Mật khẩu cần băm
        $password = $user_pass;

        // Băm mật khẩu
        // Kiểm tra mật khẩu
        $isMatch = $hasher->CheckPassword($password, $hashedPassword);

        if ($isMatch) {
            return true;
        }
        return false;
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
