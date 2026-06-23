<?php
/**
 * crm模块下的通用功能逻辑类
 *
 * @author qifan
 * @date 2020-12-11
 */

namespace app\crm\logic;

use app\admin\controller\ApiCommon;
use app\admin\model\User;
use app\admin\traits\FieldVerificationTrait;
use app\crm\model\Customer;
use think\Db;
use think\Validate;

class CommonLogic
{
    use FieldVerificationTrait;

    public $error = '操作失败！';
    
    /**
     * 快捷编辑【线索、客户、联系人、商机、合同、回款、发票、回访、产品】
     *
     * @param $param
     * @return false|int|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function quickEdit($param)
    {
        /**
         * $param['types']     表名
         * $param['action_id'] 主键ID
         * $param['field']     字段
         * $param['name']      字段中文名，用作提示
         * $param['value]      字段值
         */
        
        $actionId = $param['action_id'];
        $types = $param['types'];
        unset($param['action_id']);
        unset($param['types']);
        
        # 模型
        $model = db($types);
        
        # 主键
        $primaryKey = getPrimaryKeyName($types);

        $info='';
        switch ($types) {
            case 'crm_leads' :
                $dataModel=new \app\crm\model\Leads();
                $info=$dataModel->getDataById($actionId);
                break;
            case 'crm_customer' :
                $info=db('crm_customer')->where('customer_id',$actionId)->find();
                break;
            case 'crm_contacts' :
                $dataModel=new \app\crm\model\Contacts();
                $info=$dataModel->getDataById($actionId);
                break;
            case 'crm_business' :
                $dataModel=new \app\crm\model\Business();
                $info=$dataModel->getDataById($actionId);
                break;
            case 'crm_contract' :
                $info=db('crm_contract')->where('customer_id',$actionId)->find();
                break;
            case 'crm_receivables' :
                $info=db('crm_receivables')->where('customer_id',$actionId)->find();
                break;
            case 'crm_invoice' :
                $info = $model->where($primaryKey, $actionId)->find();
                break;
            case 'crm_visit' :
                $dataModel=new \app\crm\logic\VisitLogic();
                $info=$dataModel->getDataById($actionId);
                break;
            case 'crm_product' :
                $dataModel=new \app\crm\model\Product();
                $info=$dataModel->getDataById($actionId);
                break;
        }
        $apiCommon = new ApiCommon();
        $userModel = new User();
        $userInfo = $apiCommon->userInfo;

        if (in_array($types, ['crm_contract', 'crm_receivables'])) {
            $checkStatus = $model->where($primaryKey, $actionId)->value('check_status');
            if (!in_array($checkStatus, [4, 5, 6])) {
                $this->error = '只能编辑状态为撤销、草稿或作废的信息！';
                return false;
            }
        }

        // 数据验证
        $validateData = [];
        if (!empty($param['list'])) {
            foreach ($param['list'] AS $key => $value) {
                foreach ($value AS $k => $v) {
                    $validateData[$k] = $v;
                }
            }
        }
        $validateResult = $this->fieldDataValidate($validateData, $types, $userInfo['id'], $actionId);
        if (!empty($validateResult)) {
            $this->error = $validateResult;
            return false;
        }

        # 产品修改验证
        if($types == 'crm_product'){
            foreach ($param['list'] as $val){
                $infoData=db('crm_product')->where(['name'=>$val['name'],'delete_user_id'=>0])->find();
                if(!empty($infoData)){
                    $fieldModel = new \app\admin\model\Field();
                    $validateArr = $fieldModel->validateField('crm_product'); //获取自定义字段验证规则
                    $validate = new Validate($validateArr['rule'], $validateArr['message']);
                    $result = $validate->check($val);
                    if (!$result) {
                        $this->error = $validate->getError();
                        return false;
                    }
                }
            }
        }

        # 客户模块快捷编辑权限验证
        if ($types == 'crm_customer') {
            $dataInfo = $model->field(['ro_user_id', 'rw_user_id', 'owner_user_id'])->where($primaryKey, $actionId)->find();
            $auth_user_ids = $userModel->getUserByPer('crm', 'customer', 'update');
            $rwPre = $userModel->rwPre($apiCommon->userInfo['id'], $dataInfo['ro_user_id'], $dataInfo['rw_user_id'], 'update');
            $wherePool = (new Customer())->getWhereByPool();
            $resPool = db('crm_customer')->alias('customer')->where(['customer_id' => $param['action_id']])->where($wherePool)->find();
            if ($resPool || (!in_array($dataInfo['owner_user_id'], $auth_user_ids) && !$rwPre)) {
                $this->error = '无权操作！';
                return false;
            }
        }
        
        # 商机模块快捷编辑权限验证
        if ($types == 'crm_business') {
            $dataInfo = $model->field(['ro_user_id', 'rw_user_id', 'owner_user_id'])->where($primaryKey, $actionId)->find();
            $auth_user_ids = $userModel->getUserByPer('crm', 'business', 'update');
            $rwPre = $userModel->rwPre($apiCommon->userInfo['id'], $dataInfo['ro_user_id'], $dataInfo['rw_user_id'], 'update');
            if (!in_array($dataInfo['owner_user_id'], $auth_user_ids) && !$rwPre) {
                $this->error = '无权操作！';
                return false;
            }
        }
        
