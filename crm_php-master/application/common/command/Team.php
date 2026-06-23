<?php
/**
 * 使用定时器将符合条件的团队成员移出团队
 *
 * @author guogaobo
 * @since 2021-05-20
 */

namespace app\common\command;

use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\response\Json;
use Workerman\Lib\Timer;
use Workerman\Worker;

class Team extends Command
{
    protected $timer;
    protected $interval = 10;
    
    protected function configure()
    {
        $this->setName('team')
            ->addArgument('status', Argument::REQUIRED, 'start/stop/reload/status/connections')
            ->addOption('d', null, Option::VALUE_NONE, 'daemon（守护进程）方式启动')
            ->setDescription('团队成员移出定时器');
        
        // 读取数据库配置文件
        $filename = ROOT_PATH . 'config' . DS . 'database.php';
        // 重新加载数据库配置文件
        Config::load($filename, 'database');
    }
    
    /**
     * 初始化
     *
     * @param Input $input
     * @param Output $output
     */
    protected function init(Input $input, Output $output)
    {
        global $argv;
        
        $argv[1] = $input->getArgument('status') ?: 'start';
        
        if ($input->hasOption('d')) {
            $argv[2] = '-d';
        } else {
            unset($argv[2]);
        }
    }
    
    /**
     * 停止定时器
     */
    public function stop()
    {
        Timer::del($this->timer);
    }
    
    /**
     * 启动定时器
     */
    public function start()
    {
        $this->timer = Timer::add(1, function () {
            # 只在凌晨12点至6点间执行

            if ((int)date('H') >= 0 && (int)date('H') < 6) {
            # 团队成员过滤规则
            db('crm_team')->where('target_time',0)->delete();
            $ruleList = db('crm_team')
                ->where('target_time', '<', time())->select();
            if (!empty($ruleList)) {
                Db::startTrans();
                try {
                    foreach ($ruleList as $v) {
                        
                        switch ($v['types']) {
                            case 1 :
                                $data_name = 'customer_id';
                                $types = 'crm_customer';
                                $typesName = '客户';
                                break;
                            case 2 :
                                $data_name = 'contacts_id';
                                $types = 'crm_contacts';
                                $typesName = '联系人';
                                break;
                            case 3 :
                                $data_name = 'business_id';
                                $types = 'crm_business';
                                $typesName = '商机';
                                break;
                            case 4 :
                                $data_name = 'contract_id';
                                $types = 'crm_contract';
                                $typesName = '合同';
                                break;
                            case 5 :
                                $data_name = 'receivables_id';
                                $types = 'crm_receivables';
                                $typesName = '回款';
                                break;
                        }
                        $resData = db($types)->where([$data_name => $v['target_id']])->field('name,rw_user_id,ro_user_id')->find();
                        if ($v['types'] == 5) {
                            $resData = db($types)->where([$data_name => $v['target_id']])->field('number as name,rw_user_id,ro_user_id')->find();
                        }
                        $team_user_id = array_column($ruleList, 'team_user_id');
                        $old_rw_user_id = !empty($resData['rw_user_id']) ? explode(',', trim($resData['rw_user_id'], ',')) : []; //去重
                        //只读
                        $old_ro_user_id = !empty($resData['ro_user_id']) ? explode(',', trim($resData['ro_user_id'], ',')) : []; //去重
                        if ($v['auth'] == 1) {
                            $all_rw_user_id = $team_user_id ? array_diff($old_ro_user_id, $team_user_id) : ''; // 差集
                            $data['ro_user_id'] = $all_rw_user_id ? ',' . implode(',', $all_rw_user_id) . ',' : ''; //去空
                        } else {
                            $all_ro_user_id = $team_user_id ? array_diff($old_rw_user_id, $team_user_id) : ''; // 差集
                            $data['rw_user_id'] = $all_ro_user_id ? ',' . implode(',', $all_ro_user_id) . ',' : ''; //去空    ;
                        }
                        $upData = db($types)->where([$data_name => $v['target_id']])->update($data);
                        db('crm_team')->where(['target_id' => $v['target_id'], 'types' => $v['types'], 'team_user_id' => ['in', arrayToString($team_user_id)]])->delete();
//                        updateActionLog(0, $v['types'], $data, '', '', '自动删除到期员工');
                    }
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                }
            }
            }
        });
    }
    
    protected function execute(Input $input, Output $output)
    {
        # 动态修改运行时参数
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        
        $this->init($input, $output);
        
        # 创建定时器任务
        $worker = new Worker();
        $worker->name = 'team';
        $worker->count = 1;
        $worker->onWorkerStart = [$this, 'start'];
        $worker->runAll();
    }
}