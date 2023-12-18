<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlashSaleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
       
        $campaigns = DB::connection('mysql_external')->table('campaigns')->where('status','publish')->whereDate('start_date', '<', Carbon::now())->whereDate('end_date', '>=', Carbon::now())->latest()->get();
        $store = $request['data_reponse'];

        foreach($campaigns as $key => $campaign){
            $title = '';
            if(json_decode($campaign->title)){
                if(isset(json_decode($campaign->title)->vi)){
                    $title = json_decode($campaign->title)->vi;
                }   
            }
            $campaigns[$key]->title = $title;

            $subtitle = '';
            if(json_decode($campaign->subtitle)){
                if(isset(json_decode($campaign->subtitle)->vi)){
                    $subtitle = json_decode($campaign->subtitle)->vi;
                }   
            }
            $campaigns[$key]->subtitle = $subtitle;
            $campaigns[$key]->products = $this->getProductByCampaign($campaign->id,$store);
            $campaigns[$key]->image = $this->getImage($campaign->image,$store);

        }
        return $this->returnSuccess($campaigns);
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
