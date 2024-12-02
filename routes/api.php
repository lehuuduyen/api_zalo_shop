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
Route::group([  'middleware' => ['GetData']], function()
{
    Route::get('product/categories', 'App\Http\Controllers\Api\ProductController@getCategories');
    Route::get('product/attribute', 'App\Http\Controllers\Api\ProductController@getAttribute');
    Route::get('products', 'App\Http\Controllers\Api\ProductController@index');
    Route::get('blogs', 'App\Http\Controllers\Api\BlogController@index');
    Route::get('get_payment_method', 'App\Http\Controllers\Api\StoreController@getPaymentMethod');
    Route::get('coupons', 'App\Http\Controllers\Api\CouponsController@index');
    
});

Route::group([  'middleware' => ['CorsApi','GetData']], function()
{
Route::get('getFee', 'App\Http\Controllers\Api\StoreController@getFee')->middleware('CorsApi');
Route::get('list_rotation', 'App\Http\Controllers\Api\StoreController@listRotation')->middleware('CorsApi');
Route::get('get_turn', 'App\Http\Controllers\Api\StoreController@getTurn')->middleware('CorsApi');
Route::post('add_turn', 'App\Http\Controllers\Api\StoreController@addTurn')->middleware('CorsApi');
Route::post('active_rotation', 'App\Http\Controllers\Api\StoreController@activeRotation')->middleware('CorsApi');
Route::get('check_turn_daily', 'App\Http\Controllers\Api\StoreController@checkApiTurnDaily');
Route::get('get_xu', 'App\Http\Controllers\Api\StoreController@getXu');
Route::post('change_xu_to_point', 'App\Http\Controllers\Api\StoreController@changeXuToPoint');

    Route::get('getShare', 'App\Http\Controllers\Api\StoreController@getShare');
    Route::post('log', 'App\Http\Controllers\Api\StoreController@log');
    
    Route::get('check_yeuthich', 'App\Http\Controllers\Api\ProductController@checkFavorite');
    Route::get('yeuthich', 'App\Http\Controllers\Api\ProductController@getFavorite');
    Route::post('yeuthich', 'App\Http\Controllers\Api\ProductController@addFavorite');
    Route::post('check_coupon', 'App\Http\Controllers\Api\ProductController@checkCoupon');
    Route::post('product/review', 'App\Http\Controllers\Api\ProductController@review');
    Route::post('product/reviewProductOrder', 'App\Http\Controllers\Api\ProductController@reviewProductOrder');
    Route::get('reward_policy', 'App\Http\Controllers\Api\ProductController@rewardPolicy');

    Route::get('brands', 'App\Http\Controllers\Api\BrandsController@index');
    Route::get('store', 'App\Http\Controllers\Api\StoreController@index');
    Route::get('orders', 'App\Http\Controllers\Api\OrdersController@index');
    Route::get('campaigns', 'App\Http\Controllers\Api\FlashSaleController@index');
    Route::post('order', 'App\Http\Controllers\Api\OrdersController@store');
    Route::put('update_payment_method', 'App\Http\Controllers\Api\StoreController@update_payment_method');
    Route::post('withdraw', 'App\Http\Controllers\Api\StoreController@withdraw');

    Route::get('country', 'App\Http\Controllers\Api\StoreController@country');
    Route::get('state', 'App\Http\Controllers\Api\StoreController@state');
    Route::put('user', 'App\Http\Controllers\Api\StoreController@update');
    Route::get('user', 'App\Http\Controllers\Api\StoreController@info');
    Route::get('user_child', 'App\Http\Controllers\Api\StoreController@userChild');
    Route::get('history_withdraw', 'App\Http\Controllers\Api\StoreController@historyWithdraw');
    Route::get('ranks', 'App\Http\Controllers\Api\RanksController@index');
    Route::get('get_point_to_money', 'App\Http\Controllers\Api\RanksController@get_point_to_money');

    Route::post('storeImage', 'App\Http\Controllers\Api\StoreController@storeImage');
    Route::put('register_aff', 'App\Http\Controllers\Api\StoreController@register_aff');
    Route::post('history_share_link', 'App\Http\Controllers\Api\StoreController@history_share_link');

    Route::prefix('booking')->group(function () {
        Route::get('categories', 'App\Http\Controllers\Api\ProductController@getCategories');
        Route::get('banner', 'App\Http\Controllers\Api\StoreController@banner');
        Route::get('products', 'App\Http\Controllers\Api\ProductController@index');
    });

});

