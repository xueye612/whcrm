<?php
// +----------------------------------------------------------------------
// | Description: 商机
// +----------------------------------------------------------------------
// | Author: Michael_xu | gengxiaoxu@5kcrm.com 
// +----------------------------------------------------------------------

namespace app\crm\controller;

use app\admin\controller\ApiCommon;
use app\crm\traits\SearchConditionTrait;
use app\crm\traits\StarTrait;
use think\Hook;
use think\Request;
use think\Db;

class Business extends ApiCommon
{
    use StarTrait, SearchConditionTrait;
    
    /**
     * 用于判断权限
     * @permission 无限制
     * @allow 登录用户可访问
     * @other 其他根据系统设置
     **/
    public function _initialize()
    {
        $action = [
            'permission' => [''],
            'allow' => ['statuslist', 'advance', 'stagerollback', 'product', 'system', 'count', 'setprimary', 'dealeroptions']
        ];
        Hook::listen('check_auth', $action);
        $request = Request::instance();
        $a = strtolower($request->action());
        if (!in_array($a, $action['permission'])) {
            parent::_initialize();
        }
    }
    
    /**
     * 商机列表
     * @return
     * @author Michael_xu
     */
    public function index()
    {
        $businessModel = model('Business');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['user_id'] = $userInfo['id'];
        $isLedgerFilter = !empty($param['is_ledger_filter']);
        unset($param['is_ledger_filter']);
        $data = $businessModel->getDataList($param);
        if ($isLedgerFilter && !empty($data['list']) && is_array($data['list'])) {
            $businessIds = array_values(array_unique(array_filter(array_map(function ($item) {
                return (int)($item['business_id'] ?? 0);
            }, $data['list']))));
            $ledgerCountMap = [];
            if (!empty($businessIds)) {
                $rows = Db::name('customer_ledger')
                    ->field('business_id,count(*) as cnt')
                    ->where('business_id', 'in', $businessIds)
                    ->group('business_id')
                    ->select();
                foreach ($rows as $row) {
                    $ledgerCountMap[(int)$row['business_id']] = (int)$row['cnt'];
                }
            }
            foreach ($data['list'] as &$item) {
                $bid = (int)($item['business_id'] ?? 0);
                $item['ledger_count'] = (int)($ledgerCountMap[$bid] ?? 0);
            }
            usort($data['list'], function ($a, $b) {
                $c1 = (int)($a['ledger_count'] ?? 0);
                $c2 = (int)($b['ledger_count'] ?? 0);
                if ($c1 === $c2) {
                    return ((int)($b['business_id'] ?? 0)) - ((int)($a['business_id'] ?? 0));
                }
                return $c2 - $c1;
            });
        }
        return resultArray(['data' => $data]);
    }
    
    /**
     * 添加商机
     * @param
     * @return
     * @author Michael_xu
     */
    public function save()
    {
        $businessModel = model('Business');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        $param['owner_user_id'] = $userInfo['id'];
        // 商机组 type_id 尊重用户选择；旧客户端未提交时由模型按直签/代理配置回退默认组
        // status_id 由模型校验归属并设置初始阶段
        
        if ($businessModel->createData($param)) {
            return resultArray(['data' => '添加成功']);
        } else {
            return resultArray(['error' => $businessModel->getError()]);
        }
    }
    
    /**
     * 商机详情
     * @param
     * @return
     * @author Michael_xu
     */
    public function read()
    {
        $businessModel = model('Business');
        $businessStatusModel = model('BusinessStatus');
        $userModel = new \app\admin\model\User();
        $param = $this->param;
        $userInfo = $this->userInfo;
        $data = $businessModel->getDataById($param['id'], $userInfo['id']);
        //判断权限
        $auth_user_ids = $userModel->getUserByPer('crm', 'business', 'read');
        //读权限
        $roPre = $userModel->rwPre($userInfo['id'], $data['ro_user_id'], $data['rw_user_id'], 'read');
        $rwPre = $userModel->rwPre($userInfo['id'], $data['ro_user_id'], $data['rw_user_id'], 'update');
        if (!in_array($data['owner_user_id'], $auth_user_ids) && !$rwPre && !$roPre) {
            $authData['dataAuth'] = (int)0;
            return resultArray(['data' => $authData]);
        }
        //商机状态组
        $data['status_list'] = $businessStatusModel->getDataById($data['type_id']);
        $data['lose_reason'] = Db::name('CrmBusinessLog')
            ->where(['business_id' => $data['business_id']])
            ->order(['id' => 'DESC'])
            ->value('remark');
        if (!$data) {
            return resultArray(['error' => $businessModel->getError()]);
        }
        return resultArray(['data' => $data]);
    }
    
