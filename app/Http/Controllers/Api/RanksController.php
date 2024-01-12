<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RanksController extends Controller
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
        $data = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_woo_rank')->orderBy('minimum_spending', 'ASC')->get();
        foreach($data  as $key  => $value){
            $content = '';
            if($value->is_limit){
                $content = 'Số tiền khuyến mãi tối đa đơn hàng: '.$value->price_sale_off_max;
            }
            $data[$key]->coupon=['coupon'=>'Ưu đãi voucher '.$value->price_sale_off,'content'=>$content];
        }



        return $this->returnSuccess($data);
    }
    public function get_point_to_money(Request $request)
    {
        $store = $request['data_reponse'];
        $this->_PRFIX_TABLE = $store->prefixTable;
        $data = DB::connection('mysql_external')->table( $this->_PRFIX_TABLE .'_woo_setting')->select('points_converted_to_money')->where('id', '1')->first();
        $points_converted_to_money = 0;
        if($data){
            $points_converted_to_money = $data->points_converted_to_money;

        }
        return $this->returnSuccess($points_converted_to_money);
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