        # 合同模块快捷编辑权限验证
        if ($types == 'crm_contract') {
            $dataInfo = $model->field(['ro_user_id', 'rw_user_id', 'owner_user_id'])->where($primaryKey, $actionId)->find();
            $auth_user_ids = $userModel->getUserByPer('crm', 'contract', 'update');
            $rwPre = $userModel->rwPre($apiCommon->userInfo['id'], $dataInfo['ro_user_id'], $dataInfo['rw_user_id'], 'update');
            if (!in_array($dataInfo['owner_user_id'], $auth_user_ids) && !$rwPre) {
                $this->error = '无权操作！';
                return false;
            }
        }

        $fieldModel = new \app\admin\model\Field();
        # 日期时间类型
        $datetimeField = $fieldModel->getFieldByFormType($types, 'datetime');
        # 附件类型
        $fileField = $fieldModel->getFieldByFormType($types, 'file');
        # 多选类型
        $checkboxField = $fieldModel->getFieldByFormType($types, 'checkbox');
        # 人员类型
        $userField = $fieldModel->getFieldByFormType($types, 'user');
        # 部门类型
        $structureField = $fieldModel->getFieldByFormType($types, 'structure');
        # 地址
        $positionField = $fieldModel->getFieldByFormType($types, 'position');
        # 定位
        $locationField = $fieldModel->getFieldByFormType($types, 'location');
        # 日期区间
        $dateIntervalField = $fieldModel->getFieldByFormType($types, 'date_interval');
        # 手写签名
        $handwritingField = $fieldModel->getFieldByFormType($types, 'handwriting_sign');
        # 明细表格
        $detailTableField = $fieldModel->getFieldByFormType($types, 'detail_table');

        # 处理数据 data 常规数据 extraData 扩展数据（地址、定位、日期区间、详细表格）
        $data = [];
        $extraData = [];
        $deleteExtraWhere = [];
        if (!empty($param['list'])) {
            foreach ($param['list'] as $key => $value) {
                foreach ($value as $k => $v) {
                    if ($k == 'next_time' || in_array($k, $datetimeField)) {
                        # 处理下次联系时间格式、datetime类型数据
                        $data[$k] = !empty($v) && $v == strtotime($v) ? strtotime($v) : $v;
                    } elseif ($types == 'crm_product' && $k == 'category_id') {
                        # 处理产品类别
                        $categorys = explode(',', $v);
                        $data[$k] = $categorys[count($categorys) - 1];
                    } elseif (in_array($k, $fileField) || in_array($k, $checkboxField) || in_array($k, $userField) || in_array($k, $structureField)) {
                        # 处理附件、多选、人员、部门类型数据
                        $data[$k] = !empty($v) ? arrayToString($v) : '';
                    } elseif ($types == 'crm_visit' && $k == 'contract_id') {
                        # 处理回访提交过来的合同编号
                        if (!empty($v[0]['contract_id'])) $data[$k] = $v[0]['contract_id'];
                    } elseif (in_array($k, $handwritingField)) {
                        // 手写签名
                        $data[$k] = !empty($v['file_id']) ? $v['file_id'] : 0;
                    } elseif (in_array($k, $positionField)) {
                        // 地址
                        if (!empty($v)) {
                            $extraData[] = [
                                $primaryKey => $actionId,
                                'field' => $k,
                                'content' => json_encode($v),
                                'create_time' => time()
                            ];
                            $positionNames = array_column($v, 'name');
                            $data[$k] = implode(',', $positionNames);
                        } else {
                            $data[$k] = '';
                        }
                        $deleteExtraWhere[] = $k;
                    } elseif (in_array($k, $locationField)) {
                        // 定位
                        if (!empty($v)) {
                            $extraData[] = [
                                $primaryKey => $actionId,
                                'field' => $k,
                                'content' => json_encode($v),
                                'create_time' => time()
                            ];
                            $data[$k] = $v['address'];
                        } else {
                            $data[$k] = '';
                        }
                        $deleteExtraWhere[] = $k;
                    } elseif (in_array($k, $dateIntervalField)) {
                        // 日期区间
                        if (!empty($v)) {
                            $extraData[] = [
                                $primaryKey => $actionId,
                                'field' => $k,
                                'content' => json_encode($v),
                                'create_time' => time()
                            ];
                            $data[$k] = implode('_', $v);
                        } else {
                            $data[$k] = '';
                        }
                        $deleteExtraWhere[] = $k;
                    } elseif (in_array($k, $detailTableField)) {
                        // 明细表格
                        if (!empty($v)) {
                            $extraData[] = [
                                $primaryKey => $actionId,
                                'field' => $k,
                                'content' => json_encode($v),
                                'create_time' => time()
                            ];
                        }
                        $deleteExtraWhere[] = $k;
                    } else {
                        $data[$k] = $v;
                    }
                }
            }
            $data[$primaryKey]   = $actionId;
            $data['update_time'] = time();
        }

        $res = $model->update($data);
        unset($data[$primaryKey]);
        unset($data['update_time']);
        // 详细信息修改新增操作记录、处理扩展数据
        if ($res) {
            // 删除扩展数据
            if (!empty($deleteExtraWhere)) db($types . '_data')->where([$primaryKey => $actionId, 'field' => ['in', $deleteExtraWhere]])->delete();
            // 添加扩展数据
            if (!empty($extraData)) db($types . '_data')->insertAll($extraData);
            // 修改记录
            updateActionLog($userInfo['id'], $types, $actionId, $info, $data);
            RecordActionLog($userInfo['id'], $types, 'update',$info['name'], $info, $data);
        }
        return $res;
    }
}