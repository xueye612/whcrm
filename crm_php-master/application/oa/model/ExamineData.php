<?php
// +----------------------------------------------------------------------
// | Description: 公告
// +----------------------------------------------------------------------
// | Author:  Michael_xu | gengxiaoxu@5kcrm.com
// +----------------------------------------------------------------------
namespace app\oa\model;

use think\Db;
use app\admin\model\Common;
use think\Request;
use think\Validate;
use think\helper\Time;

class ExamineData extends Common
{
	/**
     * 为了数据库的整洁，同时又不影响Model和Controller的名称
     * 我们约定每个模块的数据表都加上相同的前缀，比如CRM模块用crm作为数据表前缀
     */
	protected $name = 'oa_examine_data';

	/**
	 * 存储自定义字段数据
	 * @author Michael_xu
	 * @param  
	 * @return                            
	 */	
	public function createData($param, $examine_id)
	{
		if (!$examine_id) {
			$this->error = '参数错误';
			return false;			
		}
		$fieldList = db('admin_field')->where(['types' => 'oa_examine','types_id' => $param['category_id']])->select();
		//过滤掉固定字段
		$unField = ['content','remark','start_time','end_time','duration','cause','money','category_id','check_user_id','check_status','flow_id','order_id','create_user_id'];
		$data = [];
		foreach ($fieldList as $k=>$v) {
			if (!in_array($v['field'], $unField)) {
                $data[$k]['examine_id'] = $examine_id;
                $data[$k]['field'] = $v['field'];
                // 处理数据格式是对象的字段：position 地址、location 定位、date_interval 日期区间、detail_table 明细表格
                if (in_array($v['form_type'], ['position', 'location', 'date_interval', 'detail_table'])) {
                    $data[$k]['value'] = !empty($param[$v['field']]) ? json_encode($param[$v['field']], JSON_NUMERIC_CHECK) : '';
                } else {
                    $data[$k]['value'] = !empty($param[$v['field']]) ? $param[$v['field']] : '';
                }

                // 处理手写签名
                if ($v['form_type'] == 'handwriting_sign') {
                    $data[$k]['value'] = !empty($param[$v['field']]['file_id']) ? $param[$v['field']]['file_id'] : '';
                }
                // 处理附件
                if ($v['form_type'] == 'file') {
                    $data[$k]['value'] = !empty($param[$v['field']]) ? arrayToString($param[$v['field']]) : '';
                }
			}
		}
		if ($data) {
		    db('oa_examine_data')->where('examine_id', $examine_id)->delete();
//		    print_r($data);exit;
			$resData = db('oa_examine_data')->insertAll($data);
			if (!$resData) {
				$this->error = '添加失败';
				return false;			
			}
		}
		return true;
	}

	/**
	 * 读取自定义字段数据
	 * @author Michael_xu
	 * @param  
	 * @return                            
	 */	
	public function getDataById($examine_id)
	{
		if (!$examine_id) {
			$this->error = '参数错误';
			return false;			
		}
		$dataList = db('oa_examine_data')->where(['examine_id' => $examine_id])->select();
		$newData = [];
		foreach ($dataList as $k=>$v) {
			$newData[$v['field']] = $v['value'];
		}
		return $newData ? : [];		
	}	
}