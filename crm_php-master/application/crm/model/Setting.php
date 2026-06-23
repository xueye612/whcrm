<?php
// +----------------------------------------------------------------------
// | Description: CRM相关设置
// +----------------------------------------------------------------------
// | Author:  Michael_xu | gengxiaoxu@5kcrm.com
// +----------------------------------------------------------------------
namespace app\crm\model;

use app\admin\controller\ApiCommon;
use think\Db;
use app\admin\model\Common;
use think\Exception;

class Setting extends Common
{
	/**
     * 为了数据库的整洁，同时又不影响Model和Controller的名称
     * 我们约定每个模块的数据表都加上相同的前缀，比如CRM模块用crm作为数据表前缀
     */
    
	/**
     * 团队成员
     * @author Michael_xu
     * @param types 类型
	 * @param types_id 类型ID数组
	 * @param type  权限 1只读2读写
	 * @param user_id [array] 协作人
	 * @param is_del 1 移除操作 
     * @return
     */  
    public function createTeamData($param)
    {
    	if (!is_array($param['user_id'])) {
    		$param['user_id'] = [intval($param['user_id'])];
    	}
        if (!is_array($param['types_id'])) {
            $param['types_id'] = [intval($param['types_id'])];
        }
        $res = teamUserId($param,$param['types'], $param['types_id'], $param['type'], $param['user_id'], $param['is_del'], $param['owner_user_id']);
		if ($res == '1') {
            //同时关联其他模块(仅限客户模块)
            if (is_array($param['module']) && $param['types'] == 'crm_customer') {
                foreach ($param['module'] as $v) {
                    // 台账、收支不支持CRM团队字段，避免在此处写入
                    if (in_array($v, ['customer_ledger', 'finance_record'])) {
                        continue;
                    }
                    $where = [];
                    $where['customer_id'] = array('in',$param['types_id']);
                    // $where['owner_user_id'] = $param['owner_user_id'];
                    $moduleList = db($v)->where($where)->select();
                    $module_id = '';
                    switch ($v) {
                        case 'crm_contacts' : $module_id = 'contacts_id'; break;
                        case 'crm_business' : $module_id = 'business_id'; break;
                        case 'crm_contract' : $module_id = 'contract_id'; break;
                        case 'crm_leads' : $module_id = 'leads_id'; break;
                        case 'crm_receivables' : $module_id = 'receivables_id'; break;
                    }
                    if (!$module_id) {
                        continue;
                    }
                    foreach ($moduleList as $val) {
                        teamUserId($param,$v, $val[$module_id], $param['type'], $param['user_id'], $param['is_del'], $param['owner_user_id'], 0);
                    }                             
                }
            }
            return true; 
        } else {
        	return $res;
        }    	
    }

    /**
     * 设置回访提醒
     *
     * @param $status
     * @param $day
     * @return bool
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function setVisitDay($status, $day,$userId)
    {
        $status = intval($status);
        $day    = intval($day);

        # 是否开启回访提醒
        if (Db::name('crm_config')->where('name', 'visit_config')->value('id')) {
            Db::name('crm_config')->where('name', 'visit_config')->update(['value' => $status]);
        } else {
            Db::name('crm_config')->insert([
                'name'        => 'visit_config',
                'value'       => $status,
                'description' => '是否开启回访提醒：1开启；0不开启'
            ]);
        }

        # 客户回访提醒天数
        if (!empty($day)) {
            if (Db::name('crm_config')->where('name', 'visit_day')->value('id')) {
                Db::name('crm_config')->where('name', 'visit_day')->update(['value' => $day]);
            } else {
                Db::name('crm_config')->insert([
                    'name'        => 'visit_day',
                    'value'       => $day,
                    'description' => '客户回访提醒天数'
                ]);
            }
        }
        # 系统操作日志
        SystemActionLog($userId, 'crm_config','customer', 1, 'update','客户回访提醒' , '', '','设置了客户回访提醒');
        return true;
    }

    /**
     * 获取回访提醒
     *
     * @return array
     */
    public function getVisitDay()
    {
        $status = Db::name('crm_config')->where('name', 'visit_config')->value('value');
        $day    = Db::name('crm_config')->where('name', 'visit_day')->value('value');

        return ['status' => !empty($status) ? 1 : 0, 'day' => !empty($day) ? intval($day) : 0];
    }

    /**
     * 设置自动编号
     *
     * @param $param
     * @return bool
     */
    public function setNumber($param,$userId)
    {
        $apiCommon = new ApiCommon();

        Db::startTrans();
        try {
            foreach ($param AS $key => $value) {
                # 前端传来的status值为1代表启用，后端保存的status值为0代表启用，这里执行以下取反操作；
                $status = $value['status'] == 1 ? 0 : 1;
                $sort   = 0;

                # 删除未提交过来的数据，先查出某一类型的全部ID数据
                $sequenceIds = Db::name('crm_number_sequence')->where('number_type', $value['number_type'])->column('number_sequence_id');
                # 记录提交的ID，用于删除，没有提交过来的就是要删除的
                $updateIds = [];
                foreach ($value['setting'] AS $k => $v) {
                    $v['status'] = $status;
                    if (!empty($v['sort'])) $sort = $v['sort'];

                    # 编辑
                    if (!empty($v['number_sequence_id'])) {
                        $updateIds[] = $v['number_sequence_id'];
                        Db::name('crm_number_sequence')->update($v);
                    }
                    # 新增
                    if (empty($v['number_sequence_id'])) {
                        $increaseNumber = !empty($v['increase_number']) ? $v['increase_number'] : 1;
                        $reset          = !empty($v['reset'])           ? $v['reset']           : 0;

                        $insertData =[
                            'sort'            => $sort + 1,
                            'type'            => $v['type'],
                            'value'           => $v['value'],
                            'increase_number' => $v['type'] == 3 ? $increaseNumber : null,
                            'reset'           => $v['type'] == 3 ? $reset : null,
                            'create_time'     => time(),
                            'create_user_id'  => $apiCommon->userInfo['id'],
                            'status'          => $v['status'],
                            'number_type'     => $value['number_type']
                        ];

                        Db::name('crm_number_sequence')->insert($insertData);
                    }
                }

                # 删除
                $sequenceIds = array_diff($sequenceIds, $updateIds);
                if (!empty($sequenceIds)) Db::name('crm_number_sequence')->whereIn('number_sequence_id', $sequenceIds)->delete();
            }

            Db::commit();
            # 系统操作日志
            SystemActionLog($userId, 'crm_number_sequence','customer', 1, 'update','编号规则设置' , '', '','设置了编号规则');
            return true;
        } catch (Exception $e) {
            Db::rollback();

            return false;
        }
    }
    
    /**
     * 导航列表
     * @param $param
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/6/22 0022 11:39
     */
    public function appMenuConfig($param)
        {
            $data = db('app_navigation')->where('create_user_id', $param)->value('data');
            $data = unserialize($data);
            return $data?:[];
        }
    
    /**
     * 修改导航信息
     * @param $param
     * @param $userId
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/6/22 0022 14:49
     */
    public function setMenuConfig($param,$userId){
            $data = db('app_navigation')->where('create_user_id', $userId)->value('data');
            $res=[];
            $res['data']=serialize($param);
            if($data){
                $list=db('app_navigation')->where('create_user_id', $userId)->update($res);
            }else{
                $res['create_user_id']=$userId;
                $list=db('app_navigation')->insertGetId($res);
            }
            return $list;
    }
} 		
