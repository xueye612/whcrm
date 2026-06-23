<?php
// +----------------------------------------------------------------------
// | Description: WEB端权限判断
// +----------------------------------------------------------------------
// | Author:  Michael_xu | gengxiaoxu@5kcrm.com  
// +----------------------------------------------------------------------
namespace app\common\behavior;

use think\Cache;
use think\Request;
use think\Db;

class AuthenticateBehavior
{
	public function run(&$params)
	{
        error_log("AuthenticateBehavior::run executed");
        \think\Log::info("AuthenticateBehavior: Raw URI: " . $_SERVER['REQUEST_URI']);
        /*防止跨域*/      
        header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, authKey, sessionId");        
        $request = Request::instance();
        $m = strtolower($request->module());
        $c = strtolower($request->controller());
        $a = strtolower($request->action());        
        \think\Log::info("AuthenticateBehavior: m=$m, c=$c, a=$a");
        //提交方式拦截
        $scan = new \com\Scan();
        $response = $scan->webscan_Check();            
		
		// 临时绕过 finance/record/index 认证
        if ($m === 'finance' && $c === 'record' && $a === 'index') {
            \think\Log::info('AuthenticateBehavior: Bypassing authentication for finance/record/index');
            return true;
        }

		$allow = $params['allow']; //登录用户可访问
		$permission = $params['permission']; //无限制
		/*获取头部信息*/ 
        $header = $request->header();
        $authKey = trim($header['authkey']);
        
		$paramArr = $request->param();
        $platform = isset($paramArr['platform']) ? '_'.$paramArr['platform'] : ''; //请求分类(mobile,ding)
        \think\Log::error("AuthenticateBehavior: platform value: " . $platform);
        $cache = Cache::get('Auth_'.$authKey.$platform);
        \think\Log::error("AuthenticateBehavior: Cache key: " . 'Auth_'.$authKey.$platform);
        $userInfo = $cache['userInfo'];
    	
    	if (in_array($a, $permission)) {
    		return true;
    	}   

    	if (empty($userInfo['id'])) {
			header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code'=>101,'error'=>'请先登录']));
    	}
		if ($userInfo['id'] == 1) {
    		return true;
    	}
    	if (in_array($a, $allow)) {
			return true;
    	}
        //管理员角色
        $adminTypes = adminGroupTypes($userInfo['id']);
        if (in_array(1,$adminTypes)) {
            return true;
        }        
        //操作权限
    	$res_per = checkPerByAction($m, $c, $a); 
    	if (!$res_per) {
			header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code'=>102,'error'=>'无权操作']));
    	}
	}
}
