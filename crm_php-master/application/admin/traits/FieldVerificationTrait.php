<?php
/**
 * 字段验证（线索、客户、联系人、商机、合同、回款、回访、产品、办公审批）
 */

namespace app\admin\traits;

trait FieldVerificationTrait
{

    /**
     * 数据验证
     *
     * @param array $param 要验证的数据
     * @param int $userId 用户ID
     * @param string $types 自定义表栏目类型：crm_leads、crm_customer ...
     * @param int $dataId 编辑时，相应的模块数据的ID
     * @param int $typesId 自定义表栏目类型ID
     * @return string
     * @since 2021-05-18
     * @author fanqi
     */
    public function fieldDataValidate($param, $types, $userId, $dataId = 0, $typesId = 0)
    {
        $error = '';

        $grantData = getFieldGrantData($userId);     # 字段授权
        $userLevel = isSuperAdministrators($userId); # 用户级别

        # 查询自定义字段表数据
        $fieldList = $this->getFieldList($types, $typesId);
        # 验证
        foreach ($fieldList as $key => $value) {

            # 字段授权，没有读写权限，跳过验证。
            if (!$userLevel && !empty($grantData[$types])) {
                $status = getFieldGrantStatus($value['field'], $grantData[$types]);

                if (empty($status['read']) || (empty($dataId) && empty($status['write']))) continue;
            }
            # 验证非明细表格字段数据
            if ($value['form_type'] != 'detail_table' && !empty($value['is_null']) && !in_array($value['form_type'], ['detail_table', 'boolean_value','floatnumber']) && (isset($param[$value['field']]) && empty($param[$value['field']]))) {

                $error = $value['name'] . '字段不能为空！';
                break;
            }

            # 验证字段长度
            if (!empty($value['max_length']) && $value['form_type'] != 'detail_table' && strlen($param[$value['field']]) > $value['max_length']) {
                $error = $value['name'] . '字段超过设定长度！';
                break;
            }

            # 验证百分数字段长度
            if ($value['form_type'] == 'percent' && strlen($param[$value['field']]) > 11) {
                $error = $value['name'] . "字段长度不能大于10位！";
                break;
            }

            # 验证数字字段长度
            if ($value['form_type'] == 'number' && strlen($param[$value['field']]) > 16) {
                $error = $value['name'] . "字段长度不能大于16位！";
                break;
            }

            
            # 验证明细表格不能为空
            if (!empty($value['is_null']) && $value['form_type'] == 'detail_table' && isset($param[$value['field']]) && empty($param[$value['field']])) {
                $error = $value['name'] . '数据不能为空！';
            }
            # 验证明细表格可以为空，明细表格里的字段不能为空的情况。
            if ($value['form_type'] == 'detail_table') {
                foreach ($param[$value['field']] as $val) {
                    foreach ($val as $v) {
                        if ($v['form_type']!='boolean_value' && !empty($v['is_null']) && empty($v['is_hidden']) && isset($v['value']) && empty($v['value'])) {
                            $error = $value['name'] . '中的' . $v['name'] . '字段不能为空！';
                            break;
                        }
                    }
                }
            }
            if (empty($value['is_unique'])) continue;

            // 人员、部门、文件、手写签名、描述文字、多选、明细表格跳过验证
            if (in_array($value['form_type'], ['file', 'handwriting_sign', 'desc_text', 'checkbox', 'detail_table'])) continue;
            $uniqueStatus = false;
            # 验证唯一性
            if ($value['form_type'] == 'date_interval' && !empty($param[$value['field']])) {
                // 日期区间
                $uniqueStatus = $this->checkDataUniqueForDateInterval($types, $value['field'], $param[$value['field']], $dataId);
            } elseif ($value['form_type'] == 'position' && !empty($param[$value['field']])) {
                // 地址
                $uniqueStatus = $this->checkDataUniqueForPosition($types, $value['field'], $param[$value['field']], $dataId);
            } elseif ($value['form_type'] == 'location' && !empty($param[$value['field']])) {
                // 定位
                $uniqueStatus = $this->checkDataUniqueForLocation($types, $value['field'], $param[$value['field']], $dataId);
            } elseif ($value['form_type'] == 'user' && !empty($param[$value['field']])) {
                // 人员
                $uniqueStatus = $this->checkDataUniqueForUser($types, $value['field'], $param[$value['field']], $dataId);
            } elseif ($value['form_type'] == 'structure' && !empty($param[$value['field']])) {
                // 部门
                $uniqueStatus = $this->checkDataUniqueForStructure($types, $value['field'], $param[$value['field']], $dataId);
            } else {
                if (!empty($param[$value['field']])) $uniqueStatus = $this->checkDataUniqueForCommon($types, $value['field'], $param[$value['field']], $dataId);

            }

            if (!empty($uniqueStatus)) {
                $error = $types == 'crm_customer' ? '(客户/公海)中的' . $value['name'] . "字段值重复！" : $value['name'] . "字段值重复！";
                break;
            }
        }
        return $error;
    }

