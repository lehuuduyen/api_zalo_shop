<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
        $infor = DB::table($this->_PRFIX_TABLE . '_posts')->where('post_name', 'lien-he')->where('post_status', 'publish')->where('post_type', 'page')->first();



        $info = new \stdClass();
        $info->email = "";
        $info->phone = "";
        $info->website = "";
        $info->open_hour = "08:00 - 22:00";
        $branchs = DB::table($this->_PRFIX_TABLE . '_woo_branchs')->where('status', 1)
            ->get();
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: MyGeolocationApp/1.0 (your@email.com)\r\n" // Required by OSM
            ]
        ];
        $context = stream_context_create($opts);

        foreach ($branchs as $key => $val) {
            $branchs[$key]->city_name = ($val->city) ? $this->city($request, $val->city) : "";
            $branchs[$key]->quan_name = ($val->district) ? $this->quan($request, $val->city, $val->district) : "";
            $branchs[$key]->phuong_name = ($val->ward) ? $this->phuong($request, $val->district, $val->ward) : "";
            $branchs[$key]->img = 'https://scontent.fsgn8-4.fna.fbcdn.net/v/t39.30808-6/466000325_1318374532658086_1906160599489764874_n.jpg?_nc_cat=107&ccb=1-7&_nc_sid=6ee11a&_nc_ohc=r_rKZjhtKV4Q7kNvgFfQNPY&_nc_oc=AdjLZFSDPdUCHswWF-J4VyqgdI2UJLWKq0QVLlPUEXxypv7K7mtxxK-i5zLDMtE5rbI&_nc_zt=23&_nc_ht=scontent.fsgn8-4.fna&_nc_gid=A3CCli41rSY71n_p_MuiytM&oh=00_AYGS0qxLBxgqtRbjERiqwPcwL4vs0f-DBm44DHsJ4L0-bQ&oe=67D9A101'; // Properly encode the address
            $phuong = preg_replace('/\b0(\d)/', '$1', $branchs[$key]->phuong_name) . PHP_EOL;
            $address = urlencode($branchs[$key]->address . ',' . $phuong . ',' . $branchs[$key]->quan_name . ',' . $branchs[$key]->city_name); // Properly encode the address

            $position = file_get_contents('https://nominatim.openstreetmap.org/search?q=' . $address . '&format=json&limit=1', false, $context);
            if (!$position) {
                // return ['error' => 'Failed to retrieve location'];
                $branchs[$key]->lat = '';
                $branchs[$key]->lon = '';
            } else {
                $json = json_decode($position, true);
                $branchs[$key]->lat = $json[0]['lat'];
                $branchs[$key]->lon = $json[0]['lon'];
            }
        }
        $info->chi_nhanh = $branchs;

        if ($infor) {
            $content = $infor->post_content;
            $lineAfterPhone = $this->lienhe($content, 'Điện thoại:', 11);


            $email = $this->lienhe($content, 'Email:', 6);
            $website = $this->lienhe($content, 'Website:', 6);
            $email = $this->lienhe($content, 'Email:', 6);

            $info->email = $email;
            $info->phone = $lineAfterPhone;
            $info->website = $website;
        }

        return $this->returnSuccess($info);
    }
    public function country(Request $request)
    {

        $country = DB::table('countries')->where('status', 'publish')->get();
        return $this->returnSuccess($country);
    }
    public function getShare(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;


        $count = DB::table($this->_PRFIX_TABLE . '_woo_history_share_link')->where('user_parent', $store->user_id)->count();
        return $this->returnSuccess($count);
    }
    public function log(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $log = DB::table($this->_PRFIX_TABLE . '_woocommerce_log')->insertGetId(
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
        $cod = DB::table($this->_PRFIX_TABLE . '_options')->where('option_name', 'woocommerce_cod_settings')->first();
        if ($cod) {
            $cod = unserialize($cod->option_value);
            if ($cod['enabled'] == 'yes') {
                $result['cod'] = $cod;
            }
        }
        $paymentMethod = $this->getOptionsMeta('ttqr');
        if ($paymentMethod) {
            $paymentMethod = unserialize($paymentMethod);
            if (isset($paymentMethod['bank_transfer_accounts'])) {
                foreach ($paymentMethod['bank_transfer_accounts'] as $key => $value) {
                    $bin = $this->getBinByShortName($key);
                    if (count($value) > 0) {
                        $value[0]['bin'] = $bin;
                        $result[$key] = $value[0];
                    }
                }
            }
        }
        return $this->returnSuccess($result);
    }
    public function getBinByShortName($shortName)
    {
        // Read data from file.json
        $data = json_decode(file_get_contents('bank.json'));

        // Search for the shortName
        foreach ($data as $bank) {
            if (strtolower($bank->shortName) == $shortName) {
                return $bank->bin;
            }
        }

        // Return null if no match found
        return null;
    }
    // _transient_woocommerce_admin_payment_gateway_suggestions_specs

    public function state(Request $request)
    {
        try {
            //code...
            $state = DB::table('states')->where('status', 'publish')->where('country_id', $request['country_id'])->get();

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

            $store = $request['data_reponse'];
            $this->_PRFIX_TABLE = $store->prefixTable;
            $userId = $store->user_id;
            $getUserParent = $this->getUserMeta($userId, 'user_parent');
            if (isset($data['user_parent']) && !empty($data['user_parent']) &&  !$getUserParent  &&  $data['user_parent'] != '77777777' &&  $userId != 0) {
                $checkUserParent = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $data['user_parent'])->first();
                if ($checkUserParent && $store->sdt != $data['user_parent']) {
                    $this->woo_logs('user_parent_save', $checkUserParent->ID . '-' . $userId);

                    $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->insert(
                        array(
                            'user_id' => $userId,
                            'meta_key' => 'user_parent',
                            'meta_value' => $checkUserParent->ID
                        ),
                    );
                    $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->insert(
                        array(
                            'user_id' => $userId,
                            'meta_key' => 'user_parent_created',
                            'meta_value' => date("d/m/Y")
                        ),
                    );
                } else {
                    return $this->returnError(new \stdClass, 'Mã giới thiệu không tồn tại');
                }
            }

            if (isset($data['address'])) {

                $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                    array(
                        'user_id' => $userId,
                        'meta_key' => 'shipping_address_1'
                    ),
                    array(
                        'meta_value' => $data['address'],
                    )
                );
            }
            if (isset($data['user_key_notification'])) {

                $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                    array(
                        'user_id' => $userId,
                        'meta_key' => 'user_key_notification'
                    ),
                    array(
                        'meta_value' => $data['user_key_notification'],
                    )
                );
            }
            if (isset($data['birthday'])) {

                $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                    array(
                        'user_id' => $userId,
                        'meta_key' => 'birthday'
                    ),
                    array(
                        'meta_value' => $data['birthday'],
                    )
                );
            }
            if (isset($data['image'])) {
                $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                    array(
                        'user_id' => $userId,
                        'meta_key' => 'image_user'
                    ),
                    array('meta_value' => $data['image'])
                );
            }
            if (isset($data['city'])) {
                $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                    array(
                        'user_id' => $userId,
                        'meta_key' => 'city'
                    ),
                    array('meta_value' => $data['city'])
                );
            }
            if (isset($data['quan'])) {
                $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                    array(
                        'user_id' => $userId,
                        'meta_key' => 'quan'
                    ),
                    array('meta_value' => $data['quan'])
                );
            }
            if (isset($data['phuong'])) {
                $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                    array(
                        'user_id' => $userId,
                        'meta_key' => 'phuong'
                    ),
                    array('meta_value' => $data['phuong'])
                );
            }

            if (isset($data['email'])) {
                $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', "$store->sdt")->update(
                    array(
                        'user_email' => $data['email'],
                    )
                );
            }
            if (isset($data['display_name'])) {
                $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', "$store->sdt")->update(
                    array(
                        'display_name' => $data['display_name'],
                    )
                );
            }
            return $this->returnSuccess($userId, 'Cập nhật thành công');
        } catch (\Throwable $th) {
            $this->woo_logs('update', $th->getMessage());
            return $this->returnError([], "Lỗi hệ thống");
        }
    }
    public function storeImage(Request $request)
    {
        // Kiểm tra file có tồn tại không
        if (!$request->hasFile('photo')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        // Lấy file từ request
        $file = $request->file('photo');

        // Kiểm tra file có hợp lệ không
        if (!$file->isValid()) {
            return response()->json(['error' => 'Invalid file'], 400);
        }

        // Lưu file vào thư mục storage/app/public/uploads
        $path = $file->store('uploads', 'public'); // Lưu file

        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;

        $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
            array(
                'user_id' => $userId,
                'meta_key' => 'image_user'
            ),
            array('meta_value' => $path)
        );
        return response()->json(['path' => $path], 200);
    }

    public function info(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $store->sdt)->select('ID', 'display_name as name', 'user_email as email', 'user_login as mobile')
            ->first();
        $countNoti = DB::table($this->_PRFIX_TABLE . '_woo_user_notification')->where('user_id', $user->ID)->where('status', 0)
            ->count();
        $address = $this->getUserMeta($user->ID, 'shipping_address_1');
        $company = $this->getUserMeta($user->ID, 'company');
        $city = $this->getUserMeta($user->ID, 'city');
        $quan = $this->getUserMeta($user->ID, 'quan');
        $phuong = $this->getUserMeta($user->ID, 'phuong');
        $birthday = $this->getUserMeta($user->ID, 'birthday');
        $image = $this->getUserMeta($user->ID, 'image_user');
        if ($image) {
            $image = env('API_URL_BACKEND') . "/storage/" . $image;
        }
        $CouponsController =  new CouponsController();
        $coupons = $CouponsController->index($request, true);

        $user->countCoupon = count($coupons);


        $user->count_notification_not_read = $countNoti;
        $user->address = $address;
        $user->avt = $image;
        $user->user_parent = $this->getUserMeta($user->ID, 'user_parent');
        $user->user_parent_created = $this->getUserMeta($user->ID, 'user_parent_created');
        $user->user_key_notification = $this->getUserMeta($user->ID, 'user_key_notification');

        $user->company = $company;
        $user->city = $city;
        $user->city_name = ($city) ? $this->city($request, $city) : "";
        $user->quan = $quan;
        $user->quan_name = ($quan) ? $this->quan($request, $city, $quan) : "";
        $user->phuong = $phuong;
        $user->phuong_name = ($phuong) ? $this->phuong($request, $quan, $phuong) : "";

        $user->birthday = $birthday;
        // $user->xu = 10000;e/

        $paymentMethod = $this->getUserMeta($user->ID, 'payment_method');
        $user->payment_method = ($paymentMethod) ? json_decode($paymentMethod) : "";

        $user->history = $this->getHistoryUser($user->ID);

        $user->point = $this->getPointUser($user->history);
        $is_affliate = $this->getUserMeta($user->ID, 'is_affliate');
        $user->is_affliate = $is_affliate;
        $user->store = $store->store;



        $user->cho_doi_soat = $this->choDoiSoat($user->ID);
        $user->thuc_nhan = $this->thucNhan($user->ID);
        $user->tong_hoa_hong = $this->tongHoaHong([$user->ID], []);
        $user->tong_hoa_hong_chua_nhan = $this->tongHoaHongChuaNhan([$user->ID], []);

        $user->hoa_hong = $user->tong_hoa_hong - $user->thuc_nhan - $user->cho_doi_soat;
        $user->hoa_hong_da_rut = $user->thuc_nhan;
        $user->tong_doanh_thu = $this->tongDoanhThu([$user->ID], []);
        $user->tong_don_hang = $this->tongDonHang([$user->ID], []);

        // $getWeek = $this->getWeek();
        // $arr[0]['label'] = 'Tổng hoa hồng';
        // $arr[1]['label'] = 'Tổng doanh thu';
        // $arr[2]['label'] = 'Tổng hoa hồng đã rút';
        // $arr[3]['label'] = 'Tổng đơn';
        // $listTongHoaHong = [];
        // $listTongDoanhThu = [];
        // $listTongHoaHongDaRut = [];
        // $listTongDon = [];
        // foreach ($getWeek as $day) {
        //     $arrDay = explode('-', $day);
        //     $date = $arrDay[2];
        //     $month = $arrDay[1];
        //     $year = $arrDay[0];
        //     $tongHoaHong = $this->tongHoaHong([$user->ID], [], $date, $month, $year);
        //     $tongDoanhThu = $this->tongDoanhThu([$user->ID], [], $date, $month, $year);
        //     $tongHoaHongDaRut = $this->thucNhan([$user->ID], $date, $month, $year);
        //     $tongDon =  $this->tongDonHang([$user->ID], [], $date, $month, $year);
        //     $listTongHoaHong[] = $tongHoaHong;
        //     $listTongDoanhThu[] = $tongDoanhThu;
        //     $listTongHoaHongDaRut[] = $tongHoaHongDaRut;
        //     $listTongDon[] = $tongDon;
        // }
        // $arr[0]['data'] = $listTongHoaHong;
        // $arr[1]['data'] = $listTongDoanhThu;
        // $arr[2]['data'] = $listTongHoaHongDaRut;
        // $arr[3]['data'] = $listTongDon;

        // $arr[0]['backgroundColor'] = '#F57C00';
        // $arr[1]['backgroundColor'] = '#00E572';
        // $arr[2]['backgroundColor'] = '#EB00F0';
        // $arr[3]['backgroundColor'] = '#3D3BC2';
        // $user->bieu_do = $arr;
        return $this->returnSuccess($user);
    }
    public function userChild(Request $request)
    {
        try {
            //code...
            $store = $request['data_reponse'];
            $this->_PRFIX_TABLE = $store->prefixTable;
            $userId = $store->user_id;

            $listUserChild = DB::table($this->_PRFIX_TABLE . '_woo_history_user_commission')->select(
                $this->_PRFIX_TABLE . '_users.ID',
                $this->_PRFIX_TABLE . '_users.user_login as mobile',
                $this->_PRFIX_TABLE . '_users.display_name as name',

                DB::raw("SUM(" . $this->_PRFIX_TABLE . "_woo_history_user_commission.commission) as total_commission"),
                DB::raw("SUM(" . $this->_PRFIX_TABLE . "_woo_history_user_commission.total_order) as total_order")
                // , $this->_PRFIX_TABLE . '_woo_history_user_commission.create_at'
            )
                ->join($this->_PRFIX_TABLE . '_users', $this->_PRFIX_TABLE . '_users.ID', $this->_PRFIX_TABLE . '_woo_history_user_commission.user_id')
                ->where('user_parent', $userId)->where('status', 1);


            if (isset($request['search'])) {
                $listUserChild = $listUserChild->where('user_login', 'like', '%' . $request['search'] . '%');
            }
            if (isset($request['order'])) {
                $listUserChild = $listUserChild->orderBy('ID', $request['order']);
            }
            $listUserChild = $listUserChild
                ->groupBy($this->_PRFIX_TABLE . '_users.ID', $this->_PRFIX_TABLE . '_users.display_name', $this->_PRFIX_TABLE . '_users.user_login')
                ->get();

            $tempIds = [];
            foreach ($listUserChild as $key => $child) {
                $tempIds[] = $child->ID;
            }


            //----Get user click
            // $listUserClick = DB::
            // table($this->_PRFIX_TABLE . '_woo_history_share_link')->
            // select($this->_PRFIX_TABLE . '_users.ID',$this->_PRFIX_TABLE . '_users.display_name as name', $this->_PRFIX_TABLE . '_users.user_login as mobile', $this->_PRFIX_TABLE . '_woo_history_share_link.create_at')->
            // join($this->_PRFIX_TABLE . '_users', $this->_PRFIX_TABLE . '_users.ID', $this->_PRFIX_TABLE . '_woo_history_share_link.user_id')->
            // where('user_parent', $userId)->where('user_id','!=', $userId)->where('status','!=', 2)->whereNotIn('user_id', $tempIds);

            // if (isset($request['search'])) {
            //     $listUserClick = $listUserClick->where('user_login', 'like', '%' . $request['search'] . '%');
            // }
            // if (isset($request['order'])) {
            //     $listUserClick = $listUserClick->orderBy('ID', $request['order']);
            // }


            // $listUserClick = $listUserClick->groupBy($this->_PRFIX_TABLE . '_users.ID',$this->_PRFIX_TABLE . '_users.display_name',$this->_PRFIX_TABLE . '_users.user_login',$this->_PRFIX_TABLE . '_woo_history_share_link.create_at')->get();
            // $tempClick =[];
            // $listUserClickNew =[];
            // foreach($listUserClick as $val){
            //     if(!in_array($val->ID,$tempClick)){
            //         $tempClick[]=$val->ID;
            //         $listUserClickNew[]=$val;
            //     }
            // }

            // $mergedData = $listUserChild->merge($listUserClickNew);
            // $sortedData = $mergedData->sortByDesc('create_at');

            //----Get user click

            $sortedData = $listUserChild->sortByDesc('create_at');
            // $userParent = $this->loopChild($userId);

            $stt = 0;
            $result = [];
            foreach ($sortedData as $key => $user) {
                $result[$stt] = $user;
                $result[$stt]->tong_hoa_hong = (isset($user->total_commission)) ? $user->total_commission : 0;
                $result[$stt]->tong_doanh_thu = (isset($user->total_order)) ? $user->total_order : 0;
                $result[$stt]->level = "Cấp 1";
                $result[$stt]->image = $this->getUserMeta($user->ID, 'image_user');
                $result[$stt]->date = $this->getUserMeta($user->ID, 'user_parent_created');
                $stt++;
            }
            // $result = array_merge($userParent, $result);
            return $this->returnSuccess($result);
        } catch (\Throwable $th) {
            return $this->returnError($th->getMessage());
        }
    }
    public function loopChild($userParentId)
    {
        $userParentIsset = DB::table($this->_PRFIX_TABLE . '_usermeta')
            ->select(
                $this->_PRFIX_TABLE . '_users.user_login as mobile',
                $this->_PRFIX_TABLE . '_users.display_name as name',
                $this->_PRFIX_TABLE . '_usermeta.user_id'
            )
            ->leftJoin($this->_PRFIX_TABLE . '_users',  $this->_PRFIX_TABLE . '_users.ID',  $this->_PRFIX_TABLE . '_usermeta.user_id')
            ->where($this->_PRFIX_TABLE . '_usermeta.meta_value', $userParentId)
            ->where($this->_PRFIX_TABLE . '_usermeta.meta_key', 'user_parent')
            ->get();
        $userParents = [];

        foreach ($userParentIsset as $val) {
            $val->parent = $this->loopChild($val->user_id);
            $val->image = $this->getUserMeta($val->user_id, 'image_user');
            $userParents[] = $val;
        }
        return $userParents;
    }
    public function historyWithdraw(Request $request)
    {
        $store = $request['data_reponse'];

        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $status = explode(',', $_GET['status']);

            $data = DB::table($this->_PRFIX_TABLE . '_woo_history_user_commission')->where('user_id', $userId)->whereIn('status', $status)->get();
        } else {
            $data = DB::table($this->_PRFIX_TABLE . '_woo_history_user_commission')->where('user_id', $userId)->whereIn('status', [2, 4, 5])->get();
        }

        return $this->returnSuccess($data);
    }
    public function register_aff(Request $request)
    {
        $data = $request->all();
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;

        $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
            array(
                'user_id' => $userId,
                'meta_key' => 'is_affliate'
            ),
            array(
                'meta_value' => true,
            )
        );
        return $this->returnSuccess($userId, 'Cập nhật thành công');
    }
    public function receiver_aff(Request $request)
    {
        $data = $request->all();
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;

        $user = DB::table($this->_PRFIX_TABLE . '_woo_history_user_commission')
            ->where('user_parent', $userId)
            ->where('status', 6)
            ->update(
                array(
                    'status' => 1,
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
            $userParent = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $data['user_parent'])->first();
            if ($data && $userParent && $data['user_parent'] != "77777777") {
                DB::table($this->_PRFIX_TABLE . '_woo_history_share_link')->insertGetId(
                    array(
                        'user_id' => $userId,
                        'user_parent' => $userParent->ID,
                        'product' => (isset($data['product'])) ? $data['product'] : NULL,
                    )
                );
                $userParentIsset = DB::table($this->_PRFIX_TABLE . '_usermeta')
                    ->where('user_id', $userId)
                    ->where('meta_key', 'user_parent')
                    ->first();
                if (!$userParentIsset) {
                    DB::table($this->_PRFIX_TABLE . '_usermeta')->insert(
                        array(
                            'user_id' => $userId,
                            'meta_key' => 'user_parent',
                            'meta_value' => $userParent->ID
                        ),
                    );
                }
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
                $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                    array(
                        'user_id' => $userId,
                        'meta_key' => 'payment_method'
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
            DB::beginTransaction();
            $data = $request->all();
            $store = $request['data_reponse'];
            $this->_PRFIX_TABLE = $store->prefixTable;
            $validator = Validator::make($request->all(), [
                // 'name' => 'required',
                // 'stk' => 'required',
                // 'bankname' => 'required',
                'money' => 'required',

            ], [
                // 'name.required' => "Vui lòng tên tài khoản ",
                // 'stk.required' => "Vui lòng nhập STK",
                // 'bankname.required' => "Vui lòng nhập tên ngân hàng",
                'money.required' => "Vui lòng nhập tiền rút",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                if ($data['money'] < 100) {
                    return $this->returnError(new \stdClass, "Số tiền rút phải lớn hơn 100.000");
                }
                $userId = $store->user_id;

                // check stk






                $cho_doi_soat = $this->choDoiSoat($userId);
                $thuc_nhan = $this->thucNhan($userId);
                $tong_hoa_hong = $this->tongHoaHong([$userId], []);
                $hoa_hong = $tong_hoa_hong - $thuc_nhan - $cho_doi_soat;
                if ($data['money'] > $hoa_hong) {
                    return $this->returnError(new \stdClass, "Tiền hoa hồng chỉ còn " . $hoa_hong);
                }
                if (isset($data['sdt'])) {
                    $data['stk'] = $data['sdt'];
                    $data['bankname'] = "MOMO";
                    $data['name'] = $data['sdt'];
                }

                // check stk
                $list = ['name' => $data['name'], 'stk' => $data['stk'], 'bankname' => $data['bankname']];
                $getWallet = $this->getUserMeta($userId, 'wallet');
                $isSave = true;
                if ($getWallet) {
                    $listWallet = unserialize($getWallet);
                    foreach ($listWallet as $walletOne) {
                        if ($data['bankname'] == "MOMO" && $walletOne['bankname'] == "MOMO") {
                            if ($walletOne['name'] != $list['name']) {
                                $isSave = false;
                                return $this->returnError([], "Bảo mật, Số điện thoại phải là " . $walletOne['name'] . " . Hãy liên hệ tổng đài để đổi. ");
                            }
                        } else {
                            if ($walletOne['name'] != $list['name']) {
                                $isSave = false;
                                return $this->returnError([], "Bảo mật, Tên tài khoản phải là " . $walletOne['name'] . " . Hãy liên hệ tổng đài để đổi. ");
                            }
                        }

                        if ($walletOne['name'] ==  $list['name'] && $walletOne['stk'] ==  $list['stk'] && $walletOne['bankname'] ==  $list['bankname']) {
                            $isSave = false;
                        }
                    }

                    array_push($listWallet, $list);
                } else {
                    $listWallet = [['name' => $data['name'], 'stk' => $data['stk'], 'bankname' => $data['bankname']]];
                }
                if ($isSave) {

                    $paymentMethod = serialize($listWallet);


                    $wallet = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                        array(
                            'user_id' => $userId,
                            'meta_key' => 'wallet'
                        ),
                        array('meta_value' => $paymentMethod)
                    );
                }





                $paymentMethod = json_encode($list);
                DB::table($this->_PRFIX_TABLE . '_woo_history_user_commission')->insertGetId(
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
                DB::commit();

                return $this->returnSuccess($userId, 'Cập nhật thành công');
            }
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('withdraw', $th->getMessage());
            DB::rollback();

            return $this->returnError([],  $th->getMessage());
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
        $banner = DB::table('badges')->where('status', 'active')
            ->get();
        foreach ($banner as $key =>   $value) {
            $banner[$key]->name = $this->getTextByLanguare($value->name);
            $banner[$key]->image = $this->getImage($value->image, $store);
        }
        return $this->returnSuccess($banner);
    }
    public function city(Request $request, $idCity = "")
    {
        $city = file_get_contents('data/tinh_tp.json');
        $city = json_decode($city);
        if ($idCity) {
            return $city->$idCity->name_with_type;
        }
        $listCity = [];
        foreach ($city as $id => $val) {
            $json['id'] = $id;
            $json['name'] = $val->name_with_type;
            $listCity[] = $json;
        }
        return $this->returnSuccess($listCity);
    }
    public function quan(Request $request, $idCity = "", $idQuan = "")
    {
        if ($idCity && $idQuan) {
            $param = $idCity;
            $quan = file_get_contents("data/quan-huyen/$param.json");
            $quan = json_decode($quan);
            return $quan->$idQuan->name_with_type;
        }
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
                $quan = file_get_contents("data/quan-huyen/$param.json");
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
    public function phuong(Request $request, $idQuan = "", $idPhuong = "")
    {


        if ($idPhuong && $idQuan) {
            $param = $idQuan;
            $phuong = file_get_contents("data/xa-phuong/$param.json");
            $phuong = json_decode($phuong);
            return $phuong->$idPhuong->name_with_type;
        }
        try {
            $data = $request->all();
            $validator = Validator::make($request->all(), [
                'parent' => 'required',
            ], [
                'parent.required' => "Vui lòng chọn quận ",
            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                $param = $data['parent'];
                $phuong = file_get_contents("data/xa-phuong/$param.json");
                $phuong = json_decode($phuong);
                if ($idPhuong) {
                    return $phuong->$idPhuong->name_with_type;
                }
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
    public function listRotation(Request $request)
    {
        $rotation = DB::table($this->_PRFIX_TABLE . '_woo_list_rotations')->get();

        return $this->returnSuccess($rotation);
    }
    public function getTurn(Request $request)
    {

        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;
        $this->woo_logs('getTurn', $userId);

        $turn = $this->getUserMeta($userId, 'turn');
        return $this->returnSuccess(($turn) ? $turn : 0);
    }
    public function addTurn(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;
        $checkTurnDaily = $this->checkTurnDaily($userId);
        if (!$checkTurnDaily) {
            DB::table($this->_PRFIX_TABLE . '_woo_user_turn_rotations')->insertGetId(
                array(
                    'user_id' => $userId,
                    'date' => date('Y/m/d'),

                )
            );
            $turn = $this->getUserMeta($userId, 'turn');

            $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                array(
                    'user_id' => $userId,
                    'meta_key' => 'turn'
                ),
                array('meta_value' => ($turn) ? $turn + 1 : 1)
            );
            return $this->returnSuccess(true, 'Đăng nhập nhận xu thành công');
        } else {
            return $this->returnError(false, 'Bạn đã nhận hôm nay');
        }
        // $rotation = DB::table($this->_PRFIX_TABLE . '_woo_list_rotations')->get();

    }
    public function checkApiTurnDaily(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;
        return $this->returnSuccess($this->checkTurnDaily($userId));
    }
    public function checkTurnDaily($userId)
    {
        $checkTurnDaily = DB::table($this->_PRFIX_TABLE . '_woo_user_turn_rotations')->where('user_id', $userId)->where('date', date('Y/m/d'))->first();
        return ($checkTurnDaily) ? true : false;
    }
    public function changeXuToPoint(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;
        $xuChange = $request['xu'];
        $xuNow = $this->getXuUser($userId);
        if ($xuChange > $xuNow) {
            return $this->returnError($userId, 'Số xu còn lại ' . $xuNow);
        } else {
            $tileXuPoint = $this->getOptionsMeta('woo_rotation_change_xu');
            $point = floor($xuChange / $tileXuPoint);
            $xuTru = ($point * $tileXuPoint);
            $results = DB::table($this->_PRFIX_TABLE . '_woo_history_user_rotation')->insertGetId(
                array(
                    'user_id' => $userId,
                    'total_order' => 0,
                    'commission' => $xuTru,
                    'date' => date('d'),
                    'month' => date('m'),
                    'year' => date('Y'),
                    'status' => 2,
                )
            );
            $results = DB::table($this->_PRFIX_TABLE . '_woo_history_user_point')->insertGetId(
                array(
                    'user_id' => $userId,
                    'total_order' => 0,
                    'point' => $point,
                    'minimum_spending' => 0,
                    'points_converted_to_money' => 0,
                )
            );
            return $this->returnSuccess($userId . '-' . $xuTru, 'Số điểm nhận được ' . $point);
        }
    }
    function weighted_random($values)
    {
        // Tính tổng phần trăm của tất cả các giá trị
        $total_percentage = array_sum($values);

        // Tạo một số ngẫu nhiên từ 0 đến tổng phần trăm
        $random = mt_rand(1, $total_percentage);

        // Lặp qua mảng và tăng dần tổng phần trăm cho đến khi tổng phần trăm vượt qua số ngẫu nhiên
        $cumulative_percentage = 0;
        foreach ($values as $key => $percentage) {
            $cumulative_percentage += $percentage;
            if ($random <= $cumulative_percentage) {
                return $key;
            }
        }
    }
    public function activeRotation(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;
        $this->woo_logs('activeRotation', $userId);

        $turnUser = $this->getUserMeta($userId, 'turn');
        $this->woo_logs('activeRotation turn', $turnUser);

        $result = [];
        if ($turnUser > 0) {

            $getReward = DB::table($this->_PRFIX_TABLE . '_woo_list_rotations')->get();
            $rates = [];
            $nameVongQuay = [];
            $pointVongQuay = [];
            foreach ($getReward as $value) {
                $tile = $value->rate;
                $rates[$value->id] = $tile;
                $nameVongQuay[$value->id] = $value->name;
                $pointVongQuay[$value->id] = $value->point;
            }
            $selected_rate = $this->weighted_random($rates);


            if ($selected_rate) {
                $user = DB::table($this->_PRFIX_TABLE . '_usermeta')->updateOrInsert(
                    array(
                        'user_id' => $userId,
                        'meta_key' => 'turn'
                    ),
                    array('meta_value' => $turnUser - 1)
                );
                $results = DB::table($this->_PRFIX_TABLE . '_woo_history_user_rotation')->insertGetId(
                    array(
                        'user_id' => $userId,
                        'total_order' => 0,
                        'commission' => $pointVongQuay[$selected_rate],
                        'date' => date('d'),
                        'month' => date('m'),
                        'year' => date('Y'),
                        'create_at' => date('Y-m-d  H:i:s'),

                    )
                );
                return $this->returnSuccess($selected_rate, "Chúc mừng bạn nhận được " . $nameVongQuay[$selected_rate]);
            } else {
                return $this->returnError($userId, "Không có thông tin");
            }
        } else {
            return $this->returnError($userId, "Bạn đã hết lượt quay");
        }
    }
    public function getXu(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;
        return $this->returnSuccess([
            'so_xu' => $this->getXuUser($userId),
            'rate_xu' => $this->getOptionsMeta('woo_rotation_change_xu'),
            'to_point' => $this->getOptionsMeta('woo_rotation_to_point'),
        ]);
    }

    public function getFee(Request $request)
    {
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

                $fee = $this->calFee($data['quan'], $data['phuong']);

                return $this->returnSuccess($fee);
            }
        } catch (\Throwable $th) {
            //throw $th;

            return $this->returnError([], $th->getMessage());
        }
    }

    public function notifications(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;
        $noti = DB::table($this->_PRFIX_TABLE . '_woo_notification')
            ->join($this->_PRFIX_TABLE . '_woo_user_notification', $this->_PRFIX_TABLE . '_woo_notification.id', $this->_PRFIX_TABLE . '_woo_user_notification.notification_id')
            ->where($this->_PRFIX_TABLE . '_woo_user_notification.user_id', $userId)
            ->get();
        return $this->returnSuccess($noti);
    }
    public function notification_read(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;
        $user = DB::table($this->_PRFIX_TABLE . '_woo_user_notification')->where('user_id', $userId)->update(
            array(
                'status' => 1,
            )
        );
        return $this->returnSuccess([1], "Đã xem");
    }

    public function prize(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $prizes = DB::table($this->_PRFIX_TABLE . '_woo_point_prize')
            ->get();
        return $this->returnSuccess($prizes);
    }
    public function doi_qua(Request $request)
    {
        try {
            //code...
            DB::beginTransaction();

            $store = $request['data_reponse'];

            $data = $request->all();
            $this->_PRFIX_TABLE = $store->prefixTable;
            $userId = $store->user_id;
            $timeNow = date('Y/m/d H:i:s');

            $prizes = DB::table($this->_PRFIX_TABLE . '_woo_point_prize')
                ->where('id', $data['prize_id'])
                ->where('status', 1)
                ->first();
            if (!$prizes) {
                return $this->returnError([], "Phần thưởng không tồn tại ");
            }
            if ($prizes->quantity >= $prizes->count) {
                return $this->returnError([], "Phần thưởng hiện tại đã hết ");
            }
            $history = $this->getHistoryUser($userId);

            $point = $this->getPointUser($history);
            $pointDoiThuong = $point['totalDoiThuong'];
            if ($pointDoiThuong < $prizes->point) {
                return $this->returnError([], "Bạn chỉ còn $pointDoiThuong điểm, không đủ để đổi thưởng ");
            }
            //type là voucher
            if ($prizes->type == 1) {
                //them voucher
                $postId = DB::table($this->_PRFIX_TABLE . '_posts')->insertGetId(
                    array(
                        'post_date' => $timeNow,
                        'post_date_gmt' => $timeNow,
                        'post_modified' => $timeNow,
                        'post_modified_gmt' => $timeNow,
                        'post_title' => uniqid(),
                        'post_status' => 'publish',
                        'post_type' => 'shop_coupon',
                        'post_content' => '',
                        'post_excerpt' => '[Đổi quà]  ' . $prizes->name,
                        'post_name' =>  Str::slug('[Đổi quà]  ' . $prizes->name),
                        'to_ping' => '',
                        'pinged' => '',
                        'post_content_filtered' => '',

                        'comment_count' => '0',
                    )
                );
                $postMeta = DB::table($this->_PRFIX_TABLE . '_postmeta')->insert(
                    array(

                        array(
                            'post_id' => $postId,
                            'meta_key' => 'coupon_amount',
                            'meta_value' => $prizes->percent,
                        ),
                        array(
                            'post_id' => $postId,
                            'meta_key' => 'discount_type',
                            'meta_value' => 'percent',
                        ),
                        array(
                            'post_id' => $postId,
                            'meta_key' => 'individual_use',
                            'meta_value' => 'no',
                        ),
                        array(
                            'post_id' => $postId,
                            'meta_key' => 'usage_limit',
                            'meta_value' => 1,
                        ),
                        array(
                            'post_id' => $postId,
                            'meta_key' => 'usage_limit_per_user',
                            'meta_value' => 1,
                        ),

                        array(
                            'post_id' => $postId,
                            'meta_key' => 'usage_count',
                            'meta_value' => 0,
                        ),
                        array(
                            'post_id' => $postId,
                            'meta_key' => 'date_expires',
                            'meta_value' => strtotime("+1 month"),
                        ),
                        array(
                            'post_id' => $postId,
                            'meta_key' => 'customer_email',
                            'meta_value' => serialize([$store->email]),
                        )


                    )
                );
            }
            // type là quà tặng
            $results = DB::table($this->_PRFIX_TABLE . '_woo_history_user_point')->insertGetId(
                array(
                    'user_id' => $userId,
                    'total_order' => 0,
                    'point' => $prizes->point,
                    'minimum_spending' => 0,
                    'points_converted_to_money' => 0,
                    'status' => 2,
                    'prize_id' => $prizes->id,
                )
            );

            DB::commit();
            return $this->returnSuccess($postId, 'Đổi quà thành công');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->returnError([], $th->getMessage());
        }
    }

    public function wallet(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $userId = $store->user_id;
        $getWallet = $this->getUserMeta($userId, 'wallet');
        if ($getWallet) {
            $getWallet = unserialize($getWallet);
        } else {
            $getWallet = [];
        }



        return $this->returnSuccess($getWallet);
    }
    public function send_notification()
    {
        try {
            $channelName = 'new';
            $recipient = 'ExponentPushToken[PbX_02HqX8EYES8VPInoNO]';

            // You can quickly bootup an expo instance
            $expo = \ExponentPhpSDK\Expo::normalSetup();

            // Subscribe the recipient to the server
            $expo->subscribe($channelName, $recipient);

            // Build the notification data
            $notification = ['body' => 'Hello World! 32323243'];

            // Notify an interest with a notification
            $expo->notify([$channelName], $notification);
        } catch (\Throwable $th) {
            throw $th;
        }
    }
    public function config()
    {
        $is = env('IS_PAYMENT');

        return $this->returnSuccess($is, ($is) ? "" : 'Tính năng đặt hàng đang phát triển');
    }
}
