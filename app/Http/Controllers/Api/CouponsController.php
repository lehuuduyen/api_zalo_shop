<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CouponsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $coupons = DB::connection('mysql_external')->table('wp_posts')->where('post_status','publish')->where('post_type','shop_coupon')->get();
        $listCoupons =[];
        $i =0;
        foreach($coupons as $key => $val){
            $discount_type = $this->getPostMeta($val->ID,'discount_type');
            if($discount_type == "percent"){
                $discount_type = 'percentage';
            }
            $discount = $this->getPostMeta($val->ID,'coupon_amount');
            $date_expires = $this->getPostMeta($val->ID,'date_expires');
            if($date_expires < time()){
                continue;
            }
            
            $coupons[$key]->title=$val->post_excerpt;
            $coupons[$key]->code=$val->post_title;
            $coupons[$key]->discount_type=$discount_type;
            $coupons[$key]->discount=$discount;
            $coupons[$key]->expire_date=date('d/m/Y H:i:s', $date_expires);
            $listCoupons[$i] = $coupons[$key];
            $i++;
        }


        return $this->returnSuccess($listCoupons);
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
