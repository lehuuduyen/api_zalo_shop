<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AffiliateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function registerAffiliate(Request $request)
    {
        try {
            DB::connection('mysql_external')->beginTransaction();

            $validator = Validator::make($request->all(), [
                'referral_code' => 'required',
            ], [
                'referral_code.required' => "Vui lòng nhập mã giới thiệu",

            ]);
            if ($validator->fails()) {
                return $this->returnError(new \stdClass, $validator->errors()->first());
            } else {
                $store = $request['data_reponse'];
                //người nhập mã giới thiệu

                $user = DB::connection('mysql_external')->table('users')->where('mobile', $store->sdt)->first();
                if (isset($user->referral_code)) {
                    if (!empty($user->referral_code)) {
                        return $this->returnError(new \stdClass, 'Bạn đã nhập mã giới thiệu');
                    }
                }
                //người được giới thiệu
                $userReferalCode = DB::connection('mysql_external')->table('users')->where('mobile', $request['referral_code'])->first();
                if(!$userReferalCode || $request['referral_code'] == $store->sdt ){
                    return $this->returnError(new \stdClass, 'Mã giới thiệu nhập không đúng');
                }
                // thêm mã giới thiệu
                DB::connection('mysql_external')->table('users')->where('id', $user->id)->update(
                    array(
                        'referral_code' => $request['referral_code'],
                    )
                );

                //check xem refferal có đại lý cấp 2 ko
                $listDaiLy2 = DB::connection('mysql_external')->table('agency_level')->where('user_agency_second', $userReferalCode->id)->get();
                foreach($listDaiLy2 as $key=> $value){
                    //kiểm tra xem row có đại lý 3 chưa
                    if(empty($value->user_agency_third)){
                        DB::connection('mysql_external')->table('agency_level')->where('id', $value->id)->update(
                            array(
                                'user_agency_third' => $user->id,
                            )
                        );
                        break;
                    }
                    if(count($listDaiLy2) == $key + 1){
                        DB::connection('mysql_external')->table('agency_level')->insertGetId(
                            array(
                                'user_agency_first' => $value->user_agency_first,
                                'user_agency_second' => $value->user_agency_second,
                                'user_agency_third' => $user->id,
                            )
                        );
                    }
                }
                // thêm đại lý cấp 1 2
                DB::connection('mysql_external')->table('agency_level')->insertGetId(
                    array(
                        'user_agency_first' => $userReferalCode->id,
                        'user_agency_second' => $user->id,
                    )
                );
                DB::connection('mysql_external')->commit();
                return $this->returnSuccess(new \stdClass(),'Thêm mã giới thiệu thành công');

            }
        } catch (\Throwable $th) {
            DB::connection('mysql_external')->rollBack();
            return $this->returnError(new \stdClass, $th->getMessage());
        }
    }
    public function calCommission($userId,$finalPriceDetails,$product_orders_id){
        //kiểm tra xem người này có phải đại lý 3
        $user = DB::connection('mysql_external')->table('agency_level')->where('user_agency_third', $userId)->first();
        if($user){
            $agencyFirst = $user->user_agency_first;
            $agencySecond = $user->user_agency_second;
            $commission_agency_first = 0;
            $commission_agency_second = 0;
            $configCommission = DB::connection('mysql_external')->table('configure_commissions')->first();
            if($configCommission){
                $commission_agency_first = $configCommission->commission_agency_first;
                $commission_agency_second = $configCommission->commission_agency_second;
            }
            $price_commssion_first = $finalPriceDetails * $commission_agency_first /100;
            $price_commssion_second = $finalPriceDetails * $commission_agency_second /100;
            DB::connection('mysql_external')->table('history_commission')->insertGetId(
                array(
                    'user_receiver_commission' => $agencyFirst,
                    'user_send_commission' => $userId,
                    'total' => $price_commssion_first,
                    'config_commission' => $commission_agency_first,
                    'product_orders_id' => $product_orders_id,
                    'status' => 1,
                )
            );
            DB::connection('mysql_external')->table('history_commission')->insertGetId(
                array(
                    'user_receiver_commission' => $agencySecond,
                    'user_send_commission' => $userId,
                    'total' => $price_commssion_second,
                    'config_commission' => $commission_agency_second,
                    'product_orders_id' => $product_orders_id,
                    'status' => 1,
                )
            );
            //update hoa hồng cho user
            DB::connection('mysql_external')->table('users')->where('id', $agencyFirst)->increment('total_commission',$price_commssion_first);
            DB::connection('mysql_external')->table('users')->where('id', $agencySecond)->increment('total_commission',$price_commssion_second);
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
