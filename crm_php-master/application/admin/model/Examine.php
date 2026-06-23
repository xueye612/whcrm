<?php
// +----------------------------------------------------------------------
// | Description: 审批流程
// +----------------------------------------------------------------------
// | Author: zjf
// +----------------------------------------------------------------------
namespace app\admin\model;

use app\admin\controller\ApiCommon;
use think\Db;
use app\admin\model\Common;
use think\Request;
use think\Validate;

class Examine extends Common
{
	/**
     * 为了数据库的整洁，同时又不影响Model和Controller的名称
     * 我们约定每个模块的数据表都加上相同的前缀，比如CRM模块用crm作为数据表前缀
     */
	protected $name = 'examine';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
	protected $autoWriteTimestamp = true;
	protected $typesArr = ['crm_contract', 'crm_receivables', 'crm_invoice', 'oa_examine', 'jxc_purchase', 'jxc_retreat', 'jxc_sale', 'jxc_salereturn', 'jxc_payment', 'jxc_collection', 'jxc_allocation', 'jxc_inventory'];

	/**
     * [getDataList 审批流程list]
     * @author zjf
     * @param     [string]                   $map [查询条件]
     * @param     [number]                   $page     [当前页数]
     * @param     [number]                   $limit    [每页数量]
     * @return    [array]                    [description]
     */		
	public function getDataList($request)
    {  
        $request = $this->fmtRequest( $request );
        $map = $request['map'] ? : [];
        if (isset($map['search'])) {
            //普通筛选
            $map['a.examine_name'] = ['like', '%'.$map['search'].'%'];
            unset($map['search']);
        }
        $map['a.status'] = ['neq', 3];
        $list = db('examine')
                ->alias('a')
                // ->join('examine_manager_user b','a.examine_id = b.examine_id', 'left')
                ->join('admin_user c','a.update_user_id = c.id', 'left')
                ->page($request['page'], $request['limit'])
                ->field('a.*, c.realname as update_realname, c.thumb_img as update_thumb_img')
                ->where($map)
                ->order('a.status desc,a.update_time desc')
                ->select(); 
        foreach ($list as $k=>$v) {
            $list[$k]['status'] = $v['status']==1 ? '启用' : '停用';
            $list[$k]['recheck_type'] = $v['recheck_type']==1 ? '从第一层开始' : '从拒绝的层级开始';
            // 1 合同 2 回款 3发票 4薪资 5 采购审核 6采购退货审核 7销售审核 8 销售退货审核 9付款单审核10 回款单审核11盘点审核12调拨审核',
            switch ($v['label']) {
                case '1' : $label = '合同'; break;
                case '2' : $label = '回款'; break;
                case '3' : $label = '发票'; break;
                case '4' : $label = '薪资'; break;
                case '5' : $label = '采购审核'; break;
                case '6' : $label = '采购退货审核'; break;
                case '7' : $label = '销售审核'; break;
                case '8' : $label = '销售退货审核'; break;
                case '9' : $label = '付款单审核'; break;
                case '10' : $label = '回款单审核1'; break;
                case '11' : $label = '盘点审核'; break;
                case '12' : $label = '调拨审核'; break;
                default : $label = ''; break;
            }

            // 审批流管理员
            $manager_user = db('examine_manager_user')
                ->alias('a')
                ->join('admin_user b','a.user_id = b.id')
                ->field('a.user_id, b.realname')
                ->where('examine_id', $v['examine_id'])
                ->column('user_id');
            $list[$k]['managerList'] = $manager_user;

            $list[$k]['label'] = $label;
        }
        $dataCount = db('examine')
                ->alias('a')
                ->join('examine_manager_user b','a.examine_id = b.examine_id', 'left')
                ->join('admin_user c','a.update_user_id = c.id', 'left')
                ->where($map)
                ->count('a.examine_id');

        $data = [];
        $data['list'] = $list;
        $data['dataCount'] = $dataCount ? : 0;

        return $data;
    }

    /**
     * 审批流程详情
     * @author zjf
     * @param  
     * @return                            
     */ 
    public function getDataById($examine_id = '')
    {
        $userModel = new \app\admin\model\User();
        $dataInfo = $this->get($examine_id);
        if (!$dataInfo) {
            $this->error = '数据不存在或已删除';
            return false;
        }

        //审批步骤
        $flowList = db('examine_flow')->where(['examine_id' => $examine_id])->where('condition_id', 0)->select();
        foreach ($flowList as $k=>$v) {
            if($v['examine_type'] == 0){
                $conditionList = db('examine_condition')->where(['flow_id' => $v['flow_id']])->select();
                foreach ($conditionList as $key => $value) {
                    $conditionList[$key]['conditionDataList'] = db('examine_condition_data')->where(['condition_id' => $value['condition_id']])->select();
                    $examineDataList = $this->recursion($value['condition_id']);
                    $conditionList[$key]['examineDataList'] = $examineDataList;
                }
                $flowList[$k]['conditionList'] = $conditionList;


                
            }    
        }
        return $flowList;
    }

    public function recursion($condition_id = '')
    {
        //审批步骤
        $flowList = db('examine_flow')->where(['condition_id' => $condition_id])->select();
        foreach ($flowList as $k=>$v) {
            if($v['examine_type'] == 0){
                $conditionList = db('examine_condition')->where(['flow_id' => $v['flow_id']])->select();
                foreach ($conditionList as $key => $value) {
                    $conditionList[$key]['conditionDataList'] = db('examine_condition_data')->where(['condition_id' => $value['condition_id']])->select();
                    $examineDataList = $this->recursion($value['condition_id']);
                    $conditionList[$key]['examineDataList'] = $examineDataList;
                }
                $flowList[$k]['conditionList'] = $conditionList;
            }  
        }
        return $flowList;
    }
}