<?php
/**
 * 更新sql（包含安装）
 *
 * @author fanqi
 * @since 2021-05-08
 */
namespace app\admin\controller;

use think\Db;
use think\Exception;

class UpdateSql
{
    /**
     * 添加公海默认数据
     *
     * @author fanqi
     * @since 2021-05-08
     * @return bool
     */
    static public function addPoolDefaultData()
    {
        // 员工ID
        $userIds = db('admin_user')->column('id');

        // 公海主数据
        $poolData = [
            'pool_name'         => '系统默认公海',
            'admin_user_ids'    => ',1,',
            'user_ids'          => ','.implode(',', $userIds).',',
            'department_ids'    => '',
            'status'            => 1,
            'before_owner_conf' => 0,
            'before_owner_day'  => 0,
            'receive_conf'      => 0,
            'receive_count'     => 0,
            'remind_conf'       => 0,
            'remain_day'        => 0,
            'recycle_conf'      => 1,
            'create_user_id'    => 1,
            'create_time'       => time()
        ];

        // 公海规则数据
        $poolRuleData = [
            'pool_id'         => 0,
            'type'            => 1,
            'deal_handle'     => 0,
            'business_handle' => 0,
            'level_conf'      => 1,
            'level'           => json_encode([['level' => '所有客户', 'limit_day' => 30]]),
            'limit_day'       => 0
        ];

        // 公海字段数据
        $poolFieldData = [];
        $fields = db('admin_field')->field(['field', 'name', 'form_type', 'is_hidden'])->where(['types' => 'crm_customer'])->select();
        foreach ($fields AS $key => $value) {
            $poolFieldData[] = [
                'field_name' => $value['field'],
                'name'       => $value['name'],
                'form_type'  => $value['form_type'],
                'is_hidden'  => $value['is_hidden']
            ];
        }
        $poolFieldData[] = ['field_name' => 'address', 'name' => '省、市、区/县', 'form_type' => 'customer_address', 'is_hidden' => 0];
        $poolFieldData[] = ['field_name' => 'detail_address', 'name' => '详细地址', 'form_type' => 'text', 'is_hidden' => 0];
        $poolFieldData[] = ['field_name' => 'last_record', 'name' => '最后跟进记录', 'form_type' => 'text', 'is_hidden' => 0];
        $poolFieldData[] = ['field_name' => 'last_time', 'name' => '最后跟进时间', 'form_type' => 'datetime', 'is_hidden' => 0];
        $poolFieldData[] = ['field_name' => 'before_owner_user_id', 'name' => '前负责人', 'form_type' => 'user', 'is_hidden' => 0];
        $poolFieldData[] = ['field_name' => 'into_pool_time', 'name' => '进入公海时间', 'form_type' => 'datetime', 'is_hidden' => 0];
        $poolFieldData[] = ['field_name' => 'create_time', 'name' => '创建时间', 'form_type' => 'datetime', 'is_hidden' => 0];
        $poolFieldData[] = ['field_name' => 'update_time', 'name' => '更新时间', 'form_type' => 'datetime', 'is_hidden' => 0];
        $poolFieldData[] = ['field_name' => 'create_user_id', 'name' => '创建人', 'form_type' => 'user', 'is_hidden' => 0];

        Db::startTrans();
        try {
            // 添加公海主数据
            $poolId = Db::name('crm_customer_pool')->insert($poolData, false, true);

            // 添加公海规则数据
            $poolRuleData['pool_id'] = $poolId;
            Db::name('crm_customer_pool_rule')->insert($poolRuleData);

            // 添加公海字段数据
            array_walk($poolFieldData, function (&$val) use ($poolId) {
                $val['pool_id'] = $poolId;
            });
            Db::name('crm_customer_pool_field_setting')->insertAll($poolFieldData);

            Db::commit();

            return true;
        } catch (Exception $e) {
            Db::rollback();

            return false;
        }
    }

    /**
     * 添加跟进记录的导入导出权限数据
     *
     * @author fanqi
     * @since 2021-05-08
     */
    static public function addFollowRuleData()
    {
        // 删除旧版的跟进记录权限规则数据
        db('admin_rule')->where(['types' => 2, 'title' => '跟进记录管理', 'name' => 'record', 'level' => 2, 'pid' => 1])->delete();

        // 新版跟进记录权限规则增加导入导出
        $activityPid = db('admin_rule')->where(['types' => 2, 'title' => '跟进记录', 'name' => 'activity', 'level' => 2])->value('id');
        if (!db('admin_rule')->where(['types' => 2, 'pid' => $activityPid, 'name' => 'excelImport'])->value('id')) {
            db('admin_rule')->insert(['types' => 2, 'title' => '导入', 'name' => 'excelImport', 'level' => 3, 'pid' => $activityPid, 'status' => 1]);
        }
        if (!db('admin_rule')->where(['types' => 2, 'pid' => $activityPid, 'name' => 'excelExport'])->value('id')) {
            db('admin_rule')->insert(['types' => 2, 'title' => '导出', 'name' => 'excelExport', 'level' => 3, 'pid' => $activityPid, 'status' => 1]);
        }
    }

