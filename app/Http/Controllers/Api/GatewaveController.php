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
    public function code_verifier()
    {
        $code_verifier = $this->generateCodeVerifier();
       
        // Bước 1: Tạo hash SHA-256 từ code_verifier
        $sha256Hash = hash('sha256', $code_verifier, true);

        // Bước 2: Encode kết quả hash bằng Base64
        $base64EncodedHash = base64_encode($sha256Hash);

        // Bước 3: Loại bỏ padding '=' ở cuối Base64 (nếu yêu cầu, ví dụ trong OAuth PKCE)
        $base64EncodedHash = rtrim($base64EncodedHash, '=');
        return $base64EncodedHash;
    }
    public function generateCodeVerifier($length = 43) {
        // Danh sách ký tự bao gồm chữ hoa, chữ thường và số
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    
        // Tạo chuỗi ngẫu nhiên
        $codeVerifier = '';
        $maxIndex = strlen($characters) - 1;
    
        for ($i = 0; $i < $length; $i++) {
            $codeVerifier .= $characters[random_int(0, $maxIndex)];
        }
    
        return $codeVerifier;
    }
    public function call_otp(Request $request)
    {
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
                        'access_token' => \env('ACCESS_TOKEN_ZALO') // Thêm nếu cần token
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
                } else {
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
                'otp' => 'required',
            ], [
                'sdt.required' => "Vui lòng nhập sdt",
                'name.required' => "Vui lòng nhập name",
                'pass.required' => "Vui lòng nhập mật khẩu",
                'otp.required' => "Vui lòng nhập otp",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                //check otp 
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
                    $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $request['sdt'])->first();
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
                $hash = $this->getToken($nameRole, $request['sdt'], $databaseStore, $request['name'], $user->ID, $user->user_email, $prefixTable);
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
