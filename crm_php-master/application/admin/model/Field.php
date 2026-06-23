<?php
// +----------------------------------------------------------------------
// | Description: 自定义字段
// +----------------------------------------------------------------------
// | Author: Michael_xu | gengxiaoxu@5kcrm.com 
// +----------------------------------------------------------------------

namespace app\admin\model;

use app\admin\controller\ApiCommon;
use think\Config;
use think\Db;
use think\Model;
use think\Request;
use think\Validate;

class Field extends Model
{
    /**
     * 为了数据库的整洁，同时又不影响Model和Controller的名称
     * 我们约定每个模块的数据表都加上相同的前缀，比如CRM模块用crm作为数据表前缀
     */
    protected $name = 'admin_field';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $autoWriteTimestamp = true;

    private $tableName = ''; //表名
    private $queryStr = ''; //sql语句
    private $__db_prefix; //数据库表前缀

    private $types_arr = [
        'crm_leads',
        'crm_customer',
        'crm_contacts',
        'crm_product',
        'crm_business',
        'crm_contract',
        'oa_examine',
        'hrm_parroll',
        'admin_user',
        'crm_receivables',
        'crm_receivables_plan',
        'crm_invoice',
        'crm_visit',
        'jxc_product',
        'jxc_supplier',
        'jxc_purchase',
        'jxc_retreat',
        'jxc_sale',
        'jxc_salereturn',
        'jxc_receipt',
        'jxc_outbound',
        'jxc_payment',
        'jxc_collection',
        'jxc_inventory',
        'jxc_allocation',
    ]; //支持自定义字段的表，不包含表前缀
    private $formtype_arr = [
        'text',
        'pic',
        'textarea',
        'mobile',
        'email',
        'number',
        'floatnumber',
        'radio',
        'select',
        'checkbox',
        'date',
        'datetime',
        'address',
        'user',
        'file',
        'structure',
        'boolean_value', # 布尔值
        'percent', # 百分数
        'website', # 网址
        'position', # 地址
        'location', # 地址
        'handwriting_sign', # 手写签名
        'date_interval', # 日期区间
        'desc_text', # 描述类型
        'detail_table', # 明细表格
    ];
    protected $type = [
        'form_value' => 'array',
    ];


