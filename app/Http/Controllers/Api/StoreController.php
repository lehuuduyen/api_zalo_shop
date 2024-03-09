<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Database\QueryException;
class StoreController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $infor = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_posts')->where('post_name', 'lien-he')->where('post_status', 'publish')->where('post_type', 'page')->first();



        $info = new \stdClass();
        $info->email = [
            'title' => "",
            'value' => "",
        ];
        $info->phone = [
            'title' => "",
            'value' => "",
        ];
        $info->address = [
            'title' => "",
            'value' => "",
        ];
        $info->open_hour = [
            'title' => "",
            'value' => "",
        ];
        if ($infor) {
            $content = $infor->post_content;
            $lineAfterPhone = $this->lienhe($content, 'Điện thoại:', 11);


            $info->phone = [
                'title' => 'Điện thoại:',
                'value' => $lineAfterPhone,
            ];
            $email = $this->lienhe($content, 'Email:', 6);
            $info->email = [
                'title' => 'Email:',
                'value' => $email,
            ];
            $address = $this->lienhe($content, 'Địa chỉ:', 8);
            $info->address = [
                'title' => 'Địa chỉ :',
                'value' => $address,
            ];
        }

        return $this->returnSuccess($info);
    }
    public function country(Request $request)
    {

        $country = DB::connection('mysql_external')->table('countries')->where('status', 'publish')->get();
        return $this->returnSuccess($country);
    }
    public function log(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $log = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woocommerce_log')->insertGetId(
            array(
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => 1,
                'source' => '',
                'message' => $request['value'],

            )
        );
        return $this->returnSuccess($log);
    }
    public function getPaymentMethod(Request $request)
    {
        //check Cod
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $result = [];
        $cod = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_options')->where('option_name', 'woocommerce_cod_settings')->first();
        if ($cod) {
            $cod = unserialize($cod->option_value);
            if ($cod['enabled'] == 'yes') {
                $result['cod'] = $cod;
            }
        }
        $payment = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_options')->where('option_name', 'woocommerce_bacs_settings')->first();
        if ($payment) {
            $payment = unserialize($payment->option_value);
            if ($payment['enabled'] == 'yes') {
                $payment['account'] = [];

                $paymentAccount = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_options')->where('option_name', 'woocommerce_bacs_accounts')->first();
                if ($paymentAccount) {
                    $paymentAccount = unserialize($paymentAccount->option_value);
                    $payment['account'] = $paymentAccount;
                }


                $result['bacs'] = $payment;
            }
        }


        return $this->returnSuccess($result);
    }
    // _transient_woocommerce_admin_payment_gateway_suggestions_specs

    public function state(Request $request)
    {
        try {
            //code...
            $state = DB::connection('mysql_external')->table('states')->where('status', 'publish')->where('country_id', $request['country_id'])->get();

            return $this->returnSuccess($state);
        } catch (\Throwable $th) {
            //throw $th;
            return $this->returnError([], $th->getMessage());
        }
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        try {
            $data = $request->all();
            
            if (isset($data['name'])) {
                $user = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_users')->where('user_login', $data['sdt'])->update(
                    array(
                        'user_nicename' => $data['name'],
                    )
                );
            }
            if (isset($data['email'])) {
                $user = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_users')->where('user_login', $data['sdt'])->update(
                    array(
                        'user_email' => $data['email'],
                    )
                );
            }

            return $this->returnSuccess(new stdClass(), 'Cập nhật thành công');
        } catch (\Throwable $th) {
            $this->woo_logs('update', $th->getMessage());
            return $this->returnError([], "Lỗi hệ thống");
        }
    }
    public function storeImage(Request $request)
    {
        $path = $request->file('photo')->store('');
        $request->file('photo')->storeAs('', $path, 'uploads');
        return $this->returnSuccess('/storage/app/' . $path, 'Cập nhật thành công');
    }
    public function info(Request $request)
    {try {
        $validator = Validator::make($request->all(), [
            'sdt' => 'required',
            'user_id' => 'required',
            'name' => 'required'
        ],[
            'sdt.required' => "Vui lòng nhập sdt",
            'user_id.required' => "Vui lòng nhập id",
            'name.required' => "Vui lòng nhập name"
        ]);
        if ($validator->fails()) {
            return $this->returnError(new \stdClass,$validator->errors()->first());
        }else{
                $user = DB::table($this->_PRFIX_TABLE.'_users')->where('user_login', $request['sdt'])->first();
                // wp_wc_customer_lookup

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

                }else{
                    $email = $user->user_email;
                }
                $customer = DB::table($this->_PRFIX_TABLE.'_wc_customer_lookup')->where('user_id', $request['user_id'])->first();
                if(!$customer){
                    $insertCus = DB::table($this->_PRFIX_TABLE.'_wc_customer_lookup')->insert(
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
                
    
            // $paymentMethod = $this->getUserMeta($user->ID, 'payment_method');
            // $user->payment_method = ($paymentMethod) ? json_decode($paymentMethod) : "";
    
    
    
            return $this->returnSuccess($user);
                
        }
    }  catch (QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                // Handle the duplicate entry error here
                // For example, you can log the error or display a message to the user
                $mess ="Error: Duplicate entry found.";
            } else {
                // Handle other query exceptions
                // For example, you can log the error or display a generic error message
                $mess =  $e->getMessage();
            }
            $this->woo_logs('info', $e->getMessage());

            return $this->returnError(new \stdClass,$mess );
        } 
       
       
    }
    public function userChild(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;

        $listUserChild = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->select($this->_PRFIX_TABLE . '_users.*', $this->_PRFIX_TABLE . '_users.user_login as mobile', $this->_PRFIX_TABLE . '_woo_history_user_commission.commission', $this->_PRFIX_TABLE . '_woo_history_user_commission.total_order')->join($this->_PRFIX_TABLE . '_users', $this->_PRFIX_TABLE . '_users.ID', $this->_PRFIX_TABLE . '_woo_history_user_commission.user_id')->where('user_parent', $userId)->where('status', 1);


        if (isset($request['search'])) {
            $listUserChild = $listUserChild->where('user_login', 'like', '%' . $request['search'] . '%');
        }
        if (isset($request['order'])) {
            $listUserChild = $listUserChild->orderBy('ID', $request['order']);
        }
        $listUserChild = $listUserChild->get();

        foreach ($listUserChild as $key => $user) {
            $listUserChild[$key]->tong_hoa_hong = $user->commission;
            $listUserChild[$key]->tong_doanh_thu = $user->total_order;
            $listUserChild[$key]->level = "Cấp 1";
        }
        return $this->returnSuccess($listUserChild);
    }
    public function historyWithdraw(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;
        $data = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->where('user_id', $userId)->whereIn('status', [2, 4, 5])->get();

        return $this->returnSuccess($data);
    }
    public function register_aff(Request $request)
    {
        $data = $request->all();
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;

        $user = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
            array(
                'user_id' => $userId, 'meta_key' => 'is_affliate'
            ),
            array(
                'meta_value' => true,
            )
        );
        return $this->returnSuccess($userId, 'Cập nhật thành công');
    }
    public function history_share_link(Request $request)
    {
        $data = $request->all();
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $validator = Validator::make($request->all(), [
            'user_parent' => 'required',
        ], [
            'user_parent.required' => "Vui lòng nhập user_parent",
        ]);
        if ($validator->fails()) {
            return $this->returnError(new \stdClass, $validator->errors()->first());
        } else {
            $userId = $store->user_id;
            $userParent = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_users')->where('user_login', $data['user_parent'])->first();
            if ($data) {
                DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_share_link')->insertGetId(
                    array(
                        'user_id' => $userId,
                        'user_parent' => $userParent->ID,
                        'product' => (isset($data['product'])) ? $data['product'] : NULL,
                    )
                );
            }

            return $this->returnSuccess($userId, 'Cập nhật thành công');
        }
    }
    public function update_payment_method(Request $request)
    {
        try {
            $data = $request->all();
            $store = $request['data_reponse'];
            $this->_PRFIX_TABLE = $store->prefixTable;
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'stk' => 'required',
                'bankname' => 'required',

            ], [
                'name.required' => "Vui lòng tên tài khoản ",
                'stk.required' => "Vui lòng nhập STK",
                'bankname.required' => "Vui lòng nhập tên ngân hàng",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                $userId = $store->user_id;
                $user = DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                    array(
                        'user_id' => $userId, 'meta_key' => 'payment_method'
                    ),
                    array(
                        'meta_value' => json_encode(['name' => $data['name'], 'stk' => $data['stk'], 'bankname' => $data['bankname']]),
                    )
                );
                return $this->returnSuccess($userId, 'Cập nhật thành công');
            }
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('update_payment_method', $th->getMessage());

            return $this->returnError([], "Lỗi hệ thống");
        }
    }
    public function withdraw(Request $request)
    {
        try {
            $data = $request->all();
            $store = $request['data_reponse'];
            $this->_PRFIX_TABLE = $store->prefixTable;
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'stk' => 'required',
                'bankname' => 'required',
                'money' => 'required',

            ], [
                'name.required' => "Vui lòng tên tài khoản ",
                'stk.required' => "Vui lòng nhập STK",
                'bankname.required' => "Vui lòng nhập tên ngân hàng",
                'money.required' => "Vui lòng nhập tiền rút",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                if ($data['money'] < 100) {
                    return $this->returnError(new \stdClass, "Số tiền rút phải lớn hơn 100000");
                }
                $userId = $store->user_id;


                $cho_doi_soat = $this->choDoiSoat($userId);
                $thuc_nhan = $this->thucNhan($userId);
                $tong_hoa_hong = $this->tongHoaHong([$userId], []);
                $hoa_hong = $tong_hoa_hong - $thuc_nhan - $cho_doi_soat;
                if ($data['money'] > $hoa_hong) {
                    return $this->returnError(new \stdClass, "Tiền hoa hồng chỉ còn " . $hoa_hong);
                }
                $paymentMethod = json_encode(['name' => $data['name'], 'stk' => $data['stk'], 'bankname' => $data['bankname']]);
                DB::connection('mysql_external')->table($this->_PRFIX_TABLE . '_woo_history_user_commission')->insertGetId(
                    array(
                        'user_id' => $userId,
                        'total_order' => 0,
                        'commission' => $data['money'],
                        'payment_method' => $paymentMethod,
                        'date' => date('d'),
                        'month' => date('m'),
                        'year' => date('Y'),
                        'status' => 4,
                    )
                );
                return $this->returnSuccess($userId, 'Cập nhật thành công');
            }
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('withdraw', $th->getMessage());

            return $this->returnError([], "Lỗi hệ thống");
        }
    }
    public function getWeek()
    {
        $today = date("Y-m-d");
        $list = [];
        // Lấy ngày của tuần đầu tiên
        $firstDayOfWeek = date("Y-m-d", strtotime('monday this week', strtotime($today)));

        // Tạo một mảng để lưu trữ các ngày trong tuần
        $daysOfWeek = array();

        // Lặp qua từng ngày trong tuần và thêm vào mảng
        for ($i = 0; $i < 7; $i++) {
            $daysOfWeek[] = date("Y-m-d", strtotime("+" . $i . " days", strtotime($firstDayOfWeek)));
        }

        // Hiển thị các ngày trong tuần
        foreach ($daysOfWeek as $day) {
            $list[] = $day;
        }
        return $list;
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function banner(Request $request)
    {
        $store = $request['data_reponse'];
        $banner = DB::connection('mysql_external')->table('badges')->where('status', 'active')
            ->get();
        foreach ($banner as $key =>   $value) {
            $banner[$key]->name = $this->getTextByLanguare($value->name);
            $banner[$key]->image = $this->getImage($value->image, $store);
        }
        return $this->returnSuccess($banner);
    }
    public function city(Request $request)
    {
        $city = file_get_contents('public/data/tinh_tp.json');
        $city = json_decode($city);
        $listCity = [];
        foreach ($city as $id => $val) {
            $json['id'] = $id;
            $json['name'] = $val->name_with_type;
            $listCity[] = $json;
        }
        return $this->returnSuccess($listCity);
    }
    public function quan(Request $request)
    {
        try {
            $data = $request->all();
            $validator = Validator::make($request->all(), [
                'parent' => 'required',
            ], [
                'parent.required' => "Vui lòng chọn thành phố ",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                $param = $data['parent'];
                $quan = file_get_contents("public/data/quan-huyen/$param.json");
                $quan = json_decode($quan);
                $listQuan = [];
                foreach ($quan as $id => $val) {
                    $json['id'] = $id;
                    $json['name'] = $val->name_with_type;
                    $listQuan[] = $json;
                }
                return $this->returnSuccess($listQuan);
            }
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('quan', $th->getMessage());

            return $this->returnError([], "Lỗi hệ thống");
        }
    }
    public function phuong(Request $request)
    {
        try {
            $data = $request->all();
            $validator = Validator::make($request->all(), [
                'parent' => 'required',
            ], [
                'parent.required' => "Vui lòng chọn thành phố ",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                $param = $data['parent'];
                $phuong = file_get_contents("public/data/xa-phuong/$param.json");
                $phuong = json_decode($phuong);
                $listPhuong = [];
                foreach ($phuong as $id => $val) {
                    $json['id'] = $id;
                    $json['name'] = $val->name_with_type;
                    $listPhuong[] = $json;
                }
                return $this->returnSuccess($listPhuong);
            }
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('phuong', $th->getMessage());

            return $this->returnError([], "Lỗi hệ thống");
        }
    }
    public function getFee(Request $request){
        try {
            $data = $request->all();
            $validator = Validator::make($request->all(), [
                'quan' => 'required',
                'phuong' => 'required',
            ], [
                'quan.required' => "Vui lòng chọn quận ",
                'phuong.required' => "Vui lòng chọn phường xã ",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                
                $fee = $this->calFee($data['quan'],$data['phuong']);
                
                return $this->returnSuccess($fee);
            }
        } catch (\Throwable $th) {
            //throw $th;

            return $this->returnError([], $th->getMessage());
        }
    }
}
