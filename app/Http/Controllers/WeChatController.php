<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class WeChatController extends Controller {

    public function __construct() {
        
    }
    private $user;
    /**
     * 处理微信的请求消息
     *
     * @return string
     */
    public function serve() {      
        $app = app('wechat.official_account');       
        $app->server->push(function($message) {     
  
        });
        return $app->server->serve();        
    }

    private function findUserByOpenId($wechatUser) {
        $user = \App\User::where('openId',$wechatUser->id)->first();
        if($user===null){            
            session()->put('wechat_user', $wechatUser);
        }
        return $user;
    }
    
      public function autoLogin() {       
        $userInfo = $this->getEasyWechatSession();  
        $user = $this->findUserByOpenId($userInfo->id);      
        if($user!=null){
            return redirect(route('home'));
        }else{
            return view('wechat.registerEntry');
        }
      
    }
    
    /**
     * index
     *
     * @return wechat homepage
     */
    public function index() {
        $user = Auth::user();

        //test for payment
//        return view('wechat_home');

        if ($user->hasRole('parent')) {
            return redirect()->route('getMyKids');
        } else {
            return redirect()->route('home');
        }
    }

    /**
     * 添加菜单
     */
    public function menu_add() {
        $app = app('wechat.official_account');

        $buttons = [
            [
                'name' => '关于我们',
                'sub_button' => [
                    [
                        'type' => 'view',
                        'name' => '品牌简介',
                        'url' => 'https://pillec.cn/app/public/index.php/brandStory',
                        'sub_button' => []
                    ],
                    [
                        'type' => 'view',
                        'name' => '教育理念',
                        'url' => 'https://pillec.cn/app/public/index.php/concept',
                        'sub_button' => []
                    ],
                    [
                        'type' => 'view',
                        'name' => '校区展示',
                        'url' => 'https://pillec.cn/app/public/index.php/show',
                        'sub_button' => []
                    ]
                ]
            ],
            [
                'name' => '课程体系',
                'sub_button' => [
                    [
                        'type' => 'view',
                        'name' => '托管',
                        'url' => 'https://pillec.cn/app/public/index.php/trust',
                        'sub_button' => []
                    ],
                    [
                        'type' => 'view',
                        'name' => '幼小衔接',
                        'url' => 'https://pillec.cn/app/public/index.php/connect',
                        'sub_button' => []
                    ],
                    [
                        'type' => 'view',
                        'name' => '培训课程',
                        'url' => 'https://pillec.cn/app/public/index.php/training',
                        'sub_button' => []
                    ]
                ]
            ],
            [
                'type' => 'view',
                'name' => '实用工具',
                'url' => 'https://pillec.cn/app/public/index.php/api/auth/login',
                'sub_button' => []
            ]
        ];
        $app->menu->create($buttons);
    }

    /**
     * 删除菜单
     */
    public function menu_destroy() {
        $app = app('wechat.official_account');
        $menu = $app->menu;
        $menu->delete();
    }

    /**
     * 查看微信公众号当前的菜单
     */
    public function menu_current() {
        $app = app('wechat.official_account');
        $menu = $app->menu;
        $menus = $menu->current();
        return ($menus);
    }

    /*
     * 微信支付成功回调
     */

    public function notify() {
        $app = app('wechat.payment');
        $app->handlePaidNotify(function ($message, $fail) {
            // 处理订单等，你的业务逻辑
            if ($message['return_code'] == 'SUCCESS') {
                Log::info('pay successfully');
                Log::info($message);
                return view('backend.wechat_payment.payment_result')->with('msg',$message);
            } else {
                Log::info('pay failed');
                Log::info($message['return_msg']);
                return view('backend.wechat_payment.payment_result')->with('msg',$message);
            }
        });
    }

    /*
     * 
     */

    public function pay(Request $request) {
        Log::info('[' . \Illuminate\Support\Carbon::now() . '] ' . '-------start in pay method-----');
        $app = app('wechat.payment');
        $result = $app->order->unify([
            'body' => 'pillec.cn testing',
            'out_trade_no' => '201905031001',
            'total_fee' => $request->input('total'),
            'trade_type' => 'JSAPI',
            'openid' => 'oaFdG0uEVQvmTENDJSRrjxzwjfrk',
        ]);

        if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
            $result = $app->jssdk->appConfig($result['prepay_id']); //第二次签名
            return [
                'code' => 'success',
                'msg' => $result
            ];
            Log::info ('微信支付签名successful:'.var_export($result ,1));
             return view('backend.wechat_payment.payment_result')->with('msg',$result);
        } else {
            Log::info ('微信支付签名失败:'.var_export($result ,1));
             return view('backend.wechat_payment.payment_result')->with('msg',$result);
        }
        Log::info('[' . \Illuminate\Support\Carbon::now() . '] ' . '-------end in pay method-----');
    }
         //验证消息
    public function api()
     {
         $echoStr = $_GET["echostr"];
         Log::info('From Wechat');
         Log::info($echoStr);
         if($this->checkSignature()){
             echo $echoStr;
             exit;
         }
     }
     //检查签名
     private function checkSignature()
     {
         Log::info('Checking....');
         $signature = $_GET["signature"];
          Log::info('signature:'.$signature);
         $timestamp = $_GET["timestamp"];
          Log::info('timestamp:'.$timestamp);
         $nonce = $_GET["nonce"];
           Log::info('nonce:'.$nonce);
         $token = "dalianxst";//可以随便填，只要和后台添加的一样就行
         $tmpArr = array($token, $timestamp, $nonce);
         sort($tmpArr, SORT_STRING);
         $tmpStr = implode($tmpArr);
         $tmpStr = sha1($tmpStr);
         if($tmpStr == $signature){
             return true;
 
         }else{
             return false;
         }
     }

     
     private function getEasyWechatSession() {
         $user = session('wechat.oauth_user.default');
//        $wechat_user = session('wechat.oauth_user');
        // return $user;
        return $user;
    }
     
     
}