    /**
     * 列表展示额外关联字段
     */
    public $orther_field_list = [
        'crm_leads' => [
            [
                'field' => 'last_record',
                'name' => '最后跟进记录',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'last_time',
                'name' => '最后跟进时间',
                'form_type' => 'datetime',
                'width' => '',
                'is_hidden' => 0,
            ],
        ],
        'crm_customer' => [
            [
                'field' => 'last_record',
                'name' => '跟进记录',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'last_time',
                'name' => '最后跟进时间',
                'form_type' => 'datetime',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'address',
                'name' => '省、市、区/县',
                'form_type' => 'customer_address',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'detail_address',
                'name' => '详细地址',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'crm_contacts' => [
            [
                'field' => 'last_record',
                'name' => '跟进记录',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'last_time',
                'name' => '最后跟进时间',
                'form_type' => 'datetime',
                'width' => '',
                'is_hidden' => 0,
            ],
        ],
        'crm_business' => [
            [
                'field' => 'last_record',
                'name' => '跟进记录',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'last_time',
                'name' => '最后跟进时间',
                'form_type' => 'datetime',
                'width' => '',
                'is_hidden' => 0,
            ],
        ],
        'crm_contract' => [
            [
                'field' => 'check_status',
                'name' => '审核状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'last_record',
                'name' => '跟进记录',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'last_time',
                'name' => '最后跟进时间',
                'form_type' => 'datetime',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'done_money',
                'name' => '已回款',
                'form_type' => 'floatnumber',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'un_money',
                'name' => '未回款',
                'form_type' => 'floatnumber',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'crm_receivables' => [
            [
                'field' => 'check_status',
                'name' => '审核状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'contract_money',
                'name' => '合同金额',
                'form_type' => 'floatnumber',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_supplier' => [
            [
                'field' => 'detail_address',
                'name' => '地址',
                'form_type' => 'map_address',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_purchase' => [
            [
                'field' => 'check_status',
                'name' => '审核状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_retreat' => [
            [
                'field' => 'check_status',
                'name' => '审核状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_product' => [
            [
                'field' => 'product_code',
                'name' => '产品编码',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'product_picture',
                'name' => '产品图片',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ],
            [
                'field' => 'sp_data_value',
                'name' => '产品规格',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_sale' => [
            [
                'field' => 'check_status',
                'name' => '审核状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_salereturn' => [
            [
                'field' => 'check_status',
                'name' => '审核状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_receipt' => [
            [
                'field' => 'state',
                'name' => '状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_outbound' => [
            [
                'field' => 'state',
                'name' => '状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_allocation' => [
            [
                'field' => 'check_status',
                'name' => '审核状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_inventory' => [
            [
                'field' => 'check_status',
                'name' => '审核状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_collection' => [
            [
                'field' => 'check_status',
                'name' => '审核状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
        'jxc_payment' => [
            [
                'field' => 'check_status',
                'name' => '审核状态',
                'form_type' => 'text',
                'width' => '',
                'is_hidden' => 0,
            ]
        ],
    ];

    protected function initialize()
    {
        $this->__db_prefix = Config::get('database.prefix');
    }

    /**
     * [getDataList 获取列表]
     * @param types  分类
     * @return    [array]
     * @author Michael_xu
     */
    public function getDataList($param)
    {
        $types = trim($param['types']);
        if (!in_array($types, $this->types_arr)) {
            $this->error = '参数错误';
            return false;
        }
        $map = $param;
        if ($types == 'oa_examine') {
            $map['types_id'] = $param['types_id'];
        }
        if ($param['types'] == 'crm_customer') {
            $map['field'] = array('not in', ['deal_status']);
        }
        if ($types == 'crm_receivables_plan') {
            $setting = Db::name('AdminField')->where('types', 'crm_receivables')->value('setting');
        }
        $list = Db::name('AdminField')->where($map)->order('form_position', 'asc')->select();
        $detailTableList = db('admin_field_extend')->field(['field', 'content'])->where('types', $types)->select();
        $detailTableData = [];
        foreach ($detailTableList as $key => $value) {
            $detailTableData[$value['field']] = !empty($value['content']) ? json_decode($value['content'], true) : [];
        }

        foreach ($list as $k => $v) {
            $list[$k]['setting'] = $v['setting'] ? explode(chr(10), $v['setting']) : [];
            if ($types == 'crm_receivables_plan') {
                $list[$k]['stting'] = $setting ? explode(chr(10), $v['setting']) : [];
            }
            if ($v['form_type'] == 'checkbox') {
                $list[$k]['default_value'] = $v['default_value'] ? explode(',', $v['default_value']) : array();
            }
            if ($v['form_type']=='date_interval') {
                $list[$k]['default_value'] = !empty($v['default_value']) ? explode(',',$v['default_value']) : [];
            }
            if($v['form_type']=='position'){
                $list[$k]['default_value'] = !empty($v['default_value']) ? json_decode($v['default_value'], true) : [];
            }
            if ($v['form_type'] == 'detail_table') {
                $list[$k]['fieldExtendList'] = !empty($detailTableData[$v['field']]) ? $detailTableData[$v['field']] : [];
            }
            if (!empty($v['form_position'])) {
                $coordinate = explode(',', $v['form_position']);
                $list[$k]['xaxis'] = (int)$coordinate[0];
                $list[$k]['yaxis'] = (int)$coordinate[1];
            }
            if (!empty($v['relevant'])) {
                $list[$k]['relevant'] = !empty($v['relevant']) ? (int)$v['relevant'] : [];
            }
            if (!empty($v['options'])) {
                $list[$k]['optionsData'] = json_decode($v['options'], true);
            } else {
                $list[$k]['options'] = $v['setting'];
            }
            // 处理数值范围字段
            $list[$k]['minNumRestrict'] = $v['min_num_restrict'];
            $list[$k]['maxNumRestrict'] = $v['max_num_restrict'];
            unset($list[$k]['min_num_restrict']);
            unset($list[$k]['max_num_restrict']);
        }

        return getFieldGroupOrderData((array)$list);
    }

    /**
     * [createData 创建自定义字段]
     * @param types 分类
     * @param field 字段名
     * @param name 字段标识名（字段注释）
     * @param form_type 字段类型
     * @param max_length 字段最大长度
     * @param default_value 默认值
     * @param setting 单选、下拉、多选类型的选项值
     * @return    [array]
     * @author Michael_xu
     */
    public function createData($types, $param)
    {
        if (!$types || !in_array($types, $this->types_arr) || !is_array($param)) {
            $this->error = '参数错误';
            return false;
        }
        # 公海数据
        $poolList = [];
        $poolData = [];
        if ($types == 'crm_customer') {
            $poolList = db('crm_customer_pool')->column('pool_id');
        }

        # 用户自定义字段
        $userFields = db('admin_user_field')->field(['id', 'datas'])->where('types', $types)->select();

        # 获取最大formAssistId
        $formAssistId = db('admin_field')->where('types', $types)->order('formAssistId', 'desc')->value('formAssistId');
        $formAssistId = !empty($formAssistId) ? $formAssistId : 1000;

        $error_message = [];
        $i = 0;
        foreach ($param as $k => $data) {
            // 设置$formAssistId值
            $formAssistId += 1;
            $data['formAssistId'] = $formAssistId;
            // 数值范围
            if (!empty($data['minNumRestrict'])) $data['min_num_restrict'] = $data['minNumRestrict'];
            if (!empty($data['maxNumRestrict'])) $data['max_num_restrict'] = $data['maxNumRestrict'];

            // 清除坐标
            unset($data['xaxis']);
            unset($data['yaxis']);
            unset($data['maxNumRestrict']);
            unset($data['minNumRestrict']);
            // 设置明细表格类型的默认值为空，防止为null的报错。
            if ($data['form_type'] == 'detail_table') {
                $data['default_value'] = '';
            }
            $i++;
            $data['types'] = $types;
            if ($types == 'oa_examine' && !$data['types_id']) {
                $error_message[] = $data['name'] . '参数错误';
            }
            $data['types_id'] = $data['types_id'] ?: 0;

            if (!in_array($data['form_type'], $this->formtype_arr)) {
                $error_message[] = $data['name'] . ',字段类型错误';
            }

            //生成字段名
            if (!$data['field']) $data['field'] = $this->createField($types, $types == 'oa_examine' ? 'oa_' : 'crm_');

            $rule = [
                'field' => ['regex' => '/^[a-z]([a-z]|_)+[a-z]$/i'],
//                'name' => 'require',
                'types' => 'require',
                'form_type' => 'require',
            ];
            $msg = [
                'field.regex' => '字段名称格式不正确！',
//                'name.require' => '字段标识必须填写',
                'types.require' => '分类必须填写',
                'form_type.require' => '字段类型必须填写',
            ];
            // 验证
            // $validate = validate($this->name);
            $validate = new Validate($rule, $msg);

            if (!$validate->check($data)) {
                $error_message[] = $validate->getError();
            } else {
                //单选、下拉、多选类型(使用回车符隔开)
                if (in_array($data['form_type'], ['radio', 'select', 'checkbox']) && $data['setting']) {
                    $data = $this->settingValue($data);
                }

                //表格类型
                if ($data['form_type'] == 'form' && $data['form_value']) {
                    $new_form_value = [];
                    foreach ($data['form_value'] as $form => $fromVal) {
                        $fromVal['field'] = 'form_' . $this->createField($types);
                        if (in_array($fromVal['form_type'], ['radio', 'select', 'checkbox']) && $fromVal['setting']) {
                            $fromVal = $this->settingValue($fromVal);
                        }
                    }
                    $new_form_value = $fromVal;
                    $data['form_value'] = $new_form_value;
                }

                # 处理日期区间、地址类型的默认数据
//                if (in_array($data['form_type'], ['position', 'date_interval']) && !empty($data['default_value'])) {
//                    $data['default_value'] = json_encode($data['default_value']);
//                }

                # 处理明细表格中的字段数据
                if ($data['form_type'] == 'detail_table' && !empty($data['fieldExtendList']) && $this->setDetailTableData($types, $data['field'], $data['fieldExtendList']) === false) {
                    $error_message[] = '创建明细表单失败！';
                }

                # 处理选项中的逻辑表单数据
//                if (in_array($data['form_type'], ['select', 'checkbox'])) {
//                    $data['options'] = !empty($data['options']) ? json_encode($data['options']) : '';
//                }

                # 设置描述文字类型的字段名称
                if (empty($data['name']) && $data['form_type'] == 'desc_text') {
                    $data['name'] = '描述文字';
                }
                unset($data['field_id']);

                if ($i > 1) {
                    $resField = $this->data($data)->allowField(true)->isUpdate(false)->save();
                } else {
                    $resField = $this->data($data)->allowField(true)->save();
                }
                # 处理公海字段数据
                if ($types == 'crm_customer') {
                    foreach ($poolList as $k1 => $poolId) {
                        $poolData[] = [
                            'pool_id' => $poolId,
                            'name' => $data['name'],
                            'field_name' => $data['field'],
                            'form_type' => $data['form_type'],
                            'is_null' => $data['is_null'],
                            'is_unique' => $data['is_unique'],
                            'is_hidden' => 1
                        ];
                    }
                }

                if ($types !== 'oa_examine') {
                    if ($resField) {
                        $this->tableName = $types;
                        $maxlength = '255';
                        $defaultvalue = $data['default_value'] ? "DEFAULT '" . $data['default_value'] . "'" : "DEFAULT NULL";
                        //根据字段类型，创建字段
                        switch ($data['form_type']) {
                            case 'address' :
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` VARCHAR(500) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '" . $data['name'] . "'";
                                break;
                            case 'radio' :
                            case 'select' :
                            case 'checkbox' :
                                $defaultvalue = $data['default_value'] ? "DEFAULT '" . $data['default_value'] . "'" : '';
                                $maxlength = 500;
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` VARCHAR( " . $maxlength . " ) CHARACTER SET utf8 COLLATE utf8_general_ci " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'textarea' :
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` TEXT COMMENT '" . $data['name'] . "'";
                                break;
                            case 'number' :
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` VARCHAR(255) NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'floatnumber' :
                                $defaultvalue = abs(intval($data['default_value'])) > 9999999999999999.99 ? 9999999999999999.99 : intval($data['default_value']);
                                $maxlength = 18;
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` decimal (" . $maxlength . ",2) DEFAULT '" . $defaultvalue . "' COMMENT '" . $data['name'] . "'";
                                break;
                            case 'date' :
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` DATE " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'datetime' :
                                $defaultvalue = $data['default_value'] ? "DEFAULT '" . strtotime($data['default_value']) . "'" : '';
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` int (11) " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'file' :
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` VARCHAR ( " . $maxlength . " ) CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT '" . $data['name'] . "'";
                                break;
                            case 'form' :
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT '" . $data['name'] . "'";
                                break;
                            case 'boolean_value' :
                                # 布尔值类型字段
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` TINYINT(1) unsigned NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'percent' :
                                # 百分数类型字段
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` VARCHAR(255) NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'position' :
                                # 地址类型字段，存放在相应的数据扩展表中，比如crm_customer_extra_data
                                $addressValue = [];
                                if (!empty($data['default_value'])) {
                                    $data['default_value'] = json_decode($data['default_value'], true);
                                    foreach ($data['default_value'] as $kk => $vv) {
                                        if (!empty($vv['name'])) $addressValue[] = $vv['name'];
                                    }
                                }
                                $defaultValue = !empty($addressValue) ? "DEFAULT '" . implode(',', $addressValue) . "'" : "DEFAULT NULL";
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL  " . $defaultValue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'location' :
                                # 定位类型字段，存放在相应的数据扩展表中，比如crm_customer_extra_data
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'handwriting_sign' :
                                # 手写签名类型字段
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` INT(10) unsigned NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'date_interval' :
                                # 日期区间类型字段，存放在相应的数据扩展表中，比如crm_customer_extra_data
                                $defaultValue = !empty($data['default_value']) ? "DEFAULT '" . implode('_', json_decode($data['default_value'], true)) . "'" : "DEFAULT NULL";
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL " . $defaultValue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'desc_text' :
                                # 描述文字类型字段
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` VARCHAR(1000) NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'detail_table' :
                                # 明细表格类型字段，存放在相应的数据扩展表中，比如crm_customer_extra_data
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            default :
                                $maxlength = 255;
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` ADD `" . $data['field'] . "` VARCHAR( " . $maxlength . " ) CHARACTER SET utf8 COLLATE utf8_general_ci " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                        }
                        if (!empty($this->queryStr)) {
                            $resData = Db::execute($this->queryStr);
                            if ($resData === false) {
                                $this->where(['field_id' => $this->field_id])->delete();
                                $error_message[] = $data['name'] . ',添加失败';
                            }
                        }
                        # 处理用户自定义字段数据
                        if (!empty($userFields)) {
                            foreach ($userFields as $key => $value) {
                                if (in_array($data['form_type'], ['handwriting_sign', 'desc_text', 'detail_table'])) continue;

                                $userFields[$key]['datas'] = json_decode($value['datas'], true);
                                $userFields[$key]['datas'][$data['field']] = ['width' => '', 'is_hide' => 0];
                                $userFields[$key]['datas'] = json_encode($userFields[$key]['datas']);
                            }
                        }
                    } else {
                        $error_message[] = $data['name'] . ',添加失败';
                    }
                }
            }
        }
        # 更新用户自定义字段
        if (!empty($userFields)) {
            foreach ($userFields as $key => $value) {
                db('admin_user_field')->where('id', $value['id'])->update(['datas' => $value['datas']]);
            }
        }
        # 更新公海字段
        if (!empty($poolData)) {
            db('crm_customer_pool_field_setting')->insertAll($poolData);
        }
        if ($error_message) {
            $this->error = implode(';', $error_message);
            return false;
        }
        return true;
    }

    /**
     * [settingValue 单选、下拉、多选值]
     * @return    [array]
     * @author Michael_xu
     */
    public function settingValue($data, $controller = '')
    {
        //将英文逗号转换为中文逗号
        $new_setting = [];
        foreach ($data['setting'] as $k => $v) {
            $v = str_replace(')', '）', $v);
            $v = str_replace('(', '（', $v);
            $new_setting[] = str_replace(',', '，', $v);
        }
        $data['setting'] = implode(chr(10), $new_setting);
        //默认值
        $new_default_value = [];
        if ($data['default_value'] && $data['form_type'] == 'checkbox' && !empty($data['default_value'])) {
            foreach ($data['default_value'] as $k => $v) {
                $new_default_value[] = str_replace(',', '，', $v);
            }
            $data['default_value'] = implode(',', $new_default_value);
        }elseif($data['default_value'] && $data['form_type'] == 'select' && !empty($data['default_value'])){
            $data['default_value'] =  $data['default_value'];
        } else {
            $data['default_value'] = '';
        }
        return $data;
    }

    /**
     * [updateDataById 編輯自定义字段]
     * @param types 分类
     * @param field 字段名
     * @param name 字段标识名（字段注释）
     * @param form_type 字段类型
     * @param max_length 字段最大长度
     * @param default_value 默认值
     * @return    [array]
     * @author Michael_xu
     */
    public function updateDataById($param, $types = '')
    {
        $error_message = [];
        if (!is_array($param)) {
            $this->error = '参数错误';
            return false;
        }
        // 查询老数据
        $oldData = [];
        if (!empty($types) && $types == 'crm_customer') {
            $oldList = db('admin_field')->field(['field', 'name'])->where('types', $types)->select();
            foreach ($oldList as $key => $value) {
                $oldData[$value['field']] = $value['name'];
            }
        }
        // 获取最大formAssistId
        $formAssistId = db('admin_field')->where('types', $types)->order('formAssistId', 'desc')->value('formAssistId');
        $formAssistId = !empty($formAssistId) ? $formAssistId : 1000;
        $i = 0;
        foreach ($param as $data) {
            // 设置formAssistId
            if (empty($data['formAssistId'])) {
                $formAssistId += 1;
                $data['formAssistId'] = $formAssistId;
            }
            // 数值范围
            if (!empty($data['minNumRestrict'])) {
                $data['min_num_restrict'] = $data['minNumRestrict'];
            }else{
                $data['min_num_restrict']='';
            }
            if (!empty($data['maxNumRestrict'])){
                $data['max_num_restrict'] = $data['maxNumRestrict'];
            }else{
                $data['max_num_restrict'] = '';
            }
            // 清除坐标
            unset($data['xaxis']);
            unset($data['yaxis']);
            unset($data['maxNumRestrict']);
            unset($data['minNumRestrict']);
            unset($data['stting']);
            // 设置明细表格类型的默认值为空，防止为null的报错。
            if ($data['form_type'] == 'detail_table') {
                $data['default_value'] = '';
            }
            $i++;
            $field_id = intval($data['field_id']);
            if (!$field_id) {
                $error_message[] = $data['name'] . ',参数错误';
            }
            $dataInfo = $this->get($field_id);
            if (!$dataInfo) {
                $error_message[] = $data['name'] . '参数错误';
            }
            // $error_message[] = $data['name'].',该字段不能编辑';
            $data['types'] = $dataInfo['types'];
            //单选、下拉、多选类型(使用回车符隔开)
            if (in_array($data['form_type'], ['radio', 'select', 'checkbox']) && $data['setting']) {
                //将英文逗号转换为中文逗号
                $data = $this->settingValue($data, 'update');
            }
            // 验证
            $validate = validate($this->name);
            if (!$validate->check($data)) {
                $error_message[] = $validate->getError();
            } else {
                // unset($data['field']);
                $data['field'] = $dataInfo['field'];
                unset($data['operating']);
//                $box_form_type = array('checkbox', 'select', 'radio');
//                if ((in_array($dataInfo['form_type'], $box_form_type) && !in_array($data['form_type'], $box_form_type)) || !in_array($dataInfo['form_type'], $box_form_type)) {
//                    unset($data['form_type']);
//                }

                # 处理日期区间、地址类型的默认数据
//                if (in_array($data['form_type'], ['position']) && !empty($data['default_value'])) {
//                    dump($data['default_value']);
//                    p(json_encode($data['default_value']));
//                    $data['default_value'] = json_encode($data['default_value']);
//                }

                # 处理明细表格中的字段数据
                if ($data['form_type'] == 'detail_table' && !empty($data['fieldExtendList']) && $this->setDetailTableData($data['types'], $data['field'], $data['fieldExtendList']) === false) {
                    $error_message[] = '创建明细表单失败！';
                }
                unset($data['fieldExtendList']);

                # 处理选项中的逻辑表单数据
//                if (in_array($data['form_type'], ['select', 'checkbox'])) {
//                    $data['options'] = json_encode($data['options'], JSON_NUMERIC_CHECK);
//                }


                // $resField = $this->allowField(true)->save($data, ['field_id' => $field_id]);
                unset($data['showSetting']);
                unset($data['componentName']);
                unset($data['is_deleted']);
                $data['update_time'] = time();
                $resField = db('admin_field')->where(['field_id' => $field_id])->update($data);
                if ($dataInfo['types'] !== 'oa_examine') {
                    if ($resField) {
                        # 更新公海字段
                        if (!empty($oldData[$data['field']]) && $data['name'] != $oldData[$data['field']]['name']) {
                            db('crm_customer_pool_field_setting')->where('field_name', $data['field'])->update(['name' => $data['name'], 'is_null' => $data['is_null'], 'is_unique' => $data['is_unique']]);
                        }
                        //actionLog($field_id); //操作日志
                        $this->tableName = $dataInfo['types'];
                        $maxlength = '255';
                        $defaultvalue = $data['default_value'] ? "DEFAULT '" . $data['default_value'] . "'" : "DEFAULT NULL";
                        //根据字段类型，创建字段
                        switch ($dataInfo['form_type']) {
                            case 'address' :
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` CHANGE `" . $dataInfo['field'] . "` `" . $data['field'] . "` VARCHAR( 500 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '" . $data['name'] . "'";
                                break;
                            case 'radio' :
                            case 'select' :
                            case 'checkbox' :
                                $defaultvalue = $data['default_value'] ? "DEFAULT '" . $data['default_value'] . "'" : '';
                                $maxlength = 500;
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` CHANGE `" . $dataInfo['field'] . "` `" . $data['field'] . "` VARCHAR( " . $maxlength . " ) CHARACTER SET utf8 COLLATE utf8_general_ci " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'textarea' :
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` CHANGE `" . $dataInfo['field'] . "` `" . $data['field'] . "` TEXT COMMENT '" . $data['name'] . "'";
                                break;
                            case 'number' :
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` CHANGE `" . $dataInfo['field'] . "` `" . $data['field'] . "` VARCHAR(255) NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'floatnumber' :
                                $defaultvalue = abs(intval($data['default_value'])) > 9999999999999999.99 ? 9999999999999999.99 : intval($data['default_value']);
                                $maxlength = 18;
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` CHANGE `" . $dataInfo['field'] . "` `" . $data['field'] . "` decimal (" . $maxlength . ",2) DEFAULT '" . $defaultvalue . "' COMMENT '" . $data['name'] . "'";
                                break;
                            case 'date' :
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` CHANGE `" . $dataInfo['field'] . "` `" . $data['field'] . "` DATE " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'datetime' :
                                $defaultvalue = $data['default_value'] ? "DEFAULT '" . strtotime($data['default_value']) . "'" : '';
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` CHANGE `" . $dataInfo['field'] . "` `" . $data['field'] . "` int (11) " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'file' :
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` CHANGE `" . $dataInfo['field'] . "` `" . $data['field'] . "` VARCHAR ( " . $maxlength . " ) CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT '" . $data['name'] . "' ";
                                break;
                            case 'boolean_value' :
                                # 布尔值类型字段
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` MODIFY COLUMN `" . $data['field'] . "` TINYINT(1) unsigned NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'percent' :
                                # 百分数类型字段
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` MODIFY COLUMN `" . $data['field'] . "` VARCHAR(255) NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'position' :
                                # 地址类型字段，存放在相应的数据扩展表中，比如crm_customer_extra_data
                                $addressValue = [];
                                if (!empty($data['default_value'])) {
                                    $data['default_value'] = json_decode($data['default_value'], true);
                                    foreach ($data['default_value'] as $kk => $vv) {
                                        if (!empty($vv['name'])) $addressValue[] = $vv['name'];
                                    }
                                }
                                $defaultValue = !empty($addressValue) ? "DEFAULT '" . implode(',', $addressValue) . "'" : "DEFAULT NULL";
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` MODIFY COLUMN `" . $data['field'] . "` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL " . $defaultValue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'location' :
                                # 定位类型字段，存放在相应的数据扩展表中，比如crm_customer_extra_data
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` MODIFY COLUMN `" . $data['field'] . "` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'handwriting_sign' :
                                # 手写签名类型字段
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` MODIFY COLUMN `" . $data['field'] . "` INT(10) unsigned NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'date_interval' :
                                # 日期区间类型字段，存放在相应的数据扩展表中，比如crm_customer_extra_data
                                $defaultValue = !empty($data['default_value']) ? "DEFAULT '" . implode('_', json_decode($data['default_value'], true)) . "'" : "DEFAULT NULL";
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` MODIFY COLUMN `" . $data['field'] . "` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL " . $defaultValue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'desc_text' :
                                # 描述文字类型字段
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` MODIFY COLUMN `" . $data['field'] . "` VARCHAR(1000) NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            case 'detail_table' :
                                # 明细表格类型字段，存放在相应的数据扩展表中，比如crm_customer_extra_data
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` MODIFY COLUMN `" . $data['field'] . "` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                            default :
                                $maxlength = 255;
                                $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` CHANGE `" . $dataInfo['field'] . "` `" . $data['field'] . "` VARCHAR( " . $maxlength . " ) CHARACTER SET utf8 COLLATE utf8_general_ci " . $defaultvalue . " COMMENT '" . $data['name'] . "'";
                                break;
                        }
                        if (!empty($this->queryStr)) {
                            $resData = Db::execute($this->queryStr);
                            if ($resData === false) {
                                $error_message[] = $data['name'] . ',修改失败';
                            }
                        }
                    } else {
                        $error_message[] = $data['name'] . ',修改失败';
                    }
                }
            }
        }
        if ($error_message) {
            $this->error = implode(';', $error_message);
            return false;
        }
        return true;
    }

    /**
     * [delDataById 删除自定义字段] 删除逻辑数据不可恢复，谨慎操作
     * @param $id [array] 字段ID
     * @param $types 分类
     * @author Michael_xu
     */
    public function delDataById($ids, $types = '')
    {
        if (!is_array($ids)) {
            $ids[] = $ids;
        }
        # 删除公海字段的条件
        $poolWhere = [];
        $delMessage = [];
        foreach ($ids as $id) {
            $dataInfo = [];
            $dataInfo = $this->get($id);

            if ($dataInfo) {
                //operating ： 0改删，1改，2删，3无
                if (in_array($dataInfo['operating'], ['1', '3'])) {
                    $delMessage[] = $dataInfo['name'] . ',系统字段，不能删除';
                } else {
                    $resDel = $this->where(['field_id' => $id])->delete(); //删除自定义字段信息
                    // 客户模块下的栏目，成功删除自定义字段后做相应的处理
                    if ($resDel && $dataInfo['types'] !== 'oa_examine') {
                        $this->tableName = $dataInfo['types'];
                        if ($dataInfo['form_type'] == 'img') {
                            //图片类型需删除两个字段
                            // $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` DROP `".$dataInfo['field']."`,"." DROP `thumb_".$dataInfo['field']."`";
                            $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` DROP `" . $dataInfo['field'] . "`";
                        } else {
                            $this->queryStr = "ALTER TABLE `" . $this->__db_prefix . $this->tableName . "` DROP `" . $dataInfo['field'] . "`";
                        }
                        $resData = Db::execute($this->queryStr); //删除表字段数据
                        if (!$resData) {
                            $delMessage[] = $dataInfo['name'] . ',删除失败';
                        }
                        
                        //删除列表字段配置数据
                        $userFieldList = db('admin_user_field')->where(['types' => $dataInfo['types']])->select();
                        foreach ($userFieldList as $key => $val) {
                            $datas = json_decode($val['datas'], true);
                            if ($datas) {
                                foreach ($datas as $k => $v) {
                                    $datas[$k]['field'] = $k;
                                    unset($datas[$dataInfo['field']]);
                                }
                                $dataUserField = [];
                                $dataUserField['value'] = $datas;
                                $dataUserField['hide_value'] = [];
                                $userFieldModel = new \app\admin\model\UserField();
                                $resUserField = $userFieldModel->updateConfig($dataInfo['types'], $dataUserField, $val['id']);
                                // $resUserField = model('UserField')->updateConfig($dataInfo['types'], $dataUserField, $val['id']);
                            }
                        }
                        //删除场景字段数据
                        $sceneFieldList = db('admin_scene')->where(['types' => $dataInfo['types']])->select();
                        foreach ($sceneFieldList as $key => $val) {
                            $data = json_decode($val['data'], true);
                            if ($data) {
                                foreach ($data as $k => $v) {
                                    unset($data[$dataInfo['field']]);
                                }
                                $data = $data ?: [];
                                $sceneModel = new \app\admin\model\Scene();
                                $sceneModel->updateData($data, $val['scene_id']);
                            }
                        }

                        // 处理删除公海字段的条件
                        if (!empty($types) && $types == 'crm_customer' && !empty($dataInfo['field'])) $poolWhere[] = $dataInfo['field'];

                    }
                    // 删除字段成功后做相应处理
                    if ($resDel) {
                        // 删除明细表格字段数据
                        if (!empty($dataInfo['form_type']) && $dataInfo['form_type'] == 'detail_table') {
                            db('admin_field_extend')->where(['types' => $dataInfo['types'], 'field' => $dataInfo['field']])->delete();
                        }

                        // 删除相应模块扩展数据：crm_leads_data、crm_customer_data、oa_examine ...
                        if (!empty($types)) db($types . '_data')->where('field', $dataInfo['field'])->delete();
                    }
                    // 删除失败
                    if (!$resDel) {
                        $delMessage[] = $dataInfo['name'] . ',删除失败';
                    }
                }
            }
        }
        # 删除公海字段
        if (!empty($poolWhere)) {
            db('crm_customer_pool_field_setting')->whereIn('field_name', $poolWhere)->delete();
        }

        return $delMessage ? implode(';', $delMessage) : '';
    }

    /**
     * [createField 随机生成自定义字段名]
     * @param $field_str 字段名前缀
     * @param $types 分类
     * @author Michael_xu
     */
    public function createField($types = '', $field_str = 'crm_')
    {
        for ($i = 1; $i <= 6; $i++) {
            $field_str .= chr(rand(97, 122));
        }
        //验证字段名是否已存在
        if ($this->where(['types' => $types, 'field' => $field_str])->find()) {
            $this->createField($types);
        }
        return $field_str;
    }

    /**
     * [field 获取自定义字段信息]
     * @param $types 分类
     * @param $dataInfo 数据展示
     * @param $map  查询条件
     * @param form_type  字段类型 （’text’,’textarea’,’mobile’,’email’等）
     * @param default_value  默认值
     * @param max_length  输入最大长度
     * @param is_unique  1时，唯一性验证
     * @param is_null  1时，必填
     * @param input_tips  输入框提示内容
     * @param setting  设置 （单选、下拉、多选的选项值，使用回车分隔）
     * @author Michael_xu
     */
    public function field($param, $dataInfo = [])
    {
        $apiCommon = new ApiCommon();
        $userModel = new \app\admin\model\User();
        $structureModel = new \app\admin\model\Structure();
        $fileModel = new \app\admin\model\File();
        $user_id = !empty($param['user_id']) ? $param['user_id'] : $apiCommon->userInfo['id'];
        $types = $param['types'];
        $types_id = $param['types_id'] ?: 0;
        $grantData = getFieldGrantData($user_id);
        $userLevel = isSuperAdministrators($user_id);
        if ($types == 'crm_customer_pool') $types = 'crm_customer';
        $map = $param['map'] ?: [];
        if (!in_array($types, $this->types_arr)) {
            $this->error = '参数错误';
            return false;
        }
        if ($types == 'oa_examine' && !$types_id) {
            $this->error = '参数错误';
            return false;
        } elseif ($types == 'admin_user') {
            return User::$import_field_list;
        }

        if (in_array($param['action'], array('index', 'view'))) {
            $map['types'] = array(array('eq', $types), array('eq', ''), 'or');
        } else {
            if ($param['types'] == 'crm_customer' && (in_array($param['action'], array('save', 'update', 'excel')))) {
                $map['field'] = array('not in', ['deal_status']);
            }
            $map['types'] = $types;
        }
        if ($param['controller'] == 'customer' && $param['action'] == 'pool') {
            $map['field'] = array('not in', ['owner_user_id']);
            $types = 'crm_customer_pool';
        }
        if ($param['action'] == 'excel') {
            $map['form_type'] = array('not in', ['file', 'form', 'user', 'structure', 'checkbox', 'deal_status', 'position', 'location', 'handwriting_sign', 'date_interval', 'detail_table', 'desc_text', 'boolean_value']);//删除了过滤structure  user字段类型数据 添加deal_status
        } elseif ($param['action'] == 'index') {
            $map['form_type'] = array('not in', ['file', 'form']);
        }
        $map['types_id'] = $types_id;
        $order = 'order_id asc, field_id asc';
        if ($param['action'] == 'index' || $param['action'] == 'pool') {
            $field_list = $this->getIndexFieldConfig($types, $param['user_id']);
            $field_list[] = [
                'field' => 'owner_user_structure_name',
                'name' => '所属部门',
                'form_type' => 'structure',
                'writeStatus' => 0,
                'is_hidden' => 1,
                'fieldName' => 'owner_user_structure_name'
            ];
            foreach ($field_list as $k => $v) {
                if($v['field']=='invoice_type'){
                    $field_list[$k]['setting']=$v['setting']?explode(chr(10), $v['setting']):[];
                }
                # 处理字段授权
                $field_list[$k]['writeStatus'] = 1;
                if (!$userLevel && $param['module'] == 'crm' && !empty($grantData[$param['types']])) {
                    $status = getFieldGrantStatus($v['field'], $grantData[$param['types']]);
                    # 查看权限
                    if ($status['read'] == 0) {
                        unset($field_list[(int)$k]);
                        continue;
                    }
                    $field_list[$k]['maskType'] = $status['maskType'];
                    # 编辑权限
                    $field_list[$k]['writeStatus'] = $status['write'];

                }
            }
            if ($param['types'] == 'crm_invoice') {
                $field_list[] = [
                    'field' => 'check_status',
                    'name' => '审核状态',
                    'form_type' => 'text',
                    'writeStatus' => 0,
                    'fieldName' => 'check_status'
                ];
                $field_list[] = [
                    'field' => 'invoice_number',
                    'name' => '发票号码',
                    'form_type' => 'text',
                    'writeStatus' => 0,
                    'fieldName' => 'invoice_number'
                ];
                $field_list[] = [
                    'field' => 'real_invoice_date',
                    'name' => '实际开票日期',
                    'form_type' => 'date',
                    'writeStatus' => 0,
                    'fieldName' => 'real_invoice_date'
                ];
                $field_list[] = [
                    'field' => 'logistics_number',
                    'name' => '物流单号',
                    'form_type' => 'text',
                    'writeStatus' => 0,
                    'fieldName' => 'logistics_number'
                ];
            }
        } else {
            $fields = 'field_id,field,types,name,max_num_restrict as maxNumRestrict,min_num_restrict as minNumRestrict,form_type,default_value,is_unique,is_null,input_tips,setting,is_hidden,form_position,precisions,options,style_percent,formAssistId,remark';
            $field_list = db('admin_field')->field($fields)->where($map)->where('is_hidden', 0)->order($order)->select();

            // 获取X坐标值
            $x = $this->getFormPositionXValue($types, $types_id);
            # 详情页面增加负责人字段
//            if ($param['action'] == 'read' && !in_array($param['types'], ['crm_visit', 'crm_product', 'oa_examine', 'crm_invoice'])) {
//                $field_list[] = [
//                    'field' => 'owner_user_id',
//                    'name' => '负责人',
//                    'form_type' => 'user',
//                    'writeStatus' => 0,
//                    'fieldName' => 'owner_user_name',
//                    'value' => $dataInfo['owner_user_name'],
//                ];
//            }

            //客户
            if (in_array($param['types'], ['crm_customer'])) {
                $new_field_list[] = [
                    'field' => 'customer_address',
                    'name' => '地区定位',
                    'form_type' => 'map_address',
                    'default_value' => '',
                    'is_unique' => 0,
                    'is_null' => 0,
                    'input_tips' => '',
                    'setting' => [],
                    'value' => [],
                    'style_percent' => 100,
                    'xaxis' => $x,
                    'yaxis' => 0,
                    'form_position' => $x . ',0'
                ];
            }
            // 商机下产品
            if (in_array($param['types'], ['crm_business'])) {
                $new_field_list[] = [
                    'field' => 'product',
                    'name' => '产品',
                    'form_type' => 'product',
                    'default_value' => '',
                    'is_unique' => 0,
                    'is_null' => 0,
                    'input_tips' => '',
                    'setting' => [],
                    'value' => [],
                    'style_percent' => 100,
                    'xaxis' => $x,
                    'yaxis' => 0,
                    'form_position' => $x . ',0'
                ];
            }
            // 合同下产品
            if (in_array($param['types'], ['crm_contract'])) {
                $new_field_list[] = [
                    'field' => 'product',
                    'name' => '产品',
                    'form_type' => 'product',
                    'default_value' => '',
                    'is_unique' => 0,
                    'is_null' => 0,
                    'input_tips' => '',
                    'setting' => [],
                    'value' => [],
                    'style_percent' => 100,
                    'xaxis' => $x,
                    'yaxis' => 0,
                    'form_position' => $x . ',0'
                ];
            }
            # 产品基本信息增加负责人信息
//            if ($param['action'] == 'read' && $param['types'] == 'crm_product') {
//                $new_field_list[] = db('admin_field')->where(['types_id' => 0, 'field' => 'owner_user_id'])->find();
//            }
            if ($new_field_list) $field_list = array_merge(collection($field_list)->toArray(), $new_field_list);
            foreach ($field_list as $k => $v) {
                # 处理字段授权
                $field_list[$k]['writeStatus'] = 1;
                if (!$userLevel && $param['module'] == 'crm' && !empty($grantData[$param['types']])) {
                    $status = getFieldGrantStatus($v['field'], $grantData[$param['types']]);

                    # 查看权限
                    if (empty($status['read'])) {

                        unset($field_list[(int)$k]);
                        continue;
                    }

                    # 编辑权限
                    if ($param['action'] != 'save') $field_list[$k]['writeStatus'] = $status['write'];

                }
                if ($param['action'] == 'read') {
                    $field_list[$k]['maskType'] = $status['maskType'];
                    if($status['maskType']!=0){
                        $field_list[$k]['writeStatus'] = 0;
                    }
                }

                # （联系人，商机，合同，回款，回访）关联其他模块的字段在详情页面不允许修改；创建人、负责人不允许修改
                if ($param['action'] == 'read' && in_array($v['field'], ['customer_id', 'business_id', 'contacts_id', 'contract_id', 'create_user_id', 'owner_user_id', 'plan_id'])) {
                    $field_list[$k]['writeStatus'] = 0;
                }

                // 删除描述文字的name名称
                if ($v['form_type'] == 'desc_text') {
                    $field_list[$k]['name'] = '';
                }

                //处理setting内容
                $setting = [];
                $default_value = $v['default_value'];
                $value = [];
                if (in_array($v['form_type'], ['radio', 'select', 'checkbox'])) {
                    $setting = explode(chr(10), $v['setting']);
                    if ($v['form_type'] == 'checkbox') $default_value = $v['default_value'] ? explode(',', $v['default_value']) : [];
                }
                if ($v['field'] == 'order_date') {
                    $default_value = date('Y-m-d', time());
                }

                //地图类型
                if ($v['form_type'] == 'map_address') {
                    $value = [
                        'address' => $dataInfo['address'] ? explode(chr(10), $dataInfo['address']) : [],
                        'location' => $dataInfo['location'],
                        'detail_address' => $dataInfo['detail_address'],
                        'lng' => $dataInfo['lng'],
                        'lat' => $dataInfo['lat']
                    ];
                } elseif ($v['form_type'] == 'product') {
                    //相关产品类型
                    switch ($param['types']) {
                        case 'crm_business' :
                            $rProduct = db('crm_business_product');
                            $r_id = 'business_id';
                            break;
                        case 'crm_contract' :
                            $rProduct = db('crm_contract_product');
                            $r_id = 'contract_id';
                            break;
                        default :
                            break;
                    }
                    $newProductList = [];
                    $productList = $rProduct->where([$r_id => $param['action_id']])->select();
                    foreach ($productList as $key => $product) {
                        $product_info = [];
                        $category_name = '';
                        $product_info = db('crm_product')->where(['product_id' => $product['product_id']])->field('product_id,name,category_id')->find();
                        $category_name = db('crm_product_category')->where(['category_id' => $product_info['category_id']])->value('name');
                        $productList[$key]['name'] = $product_info['name'] ?: '';
                        $productList[$key]['category_id_info'] = $category_name ?: '';
                    }
                    $value = [
                        'product' => $productList,
                        'total_price' => $dataInfo['total_price'],
                        'discount_rate' => $dataInfo['discount_rate']
                    ];
                } elseif ($v['form_type'] == 'user') {
                    $value = $userModel->getListByStr($dataInfo[$v['field']]) ?: [];
                    // if (empty($value)) $default_value = $userModel->getListByStr($param['user_id']) ?: [];
                } elseif ($v['form_type'] == 'single_user') {
                    # 单用户
                    $userInfo = $userModel->getListByStr($dataInfo[$v['field']]);
                    $value = !empty($userInfo[0]) ? $userInfo[0] : [];
                    if (empty($value)) {
                        $userInfo = $userModel->getListByStr($param['user_id']);
                        $default_value = !empty($userInfo[0]) ? $userInfo[0] : [];
                    }
                } elseif ($v['form_type'] == 'structure') {
                    $value = $structureModel->getListByStr($dataInfo[$v['field']]) ?: [];
                } elseif ($v['form_type'] == 'file') {
                    $fileIds = [];
                    $fileIds = stringToArray($dataInfo[$v['field']]);
                    $whereFile = [];
                    $whereFile['module'] = 'other';
                    $whereFile['module_id'] = 1;
                    $whereFile['file_id'] = ['in', $fileIds];
                    $fileList = $fileModel->getDataList($whereFile, 'all');
                    $value = $fileList['list'] ?: [];
                } elseif ($v['form_type'] == 'customer') {
                    $value = $dataInfo[$v['field']] ? db('crm_customer')->where(['customer_id' => $dataInfo[$v['field']]])->field('customer_id,name')->select() : [];
                } elseif ($v['form_type'] == 'business') {
                    $value = $dataInfo[$v['field']] ? db('crm_business')->where(['business_id' => $dataInfo[$v['field']]])->field('business_id,name')->select() : [];
                } elseif ($v['form_type'] == 'contacts') {
                    $value = $dataInfo[$v['field']] ? db('crm_contacts')->where(['contacts_id' => $dataInfo[$v['field']]])->field('contacts_id,name')->select() : [];
                } elseif ($v['form_type'] == 'contract') {
                    $value = $dataInfo[$v['field']] ? db('crm_contract')->where(['contract_id' => $dataInfo[$v['field']]])->field('contract_id,num')->select() : [];
                } elseif ($v['form_type'] == 'floatnumber' && $v['field'] == 'contract_money' && $types == 'crm_invoice') {
                    $contractMoney = db('crm_invoice')->alias('invoice')
                        ->join('__CRM_CONTRACT__ contract', 'invoice.contract_id = contract.contract_id', 'LEFT')
                        ->where('invoice.invoice_id', $param['action_id'])->value('contract.money');
                    $value = $contractMoney;
                } elseif ($v['form_type'] == 'category') {
                    //产品类别
                    if ($param['action'] == 'read') {
                        $category_name = db('crm_product_category')->where(['category_id' => $dataInfo['category_id']])->value('name');
                        $value = $category_name ?: '';
                    } elseif ($param['action'] == 'update') {
                        $parentIds = [];
                        if (!empty($dataInfo['category_id'])) {
                            $parentIds = $this->getProductParentIds($dataInfo['category_id']);
                            $parentIds = array_reverse($parentIds);
                            array_push($parentIds, $dataInfo['category_id']);
                        }
                        $value = $parentIds;
                    } else {
                        $categoryModel = new \app\crm\model\ProductCategory();
                        $value = $categoryModel->getDataList('tree');
                    }
                } elseif ($v['form_type'] == 'business_type') {
                    //商机状态组
                    $businessStatusModel = new \app\crm\model\BusinessStatus();
                    $userInfo = $userModel->getUserById($user_id);
                    $setting = db('crm_business_type')
                        ->where('status', 1)
                        ->where('is_display', 1)
                        ->where(function ($query) use ($userInfo) {
                            $query->where(['structure_id' => ['like', '%,' . $userInfo['structure_id'] . ',%']]);
                            $query->whereOr('structure_id', '');
                        })->select();
                    foreach ($setting as $key => $val) {
                        $setting[$key]['statusList'] = $businessStatusModel->getDataList($val['type_id'], 0);
                    }
                    $setting = $setting ?: [];
                    if ($param['action'] == 'read') {
                        $value = $dataInfo[$v['field']] ? db('crm_business_type')->where(['type_id' => $dataInfo[$v['field']]])->value('name') : '';
                    } else {
                        $value = (int)$dataInfo[$v['field']] ?: '';
                    }
                } elseif ($v['form_type'] == 'business_status') {
                    //商机阶段
                    if ($param['action'] == 'read') {
                        $value = $dataInfo[$v['field']] ? db('crm_business_status')->where(['status_id' => $dataInfo[$v['field']]])->value('name') : '';
                    } else {
                        $businessStatusModel = new \app\crm\model\BusinessStatus();
                        $setting = $businessStatusModel->getDataList($dataInfo['type_id'], 1);
                        $value = (int)$dataInfo[$v['field']] ?: '';
                    }
                } elseif ($v['form_type'] == 'receivables_plan') {
                    //回款计划期数
                    $value = $dataInfo[$v['field']] ? db('crm_receivables_plan')->where(['plan_id' => $dataInfo[$v['field']]])->value('num') : '';
                } elseif ($v['form_type'] == 'business_cause' || $v['form_type'] == 'examine_cause') {
                    $whereTravel = [];
                    $whereTravel['examine_id'] = $dataInfo['examine_id'];
                    $travelList = db('oa_examine_travel')->where($whereTravel)->select() ?: [];
                    foreach ($travelList as $key => $val) {
                        $where = [];
                        $fileList = [];
                        $imgList = [];
                        $where['module'] = 'oa_examine_travel';
                        $where['module_id'] = $val['travel_id'];
                        $newFileList = [];
                        $newFileList = $fileModel->getDataList($where, 'all');
                        if ($newFileList['list']) {
                            foreach ($newFileList['list'] as $val1) {
                                if ($val1['types'] == 'file') {
                                    $fileList[] = $val1;
                                } else {
                                    $imgList[] = $val1;
                                }
                            }
                        }
                        $travelList[$key]['start_time'] = $val['start_time'] ? date('Y-m-d H:i:s', $val['start_time']) : null;
                        $travelList[$key]['end_time'] = $val['end_time'] ? date('Y-m-d H:i:s', $val['end_time']) : null;
                        $travelList[$key]['fileList'] = $fileList ?: [];
                        $travelList[$key]['imgList'] = $imgList ?: [];
                    }
                    $value = $travelList ?: [];
                } elseif ($v['form_type'] == 'checkbox') {
                    $value = isset($dataInfo[$v['field']]) ? stringToArray($dataInfo[$v['field']]) : [];
                } elseif ($v['form_type'] == 'date') {
                    $value = ($dataInfo[$v['field']] && $dataInfo[$v['field']] !== '0000-00-00') ? $dataInfo[$v['field']] : '';
                } elseif ($v['form_type'] == 'boolean_value') {
                    // 布尔类型
                    $value = !empty($dataInfo[$v['field']]) ? (string)$dataInfo[$v['field']] : '0';
                } elseif ($v['form_type'] == 'percent') {
                    // 百分数
                    $value = !empty($dataInfo[$v['field']]) ? $dataInfo[$v['field']] : '';
                } elseif ($v['form_type'] == 'website') {
                    // 网址
                    $value = !empty($dataInfo[$v['field']]) ? $dataInfo[$v['field']] : '';
                } elseif ($v['form_type'] == 'handwriting_sign') {
                    // 手写签名
                    $fileData = !empty($dataInfo[$v['field']]) ? db('admin_file')->where('file_id', $dataInfo[$v['field']])->find() : '';
                    if (!empty($fileData['file_path'])) $fileData['file_path'] = getFullPath($fileData['file_path']);
                    if (!empty($fileData['file_path_thumb'])) $fileData['file_path_thumb'] = getFullPath($fileData['file_path_thumb']);
                    $value = !empty($fileData) ? ['file_id' => $fileData['file_id'], 'url' => $fileData['file_path']] : "";
                } elseif ($v['form_type'] == 'desc_text') {
                    // 描述文字
                    $value = !empty($dataInfo[$v['field']]) ? $dataInfo[$v['field']] : $v['default_value'];
                } elseif ($v['form_type']=='location') {
                    // 地址、定位、日期区间、明细表格
                    $primaryKey = getPrimaryKeyName($param['types']);
                    $positionJson = !empty($dataInfo[$primaryKey]) ? db($param['types'] . '_data')->where([$primaryKey => $dataInfo[$primaryKey], 'field' => $v['field']])->value($param['types'] == 'oa_examine' ? 'value' : 'content') : '';
                    $positionData = !empty($positionJson) ? json_decode($positionJson, true) : '';
                    $value = $positionData;
                } elseif($v['form_type']=='detail_table'){
//                    $fieldGrant = db('admin_field_mask')->where('types', 'contract')->select();
                    $primaryKey = getPrimaryKeyName($param['types']);
                    $positionJson = !empty($dataInfo[$primaryKey]) ? db($param['types'] . '_data')->where([$primaryKey => $dataInfo[$primaryKey], 'field' => $v['field']])->value($param['types'] == 'oa_examine' ? 'value' : 'content') : '';
                    $positionData = !empty($positionJson) ? json_decode($positionJson, true) : '';
                    foreach ($positionData as $kk => $val){
                        foreach ($val as $key => $values){
                            if($values['form_type']=='user'){
                                $positionData[$kk][$key]['value']= !empty($values['value'])?$userModel->getListByStr($values['value']) :[];
                            }
                            if($values['form_type']=='structure'){
                                $positionData[$kk][$key]['value']= !empty($values['value'])? $structureModel->getListByStr($values['value']) : [];
                            }
                            if($values['form_type']=='datetime' && is_numeric($values['value'])){
                                $positionData[$kk][$key]['value']= date('Y-m-d, H:i:s',$values['value']);
                            }
                            if($values['form_type']=='boolean_value'){
                                $positionData[$kk][$key]['value']= (string)$values['value'];
                            }
                            if($values['form_type']=='file'){
                                $fileIds = stringToArray($values['value']);
                                $whereFile = [];
                                $whereFile['module'] = 'other';
                                $whereFile['module_id'] = 1;
                                $whereFile['file_id'] = ['in', $fileIds];
                                $fileList = $fileModel->getDataList($whereFile, 'all');
                                $positionData[$kk][$key]['value'] = $fileList['list'] ?: [];
                            }
//                            foreach ($fieldGrant as $val) {
//                                if (in_array($val['statue_type'], [1, 3]) && $val['form_type'] == ['mobile']) {
//                                    $positionData[$kk][$key]['value'] = !empty($values['value']) ? (string)substr_replace($values['value'], '*', 2, 4) : null;
//                                } elseif (in_array($val['statue_type'], [1, 3]) && $val['form_type'] == ['email']) {
//                                    $email_array = explode("@", $values['value']);
//                                    $str = substr_replace($email_array[0], '*', 1);
//                                    $positionData[$kk][$key]['value'] = !empty($values['value']) ? (string)$str . $email_array[1] : null;
//                                } elseif (in_array($val['statue_type'], [1, 3]) && in_array($val['form_type'],['position','floatnumber'])) {
//                                    $positionData[$kk][$key]['value'] = !empty($dataInfo[$val['fiele_id']]) ? (string)substr_replace($values['value'], '*',0,strlen($values['value'])) : null;
//                                }
//                            }
                            $positionData[$kk][$key]['optionsData']=!empty($field_list[$k]['options']) ? json_decode($field_list[$k]['options'], true) : '';
                        }
                    }

                    $value = $positionData;
                    if ($v['form_type'] == 'detail_table') {
                        $content = db('admin_field_extend')->where(['types' => $types, 'field' => $v['field']])->value('content');
                        $content=json_decode($content, true);
                        foreach ($content as &$vv){
                            $vv['optionsData']=!empty($field_list[$k]['options']) ? json_decode($field_list[$k]['options'], true) : '';
                        }
                        $field_list[$k]['fieldExtendList'] = $content;
                    }
                } elseif($v['form_type']=='position'){
                    // 地址
                    $default_value = !empty($v['default_value']) ? json_decode($v['default_value'], true) : [];
                    if(!empty($dataInfo[$v['field']])){
                       $position= explode(',',$dataInfo[$v['field']]);
                        for ($i=0; $i<count($position); $i++) {
                            $b[]['name'] =trim(json_encode($position[$i],JSON_UNESCAPED_UNICODE),'"');
                        }
                            $value =$b;
                    }
                } elseif($v['form_type']=='date_interval'){
                    if (!empty($dataInfo[$v['field']])) {
                        $position= explode('_',$dataInfo[$v['field']]);
                        $value =$position;
                    }

                    $default_value = !empty($v['default_value'])?explode(',',$v['default_value']):[];
                } else {
                    $value = isset($dataInfo[$v['field']]) ? $dataInfo[$v['field']] : '';
                }
//                $fieldGrant = db('admin_field_mask')->where('types', 'contract')->select();
//                foreach ($fieldGrant as $val) {
//                    if (in_array($val['statue_type'], [1, 3]) && $val['form_type'] == ['mobile']) {
//                        $value = !empty($dataInfo[$val['fiele_id']]) ? (string)substr_replace($dataInfo[$val['fiele_id']], '*', 2, 4) : null;
//                    } elseif (in_array($val['statue_type'], [1, 3]) && $val['form_type'] == ['email']) {
//                        $email_array = explode("@", $dataInfo[$val['fiele_id']]);
//                        $str = substr_replace($email_array[0], '*', 1);
//                        $value = !empty($dataInfo[$val['fiele_id']]) ? (string)$str . $email_array[1] : null;
//                    } elseif (in_array($val['statue_type'], [1, 3]) && in_array($val['form_type'],['position','floatnumber'])) {
//                        $value = !empty($dataInfo[$val['fiele_id']]) ? (string)substr_replace($dataInfo[$val['fiele_id']], '*',0,strlen($dataInfo[$val['fiele_id']])) : null;
//                    }
//                }
                $field_list[$k]['setting'] = $setting;
                $field_list[$k]['default_value'] = $default_value;
                $field_list[$k]['value'] = $value;
                $field_list[$k]['options'] = !empty($field_list[$k]['options']) ? $field_list[$k]['options'] : '';
                $field_list[$k]['optionsData'] = !empty($field_list[$k]['options']) ? json_decode($field_list[$k]['options'], true) : '';
            }
        }

        return array_values($field_list) ?: [];
    }

    private function getFormPositionXValue($types, $typesId)
    {
        $positionArray = db('admin_field')->where(['types' => $types, 'types_id' => $typesId, 'form_position' => [['neq', ''], ['not null'], 'AND']])->column('form_position');

        if (!empty($positionArray)) {
            $positionString = implode('-', $positionArray);
            $positionString = str_replace(',0', '', $positionString);
            $positionString = str_replace(',1', '', $positionString);
            $positionString = str_replace(',2', '', $positionString);
            $positionString = str_replace(',3', '', $positionString);

            $positionArray = explode('-', $positionString);

            return max($positionArray) + 1;
        }

        return 0;
    }

    /**
     * [fieldSearch 获取自定义字段高级筛选信息]
     * @param $types 分类
     * @param $map  查询条件
     * @param form_type  字段类型 （’text’,’textarea’,’mobile’,’email’等）
     * @param setting  设置 （单选、下拉、多选的选项值，使用回车分隔）
     * @author Michael_xu
     */
    public function fieldSearch($param)
    {
        $types = $param['types'];
        if (!in_array($types, $this->types_arr)) {
            $this->error = '参数错误';
            return false;
        }
        $userModel = new \app\admin\model\User();
        $user_id = $param['user_id'];
        $map['types'] = ['in', ['', $types]];
        $map['form_type'] = ['not in', ['file', 'pic', 'structure', 'form', 'business_status', 'detail_table', 'desc_text', 'handwriting_sign', 'date_interval']];
        $map['is_hidden'] = 0;
        $field_list = db('admin_field')
            ->where($map)
            ->whereOr(['types' => ''])
            ->field('field,name,form_type,setting')
            ->order('order_id asc, field_id asc, update_time desc')
            ->select();
        if (in_array($types, ['crm_contract', 'crm_receivables'])) {
            $field_arr = [
                '0' => [
                    'field' => 'check_status',
                    'name' => '审核状态',
                    'form_type' => 'select',
                    'setting' => '待审核' . chr(10) . '审核中' . chr(10) . '审核通过' . chr(10) . '审核失败' . chr(10) . '已撤回' . chr(10) . '未提交' . chr(10) . '已作废'
                ]
            ];
        }
        if (in_array($param['types'], ['crm_customer'])) {
            $field_arr = [
                '0' => [
                    'field' => 'address',
                    'name' => '地区定位',
                    'form_type' => 'address',
                    'setting' => []
                ]
            ];
        }
        if ($param['types'] == 'crm_customer') {
            $field_arr[] = [
                'field' => 'detail_address',
                'name' => '详细地址',
                'form_type' => 'text',
                'setting' => []
            ];
        }
        if (in_array($param['types'], ['crm_customer', 'crm_leads', 'crm_contacts', 'crm_business', 'crm_contract'])) {
            $field_arr[] = [
                'field' => 'last_time',
                'name' => '最后跟进时间',
                'form_type' => 'datetime',
                'setting' => []
            ];
        }
        if ($field_arr) $field_list = array_merge($field_list, $field_arr);
        foreach ($field_list as $k => $v) {
            //处理setting内容
            $setting = [];
            if (in_array($v['form_type'], ['radio', 'select', 'checkbox'])) {
                $setting = explode(chr(10), $v['setting']);
            }
            $field_list[$k]['setting'] = $setting;
            if ($v['field'] == 'customer_id') {
                $field_list[$k]['form_type'] = 'module';
                $field_list[$k]['field'] = 'customer_name';
            }
            if ($v['field'] == 'business_id') {
                $field_list[$k]['form_type'] = 'module';
                $field_list[$k]['field'] = 'business_name';
            }
            if ($v['field'] == 'contract_id') {
                $field_list[$k]['form_type'] = 'module';
                $field_list[$k]['field'] = 'contract_name';
            }
            if ($v['field'] == 'contacts_id') {
                $field_list[$k]['form_type'] = 'module';
                $field_list[$k]['field'] = 'contacts_name';
            }
            if ($v['field'] == 'warehouse_id' && in_array($param['types'], ['jxc_receipt', 'jxc_outbound'])) {
                $field_list[$k]['form_type'] = 'text';
            }
            if ($v['form_type'] == 'warehouse_cause') {
                $field_list[$k]['form_type'] = 'text';
            }

            if ($v['form_type'] == 'category') {

            } elseif ($v['form_type'] == 'business_type') {
                //商机状态组
                $businessStatusModel = new \app\crm\model\BusinessStatus();
                $userInfo = $userModel->getUserById($user_id);
                $setting = db('crm_business_type')
                    ->where(['structure_id' => ['like', ',%' . $userInfo['structure_id'] . '%,'], 'status' => 1])
                    ->whereOr('structure_id', '')
                    ->select();
                foreach ($setting as $key => $val) {
                    $setting[$key]['statusList'] = $businessStatusModel->getDataList($val['type_id'], 1);
                }
                $setting = $setting ?: [];
            }
            $field_list[$k]['setting'] = $setting;
        }
        return $field_list ?: [];
    }

    /**
     * 自定义字段验证规则
     * @param string $types 类型：crm_customer crm_business ...
     * @param int $types_id 自定义表types_id
     * @param string $action 操作：save update
     * @return array
     */
    public function validateField($types, $types_id = 0, $action = 'save')
    {
        $apiCommon = new ApiCommon();
        $userId = $apiCommon->userInfo['id'];
        $grantData = getFieldGrantData($userId);
        $userLevel = isSuperAdministrators($userId);
        $unField = ['update_time', 'create_time', 'create_user_id', 'owner_user_id'];
        $fieldList = $this->where(['types' => ['in', ['', $types]], 'types_id' => $types_id, 'field' => ['not in', $unField], 'form_type' => ['not in', ['checkbox', 'user', 'structure', 'file']]])->field('field,name,form_type,is_unique,is_null,max_length')->select();
        $validateArr = [];
        $rule = [];
        $message = [];
        foreach ($fieldList as $field) {
            # 字段授权
            if (!$userLevel && !empty($grantData[$types])) {
                $status = getFieldGrantStatus($field['field'], $grantData[$types]);

                # 没有字段查看权限或者编辑时没有字段修改权限就跳过验证
                if (empty($status['read']) || ($action != 'save' && empty($status['write']))) continue;
            }

            $rule_value = '';
            $scene_value = '';

            $max_length = $field['max_length'] ?: '';

            if ($field['is_null']) {
                $rule_value .= 'require';
                $message[$field['field'] . '.require'] = $field['name'] . '不能为空';
            }
            if ($field['form_type'] == 'number') {
                if ($rule_value) $rule_value .= '|';
                $rule_value .= 'number';
                $message[$field['field'] . '.number'] = $field['name'] . '必须是数字';
            } elseif ($field['form_type'] == 'email') {
                if ($rule_value) $rule_value .= '|';
                $rule_value .= 'email';
                $message[$field['field'] . '.email'] = $field['name'] . '格式错误';
            } elseif ($field['form_type'] == 'mobile ') {
                if ($rule_value) $rule_value .= '|';
                $rule_value .= 'regex:^1[3456789][0-9]{9}?$';
                $message[$field['field'] . '.regex'] = $field['name'] . '格式错误';
            }
            if ($field['is_unique']) {
                if ($rule_value) $rule_value .= '|';
                $rule_value .= 'unique:' . $types;
                $message[$field['field'] . '.unique'] = $field['name'] . '已经存在,不能重复添加';
            }
            if ($max_length) {
                if ($rule_value) $rule_value .= '|';
                $rule_value .= 'max:' . $max_length;
                $message[$field['field'] . '.max'] = $field['name'] . '不能超过' . $max_length . '个字符';
            }
            // if ($field['form_type'] == 'datetime') {
            // 	$rule_value .= 'date';
            // 	$message[$field['field'].'.date'] = $field['name'].'格式错误';
            // }
            if ($rule_value == 'require|') $rule_value = 'require';
            if (!empty($rule_value)) $rule[$field['field']] = $rule_value;

        }
        $validateArr['rule'] = $rule ?: [];
        $validateArr['message'] = $message ?: [];
        return $validateArr;
    }

    /**
     * [getIndexField 列表展示字段]
     * @param types 分类
     * @param excel 导出使用
     * @author Michael_xu
     */
    public function getIndexFieldConfig($types, $user_id, $types_id = '', $excel = '')
    {
        $userFieldModel = new \app\admin\model\UserField();
        $userFieldData = $userFieldModel->getConfig($types, $user_id);
        $userFieldData = $userFieldData ? json_decode($userFieldData, true) : [];
        $grantData = getFieldGrantData($user_id);
        $userLevel = isSuperAdministrators($user_id);
        $fieldList = $this->getFieldList($types, $types_id, $excel);
        $where = [];
        if ($userFieldData) {
            $fieldArr = [];
            $i = 0;
            foreach ($userFieldData as $k => $v) {
                if (empty($fieldList[$k])) {
                    unset($userFieldData[$k]);
                    continue;
                }

                if (empty($v['is_hide'])) {
                    $fieldArr[$i]['field'] = $k;
                    $fieldArr[$i]['name'] = $fieldList[$k]['name'];
                    $fieldArr[$i]['form_type'] = $fieldList[$k]['form_type'];
                    $fieldArr[$i]['width'] = $v['width'] ?: '';

                    $i++;
                }
            }
            $dataList = $fieldArr;
        } else {
            $dataList = $fieldList;
        }

        # 处理字段授权
        foreach ($dataList as $k => $v) {
            if (!$userLevel && !empty($grantData[$types])) {
                $status = getFieldGrantStatus($v['field'], $grantData[$types]);
                $dataList[(int)$k]['maskType']=$status['maskType'];
                # 查看权限
                if ($status['read'] == 0) unset($dataList[(int)$k]);
            }
        }
        return array_values($dataList) ?: [];
    }

    /**
     * 获取列表展示字段
     * @return $types_id 默认为空多自定义字段条件使用
     * @return void
     * @author Ymob
     * @datetime 2019-10-23 17:32:57
     */
    public function getFieldList($types, $types_id = '', $excel = '')
    {
        $newTypes = $types;
        $unField = ['-1'];
        if ($types == 'crm_customer_pool') {
            $newTypes = 'crm_customer';
            $unField = ['owner_user_id'];
        }
        if ($excel == 'excel') {
            if ($types == 'jxc_product') {
                $unField = ['product_picture'];
            }
            $where = [
                'types' => ['IN', ['', $newTypes]],
                'form_type' => ['not in', ['file', 'form', 'pic', 'deal_status', 'handwriting_sign', 'detail_table', 'desc_text']],
                'field' => ['not in', $unField],
                'types_id' => ['eq', $types_id],
                'is_hidden' => 0
            ];
        } else {
            $where = [
                'types' => ['IN', ['', $newTypes]],
                'form_type' => ['not in', ['file', 'form', 'desc_text', 'detail_table']],
                'field' => ['not in', $unField],
                'types_id' => ['eq', $types_id],
                'is_hidden' => 0
            ];
        }
        $fieldArr = $this
            ->where($where)
            ->field(['field', 'name', 'form_type', 'is_hidden','setting'])
            ->order('order_id', 'asc')
            ->select();

        $res = [];

        foreach ($fieldArr as $val) {
            $res[] = $val->toArray();
        }

        if ($types == 'oa_examine') {

        }
        if (isset($this->orther_field_list[$newTypes])) {
            foreach ($this->orther_field_list[$newTypes] as $val) {
                if($val['field'] != 'product_picture'){
                    $res[] = $val;
                }
            }
        }

        if ($types == 'crm_customer') {
            $res[] = [
                'field' => 'pool_day',
                'name' => '距进入公海天数',
                'form_type' => 'text',
                'is_hidden' => 0
            ];
            $res[] = [
                'field' => 'is_lock',
                'name' => '锁定状态',
                'form_type' => 'text',
                'is_hidden' => 0
            ];
        } elseif ($types == 'crm_receivables_plan') {
            $res[] = [
                'field' => 'real_money',
                'name' => '实际回款金额',
                'form_type' => 'floatnumber',
                'is_hidden' => 0
            ];
            $res[] = [
                'field' => 'real_data',
                'name' => '实际回款日期',
                'form_type' => 'date',
                'is_hidden' => 0
            ];
            $res[] = [
                'field' => 'un_money',
                'name' => '未回金额',
                'form_type' => 'floatnumber',
                'is_hidden' => 0
            ];
        }

        if ($types == 'jxc_product') {
            $res[] = [
                'field'     => 'product_picture',
                'name'      => '产品图片',
                'form_type' => 'pic',
                'is_hidden' => 0
            ];
        }
        return array_column($res, null, 'field');
    }

    /**
     * [getIndexField 列表展示字段]
     * @param types 分类
     * @param is_data 1 取数据时
     * @author Michael_xu
     */
    public function getIndexField($types, $user_id, $is_data = '')
    {
        $apiCommon = new ApiCommon();
        $userFieldModel = new \app\admin\model\UserField();
        $userFieldData = $userFieldModel->getConfig($types, $user_id);
        $userFieldData = $userFieldData ? json_decode($userFieldData, true) : [];
        $othor_un_field = array_column($this->orther_field_list[$types], 'field');
        $unField = array_merge(['pool_day', 'business-check', 'call'], $othor_un_field);
        $user_id = !empty($user_id) ? $user_id : $apiCommon->userInfo['id'];
        $grantData = getFieldGrantData($user_id);
        $userLevel = isSuperAdministrators($user_id);
        $where = [];
        if ($userFieldData) {
            $dataList = [];
            foreach ($userFieldData as $k => $v) {
                if (empty($v['is_hide']) && !in_array($k, $unField)) {
                    $dataList[] = $k;
                }
            }
        } else {
            $where['types'] = ['in', ['', $types]];
            $dataList = $this->where($where)->column('field');
        }
        $newList = $dataList;
        if ($is_data == 1) {
            switch ($types) {
                case 'crm_leads' :
                    $sysField = ['leads_id', 'create_time', 'update_time', 'create_user_id', 'owner_user_id', 'last_time', 'last_record'];
                    break;
                case 'crm_business' :
                    $newList = [];
                    foreach ($dataList as $v) {
                        $newList[] = 'business.' . $v;
                    }
                    $sysField = ['business.business_id', 'business.customer_id', 'business.create_time', 'business.update_time', 'business.status_id', 'business.type_id', 'business.create_user_id', 'business.owner_user_id', 'business.ro_user_id', 'business.rw_user_id', 'business.last_time', 'business.last_record'];
                    break;
                case 'crm_customer' :
                    $sysField = ['customer_id', 'deal_time', 'create_time', 'update_time', 'is_lock', 'deal_status', 'create_user_id', 'owner_user_id', 'ro_user_id', 'rw_user_id', 'address', 'detail_address', 'last_time', 'last_record'];
                    break;
                case 'crm_customer_pool' :
                    $sysField = ['customer_id', 'deal_time', 'create_time', 'update_time', 'create_user_id', 'owner_user_id', 'ro_user_id', 'rw_user_id', 'address', 'detail_address', 'last_time', 'last_record'];
                    break;
                case 'crm_contacts' :
                    $newList = [];
                    foreach ($dataList as $v) {
                        $newList[] = 'contacts.' . $v;
                    }
                    $sysField = ['contacts.contacts_id', 'contacts.customer_id', 'contacts.create_time', 'contacts.update_time', 'contacts.create_user_id', 'contacts.owner_user_id', 'contacts.last_time', 'contacts.last_record'];
                    break;
                case 'crm_contract' :
                    $newList = [];
                    foreach ($dataList as $v) {
                        $newList[] = 'contract.' . $v;
                    }
                    $sysField = ['contract.contract_id', 'contract.create_time', 'contract.update_time', 'contract.create_user_id', 'contract.owner_user_id', 'contract.check_status', 'contract.last_time', 'contract.last_record'];
                    break;
                case 'crm_receivables' :
                    $newList = [];
                    foreach ($dataList as $v) {
                        $newList[] = 'receivables.' . $v;
                    }
                    $sysField = ['receivables.receivables_id', 'receivables.customer_id', 'receivables.contract_id', 'receivables.plan_id', 'receivables.create_time', 'receivables.update_time', 'receivables.create_user_id', 'receivables.owner_user_id', 'receivables.check_status'];
                    break;
                case 'crm_product' :
                    $newList = [];
                    foreach ($dataList as $v) {
                        $newList[] = 'product.' . $v;
                    }
                    $sysField = ['product.product_id', 'product.category_id', 'product.create_time', 'product.update_time', 'product.create_user_id', 'product.owner_user_id'];
                    break;
                case 'crm_visit' :
                    $newList = [];
                    foreach ($dataList as $v) {
                        $newList[] = 'visit.' . $v;
                    }
                    $sysField = ['visit.visit_id', 'visit.owner_user_id', 'visit.status', 'visit.customer_id', 'visit.contract_id', 'visit.create_time', 'visit.update_time', 'visit.create_user_id', 'visit.visit_time', 'visit.contacts_id'];
                    break;
                case 'crm_invoice' :
                    $newList = [];
                    foreach ($dataList as $k => $v) {
                        $newList[] = 'invoice.' . $v;
                        if ($v == 'contract_money') {
                            unset($newList[$k]);
                        }
                    }
                    $sysField = ['invoice.invoice_id', 'invoice.owner_user_id', 'invoice.invoice_status', 'invoice.invoice_number', 'invoice.invoice_type', 'invoice.check_status', 'invoice.customer_id', 'invoice.contract_id', 'invoice.create_user_id', 'invoice.flow_id', 'invoice.real_invoice_date', 'invoice.logistics_number'];
                    break;
                case 'crm_receivables_plan' :
                    $newList = [];
                    foreach ($dataList as $k => $v) {
                        $newList[] = 'receivables_plan.' . $v;
                    }
                    $sysField = ['receivables_plan.plan_id', 'receivables_plan.num', 'receivables_plan.receivables_id', 'receivables_plan.status', 'receivables_plan.contract_id', 'receivables_plan.customer_id', 'receivables_plan.money', 'receivables_plan.return_date', 'receivables_plan.return_type', 'receivables_plan.remind', 'receivables_plan.remind_date', 'receivables_plan.remark',
                        'receivables_plan.create_user_id', 'receivables_plan.owner_user_id', 'receivables_plan.create_time', 'receivables_plan.update_time', 'receivables_plan.file', 'receivables_plan.is_dealt', 'receivables_plan.un_money', 'receivables_plan.real_data', 'receivables_plan.real_money'
                    ];
                    break;
            }
            $listArr = $sysField ? array_unique(array_merge($newList, $sysField)) : $dataList;
        } else {
            $listArr = $dataList;
        }
        $typesArray = explode('_', $types);
        $type = array_pop($typesArray);
        if (isset($this->orther_field_list[$types])) {
            foreach ($this->orther_field_list[$types] as $val) {
                if (in_array($type . '.' . $val['field'], $listArr) && !in_array($types, ['crm_contract', 'crm_business', 'crm_receivables'])) {
                    unset($listArr[array_search($type . '.' . $val['field'], $listArr)]);
                }
            }
        }

        # 处理字段授权
        foreach ($listArr as $k => $v) {
            if (!$userLevel && !empty($grantData[$types])) {
                $status = getFieldGrantStatus($v, $grantData[$types]);

                # 查看权限
                if ($status['read'] == 0) unset($listArr[(int)$k]);
            }
        }
        return $listArr ?: [];
    }

    /**
     * [checkFieldPer 判断权限]
     * @param types 分类
     * @author Michael_xu
     */
    public function checkFieldPer($module, $controller, $action, $user_id = '')
    {
        $userModel = new \app\admin\model\User();
        if (!checkPerByAction($module, $controller, $action)) return false;
        if ($user_id && !in_array($user_id, $userModel->getUserByPer($module, $controller, $action))) return false;
        return true;
    }

    /**
     * [getField 获取字段属性]
     * @param types 分类
     * @author Michael_xu
     */
    public function getField($param)
    {
        $types = $param['types'];
        $unFormType = $param['unFormType'];
        if (!in_array($types, $this->types_arr)) {
            return resultArray(['error' => '参数错误']);
        }
        $field_arr = [];
        //模拟自定义字段返回
        switch ($types) {
            case 'admin_user' :
                $field_arr = \app\hrm\model\Userdet::getField();
                break;
            default :
                $data = [];
                $data['types'] = $types;
                $data['user_id'] = $param['user_id'];
                if ($unFormType) $data['form_type'] = array('not in', $unFormType);
                $field_arr = $this->fieldSearch($data);
        }
        if ($types == 'crm_visit') {
            foreach ($field_arr as $key => $value) {
                if ($value['name'] == '负责人') unset($field_arr[(int)$key]);
            }
        }
        if ($types == 'crm_invoice') {
            $field_arr = array_merge($field_arr, $this->getInvoiceSearch());
        }
        return $field_arr;
    }

    /**
     * 自定义字段验重
     *
     * @param $field string 字段名称
     * @param $val array|string 字段值
     * @param $id int 数据id
     * @param $types string 模块类型
     * @return bool
     */
    public function getValidate($field, $val, $id, $types)
    {
        $val = is_array($val) ? $val : trim($val);
        if (!$val) {
            $this->error = '验证内容不能为空';
            return false;
        }
        if (!$field) {
            $this->error = '数据验证错误，请联系管理员！';
            return false;
        }
        if (!in_array($types, $this->types_arr)) {
            $this->error = '参数错误！';
            return false;
        }

        $field_info = db('admin_field')->where(['types' => $types, 'field' => $field])->find();
        if (!$field_info) {
            $this->error = '数据验证错误，请联系管理员！';
            return false;
        }

        // 人员、部门、文件、手写签名、描述文字、多选、明细表格跳过验证唯一性
        if (in_array($field_info['form_type'], ['user', 'structure', 'file', 'handwriting_sign', 'desc_text', 'checkbox', 'detail_table'])) return true;

        // 前端传来的定位数据有问题，在提交数据时做验证
        if ($field_info['form_type'] == 'location') return true;

        // 处理日期区间类型数据
        if ($field_info['form_type'] == 'date_interval') {
            $val = implode('_', $val);
        }

        // 处理地址类型数据
        if ($field_info['form_type'] == 'position') {
            $positionNames = array_column($val, 'name');
            $val = implode(',', $positionNames);
        }

        $dataModel = '';
        switch ($types) {
            case 'crm_leads' :
                $dataModel = new \app\crm\model\Leads();
                break;
            case 'crm_customer' :
                $dataModel = new \app\crm\model\Customer();
                break;
            case 'crm_contacts' :
                $dataModel = new \app\crm\model\Contacts();
                break;
            case 'crm_business' :
                $dataModel = new \app\crm\model\Business();
                break;
            case 'crm_product' :
                $dataModel = new \app\crm\model\Product();
                break;
            case 'crm_contract' :
                $dataModel = new \app\crm\model\Contract();
                break;
            case 'crm_receivables' :
                $dataModel = new \app\crm\model\Receivables();
                break;
            case 'crm_invoice' :
                $dataModel = new \app\crm\model\Invoice();
                break;
            case 'oa_examine' :
                $dataModel = db('oa_examine');
                break;
            case 'jxc_supplier' :
                $dataModel = new \app\jxc\model\Supplier();//db('jxc_supplier');
                break;
            case 'jxc_product' :
                $dataModel = db('jxc_product');
                break;
            case 'jxc_purchase' :
                $dataModel = db('jxc_purchase');
                break;
            case 'jxc_retreat' :
                $dataModel = db('jxc_retreat');
                break;
            case 'jxc_sale' :
                $dataModel = db('jxc_sale');
                break;
            case 'jxc_salereturn' :
                $dataModel = db('jxc_salereturn');
                break;
            case 'jxc_receipt' :
                $dataModel = db('jxc_receipt');
                break;
            case 'jxc_outbound' :
                $dataModel = db('jxc_outbound');
                break;
            case 'jxc_payment' :
                $dataModel = db('jxc_payment');
                break;
            case 'jxc_collection' :
                $dataModel = db('jxc_collection');
                break;
            case 'jxc_inventory' :
                $dataModel = db('jxc_inventory');
                break;
            case 'jxc_allocation' :
                $dataModel = db('jxc_allocation');
                break;
        }

        $where = [];
        $where[$field] = ['eq', $val];
        // 编辑时的验重
        if ($id) $where[$dataModel->getpk()] = ['neq', $id];

        if ($types == 'crm_product') {
            $where['delete_user_id'] = 0;
        }
        if ($types == 'jxc_product') {
            $where['is_del'] = 0;
        }
        
        $res = $dataModel->where($where)->find();

        if ($res) {
            $this->error = '该数据已存在，请修改后提交！';
            return false;
        }
        return true;
    }

    /**
     * [getFieldByFormType 根据字段类型获取字段数组]
     * @param types 分类
     * @author Michael_xu
     */
    public function getFieldByFormType($types, $form_type)
    {
        $fieldArr = $this->where(['types' => $types, 'form_type' => $form_type])->column('field');
        return $fieldArr ?: [];
    }

    /**
     * [getFormValueByField 格式化表格字段类型值]
     * @param $field 字段名
     * @param $value 字段值
     * @author Michael_xu
     */
    public function getFormValueByField($field, $value)
    {
        $formValue = db('admin_field')->where(['field' => $field])->value('form_value');
        $formValue = sort_select($formValue, 'order_id', 1); //二维数组排序
        $field = [];
        foreach ($formValue as $k => $v) {
            $field[] = $v['field'];
        }
        $data = [];
        foreach ($value as $k => $v) {
            foreach ($field as $key => $val) {
                $data[$k][$val] = $v['value'];
            }
        }
        return $data;
    }

    /**
     * 根据form_type处理数据
     * @author lee
     */
    public function getValueByFormtype($val, $form_type, $dataInfo)
    {
        $userModel = new \app\admin\model\User();
        $structureModel = new \app\admin\model\Structure();
        switch ($form_type) {
//            case 'datetime' :
//                $val = $val > 0 ? date('Y-m-d H:i:s', $val) : '';
//                break;
            case 'user' :
                if (is_numeric($val)) {
                    $val = count($userModel->getUserNameByArr($val)) > 1 ? ArrayToString($userModel->getUserNameByArr($val)) : implode(',', $userModel->getUserNameByArr(stringToArray($val)));
                } else {
                    $val = $val;
                }
                break;
            case 'userStr' :
                $val = explode(',', $val);
                $val = count($userModel->getUserNameByArr($val)) > 1 ? ArrayToString($userModel->getUserNameByArr($val)) : implode(',', $userModel->getUserNameByArr($val));
                break;
            case 'structure' :
                if (is_numeric($val)) {
                    $val = implode(',', $structureModel->getStructureNameByArr(stringToArray($val)));
                } else {
                    $val = $val;
                }
                break;
            case 'customer' :
                $val = db('crm_customer')->where(['customer_id' => $val])->value('name');
                break;
            case 'contract' :
                $val = db('crm_contract')->where(['contract_id' => $val])->value('num');
                break;
            case 'business' :
                $val = db('crm_business')->where(['business_id' => $val])->value('name');
                break;
            case 'category' :
                $val = db('crm_product_category')->where(['category_id' => $val])->value('name');
                break;
            case 'business_type' :
                $val = db('crm_business_type')->where(['type_id' => $val])->value('name');
                break;
            case 'business_status' :
                $val = db('crm_business_status')->where(['status_id' => $val])->value('name');
                break;
            case 'location' :
                $val = $val['address'];
                break;
            case 'position' :
                $val = trim(arrayToString(array_column($val, 'name')), ',');
                break;
            case 'warehouse_cause' :
                $val = db('jxc_warehouse')->where(['warehouse_id' => $val])->value('warehouse_name');
                break;
            case 'sale_cause' :
                $val = db('jxc_sale')->where(['sale_id' => $val])->value('order_number');
                break;
            case 'supplier_cause' :
                $val = db('jxc_supplier')->where(['supplier_id' => $val])->value('supplier_name');
                break;
            case 'purchase_cause' :
                $val = db('jxc_purchase')->where(['purchase_id' => $val])->value('order_number');
                break;
            case 'order_cause' :
                if($dataInfo['receipt_type'] == '销售退货入库'){
                    $val = db('jxc_salereturn')->where(['salereturn_id' => $val])->value('order_number');
                }elseif ($dataInfo['receipt_type'] == '采购入库') {
                    $val = db('jxc_purchase')->where(['purchase_id' => $val])->value('order_number');
                }elseif ($dataInfo['outbound_type'] == '销售出库') {
                    $val = db('jxc_sale')->where(['sale_id' => $val])->value('order_number');
                }elseif ($dataInfo['outbound_type'] == '采购退货出库') {
                    $val = db('jxc_retreat')->where(['retreat_id' => $val])->value('order_number');
                }
                break;  
            case 'category_cause' :
                $val = db('jxc_product_category')->where(['category_id' => $val])->value('category_name');
                break; 
            case 'product_cause' :
                $val = db('jxc_product')->where(['product_id' => $val])->value('product_name');
                break;  
            case 'collection_object' :
                if($dataInfo['collection_type'] == '采购退货'){
                    // $val = db('jxc_supplier')->where(['supplier_id' => $val])->value('supplier_name');
                }elseif ($dataInfo['collection_type'] == '销售出库') {
                    $val = db('jxc_sale')->where(['sale_id' => $val])->value('order_number');
                }elseif ($dataInfo['payment_type'] == '采购') {
                    $val = db('crm_customer')->where(['customer_id' => $val])->value('name');
                }elseif ($dataInfo['payment_type'] == '销售退货') {
                    $val = db('jxc_salereturn')->where(['salereturn_id' => $val])->value('order_number');
                }
                
                break;
             
        }
        return $val;
    }

    /**
     * [getIndexFieldList 列表展示字段]
     * @param types 分类
     * @author Michael_xu
     */
    public function getIndexFieldList($types, $user_id)
    {
        $fieldArr = $this->getIndexField($types, $user_id);
        $types = $types == 'crm_customer_pool' ? 'crm_customer' : $types;
        $fieldList = db('admin_field')->where(['field' => array('in', $fieldArr)])->where('types', ['eq', $types], ['eq', ''], 'or')->order('order_id asc')->select();
        return $fieldList ?: [];
    }

    /**
     * [getArrayField 数组类型字段]
     * @param types 分类
     * @author Michael_xu
     */
    public function getArrayField($types)
    {
        $arrayFormType = ['structure', 'user', 'checkbox', 'file'];
        $arrFieldAtt = db('admin_field')->where(['types' => $types, 'form_type' => ['in', $arrayFormType]])->column('field');
        return $arrFieldAtt ?: [];
    }

    /**
     * 字段对照关系处理
     * @param  $types 分类
     * @param  $data 数据
     * @return
     * @author Michael_xu
     */
    public function getRelevantData($types, $data = [])
    {
        $types_arr = ['crm_leads'];
        if (!in_array($types, $types_arr)) {
            $this->error = '参数错误';
            return false;
        }
        if (!$data) return $data;
        $list = db('admin_field')->where(['types' => $types, 'relevant' => ['neq', '']])->field('field,relevant')->select();
            foreach ($list as &$val){
                $val=!empty($val)?$val:'';
            }
        $newData = [];
        //crm_hfsomz
        foreach ($list as $k => $v) {
            $customer_field=db('admin_field')->where(['types' => 'crm_customer', 'field_id' => $v['relevant']])->field('field')->find();
            $newData[$customer_field['field']] = $data[$v['field']];
        }
        return $newData ?: [];
    }

    /**
     * 字段排序
     * @param types 自定义字段分类
     * @param prefix 自定义字段前缀
     * @param field 自定义字段
     * @param order 排序规则
     * @return
     * @author Michael_xu
     */
    public function getOrderByFormtype($types, $prefix, $field, $order_type)
    {
        $form_type = $this->where(['types' => $types, 'field' => $field])->value('form_type');
        // die('123');
        // if (!$form_type) {
        // 	$this->error = '参数错误';
        // 	return false;
        // }
        $temp_field = $field;
        $field = $prefix ? $prefix . '.' . $field : $field;
        switch ($form_type) {
            case 'textarea' :
            case 'text' :
            case 'radio' :
            case 'select' :
            case 'checkbox' :
            case 'address' :
                $order = 'convert(' . $field . ' using gbk) ' . trim($order_type);
                break;
            default :
                $order = $field . ' ' . $order_type;
                break;
        }
        if (isset($this->orther_field_list[$types])) {
            foreach ($this->orther_field_list[$types] as $val) {
                // $res[] = $val;
                // $order
                $temp = trim($prefix . '.' . $val['field'], '.');
                if ($temp == $field) {
                    $order = str_replace($temp, $val['field'], $order);
                }
            }
        }
        return $order;
    }

    /**
     * 处理字段别名、权限
     *
     * @param int $userId 用户ID
     * @param string $types 模块类型
     * @param string $action 行为
     * @param array $data 字段数据
     * @return array
     * @since 2021-04-06
     * @author fanqi
     */
    public function resetField($userId, $types, $action, $data)
    {
        $grantData = getFieldGrantData($userId);
        $userLevel = isSuperAdministrators($userId);

        foreach ($data as $key => $value) {
            # 处理字段授权
            if (!$userLevel && !empty($grantData[$types])) {
                $status = getFieldGrantStatus($value['field'], $grantData[$types]);

                # 查看权限
                if ($status['read'] == 0) {
                    unset($data[(int)$key]);
                    continue;
                }
            }

            switch ($value['field']) {
                case 'create_user_id' :
                    $data[$key]['fieldName'] = 'create_user_name';
                    break;
                case 'owner_user_id' :
                    $data[$key]['fieldName'] = 'owner_user_name';
                    break;
                case 'customer_id' :
                    $data[$key]['fieldName'] = 'customer_name';
                    break;
                case 'type_id' :
                    $data[$key]['fieldName'] = 'type_id_info';
                    break;
                case 'status_id' :
                    $data[$key]['fieldName'] = 'status_id_info';
                    break;
                case 'business_id' :
                    $data[$key]['fieldName'] = 'business_name';
                    break;
                case 'contacts_id' :
                    $data[$key]['fieldName'] = 'contacts_name';
                    break;
                case 'order_user_id' :
                    $data[$key]['fieldName'] = 'order_user_name';
                    break;
                case 'contract_id' :
                    $data[$key]['fieldName'] = 'contract_num';
                    break;
                case 'plan_id' :
                    $data[$key]['fieldName'] = 'plan_id_info';
                    break;
                case 'category_id' :
                    $data[$key]['fieldName'] = 'category_name';
                    break;
                default :
                    $data[$key]['fieldName'] = $value['field'];
            }

            # 详情中不显示产品类别、回款期数
            if ($action == 'read' && in_array($value['field'], ['category_id'])) {
                unset($data[(int)$key]);
            }
        }

        return $data;
    }

    /**
     * 获取公海自定义字段数据
     *
     * @param $poolId
     * @param $dataInfo
     * @return bool|\PDOStatement|string|\think\Collection
     */
    public function getPoolFieldData($poolId, $dataInfo)
    {
        $poolFields = db('crm_customer_pool_field_setting')->field(['field_name AS field', 'form_type', 'name'])->where(['pool_id' => $poolId, 'is_hidden' => 0])->select();

        foreach ($poolFields as $key => $value) {
            # 字段值
            $poolFields[$key]['value'] = !empty($dataInfo[$value['field']]) ? $dataInfo[$value['field']] : '';

            # 处理别名
            switch ($value['field']) {
                case 'create_user_id' :
                    $poolFields[$key]['fieldName'] = 'create_user_name';
                    $poolFields[$key]['value'] = !empty($dataInfo['create_user_id_info']) ? [$dataInfo['create_user_id_info']] : '';
                    break;
                case 'before_owner_user_id' :
                    $poolFields[$key]['fieldName'] = 'before_owner_user_name';
                    $poolFields[$key]['value'] = !empty($dataInfo['before_owner_user_id_info']) ? [$dataInfo['before_owner_user_id_info']] : '';
                    break;
                default :
                    $poolFields[$key]['fieldName'] = $value['field'];
            }
            if (in_array($value['form_type'], ['user', 'structure']) && !in_array($value['field'], ['create_user_id', 'owner_user_id', 'before_owner_user_id'])) {
                $poolFields[$key]['fieldName'] = $value['field_name'] . '_name';
            }

            # 系统字段
            if (in_array($value['field'], ['last_record', 'create_user_id', 'create_time', 'update_time', 'last_time', 'before_owner_user_id'])) {
                $poolFields[$key]['system'] = 1;
            } else {
                $poolFields[$key]['system'] = 0;
            }
        }

        return $poolFields;
    }

    /**
     * 获取发票高级搜索字段
     *
     * @return array[]
     */
    private function getInvoiceSearch()
    {
        return [
//            ['field' => 'invoice_number', 'form_type' => 'text', 'setting' => [], 'name' => '发票号码'],
            ['field' => 'real_invoice_date', 'form_type' => 'datetime', 'setting' => [], 'name' => '实际开票日期'],
            ['field' => 'logistics_number', 'form_type' => 'text', 'setting' => [], 'name' => '物流单号'],
            ['field' => 'invoice_status', 'form_type' => 'select', 'setting' => ['未开票', '已开票'], 'name' => '开票状态'],
            ['field' => 'check_status', 'form_type' => 'select', 'setting' => ['待审核', '审核中', '审核通过', '审核未通过', '撤回'], 'name' => '审核状态'],
        ];
    }

    /**
     * 获取产品父类层级（不包含自身）
     *
     * @param $productId
     * @param array $parentIds
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getProductParentIds($productId, &$parentIds = [])
    {
        $category = db('crm_product_category')->select();

        foreach ($category as $key => $value) {
            if ($value['category_id'] == $productId) {
                if (!empty($value['pid'])) {
                    $parentIds[] = $value['pid'];
                    $this->getProductParentIds($value['pid'], $parentIds);
                }
            }
        }

        return $parentIds;
    }

    /**
     * 设置明细表格数据
     *
     * @param string $types 模块类型：crm_leads crm_customer ...
     * @param string $field 字段名称
     * @param array $data 明细表单中的字段数据
     * @return bool
     * @since 2021-04-27
     * @author fanqi
     */
    private function setDetailTableData($types, $field, $data)
    {
        $id = db('admin_field_extend')->where(['field' => $field, 'types' => $types])->value('id');

        foreach ($data as $k1 => $v1) {
            if (empty($v1['field'])) $data[$k1]['field'] = $this->createField($types, $types == 'oa_examine' ? 'oa_' : 'crm_');
            $data[$k1]['default_value'] = !empty($v1['default_value']) ? $v1['default_value'] : '';
        }

        if (!empty($id)) {
            return db('admin_field_extend')->where('id', $id)->update(['content' => json_encode($data)]);
        }

        if (empty($id)) {
            return db('admin_field_extend')->insert(['types' => $types, 'field' => $field, 'content' => json_encode($data), 'create_time' => time()]);
        }

        return false;
    }

    /**
     * [getFieldByFormType 获取字段类型数组]
     * @param types 分类
     * @author Michael_xu
     */
    public function getFieldTypesArray($types, $form_type)
    {
        $fieldArr = db('admin_field')->where(['types' => $types, 'form_type' => ['in', $form_type]])->field(['field','name', 'form_type'])->select();
        $fieldList = [];
        $userField = []; // 人员类型
        $structureField = []; // 部门类型
        $datetimeField = []; // 日期时间类型
        $booleanField = []; // 布尔值类型字段
        $dateIntervalField = []; // 日期区间类型字段
        $positionField = []; // 地址类型字段
        $handwritingField = []; // 手写签名类型字段
        $locationField = []; // 定位类型字段
        $boxField = []; // 多选类型字段
        $floatField = []; // 货币类型字段

        foreach ($fieldArr as $key => $value) {
            switch ($value['form_type']) {
                case 'user' :
                    $fieldList['userField'][] = $value['field'];
                    break;
                case 'structure' :
                    $fieldList['structureField'][] = $value['field'];
                    break;
                case 'datetime' :
                    $fieldList['datetimeField'][] = $value['field'];
                    break;
                case 'boolean_value' :
                    $fieldList['booleanField'][] = $value['field'];
                    break;
                case 'date_interval' :
                    $fieldList['dateIntervalField'][] = $value['field'];
                    break;
                case 'position' :
                    $fieldList['positionField'][] = $value['field'];
                    break;
                case 'handwriting_sign' :
                    $fieldList['handwritingField'][] = $value['field'];
                    break;
                case 'location' :
                    $fieldList['locationField'][] = $value['field'];
                    break;
                case 'checkbox' :
                    $fieldList['boxField'][] = $value['field'];
                    break;
                case 'floatnumber' :
                    $fieldList['floatField'][] = $value['field'];
                    break;
                case 'pic' :
                    $fieldList['picField'][] = $value['field'];
                    break;
                case 'detail_table' :
                    $fieldList['detailTableField'][] = $value['field'];
                    break;
                case 'date' :
                    $fieldList['dateField'][] = $value['field'];
                    break;
            }
        }
        return $fieldList ?: [];
    }
}