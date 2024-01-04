<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
        $infor = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_posts')->where('post_name','lien-he')->where('post_status','publish')->first();



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
        if($infor){
            $content = $infor->post_content;
            $lineAfterPhone = $this->lienhe($content,'Điện thoại:',11);


            $info->phone = [
                'title' => 'Điện thoại:',
                'value' => $lineAfterPhone,
            ];
            $email = $this->lienhe($content,'Email:',6);
            $info->email = [
                'title' => 'Email:',
                'value' => $email,
            ];
            $address = $this->lienhe($content,'Địa chỉ:',8);
            $info->address = [
                'title' => 'Địa chỉ :',
                'value' => $address,
            ];
        }

        return $this->returnSuccess($info);
    }
    public function country(Request $request)
    {
        $country = DB::connection('mysql_external')->table('countries')->where('status','publish')->get();
        return $this->returnSuccess($country);
    }
    public function getPaymentMethod(Request $request)
    {
        //check Cod
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $result = [];
        $cod = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_options')->where('option_name','woocommerce_cod_settings')->first();
        if($cod){
            $cod = unserialize( $cod->option_value);
            if($cod['enabled'] == 'yes'){
                $result['cod'] = $cod;
            }
        }
        $payment = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_options')->where('option_name','woocommerce_bacs_settings')->first();
        if($payment){
            $payment = unserialize( $payment->option_value);
            if($payment['enabled'] == 'yes'){
                $payment['account'] =[];

                $paymentAccount = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_options')->where('option_name','woocommerce_bacs_accounts')->first();
                if($paymentAccount){
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
        $state = DB::connection('mysql_external')->table('states')->where('status','publish')->where('country_id',$request['country_id'])->get();

        return $this->returnSuccess($state);
       } catch (\Throwable $th) {
        //throw $th;
        return $this->returnError([],$th->getMessage());

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
        $data = $request->all();
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $validator = Validator::make($request->all(), [
            'address' => 'required',
            'email' => 'required',

        ], [
            'address.required' => "Vui lòng nhập địa chỉ ",
            'email.required' => "Vui lòng nhập email",
        ]);
        if ($validator->fails()) {
            return $this->returnError(new \stdClass, $validator->errors()->first());
        } else {


            $userId = $store->user_id;
            $user = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_usermeta')->updateOrInsert(
                array(
                    'user_id' => $userId,'meta_key' => 'shipping_address_1'),
                array(
                    'meta_value' => $data['address'],
                )
            );
            $user = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_usermeta')->updateOrInsert(
                array(
                    'user_id' => $userId,'meta_key' => 'company'),
                array('meta_value' => $data['company'])
            );

            $user = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_users')->where('user_login', $store->sdt)->update(
                array(
                    'user_email' => $data['email'],
                )
            );

            return $this->returnSuccess($userId,'Cập nhật thành công');

        }


    }
    public function info(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $user = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_users')->where('user_login', $store->sdt)->select('ID','display_name as name','user_email as email','user_login as mobile')
        ->first();
        $address = $this->getUserMeta($user->ID,'shipping_address_1');
        $company = $this->getUserMeta($user->ID,'company');
        $user->address = $address;
        $user->company = $company;
        return $this->returnSuccess($user);

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
        foreach($banner as $key =>   $value){
            $banner[$key]->name = $this->getTextByLanguare($value->name);
            $banner[$key]->image = $this->getImage($value->image,$store);
        }
        return $this->returnSuccess($banner);
    }
}