    /**
     * 验证唯一性（通用）
     * 单行文本、多行文本、网址、布尔值、单选、数字
     * 手机、邮箱、日期、日期时间、货币、百分数
     *
     * @param string $types 栏目类型
     * @param string $field 字段名称
     * @param string $value 字段值
     * @param int $dataId 更新时，传来的数据ID
     * @return float|mixed|string
     * @since 2021-05-18
     * @author fanqi
     */
    private function checkDataUniqueForCommon($types, $field, $value, $dataId = 0)
    {
        if (empty($value)) return false;

        # 主键
        $primaryKey = getPrimaryKeyName($types);

        # 查询条件
        $where[$field] = $value;
        if (!empty($dataId)) $where[$primaryKey] = ['neq', $dataId];

        return db($types)->where($where)->value($primaryKey);
    }

    /**
     * 验证唯一性（日期区间）
     *
     * @param string $types 栏目类型
     * @param string $field 字段名称
     * @param array $value 字段值
     * @param int $dataId 更新时，传来的数据ID
     * @return float|mixed|string
     * @since 2021-05-18
     * @author fanqi
     */
    private function checkDataUniqueForDateInterval($types, $field, $value, $dataId = 0)
    {
        if (empty($value)) return false;

        # 主键
        $primaryKey = getPrimaryKeyName($types);

        # 查询条件
        $where[$field] = implode('_', $value);
        if (!empty($dataId)) $where[$primaryKey] = ['neq', $dataId];

        return db($types)->where($where)->value($primaryKey);
    }

    /**
     * 验证唯一性（地址）
     *
     * @param string $types 栏目类型
     * @param string $field 字段名称
     * @param array $value 字段值
     * @param int $dataId 更新时，传来的数据ID
     * @return float|mixed|string
     * @since 2021-05-18
     * @author fanqi
     */
    private function checkDataUniqueForPosition($types, $field, $value, $dataId = 0)
    {
        if (empty($value)) return false;

        # 主键
        $primaryKey = getPrimaryKeyName($types);

        # 查询条件
        $where[$field] = implode(',', array_column($value, 'name'));
        if (!empty($dataId)) $where[$primaryKey] = ['neq', $dataId];

        return db($types)->where($where)->value($primaryKey);
    }

    /**
     * 验证唯一性（定位）
     *
     * @param string $types 栏目类型
     * @param string $field 字段名称
     * @param array $value 字段值
     * @param int $dataId 更新时，传来的数据ID
     * @return float|mixed|string
     * @since 2021-05-18
     * @author fanqi
     */
    private function checkDataUniqueForLocation($types, $field, $value, $dataId = 0)
    {
        if (empty($value['address'])) return false;

        # 主键
        $primaryKey = getPrimaryKeyName($types);

        # 查询条件
        $where[$field] = $value['address'];
        if (!empty($dataId)) $where[$primaryKey] = ['neq', $dataId];

        return db($types)->where($where)->value($primaryKey);
    }

    /**
     * 验证唯一性（部门）
     *
     * @param string $types 栏目类型
     * @param string $field 字段名称
     * @param string $value 字段值
     * @param int $dataId 更新时，传来的数据ID
     * @return float|mixed|string
     * @since 2021-05-18
     * @author fanqi
     */
    private function checkDataUniqueForStructure($types, $field, $value, $dataId = 0)
    {
        if (empty($value)) return false;

        # 主键
        $primaryKey = getPrimaryKeyName($types);

        # 查询条件
        $where[$field] = ',' . $value . ',';
        if (!empty($dataId)) $where[$primaryKey] = ['neq', $dataId];

        return db($types)->where($where)->value($primaryKey);
    }

    /**
     * @param $types 栏目类型
     * @param $field 字段名称
     * @param $value 字段值
     * @param int $dataId 更新时，传来的数据ID
     * @return false|float|mixed|string|null
     * @since 2021-05-18
     * @author fanqi
     */
    private function checkDataUniqueForUser($types, $field, $value, $dataId = 0)
    {
        if (empty($value)) return false;

        # 主键
        $primaryKey = getPrimaryKeyName($types);

        # 查询条件
        $where[$field] = ',' . $value . ',';
        if (!empty($dataId)) $where[$primaryKey] = ['neq', $dataId];

        return db($types)->where($where)->value($primaryKey);
    }

    /**
     * 自定义字段列表
     *
     * @param string $types 自定义表栏目类型：crm_leads、crm_customer ...
     * @param int $typesId 自定义表栏目类型ID
     * @return bool|\PDOStatement|string|\think\Collection
     * @since 2021-05-18
     * @author fanqi
     */
    private function getFieldList($types, $typesId)
    {
        # 查询条件
        $where = [
            'types' => $types,
            'types_id' => $typesId,
            'is_hidden' => 0,
            'form_type' => ['neq', 'desc_text']
        ];

        # 查询字段
        $fields = ['field', 'name', 'form_type', 'is_unique', 'is_null', 'max_length'];

        return db('admin_field')->field($fields)->where($where)->select();
    }

}