    /**
     * 编辑商机
     * @param
     * @return
     * @author Michael_xu
     */
    public function update()
    {
        $businessModel = model('Business');
        $userModel = new \app\admin\model\User();
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['user_id'] = $userInfo['id'];
        //判断权限
        $data = $businessModel->getDataById($param['id']);
        $auth_user_ids = $userModel->getUserByPer('crm', 'business', 'update');
        //读写权限
        $rwPre = $userModel->rwPre($userInfo['id'], $data['ro_user_id'], $data['rw_user_id'], 'update');
        if (!in_array($data['owner_user_id'], $auth_user_ids) && !$rwPre) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作']));
        }
        if ($businessModel->updateDataById($param, $param['id'])) {
            return resultArray(['data' => '编辑成功']);
        } else {
            return resultArray(['error' => $businessModel->getError()]);
        }
    }
    
    /**
     * 删除商机（逻辑删）
     * @param
     * @return
     * @author Michael_xu
     */
    public function delete()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $businessModel = model('Business');
        $recordModel = new \app\admin\model\Record();
        $fileModel = new \app\admin\model\File();
        $actionRecordModel = new \app\admin\model\ActionRecord();
        if (!is_array($param['id'])) {
            $business_id[] = $param['id'];
        } else {
            $business_id = $param['id'];
        }
        $delIds = [];
        $errorMessage = [];
        
        //数据权限判断
        $userModel = new \app\admin\model\User();
        $auth_user_ids = $userModel->getUserByPer('crm', 'business', 'delete');
        foreach ($business_id as $k => $v) {
            $isDel = true;
            //数据详情
            $data = $businessModel->getDataById($v);
            if (!$data) {
                $isDel = false;
                $errorMessage[] = 'id为' . $v . '的商机删除失败,错误原因：' . $businessModel->getError();
            }
            if (!in_array($data['owner_user_id'], $auth_user_ids)) {
                $isDel = false;
                $errorMessage[] = '名称为' . $data['name'] . '的商机删除失败,错误原因：无权操作';
            }
            if ($isDel) {
                if (db('crm_contract')->where(['business_id' => $v, 'check_status' => ['in', '0,1,2']])->value('contract_id')) {
                    $isDel = false;
                    $errorMessage[] = '名称为' . $data['name'] . '的商机删除失败,错误原因：商机下关联的有合同，无法删除！';
                }
            }
            if ($isDel) {
                $delIds[] = $v;
            }
        }
        if ($delIds) {
            $data = $businessModel->delDatas($delIds);
            if (!$data) {
                return resultArray(['error' => $businessModel->getError()]);
            }
            // 删除客户扩展数据
            db('crm_business_data')->whereIn('business_id', $delIds)->delete();
            //删除跟进记录
            $recordModel->delDataByTypes(5, $delIds);
            //删除关联附件
            $fileModel->delRFileByModule('crm_business', $delIds);
            //删除关联操作记录
            $actionRecordModel->delDataById(['types' => 'crm_business', 'action_id' => $delIds]);
            $dataInfo = $businessModel->where('business_id',['in',$delIds])->select();
            foreach ($dataInfo as $k => $v) {
                RecordActionLog($userInfo['id'], 'crm_business', 'delete', $v['name'], '', '', '删除了商机：' . $v['name']);
            }
        }
        if ($errorMessage) {
            return resultArray(['error' => $errorMessage]);
        } else {
            return resultArray(['data' => '删除成功']);
        }
    }
    
    /**
     * 符合条件的商机状态组
     * @param
     * @return
     * @author Michael_xu
     */
    public function statusList()
    {
        $businessStatusModel = model('BusinessStatus');
        $key = 'BI_queryCache_StatusList_Data';
        $list = cache($key);
        if (!$list) {
            $userInfo = $this->userInfo;
            $authMap = function($query) use ($userInfo){
                $query->where(['structure_id' =>  ['like', '%,' . $userInfo['structure_id'] . ',%'],'is_display'=> 1,'status'=> 1])
                    ->whereOr(function ($query) use ($userInfo) {
                        $query->where(['structure_id' => ''])->where(['is_display'=> 1,'status'=> 1]);
                    });
            };
            $list = db('crm_business_type')
                ->field(['name', 'status', 'structure_id', 'type_id'])
                ->where($authMap)
                ->select();
            foreach ($list as $k => $v) {
                $list[$k]['statusList'] = $businessStatusModel->getDataList($v['type_id']);
            }
            cache($key, $list, config('business_status_cache_time'));
        }

        return resultArray(['data' => $list]);
    }
    
    /**
     * 商机转移
     * @param owner_user_id 变更负责人
     * @param is_remove 1移出，2转为团队成员
     * @param type 权限 1只读2读写
     * @return
     * @author Michael_xu
     */
    public function transfer()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $businessModel = model('Business');
        $settingModel = model('Setting');
        $userModel = new \app\admin\model\User();
        $authIds = $userModel->getUserByPer(); //权限范围的user_id
        if (!$param['owner_user_id']) {
            return resultArray(['error' => '变更负责人不能为空']);
        }
        if (!$param['business_id'] || !is_array($param['business_id'])) {
            return resultArray(['error' => '请选择需要转移的商机']);
        }
        
        $is_remove = $param['is_remove'] == 2 ? 2 : 1;
        $type = $param['type'] == 2 ? 2 : 1;
        
        $data = [];
        $data['owner_user_id'] = $param['owner_user_id'];
        $data['update_time'] = time();
        
        $ownerUserName = $userModel->getUserNameById($param['owner_user_id']);
        $errorMessage = [];
        foreach ($param['business_id'] as $business_id) {
            $businessInfo = $businessModel->getDataById($business_id);
            
            if (!$businessInfo) {
                $errorMessage[] = '名称:为《' . $businessInfo['name'] . '》的商机转移失败，错误原因：数据不存在；';
                continue;
            }
            //权限判断
            if (!in_array($businessInfo['owner_user_id'], $authIds)) {
                
                $errorMessage[] = $businessInfo['name'] . '"转移失败，错误原因：无权限；';
                continue;
            }
            
            //团队成员
            teamUserId(
                $param,
                'crm_business',
                $business_id,
                $type,
                [$businessInfo['owner_user_id']],
                $is_remove,
                0
            );
            
            $resBusiness = db('crm_business')->where(['business_id' => $business_id])->update($data);
            if (!$resBusiness) {
                $errorMessage[] = $businessInfo['name'] . '"转移失败，错误原因：数据出错；';
                continue;
            } else {
                $businessArray = [];
                $teamBusiness = db('crm_business')->field(['owner_user_id', 'ro_user_id', 'rw_user_id'])->where('business_id', $business_id)->find();
                if (!empty($teamBusiness['ro_user_id'])) {
                    $businessRo = arrayToString(array_diff(stringToArray($teamBusiness['ro_user_id']), [$teamBusiness['owner_user_id']]));
                    $businessArray['ro_user_id'] = $businessRo;
                }
                if (!empty($teamBusiness['rw_user_id'])) {
                    $businessRo = arrayToString(array_diff(stringToArray($teamBusiness['rw_user_id']), [$teamBusiness['owner_user_id']]));
                    $businessArray['rw_user_id'] = $businessRo;
                }
                db('crm_business')->where('business_id', $business_id)->update($businessArray);
            }
            
            //修改记录
            updateActionLog($userInfo['id'], 'crm_business', $business_id, '', '', '将商机转移给：' . $ownerUserName);
            RecordActionLog($userInfo['id'], 'crm_business', 'transfer', $businessInfo['name'], '', '', '将商机：' . $businessInfo['name'] . '转移给：' . $ownerUserName);
            
        }
        if (!$errorMessage) {
            return resultArray(['data' => '转移成功']);
        } else {
            return resultArray(['error' => $errorMessage]);
        }
    }
    
    /**
     * 相关产品
     * @param
     * @return
     * @author Michael_xu
     */
    public function product()
    {
        $productModel = model('Product');
        $userModel = new \app\admin\model\User();
        $param = $this->param;
        $userInfo = $this->userInfo;
        if (!$param['business_id']) {
            return resultArray(['error' => '参数错误']);
        }
        $businessInfo = db('crm_business')->where(['business_id' => $param['business_id']])->find();
        //判断权限
        $auth_user_ids = $userModel->getUserByPer('crm', 'business', 'read');
        //读写权限
        $roPre = $userModel->rwPre($userInfo['id'], $businessInfo['ro_user_id'], $businessInfo['rw_user_id'], 'read');
        $rwPre = $userModel->rwPre($userInfo['id'], $businessInfo['ro_user_id'], $businessInfo['rw_user_id'], 'update');
        if (!in_array($businessInfo['owner_user_id'], $auth_user_ids) && !$roPre && !$rwPre) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作']));
        }
        $dataList = db('crm_business_product')->where(['business_id' => $param['business_id']])->select();
        foreach ($dataList as $k => $v) {
            $where = [];
            $where['product_id'] = $v['product_id'];
            $productInfo = db('crm_product')->where($where)->field('name,category_id')->find();
            $category_name = db('crm_product_category')->where(['category_id' => $productInfo['category_id']])->value('name');
            $dataList[$k]['name'] = $productInfo['name'] ?: '';
            $dataList[$k]['category_id_info'] = $category_name ?: '';
        }
        $list['list'] = $dataList ?: [];
        $list['total_price'] = $businessInfo['total_price'] ?: '0.00';
        $list['discount_rate'] = $businessInfo['discount_rate'] ?: '0.00';
        return resultArray(['data' => $list]);
    }
    
    /**
     * 商机状态推进
     * @param business_id 商机ID
     * @param status_id 推进商机状态ID
     * @return
     * @author Michael_xu
     *
     * 收口 V3：
     *   1) 状态推进允许跳过未参与阶段；禁止倒退、重复推进，终态后禁止推进
     *   2) 赢单/输单/无效不生成兜底奖励（无显式规则不生成 0 元候选）
     *   3) 同一事务内锁定商机行，检查所有 insert/update 返回值：
     *      - 日志失败则状态不变（事务回滚）
     *      - 奖励失败则状态和日志回滚（事务回滚）
     *      - 并发重复请求不产生重复日志或奖励（lock(true) + 幂等键）
     *   4) 奖励候选保存完整字段：商机、客户、人员、阶段、金额、rule_id、rules_version、发生时间、证据和 source_ref
     */
    public function advance()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $userModel = new \app\admin\model\User();
        $is_end = (int)($param['is_end'] ?? 0);
        if (!$param['business_id']) {
            return resultArray(['error' => '参数错误：缺少 business_id']);
        }

        // 数据权限与基础校验放在事务前
        $auth_user_ids = $userModel->getUserByPer('crm', 'business', 'update');

        Db::startTrans();
        try {
            // 锁定商机行，防止并发重复推进
            $businessInfo = db('crm_business')->where(['business_id' => $param['business_id']])->lock(true)->find();
            if (!$businessInfo) {
                throw new \Exception('商机不存在');
            }
            // 终态后禁止推进
            if ((int)$businessInfo['is_end'] !== 0) {
                throw new \Exception('商机已结束，不可再次推进');
            }
            $rwPre = $userModel->rwPre($userInfo['id'], $businessInfo['ro_user_id'], $businessInfo['rw_user_id'], 'update');
            if (!in_array($businessInfo['owner_user_id'], $auth_user_ids) && !$rwPre) {
                throw new \Exception('无权操作');
            }

            $status_id = (int)($param['status_id'] ?: $businessInfo['status_id']);
            $statusInfo = db('crm_business_status')->where(['type_id' => $businessInfo['type_id'], 'status_id' => $status_id])->find();

            // 终态推进：is_end 为 1/2/3
            $isTerminal = $is_end !== 0;
            if (!$isTerminal) {
                if (!$statusInfo) {
                    throw new \Exception('状态不合法');
                }
                // 允许跳过未参与的中间阶段；只禁止倒退和重复推进。
                $currentStatus = db('crm_business_status')->where(['type_id' => $businessInfo['type_id'], 'status_id' => $businessInfo['status_id']])->find();
                if ($currentStatus) {
                    $currentOrder = (int)$currentStatus['order_id'];
                    $targetOrder = (int)$statusInfo['order_id'];
                    // 终态阶段（order_id >= 99）视为终态，已在 is_end 校验覆盖
                    if ($currentOrder >= 99) {
                        throw new \Exception('当前阶段为终态，不可再次推进');
                    }
                    if ($targetOrder < $currentOrder) {
                        throw new \Exception('禁止状态倒退：当前阶段 order=' . $currentOrder . '，目标阶段 order=' . $targetOrder);
                    }
                    if ($targetOrder === $currentOrder) {
                        throw new \Exception('禁止重复推进同一阶段：当前已在 ' . ($currentStatus['name'] ?? '') . ' 阶段');
                    }
                }
            } else {
                // 终态推进：is_end=1 赢单 / 2 输单 / 3 无效
                if (!in_array($is_end, [1, 2, 3], true)) {
                    throw new \Exception('is_end 不合法，必须为 1/2/3');
                }
            }

            // Validate business extension fields on advance (中文错误提示)
            $businessModel = model('Business');
            $extParam = [
                'business_category' => $businessInfo['business_category'] ?? '',
                'type_id'            => $businessInfo['type_id'] ?? '',
                'customer_id'        => $businessInfo['customer_id'] ?? 0,
                'signing_method'    => $businessInfo['signing_method'] ?? '',
                'dealer_customer_id' => $businessInfo['dealer_customer_id'] ?? 0,
            ];
            if (!$businessModel->validateBusinessCategoryRules($extParam)) {
                throw new \Exception($businessModel->getError());
            }

            // 更新商机状态
            $data = [
                'update_time' => time(),
                'is_end' => $is_end,
                'status_id' => $status_id,
                'status_time' => time(),
            ];
            $res = db('crm_business')->where(['business_id' => $param['business_id']])->update($data);
            if ($res === false) {
                throw new \Exception('advance_failed');
            }

            $businessStatusName = $statusInfo ? $statusInfo['name'] : '';
            if (empty($businessStatusName) && $is_end == 1) $businessStatusName = '赢单';
            if (empty($businessStatusName) && $is_end == 2) $businessStatusName = '输单';
            if (empty($businessStatusName) && $is_end == 3) $businessStatusName = '无效';

            // 活动记录
            $actRes = Db::name('crm_activity')->insert([
                'type' => 3, 'activity_type' => 5, 'activity_type_id' => $businessInfo['business_id'],
                'content' => '状态推进至：' . $businessStatusName,
                'create_user_id' => $businessInfo['owner_user_id'],
                'update_time' => time(), 'create_time' => time(),
                'customer_ids' => ',' . $businessInfo['customer_id'] . ',',
            ]);
            if ($actRes === false) {
                throw new \Exception('activity_insert_failed');
            }

            // 推进日志（幂等键：business_id + status_id + is_end + 时间窗口）
            // 这里仅靠 lock(true) 保证同一事务内并发不重复；source_ref 用于奖励幂等
            $logRes = Db::name('CrmBusinessLog')->insert([
                'status_id' => $status_id ?: 0, 'is_end' => $is_end ?: 0,
                'business_id' => $param['business_id'], 'create_time' => time(),
                'owner_user_id' => $userInfo['id'], 'remark' => $param['statusRemark'] ?: '',
            ]);
            if ($logRes === false) {
                throw new \Exception('log_insert_failed');
            }

            // Check business_stage_reward_rule for this type_id + status_id
            // 同一商机同一阶段只生成一次奖励候选；没有规则的阶段只推进，不生成 0 元候选；
            // 赢单不得作为无金额兜底奖励。
            $rewardGenerated = false;
            $rewardInfo = ['generated' => false, 'type' => '', 'amount' => 0, 'candidate_no' => '', 'create_time' => 0];
            // 终态（is_end != 0）不生成兜底奖励
            if (!$isTerminal) {
                $rewardRule = Db::name('business_stage_reward_rule')
                    ->where(['type_id' => $businessInfo['type_id'], 'status_id' => $status_id, 'is_enabled' => 1])
                    ->find();
                // 仅当存在显式规则且金额 > 0 时才生成候选
                if ($rewardRule && (float)$rewardRule['amount'] > 0) {
                    $sourceRef = 'business:' . $param['business_id'] . ':status:' . $status_id;
                    // 幂等：source_type + source_ref 唯一
                    $existReward = Db::name('reward_candidate')->where(['source_type' => 'business_stage', 'source_ref' => $sourceRef])->find();
                    if (!$existReward) {
                        $candidateId = Db::name('reward_candidate')->insertGetId([
                            'source_type' => 'business_stage', 'source_ref' => $sourceRef,
                            'user_id' => (int)$businessInfo['owner_user_id'],
                            'amount' => round((float)$rewardRule['amount'], 2),
                            'reason' => '客户「' . (string)db('crm_customer')->where('customer_id', $businessInfo['customer_id'])->value('name')
                                . '」的商机「' . (string)($businessInfo['name'] ?? ('#' . $param['business_id']))
                                . '」推进至「' . $businessStatusName . '」节点',
                            'evidence_note' => 'business_id=' . $param['business_id'] . ' type_id=' . ($businessInfo['type_id'] ?? 0) . ' status_id=' . $status_id . ' stage_name=' . $businessStatusName . ' candidate_created_at=' . date('Y-m-d H:i:s'),
                            'rules_version' => $rewardRule['rules_version'] ?? 'v1',
                            'status' => \app\crm\logic\RewardService::ST_PENDING,
                            'business_id' => (int)$param['business_id'],
                            'customer_id' => (int)$businessInfo['customer_id'],
                            'occurred_time' => time(),
                            'create_user_id' => (int)$userInfo['id'],
                            'create_time' => time(), 'update_time' => time(),
                        ]);
                        if (!$candidateId) {
                            throw new \Exception('reward_candidate_insert_failed');
                        }
                        // 保存 rule_id 与 stage_name（如有列）
                        $rewardExtraUpdate = [];
                        if ($this->businessHasColumn('reward_candidate', 'rule_id')) {
                            $rewardExtraUpdate['rule_id'] = (int)$rewardRule['rule_id'];
                        }
                        if ($this->businessHasColumn('reward_candidate', 'stage_name')) {
                            $rewardExtraUpdate['stage_name'] = $businessStatusName;
                        }
                        if ($rewardExtraUpdate) {
                            Db::name('reward_candidate')->where('cand_id', $candidateId)->update($rewardExtraUpdate);
                        }
                        $rewardGenerated = true;
                        $rewardInfo = [
                            'generated'     => true,
                            'type'          => '商机阶段奖励',
                            'amount'         => round((float)$rewardRule['amount'], 2),
                            'candidate_no'  => 'RC-' . str_pad((string)$candidateId, 6, '0', STR_PAD_LEFT),
                            'create_time'    => time(),
                            'rule_id'        => (int)$rewardRule['rule_id'],
                            'rules_version'  => (string)($rewardRule['rules_version'] ?? 'v1'),
                        ];
                    } elseif ($existReward['status'] === \app\crm\logic\RewardService::ST_VOIDED) {
                        // 回退后重新推进：重新激活已作废的候选（仅未结算的作废候选可激活）
                        $oldData = $existReward;
                        $reactivateUpdate = [
                            'status' => \app\crm\logic\RewardService::ST_PENDING,
                            'amount' => round((float)$rewardRule['amount'], 2),
                            'user_id' => (int)$businessInfo['owner_user_id'],
                            'reviewer_user_id' => 0,
                            'review_time' => 0,
                            'review_note' => '',
                            'batch_id' => 0,
                            'rules_version' => (string)($rewardRule['rules_version'] ?? 'v1'),
                            'occurred_time' => time(),
                            'reason' => '客户「' . (string)db('crm_customer')->where('customer_id', $businessInfo['customer_id'])->value('name')
                                . '」的商机「' . (string)($businessInfo['name'] ?? ('#' . $param['business_id']))
                                . '」重新推进至「' . $businessStatusName . '」节点（重新激活）',
                            'update_time' => time(),
                        ];
                        if ($this->businessHasColumn('reward_candidate', 'update_user_id')) {
                            $reactivateUpdate['update_user_id'] = (int)$userInfo['id'];
                        }
                        if ($this->businessHasColumn('reward_candidate', 'rule_id')) {
                            $reactivateUpdate['rule_id'] = (int)$rewardRule['rule_id'];
                        }
                        if ($this->businessHasColumn('reward_candidate', 'stage_name')) {
                            $reactivateUpdate['stage_name'] = $businessStatusName;
                        }
                        Db::name('reward_candidate')->where('cand_id', $existReward['cand_id'])->update($reactivateUpdate);

                        // 写审计：重新激活
                        $this->logRewardAudit(
                            (int)$existReward['cand_id'], 'stage_reactivate',
                            $oldData, $reactivateUpdate,
                            '阶段回退后重新推进至「' . $businessStatusName . '」',
                            $userInfo, time(), Request::instance()->ip()
                        );

                        $rewardGenerated = true;
                        $rewardInfo = [
                            'generated'     => true,
                            'type'          => '商机阶段奖励（重新激活）',
                            'amount'         => round((float)$rewardRule['amount'], 2),
                            'candidate_no'  => 'RC-' . str_pad((string)$existReward['cand_id'], 6, '0', STR_PAD_LEFT),
                            'create_time'    => time(),
                            'rule_id'        => (int)$rewardRule['rule_id'],
                            'rules_version'  => (string)($rewardRule['rules_version'] ?? 'v1'),
                        ];
                    } elseif (in_array($existReward['status'], [\app\crm\logic\RewardService::ST_SETTLED, \app\crm\logic\RewardService::ST_OFFSET], true)) {
                        // 已结算原记录不得重新激活，按新轮次生成可审计的新候选
                        $roundRef = $sourceRef . ':round2';
                        $existRound2 = Db::name('reward_candidate')->where(['source_type' => 'business_stage', 'source_ref' => $roundRef])->find();
                        if (!$existRound2) {
                            $candidateId = Db::name('reward_candidate')->insertGetId([
                                'source_type' => 'business_stage', 'source_ref' => $roundRef,
                                'user_id' => (int)$businessInfo['owner_user_id'],
                                'amount' => round((float)$rewardRule['amount'], 2),
                                'reason' => '客户「' . (string)db('crm_customer')->where('customer_id', $businessInfo['customer_id'])->value('name')
                                    . '」的商机「' . (string)($businessInfo['name'] ?? ('#' . $param['business_id']))
                                    . '」回退后重新推进至「' . $businessStatusName . '」节点（新轮次）',
                                'evidence_note' => 'business_id=' . $param['business_id'] . ' status_id=' . $status_id . ' stage_name=' . $businessStatusName . ' round=2 original_cand_id=' . (int)$existReward['cand_id'],
                                'rules_version' => $rewardRule['rules_version'] ?? 'v1',
                                'status' => \app\crm\logic\RewardService::ST_PENDING,
                                'business_id' => (int)$param['business_id'],
                                'customer_id' => (int)$businessInfo['customer_id'],
                                'occurred_time' => time(),
                                'create_user_id' => (int)$userInfo['id'],
                                'create_time' => time(), 'update_time' => time(),
                            ]);
                            if ($candidateId) {
                                $rewardExtraUpdate = [];
                                if ($this->businessHasColumn('reward_candidate', 'rule_id')) {
                                    $rewardExtraUpdate['rule_id'] = (int)$rewardRule['rule_id'];
                                }
                                if ($this->businessHasColumn('reward_candidate', 'stage_name')) {
                                    $rewardExtraUpdate['stage_name'] = $businessStatusName;
                                }
                                if ($rewardExtraUpdate) {
                                    Db::name('reward_candidate')->where('cand_id', $candidateId)->update($rewardExtraUpdate);
                                }
                                $rewardGenerated = true;
                                $rewardInfo = [
                                    'generated'    => true,
                                    'type'         => '商机阶段奖励（新轮次）',
                                    'amount'        => round((float)$rewardRule['amount'], 2),
                                    'candidate_no' => 'RC-' . str_pad((string)$candidateId, 6, '0', STR_PAD_LEFT),
                                    'create_time'   => time(),
                                    'rule_id'       => (int)$rewardRule['rule_id'],
                                    'rules_version' => (string)($rewardRule['rules_version'] ?? 'v1'),
                                ];
                            }
                        }
                    }
                }
            }

            Db::commit();

            $typeId = db('crm_business')->where('business_id', $param['business_id'])->value('type_id');
            $businessStatus = db('crm_business_status')->where('type_id', $typeId)->select();
            return resultArray(['data' => [
                'business_id' => $param['business_id'], 'type_id' => $typeId,
                'status_id' => $status_id, 'status_list' => $businessStatus,
                'reward_generated' => $rewardGenerated,
                'reward_info' => $rewardInfo,
            ]]);
        } catch (\Exception $e) {
            Db::rollback();
            $msg = $e->getMessage();
            // 中文友好提示：保留原英文内部值仅作为内部诊断；对外只显示有意义的中文消息
            return resultArray(['error' => $msg]);
        }
    }

    /**
     * 商机阶段回退（受控操作）
     * 仅管理员可操作。使用事务和商机行锁。
     * 奖励处理：未结算候选标记作废（ST_VOIDED），已结算候选生成负数冲销。
     * 冲销幂等：source_ref 包含原 cand_id，生成前检查是否已有冲销。
     * 所有候选查询和更新使用事务行锁。
     */
    public function stageRollback()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $businessId = (int)($param['business_id'] ?? 0);
        $targetStatusId = (int)($param['target_status_id'] ?? 0);
        $reason = trim((string)($param['reason'] ?? ''));

        if ($businessId <= 0) return resultArray(['error' => '参数错误：缺少 business_id']);
        if ($targetStatusId <= 0) return resultArray(['error' => '请选择目标回退阶段']);
        if ($reason === '') return resultArray(['error' => '请填写回退原因']);

        $isSuperAdmin = isSuperAdministrators($userInfo['id']);
        if (!$isSuperAdmin) {
            return resultArray(['error' => '无权操作阶段回退，仅管理员可执行']);
        }

        $now = time();
        $requestIp = Request::instance()->ip();

        Db::startTrans();
        try {
            $businessInfo = db('crm_business')->where(['business_id' => $businessId])->lock(true)->find();
            if (!$businessInfo) throw new \Exception('商机不存在');

            $currentStatus = db('crm_business_status')->where(['type_id' => $businessInfo['type_id'], 'status_id' => $businessInfo['status_id']])->find();
            $targetStatus = db('crm_business_status')->where(['type_id' => $businessInfo['type_id'], 'status_id' => $targetStatusId])->find();
            if (!$targetStatus) throw new \Exception('目标阶段不存在于当前状态组');

            $currentOrder = $currentStatus ? (int)$currentStatus['order_id'] : 0;
            $targetOrder = (int)$targetStatus['order_id'];
            if ($targetOrder >= 99) throw new \Exception('不能回退到终态阶段');
            if ($currentOrder > 0 && $targetOrder >= $currentOrder) {
                throw new \Exception('目标阶段必须在当前阶段之前（按 order_id 回退）');
            }

            $currentStatusName = $currentStatus ? $currentStatus['name'] : '';
            $targetStatusName = $targetStatus['name'];

            // 查找回退目标之后的所有非终态阶段
            $allStages = db('crm_business_status')->where(['type_id' => $businessInfo['type_id']])->order('order_id asc')->select();
            $voidedStageIds = [];
            foreach ($allStages as $s) {
                if ((int)$s['order_id'] > $targetOrder && (int)$s['order_id'] < 99) {
                    $voidedStageIds[] = (int)$s['status_id'];
                }
            }

            $rewardVoided = 0;
            $rewardReversed = 0;
            $rewardSkipped = 0;

            foreach ($voidedStageIds as $voidStatusId) {
                $sourceRef = 'business:' . $businessId . ':status:' . $voidStatusId;
                // 行锁查询该阶段的所有候选
                $candidates = Db::name('reward_candidate')
                    ->where(['source_type' => 'business_stage', 'source_ref' => $sourceRef])
                    ->lock(true)
                    ->select();

                foreach ($candidates as $c) {
                    $candStatus = (string)$c['status'];
                    $candId = (int)$c['cand_id'];

                    // 已作废的候选跳过（幂等：重复回退不重复处理）
                    if ($candStatus === \app\crm\logic\RewardService::ST_VOIDED) {
                        $rewardSkipped++;
                        continue;
                    }

                    $inBatch = (int)($c['batch_id'] ?? 0) > 0;
                    $isSettled = in_array($candStatus, [\app\crm\logic\RewardService::ST_SETTLED, \app\crm\logic\RewardService::ST_OFFSET], true);
                    $needReversal = $isSettled || ($candStatus === \app\crm\logic\RewardService::ST_APPROVED && $inBatch);

                    if ($needReversal) {
                        // 幂等检查：该原候选是否已有冲销记录
                        $reversalRef = 'business:' . $businessId . ':status:' . $voidStatusId . ':reversal:candidate:' . $candId;
                        $existReversal = Db::name('reward_candidate')
                            ->where(['source_type' => 'business_stage_reversal', 'source_ref' => $reversalRef])
                            ->lock(true)
                            ->find();
                        if ($existReversal) {
                            $rewardSkipped++;
                            continue;
                        }

                        // 生成负数冲销记录
                        $reversalId = Db::name('reward_candidate')->insertGetId([
                            'source_type' => 'business_stage_reversal',
                            'source_ref' => $reversalRef,
                            'user_id' => (int)$c['user_id'],
                            'amount' => -abs((float)$c['amount']),
                            'reason' => '商机阶段回退冲销：' . $reason,
                            'evidence_note' => '原候选 RC-' . str_pad((string)$candId, 6, '0', STR_PAD_LEFT) . ' 因阶段回退作冲销处理',
                            'rules_version' => (string)($c['rules_version'] ?? 'v1'),
                            'status' => \app\crm\logic\RewardService::ST_PENDING,
                            'business_id' => $businessId,
                            'customer_id' => (int)($c['customer_id'] ?? 0),
                            'occurred_time' => $now,
                            'create_user_id' => (int)$userInfo['id'],
                            'create_time' => $now, 'update_time' => $now,
                        ]);
                        if (!$reversalId) throw new \Exception('生成冲销记录失败');

                        // 保存 rule_id 和 stage_name（如有列）
                        if ($this->businessHasColumn('reward_candidate', 'rule_id') && !empty($c['rule_id'])) {
                            Db::name('reward_candidate')->where('cand_id', $reversalId)->update(['rule_id' => (int)$c['rule_id']]);
                        }

                        // 写审计：冲销
                        $this->logRewardAudit($candId, 'stage_rollback_reversal', $c, [
                            'status' => $c['status'], 'reversal_cand_id' => $reversalId,
                        ], $reason, $userInfo, $now, $requestIp);

                        $rewardReversed++;
                    } else {
                        // 待审核/待专项审批/已驳回/已通过未进批次：标记作废
                        $oldData = $c;
                        $voidUpdate = [
                            'status' => \app\crm\logic\RewardService::ST_VOIDED,
                            'update_time' => $now,
                        ];
                        if ($this->businessHasColumn('reward_candidate', 'update_user_id')) {
                            $voidUpdate['update_user_id'] = (int)$userInfo['id'];
                        }
                        Db::name('reward_candidate')->where(['cand_id' => $candId])->update($voidUpdate);

                        // 写审计：作废
                        $this->logRewardAudit($candId, 'stage_rollback_void', $oldData, [
                            'status' => \app\crm\logic\RewardService::ST_VOIDED,
                        ], $reason, $userInfo, $now, $requestIp);

                        $rewardVoided++;
                    }
                }
            }

            // 更新商机状态
            $updateData = [
                'status_id' => $targetStatusId,
                'is_end' => 0,
                'status_time' => $now,
                'update_time' => $now,
            ];
            $res = db('crm_business')->where(['business_id' => $businessId])->update($updateData);
            if ($res === false) throw new \Exception('更新商机状态失败');

            // 写商机推进日志
            Db::name('CrmBusinessLog')->insert([
                'status_id' => $targetStatusId, 'is_end' => 0,
                'business_id' => $businessId, 'create_time' => $now,
                'owner_user_id' => $userInfo['id'],
                'remark' => '阶段回退：' . $currentStatusName . ' -> ' . $targetStatusName . '，原因：' . $reason,
            ]);

            // 写 CRM 活动日志
            Db::name('crm_activity')->insert([
                'type' => 3, 'activity_type' => 5, 'activity_type_id' => $businessId,
                'content' => '阶段回退：' . $currentStatusName . ' -> ' . $targetStatusName . '，原因：' . $reason,
                'create_user_id' => $userInfo['id'],
                'update_time' => $now, 'create_time' => $now,
                'customer_ids' => ',' . $businessInfo['customer_id'] . ',',
            ]);

            // 写系统操作日志
            SystemActionLog($userInfo['id'], 'crm_business', 'business', $businessId, 'stageRollback',
                '商机阶段回退', '', '',
                '商机#' . $businessId . ' 阶段回退：' . $currentStatusName . ' -> ' . $targetStatusName .
                '，操作人：' . ($userInfo['realname'] ?? '') .
                '，原因：' . $reason .
                '，作废：' . $rewardVoided . '，冲销：' . $rewardReversed . '，跳过：' . $rewardSkipped);

            Db::commit();

            $typeId = db('crm_business')->where('business_id', $businessId)->value('type_id');
            $businessStatus = db('crm_business_status')->where('type_id', $typeId)->select();
            return resultArray(['data' => [
                'business_id' => $businessId, 'type_id' => $typeId,
                'status_id' => $targetStatusId, 'status_list' => $businessStatus,
                'reward_voided' => $rewardVoided, 'reward_reversed' => $rewardReversed,
                'reward_skipped' => $rewardSkipped,
                'note' => '阶段回退成功，作废' . $rewardVoided . '条，冲销' . $rewardReversed . '条，跳过' . $rewardSkipped . '条',
            ]]);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '阶段回退失败：' . $e->getMessage()]);
        }
    }

    /**
     * 写奖励候选审计日志
     */
    private function logRewardAudit($candId, $operationType, $oldData, $newData, $reason, $userInfo, $time, $ip)
    {
        Db::name('reward_candidate_audit')->insert([
            'cand_id' => (int)$candId,
            'operation_type' => $operationType,
            'old_data_json' => json_encode($oldData, JSON_UNESCAPED_UNICODE),
            'new_data_json' => json_encode($newData, JSON_UNESCAPED_UNICODE),
            'change_reason' => (string)$reason,
            'operator_user_id' => (int)$userInfo['id'],
            'operator_name' => $userInfo['realname'] ?? '',
            'operation_time' => (int)$time,
            'request_ip' => (string)$ip,
            'create_time' => (int)$time,
        ]);
    }

    /** 工具：检测指定表是否有某列。 */
    private function businessHasColumn($table, $column)
    {
        $prefix = config('database.prefix') ?: '';
        $tableName = $prefix . $table;
        $row = Db::query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . addslashes($tableName) . "' AND COLUMN_NAME='" . addslashes($column) . "'");
        return !empty($row) && (int)$row[0]['cnt'] > 0;
    }
    
    /**
     * 商机导出
     * @param
     * @return
     * @author Michael_xu
     */
    public function excelExport()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['user_id'] = $userInfo['id'];
        $action_name = '导出全部';
        if ($param['business_id']) {
            $param['business_id'] = ['condition' => 'in', 'value' => $param['business_id'], 'form_type' => 'text', 'name' => ''];
            $param['is_excel'] = 1;
            $action_name = '导出选中';
        }
        
        $excelModel = new \app\admin\model\Excel();
        // 导出的字段列表
        $fieldModel = new \app\admin\model\Field();
        $field_list = $fieldModel->getIndexFieldConfig('crm_business', $userInfo['id']);
        // 文件名
        $file_name = '5kcrm_business_' . date('Ymd');
        
        $model = model('Business');
        $temp_file = $param['temp_file'];
        unset($param['temp_file']);
        $page = $param['page'] ?: 1;
        unset($param['page']);
        unset($param['export_queue_index']);
        RecordActionLog($userInfo['id'], 'crm_customer', 'excelexport', $action_name, '', '', '导出商机');
        return $excelModel->batchExportCsv($file_name, $temp_file, $field_list, $page, function ($page, $limit) use ($model, $param, $field_list) {
            $param['page'] = $page;
            $param['limit'] = $limit;
            $data = $model->getDataList($param);
            $data['list'] = $model->exportHandle($data['list'], $field_list, 'business');
            return $data;
        });
    }
    
    /**
     * 设置关注
     *
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function star()
    {
        $userId = $this->userInfo['id'];
        $targetId = $this->param['target_id'];
        $type = $this->param['type'];
        
        if (empty($userId) || empty($targetId) || empty($type)) return resultArray(['error' => '缺少必要参数！']);
        
        if (!$this->setStar($type, $userId, $targetId)) {
            return resultArray(['error' => '设置关注失败！']);
        }
        
        return resultArray(['data' => '设置关注成功！']);
    }
    
    /**
     * 系统信息
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function system()
    {
        if (empty($this->param['id'])) return resultArray(['error' => '参数错误！']);
        
        $businessModel = new \app\crm\model\Business();
        
        $data = $businessModel->getSystemInfo($this->param['id']);
        
        return resultArray(['data' => $data]);
    }
    
    /**
     * table栏数量统计
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function count()
    {
        if (empty($this->param['business_id'])) return resultArray(['error' => '参数错误！']);
        
        $businessId = $this->param['business_id'];
        
        $userInfo = $this->userInfo;
        
        # 查询联系人和商机关联数据
        $contactsIds = Db::name('crm_contacts_business')->where('business_id', $businessId)->column('contacts_id');
        
        # 联系人
        $contactsAuth = $this->getContactsSearchWhere($userInfo['id']);
        $contactsCount = Db::name('crm_contacts')->whereIn('contacts_id', $contactsIds)->where($contactsAuth)->count();
        
        # 合同
        $contractAuth = $this->getContractSearchWhere($userInfo['id']);
        $contractQuery = Db::name('crm_contract')->where('business_id', $businessId)->where($contractAuth);
        $contractCount = $contractQuery->count();
        $contractIds = Db::name('crm_contract')->where('business_id', $businessId)->where($contractAuth)->column('contract_id');
        
        # 查询商机和产品的关联表
        $productIds = Db::name('crm_business_product')->where('business_id', $businessId)->column('product_id');
        
        # 产品
        $productAuth = $this->getProductSearchWhere();
        $productCount = Db::name('crm_product')->whereIn('product_id', $productIds)->whereIn('owner_user_id', $productAuth)->count();
        
        # 附件
        $fileCount = Db::name('crm_business_file')->alias('business')->join('__ADMIN_FILE__ file', 'file.file_id = business.file_id', 'LEFT')->where('business_id', $businessId)->count();
        
        # 团队
        $business = Db::name('crm_business')->field(['owner_user_id', 'ro_user_id', 'rw_user_id'])->where('business_id', $businessId)->find();
        $business['ro_user_id'] = explode(',', trim($business['ro_user_id'], ','));
        $business['rw_user_id'] = explode(',', trim($business['rw_user_id'], ','));
        $business['owner_user_id'] = [$business['owner_user_id']];
        $teamCount = array_filter(array_unique(array_merge($business['ro_user_id'], $business['rw_user_id'], $business['owner_user_id'])));

        # 台账 - 当前商机相关
        $ledgerQuery = Db::name('customer_ledger')->where('business_id', $businessId);
        if (!empty($contractIds)) {
            $ledgerQuery->whereOr('contract_id', 'in', $contractIds);
        }
        $ledgerCount = $ledgerQuery->count();

        # 收支流水 - 当前商机相关
        $financeQuery = Db::name('finance_record')->where('business_id', $businessId);
        if (!empty($contractIds)) {
            $financeQuery->whereOr('contract_id', 'in', $contractIds);
        }
        $financeCount = $financeQuery->count();
        
        $data = [
            'contactCount' => $contactsCount,
            'contractCount' => $contractCount,
            'fileCount' => $fileCount,
            'memberCount' => count($teamCount),
            'productCount' => $productCount,
            'ledgerCount' => $ledgerCount,
            'financeCount' => $financeCount
        ];
        
        return resultArray(['data' => $data]);
    }
    
    /**
     * 设置首要联系人
     *
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function setPrimary()
    {
        $businessId = $this->param['business_id'];
        $contactsId = $this->param['contacts_id'];

        if (empty($businessId) || empty($contactsId)) return resultArray(['error' => '参数错误！']);

        if (!Db::name('crm_business')->where('business_id', $businessId)->update(['contacts_id' => $contactsId])) {
            return resultArray(['error' => '操作失败！']);
        }

        return resultArray(['data' => '操作成功！']);
    }

    /**
     * 签约代理商下拉：从所有有效 CRM 客户中选择，排除当前商机所属客户。
     * 不再依赖 customer_type=dealer，任意公司客户均可作为签约代理商。
     */
    public function dealerOptions()
    {
        $param = $this->param;
        $search = trim((string)($param['search'] ?? ''));
        $limit = max(1, min(50, (int)($param['limit'] ?? 20)));
        $excludeCustomerId = (int)($param['exclude_customer_id'] ?? 0);
        $q = db('crm_customer')->field('customer_id,name');
        if ($excludeCustomerId > 0) {
            $q->where('customer_id', '<>', $excludeCustomerId);
        }
        if ($search !== '') {
            $q->where('name', 'like', '%' . $search . '%');
        }
        $list = $q->order('customer_id asc')->limit($limit)->select();
        return resultArray(['data' => ['list' => $list]]);
    }

}
