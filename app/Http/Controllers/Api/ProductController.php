<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $results =[];
        $userId = false;
        $phims = DB::table($this->_PRFIX_TABLE . '_films');

        $phims = $phims->orderBy('create_at', 'DESC')->get();
        $data = $request->all();
        if(isset($data['user'])){
            $userId = $data['user']->ID;
        }

        $time = time();
        $hot= 1;
        foreach ($phims as $key => $phim) {
            $phim->category_ids = json_decode($phim->category_ids);
            $phim->episode =[];
            $allTapDaMuaByPhim =[];
            if($userId){
                $allTapDaMuaByPhim = $this->getAllTapDaMuaByPhim($phim->id,$userId);

            }
            $listTap =[];
            $listTap = DB::table($this->_PRFIX_TABLE . '_postmeta')->
            join($this->_PRFIX_TABLE . '_posts',$this->_PRFIX_TABLE . '_posts.ID',$this->_PRFIX_TABLE . '_postmeta.post_id')->
            select($this->_PRFIX_TABLE . '_posts.post_title as name',$this->_PRFIX_TABLE . '_posts.ID')->
            where($this->_PRFIX_TABLE . '_postmeta.meta_key','_film_selected')->
            where($this->_PRFIX_TABLE . '_postmeta.meta_value',$phim->id)->get();
            if($listTap){
                foreach($listTap as $key => $tap){
                    $listTap[$key]->link_movie = $this->getPostMeta($tap->ID,'_film_episode');
                    $listTap[$key]->price = $this->getPostMeta($tap->ID,'_price');
                    $listTap[$key]->is_buy = (in_array($tap->ID,$allTapDaMuaByPhim))?0:1;
                    $listTap[$key]->film_length = $this->getPostMeta($tap->ID,'_film_length');
                }
                $phim->episode =$listTap;
                $phim->is_hot = $hot;
                $phim->content =$phim->film_description;
                $results[]=$phim;

            }



        }
        return $this->returnSuccess($results);
    }
    public function getCategories(Request $request)
    {

        $categories = DB::table($this->_PRFIX_TABLE . '_term_taxonomy')->join($this->_PRFIX_TABLE . '_terms', $this->_PRFIX_TABLE . '_terms.term_id', $this->_PRFIX_TABLE . '_term_taxonomy.term_id')->where($this->_PRFIX_TABLE . '_term_taxonomy.taxonomy', 'product_cat')->select($this->_PRFIX_TABLE . '_terms.*')->get();
        $results = [];
        if ($categories) {
            foreach ($categories as $key => $val) {
               if($val->slug == "uncategorized"){
                continue;
               }
               $results[]=$val;
            }
        }


        return $this->returnSuccess($results);
    }
    public function getFavorite(Request $request){
        $data  = $request->all();
        $result = [];
        if(isset($data['user'])  ){
            $user = $data['user'];
            $listFavorite = $this->getUserMeta($user->ID, 'favorite');
            if($listFavorite ){
                $result = json_decode($listFavorite );
            }

        }
        return $this->returnSuccess($result);

    }
    public function addFavorite(Request $request){
        $data  = $request->all();
        if(isset($data['user']) && isset($data['phim']) ){
            $user = $data['user'];

            $listFavorite = $this->getUserMeta($user->ID, 'favorite');

            if($listFavorite){
                $favorite = json_decode($listFavorite);
                if(in_array($data['phim'],$favorite)){
                    $favorite = array_values(array_diff($favorite, [$data['phim']]));
                }else{
                    $favorite[]=$data['phim'];
                }
                $result =DB::table($this->_PRFIX_TABLE . '_usermeta')->where('user_id', $user->ID)->where('meta_key', 'favorite')->update(
                    array(
                        'meta_value' => json_encode($favorite)
                    )
                );
            }else{
                $result = DB::table($this->_PRFIX_TABLE . '_usermeta')->insertGetId(
                    array(
                        'user_id'     =>   $user->ID,
                        'meta_key'     =>   'favorite',
                        'meta_value'     =>   json_encode([$data['phim']])
                    )
                );
            }
            return $this->returnSuccess($result);

        }
        return $this->returnSuccess($data);

    }
    public function getWatched(Request $request)
    {

        $data  = $request->all();
        if(isset($data['user'])){
            $user = $data['user'];
            $listWatched = $this->getUserMeta($user->ID, 'watched');
            if($listWatched){
                $data = json_decode($listWatched);


                // Flatten the data and include "phim" ID for each "tap"
                $flattenedData = [];
                foreach ($data->phim as $phim) {
                    foreach ($phim->tap as $tap) {
                        $tap->phim = $phim->id;
                        $flattenedData[] = $tap;
                    }
                }

                // Sort the flattened data by time in descending order
                usort($flattenedData, function($a, $b) {
                    return $b->time - $a->time;
                });

                // Output the sorted data
                $output = [];
                $i = 0;
                foreach ($flattenedData as $tap) {
                    if($i == 10){
                        break;
                    }
                    $output[] = [
                        'id' => $tap->id,
                        'time_watch' => $tap->time_watch,
                        'time' => $tap->time,
                        'phim' => $tap->phim
                    ];
                    $i++;
                }

                return $this->returnSuccess($output);
            }


        }

        return $this->returnSuccess($data);
    }
    public function addWatched(Request $request){
        $data  = $request->all();
        if(isset($data['user']) && isset($data['tap']) && isset($data['phim']) && isset($data['time_watch']) ){
            $user = $data['user'];
            $listWatched = $this->getUserMeta($user->ID, 'watched');
            $objectWatch = new \stdClass();
            $objectWatch->id = $data['tap'];
            $objectWatch->time_watch = $data['time_watch'];
            $objectWatch->time = time();
            if(!$listWatched)
            {
                $object = new \stdClass();

                $object->phim[]  =['id'=>$data['phim'],'tap'=>[$objectWatch]] ;
                $insertId = DB::table($this->_PRFIX_TABLE . '_usermeta')->insertGetId(
                    array(
                        'user_id'     =>   $user->ID,
                        'meta_key'     =>   'watched',
                        'meta_value'     =>   json_encode($object)
                    )
                );
            }else{
                $listWatch = json_decode($listWatched);
                // check watch ay da co
                $issetPhim = false;
                $issetTap = false;
                foreach($listWatch->phim as $keyPhim => $phim ){
                    if($phim->id == $data['phim']){
                        $issetPhim  = true;
                        foreach($phim->tap as $keyTap => $tap){
                            if($tap->id == $data['tap']){
                                $issetTap = true;
                                $listWatch->phim[$keyPhim]->tap[$keyTap]->time_watch = $data['time_watch'];
                                $listWatch->phim[$keyPhim]->tap[$keyTap]->time = time();
                            }
                        }
                        if(!$issetTap){
                            $listWatch->phim[$keyPhim]->tap[]= $objectWatch;
                        }
                    }
                }
                if(!$issetPhim){
                    $listWatch->phim[]=['id'=>$data['phim'],'tap'=>[$objectWatch]];
                }
                DB::table($this->_PRFIX_TABLE . '_usermeta')->where('user_id', $user->ID)->where('meta_key', 'watched')->update(
                    array(
                        'meta_value' => json_encode($listWatch)
                    )
                );

                // check watch ay chua co



                return $this->returnSuccess($listWatch);

            }
            return $this->returnSuccess($data);
        }
        return $this->returnSuccess([]);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function review(Request $request)
    {
        try {
            $store = $request['data_reponse'];
            $this->_PRFIX_TABLE = $store->prefixTable;
            $data = $request->all();
            $data['sdt'] = $store->sdt;
            if (!isset($data['rating'])) {
                return $this->returnError([], "Bắt buộc phải nhập rating");
            } else if (!isset($data['product_id'])) {
                return $this->returnError([], "Bắt buộc phải nhập product");
            } else {
                $user = DB::table($this->_PRFIX_TABLE . '_users')->where('user_login', $data['sdt'])->first();
                if (!$user) {
                    return $this->returnError([], "Số điện thoại chưa được đăng ký");
                }
                $insertId = DB::table($this->_PRFIX_TABLE . '_comments')->insertGetId(
                    array(
                        'comment_post_ID'     =>   $data['product_id'],
                        'comment_author'     =>   $store->name,
                        'comment_content'     =>   $data['review_text'],
                        'comment_type'     =>   'review',
                        'user_id'   =>   $store->user_id,
                        'comment_date' => date('Y/m/d H:i:s'),
                        'comment_date_gmt' => date('Y/m/d H:i:s')

                    )
                );


                $insert = DB::table($this->_PRFIX_TABLE . '_commentmeta')->insert(
                    array(
                        'comment_id'     =>   $insertId,
                        'meta_value'     =>   $data['rating'],
                        'meta_key'     =>   'rating',
                    )
                );
                return $this->returnSuccess($insert, 'Thêm review thành công');
            }
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('review', $th->getMessage());

            return $this->returnError([], "Lỗi hệ thống");

        }
    }


    public function checkCoupon(Request $request)
    {
        try {
            //code...
            $data = $request->all();
            if (!isset($data['coupon'])) {
                return $this->returnError([], "Bắt buộc phải nhập coupon");
            }
            if (!isset($data['subtotal'])) {
                return $this->returnError([], "Bắt buộc phải nhập total");
            }

            $coupon_amount_total = $this->calculateCoupon($data, [], true);
            if ($coupon_amount_total > 0) {
                return $this->returnSuccess($coupon_amount_total);
            } else {
                return $this->returnError($coupon_amount_total, 'Mã khuyễn mãi không đúng');
            }
        } catch (\Throwable $th) {
            //throw $th;
            $this->woo_logs('checkCoupon', $th->getMessage());
            return $this->returnError(0, $th->getMessage());
        }
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
