<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::post('gatewave', 'App\Http\Controllers\Api\GatewaveController@index')->middleware('CorsApi');
Route::get('checkFollow', 'App\Http\Controllers\Api\GatewaveController@checkFollow')->middleware('CorsApi');
Route::get('city', 'App\Http\Controllers\Api\StoreController@city')->middleware('CorsApi');
Route::get('quan', 'App\Http\Controllers\Api\StoreController@quan')->middleware('CorsApi');
Route::get('phuong', 'App\Http\Controllers\Api\StoreController@phuong')->middleware('CorsApi');
Route::get('getFee', 'App\Http\Controllers\Api\StoreController@getFee')->middleware('CorsApi');

Route::group([  'middleware' => ['CorsApi','CheckStore']], function()
{
    Route::post('log', 'App\Http\Controllers\Api\StoreController@log');
    Route::get('notify', 'App\Http\Controllers\Api\StoreController@notify');
    Route::post('notify', 'App\Http\Controllers\Api\StoreController@notifyPost');
    Route::get('products', 'App\Http\Controllers\Api\ProductController@index');
    Route::get('categories', 'App\Http\Controllers\Api\ProductController@getCategories');

    Route::get('favorite', 'App\Http\Controllers\Api\ProductController@getFavorite');
    Route::post('favorite', 'App\Http\Controllers\Api\ProductController@postFavorite');

    Route::get('watch', 'App\Http\Controllers\Api\ProductController@getWatched');
    Route::post('watch', 'App\Http\Controllers\Api\ProductController@addWatched');
    Route::delete('watch', 'App\Http\Controllers\Api\ProductController@deleteWatched');


    Route::post('check_coupon', 'App\Http\Controllers\Api\ProductController@checkCoupon');
    Route::post('product/review', 'App\Http\Controllers\Api\ProductController@review');
    Route::get('brands', 'App\Http\Controllers\Api\BrandsController@index');
    Route::get('coupons', 'App\Http\Controllers\Api\CouponsController@index');
    Route::get('store', 'App\Http\Controllers\Api\StoreController@index');
    Route::get('blogs', 'App\Http\Controllers\Api\BlogController@index');
    Route::get('orders', 'App\Http\Controllers\Api\OrdersController@index');
    Route::get('campaigns', 'App\Http\Controllers\Api\FlashSaleController@index');
    Route::post('order', 'App\Http\Controllers\Api\OrdersController@store');
    Route::put('update_payment_method', 'App\Http\Controllers\Api\StoreController@update_payment_method');
    Route::post('withdraw', 'App\Http\Controllers\Api\StoreController@withdraw');

    Route::get('country', 'App\Http\Controllers\Api\StoreController@country');
    Route::get('state', 'App\Http\Controllers\Api\StoreController@state');
    Route::get('get_payment_method', 'App\Http\Controllers\Api\StoreController@getPaymentMethod');
    Route::put('user', 'App\Http\Controllers\Api\StoreController@update');
    Route::get('user', 'App\Http\Controllers\Api\StoreController@info');
    Route::get('user_child', 'App\Http\Controllers\Api\StoreController@userChild');
    Route::get('history_withdraw', 'App\Http\Controllers\Api\StoreController@historyWithdraw');
    Route::get('ranks', 'App\Http\Controllers\Api\RanksController@index');
    Route::get('get_point_to_money', 'App\Http\Controllers\Api\RanksController@get_point_to_money');

    Route::post('storeImage', 'App\Http\Controllers\Api\StoreController@storeImage');
    Route::put('register_aff', 'App\Http\Controllers\Api\StoreController@register_aff');
    Route::post('history_share_link', 'App\Http\Controllers\Api\StoreController@history_share_link');

   

});

