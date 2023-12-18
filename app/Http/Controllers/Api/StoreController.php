<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StoreController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $infor = DB::connection('mysql_external')->table('wp_posts')->where('post_name','lien-he')->where('post_status','publish')->where('post_type','page')->first();
        
        
        
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
            $lineAfterPhone = $this->lienhe($content,'Điện thoại:',16);
            
            
            $info->phone = [
                'title' => 'Điện thoại:',
                'value' => $lineAfterPhone,
            ];
            $email = $this->lienhe($content,'Email:',16);
            $info->email = [
                'title' => 'Email:',
                'value' => $email,
            ];
            $address = $this->lienhe($content,'Địa chỉ :',16);
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
        $validator = Validator::make($request->all(), [
            'address' => 'required',
            'email' => 'required',

        ], [
            'company.required' => "Vui lòng nhập địa chỉ công ty",
            'email.required' => "Vui lòng nhập email",
        ]);
        if ($validator->fails()) {
            return $this->returnError(new \stdClass, $validator->errors()->first());
        } else {
            $user = DB::connection('mysql_external')->table('users')->where('mobile', $store->sdt)->update(
                array(
                    'email' => $data['email'],
                    'address' => $data['address'],
                    'company' => $data['company'],
    
                )
            );
            return $this->returnSuccess($user,'Cập nhật thành công');

        }
        
       
    }
    public function info(Request $request)
    {
        $store = $request['data_reponse'];
        $user = DB::connection('mysql_external')->table('users')->where('mobile', $store->sdt)->select('name','email','mobile','company','address')
        ->first();
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
