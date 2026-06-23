<?php
// +----------------------------------------------------------------------
// | Description: 回款计划
// +----------------------------------------------------------------------
// | Author: Michael_xu | gengxiaoxu@5kcrm.com 
// +----------------------------------------------------------------------

namespace app\crm\controller;

use app\admin\controller\ApiCommon;
use think\Hook;
use think\Request;

class ReceivablesPlan extends ApiCommon
{
    /**
     * 用于判断权限
     * @permission 无限制
     * @allow 登录用户可访问
     * @other 其他根据系统设置
    **/    
    public function _initialize()
    {
        $action = [
            'permission'=>[''],
            'allow'=>['index','save','read','update','delete']            
        ];
        Hook::listen('check_auth',$action);
        $request = Request::instance();
        $a = strtolower($request->action());        
        if (!in_array($a, $action['permission'])) {
            parent::_initialize();
        }
    } 

    /**
     * 回款计划列表
     * @author Michael_xu
     * @return 
     */
    public function index()
    {
        $receivablesPlanModel = model('ReceivablesPlan');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['user_id'] = $userInfo['id'];
        $data = $receivablesPlanModel->getDataList($param);       
        return resultArray(['data' => $data]);
    }

    /**
     * 添加回款计划
     * @author Michael_xu
     * @param 
     * @return 
     */
    public function save()
    {
        $receivablesPlanModel = model('ReceivablesPlan');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        $param['owner_user_id'] = $userInfo['id'];
        $param['user_id'] = $userInfo['id'];

        $res = $receivablesPlanModel->createData($param);
        if ($res) {
            return resultArray(['data' => '添加成功']);
        } else {
            return resultArray(['error' => $receivablesPlanModel->getError()]);
        }
    }

    /**
     * 回款计划详情
     * @author Michael_xu
     * @param  
     * @return 
     */
    public function read()
    {
        $receivablesPlanModel = model('ReceivablesPlan');
        $param = $this->param;
        $data = $receivablesPlanModel->getDataById($param['id']);
        if (!$data) {
            return resultArray(['error' => $receivablesPlanModel->getError()]);
        }
        return resultArray(['data' => $data]);
    }

    /**
     * 编辑回款计划
     * @author Michael_xu
     * @param 
     * @return 
     */
    public function update()
    {    
        $receivablesPlanModel = model('ReceivablesPlan');
        $userModel = new \app\admin\model\User();
        $param = $this->param;
        $userInfo = $this->userInfo;
        $plan_id = $param['id'];

        $dataInfo = db('crm_receivables_plan')->where(['plan_id' => $plan_id])->find();
        //根据合同权限判断
        $contractData = db('crm_contract')->where(['contract_id' => $dataInfo['contract_id']])->find();
        $auth_user_ids = $userModel->getUserByPer('crm', 'contract', 'update');
        //读写权限
        $rwPre = $userModel->rwPre($userInfo['id'], $contractData['ro_user_id'], $contractData['rw_user_id'], 'update');       
        if (!in_array($contractData['owner_user_id'],$auth_user_ids) && !$rwPre) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code'=>102,'error'=>'无权操作']));
        }
        $param['user_id'] = $userInfo['id'];
        $res = $receivablesPlanModel->updateDataById($param, $param['id']);
        if ($res) {
            return resultArray(['data' => '编辑成功']);
        } else {
            return resultArray(['error' => $receivablesPlanModel->getError()]);
        }       
    } 

    /**
     * 删除回款计划
     * @author Michael_xu
     * @param 
     * @return 
     */
    public function delete()
    {
        $userModel = new \app\admin\model\User();
        $param = $this->param;
        $userInfo = $this->userInfo;
        $plan_id = $param['id'];
        if ($plan_id) {
            $dataInfo = db('crm_receivables_plan')->where(['plan_id' => $plan_id])->find();
            if (!$dataInfo) {
                return resultArray(['error' => '数据不存在或已删除']);
            }
//            $receivablesInfo = db('crm_receivables')->where(['receivables_id' => $dataInfo['receivables_id']])->find();
//            if ($receivablesInfo) {
//                return resultArray(['error' => '已关联回款《'.$receivablesInfo['number'].'》，不能删除']);
//            }
            //根据合同权限判断
            $contractData = db('crm_contract')->where(['contract_id' => $dataInfo['contract_id']])->find();
            $auth_user_ids = $userModel->getUserByPer('crm', 'contract', 'delete');
            //读写权限
            $rwPre = $userModel->rwPre($userInfo['id'], $contractData['ro_user_id'], $contractData['rw_user_id'], 'update');       
            if (!in_array($contractData['owner_user_id'],$auth_user_ids) && !$rwPre) {
                header('Content-Type:application/json; charset=utf-8');
                exit(json_encode(['code'=>102,'error'=>'无权操作']));
            }
            $res = model('ReceivablesPlan')->delDataById($plan_id);
            if (!$res) {
                return resultArray(['error' => model('ReceivablesPlan')->getError()]);
            }
            // 删除回款计划扩展数据
            db('crm_receivables_plan_data')->where('plan_id', $plan_id)->delete();
            return resultArray(['data' => '删除成功']);
        } else {
            return resultArray(['error'=>'参数错误']);
        }        
    }
    /**
     * 回款计划导出
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
        if ($param['plan_id']) {
            $param['plan_id'] = ['condition' => 'in', 'value' => $param['plan_id'], 'form_type' => 'text', 'name' => ''];
            $action_name='导出选中';
        }
        $excelModel = new \app\admin\model\Excel();
        // 导出的字段列表
        $fieldModel = new \app\admin\model\Field();
        $field_list = $fieldModel->getIndexFieldConfig('crm_receivables_plan', $userInfo['id'],'','excel');
        // 文件名
        $file_name = '5kcrm_receivables_plan_' . date('Ymd');
        $model = model('ReceivablesPlan');
        $temp_file = $param['temp_file'];
        unset($param['temp_file']);
        $page = $param['page'] ?: 1;
        unset($param['page']);
        unset($param['export_queue_index']);
//        RecordActionLog($userInfo['id'],'crm_receivables_plan','excelexport',$action_name,'','','导出回款');
        return $excelModel->batchExportCsv($file_name, $temp_file, $field_list, $page, function ($page, $limit) use ($model, $param, $field_list) {
            $param['page'] = $page;
            $param['limit'] = $limit;
            $data = $model->getDataList($param);
            $data['list'] = $model->exportHandle($data['list'], $field_list, 'ReceivablesPlan');
            return $data;
        });
    }
}