    /**
     * 处理11.0.3升级时，没有处理旧公海数据的问题
     *
     * @author fanqi
     * @since 2021-06-23
     */
    static public function SynchronizationCustomerToPool()
    {
        $poolData    = [];
        $installData = [];
        $updateData  = [];

        // 公海数据
        $poolList = db('crm_customer_pool')->alias('pool')
            ->join('__CRM_CUSTOMER_POOL_RELATION__ relation', 'pool.pool_id = relation.pool_id', 'INNER')
            ->field(['relation.pool_id', 'relation.customer_id'])->select();
        // 整理公海数据
        foreach ($poolList AS $key => $value) {
            $poolData[$value['pool_id']][] = $value['customer_id'];
        }

        // 没有负责人和没有进入公海时间的客户
        $customerIds = db('crm_customer')->where(['owner_user_id' => 0, 'into_pool_time' => 0])->column('customer_id');

        // 整理要添加(公海客户管理表)和要编辑的数据(修改客户的进入公海时间)
        foreach ($customerIds AS $key => $value) {
            foreach ($poolData AS $k => $v) {
                if (!in_array($value, $v)) {
                    $installData[] = [
                        'pool_id' => $k,
                        'customer_id' => $value
                    ];
                    $updateData[] = $value;
                }
            }
        }

        // 添加至公海客户关联表
        if (!empty($installData)) {
            db('crm_customer_pool_relation')->insertAll($installData);
        }

        // 更新客户的进入公海时间
        if (!empty($updateData)) {
            db('crm_customer')->whereIn('customer_id', array_unique($updateData))->exp('before_owner_user_id', 'create_user_id')->update(['into_pool_time' => time()]);
        }
    }

    /**
     * 发票导出权限
     *
     * @author fanqi
     * @since 2021-06-24
     */
    static public function createInvoiceExportRule()
    {
        // 发票导出权限
        $invoiceId = db('admin_rule')->where(['types' => 2, 'title' => '发票管理', 'name' => 'invoice', 'level' => 2, 'pid' => 1])->value('id');

        if (!empty($invoiceId)) {
            db('admin_rule')->insert(['types' => 2, 'title' => '导出', 'name' => 'excelExport', 'level' => 3, 'pid' => $invoiceId, 'status' => 1]);
        }
    }

    /**
     * 修改数字字段类型
     *
     * @author fanqi
     * @since 2021-06-24
     */
    static public function updateFieldNumberType()
    {
        $leadsList = db("admin_field")->field(['field', 'name', 'default_value'])->where(['types' => 'crm_leads', 'form_type' => 'number'])->select();
        foreach ($leadsList AS $key => $value) {
            Db::execute("ALTER TABLE `5kcrm_crm_leads` MODIFY COLUMN `".$value['field']."` VARCHAR(255) NULL ".$value['default_value']." COMMENT '".$value['name']."'");
        }
        $customerList = db("admin_field")->field(['field', 'name', 'default_value'])->where(['types' => 'crm_customer', 'form_type' => 'number'])->select();
        foreach ($customerList AS $key => $value) {
            Db::execute("ALTER TABLE `5kcrm_crm_customer` MODIFY COLUMN  `".$value['field']."` VARCHAR(255) NULL ".$value['default_value']." COMMENT '".$value['name']."'");
        }
        $contactsList = db("admin_field")->field(['field', 'name', 'default_value'])->where(['types' => 'crm_contacts', 'form_type' => 'number'])->select();
        foreach ($contactsList AS $key => $value) {
            Db::execute("ALTER TABLE `5kcrm_crm_contacts` MODIFY COLUMN  `".$value['field']."` VARCHAR(255) NULL ".$value['default_value']." COMMENT '".$value['name']."'");
        }
        $businessList = db("admin_field")->field(['field', 'name', 'default_value'])->where(['types' => 'crm_business', 'form_type' => 'number'])->select();
        foreach ($businessList AS $key => $value) {
            Db::execute("ALTER TABLE `5kcrm_crm_business` MODIFY COLUMN  `".$value['field']."` VARCHAR(255) NULL ".$value['default_value']." COMMENT '".$value['name']."'");
        }
        $contractList = db("admin_field")->field(['field', 'name', 'default_value'])->where(['types' => 'crm_contract', 'form_type' => 'number'])->select();
        foreach ($contractList AS $key => $value) {
            Db::execute("ALTER TABLE `5kcrm_crm_contract` MODIFY COLUMN  `".$value['field']."` VARCHAR(255) NULL ".$value['default_value']." COMMENT '".$value['name']."'");
        }
        $receivablesList = db("admin_field")->field(['field', 'name', 'default_value'])->where(['types' => 'crm_receivables', 'form_type' => 'number'])->select();
        foreach ($receivablesList AS $key => $value) {
            Db::execute("ALTER TABLE `5kcrm_crm_receivables` MODIFY COLUMN  `".$value['field']."` VARCHAR(255) NULL ".$value['default_value']." COMMENT '".$value['name']."'");
        }
        $visitList = db("admin_field")->field(['field', 'name', 'default_value'])->where(['types' => 'crm_visit', 'form_type' => 'number'])->select();
        foreach ($visitList AS $key => $value) {
            Db::execute("ALTER TABLE `5kcrm_crm_visit` MODIFY COLUMN  `".$value['field']."` VARCHAR(255) NULL ".$value['default_value']." COMMENT '".$value['name']."'");
        }
        $productList = db("admin_field")->field(['field', 'name', 'default_value'])->where(['types' => 'crm_product', 'form_type' => 'number'])->select();
        foreach ($productList AS $key => $value) {
            Db::execute("ALTER TABLE `5kcrm_crm_product` MODIFY COLUMN  `".$value['field']."` VARCHAR(255) NULL ".$value['default_value']." COMMENT '".$value['name']."'");
        }
    }
}