<?php
// +----------------------------------------------------------------------
// | P2 原始数据/有效线索：原始批次、原始线索池、统一查重决策
// +----------------------------------------------------------------------
namespace app\crm\controller;

use app\crm\logic\LeadPoolService;
use think\Db;
use think\Request;
use think\Hook;
use app\admin\controller\ApiCommon;

class Leadpool extends ApiCommon
{
    public function _initialize()
    {
        $action = [
            'permission' => [''],
            'allow' => ['dictionary', 'converttolead']
        ];
        Hook::listen('check_auth', $action);
        if (!in_array(strtolower(Request::instance()->action()), $action['permission'])) {
            parent::_initialize();
        }
    }

    /** 字典 */
    public function dictionary()
    {
        return resultArray(['data' => LeadPoolService::dictionary()]);
    }

    /** 创建批次 */
    public function batchSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $name = trim((string)($param['name'] ?? ''));
        if ($name === '') return resultArray(['error' => '批次名称不能为空']);
        $now = time();
        $id = Db::name('lead_raw_batch')->insertGetId([
            'name' => $name,
            'channel' => trim((string)($param['channel'] ?? '')),
            'submitted_by' => (int)$userInfo['id'],
            'reward_amount' => max(0, (float)($param['reward_amount'] ?? 0)),
            'status' => '待处理',
            'create_time' => $now, 'update_time' => $now,
        ]);
        return resultArray(['data' => ['batch_id' => $id]]);
    }

    /** 提交原始线索（自动计算查重键，并标记是否疑似重复） */
    public function rawSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $batchId = (int)($param['batch_id'] ?? 0);
        if ($batchId <= 0) return resultArray(['error' => '请选择批次']);
        $name = trim((string)($param['raw_name'] ?? ''));
        $mobile = trim((string)($param['raw_mobile'] ?? ''));
        if ($name === '' && $mobile === '') return resultArray(['error' => '名称或手机号至少填一项']);
        $batch = Db::name('lead_raw_batch')->where(['batch_id' => $batchId])->find();
        if (!$batch) return resultArray(['error' => '批次不存在']);
        $key = LeadPoolService::buildDedupeKey($name, $mobile);
        $now = time();
        // 同批次内已存在相同查重键则标记重复，不重复入库
        $dup = Db::name('lead_raw')->where(['batch_id' => $batchId, 'dedupe_key' => $key])->find();
        $status = $dup ? LeadPoolService::RAW_DUP : LeadPoolService::RAW_PENDING;
        $id = Db::name('lead_raw')->insertGetId([
            'batch_id' => $batchId,
            'source' => trim((string)($param['source'] ?? '')),
            'raw_name' => $name, 'raw_mobile' => $mobile,
            'raw_company' => trim((string)($param['raw_company'] ?? '')),
            'dedupe_key' => $key, 'status' => $status,
            'submitted_by' => (int)$userInfo['id'],
            'evidence_note' => trim((string)($param['evidence_note'] ?? '')),
            'create_time' => $now, 'update_time' => $now,
        ]);
        return resultArray(['data' => ['raw_id' => $id, 'status' => $status, 'suspected_duplicate' => $dup ? true : false]]);
    }

    /** 读取批次与原始线索池（含状态统计） */
    public function poolRead()
    {
        $param = $this->param;
        $batchId = (int)($param['batch_id'] ?? 0);
        $query = Db::name('lead_raw');
        if ($batchId > 0) $query->where(['batch_id' => $batchId]);
        $raws = $query->order('raw_id desc')->limit(200)->select();
        $batches = Db::name('lead_raw_batch')->order('batch_id desc')->limit(50)->select();
        $stat = [];
        foreach (LeadPoolService::dictionary()['raw_status'] as $s) {
            $stat[$s] = Db::name('lead_raw')->where(['status' => $s])->count();
        }
        return resultArray(['data' => [
            'batches' => $batches, 'raws' => $raws, 'stat' => $stat,
            'dictionary' => LeadPoolService::dictionary(),
        ]]);
    }

    /** 查重候选查询 */
    public function dedupeQuery()
    {
        $param = $this->param;
        $rawId = (int)($param['raw_id'] ?? 0);
        if ($rawId <= 0) return resultArray(['error' => '参数错误']);
        $raw = Db::name('lead_raw')->where(['raw_id' => $rawId])->find();
        if (!$raw) return resultArray(['error' => '原始线索不存在']);
        $service = new LeadPoolService();
        $candidates = $service->findCandidates($raw['dedupe_key'], $rawId);
        return resultArray(['data' => ['raw' => $raw, 'candidates' => $candidates]]);
    }

    /** 记录查重决策：归并(指向标准线索)/独立/重复 */
    public function dedupeDecide()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $rawId = (int)($param['raw_id'] ?? 0);
        $decision = trim((string)($param['decision'] ?? ''));
        if ($rawId <= 0) return resultArray(['error' => '参数错误']);
        if (!LeadPoolService::isValidDecision($decision)) return resultArray(['error' => '决策必须为：归并/独立/重复']);
        $raw = Db::name('lead_raw')->where(['raw_id' => $rawId])->find();
        if (!$raw) return resultArray(['error' => '原始线索不存在']);
        $canonicalId = (int)($param['canonical_lead_id'] ?? 0);
        $now = time();
        $newStatus = LeadPoolService::RAW_CHECKED;
        if ($decision === LeadPoolService::DEC_MERGE) {
            if ($canonicalId <= 0) return resultArray(['error' => '归并必须指定标准线索ID']);
            $newStatus = LeadPoolService::RAW_MERGED;
        } elseif ($decision === LeadPoolService::DEC_DUPLICATE) {
            $newStatus = LeadPoolService::RAW_DUP;
        }
        Db::name('lead_raw')->where(['raw_id' => $rawId])->update([
            'status' => $newStatus, 'canonical_lead_id' => $canonicalId, 'update_time' => $now,
        ]);
        Db::name('lead_dedupe_log')->insert([
            'raw_id' => $rawId, 'decision' => $decision, 'canonical_lead_id' => $canonicalId,
            'decider' => (int)$userInfo['id'], 'reason' => trim((string)($param['reason'] ?? '')),
            'create_time' => $now,
        ]);
        return resultArray(['data' => ['raw_id' => $rawId, 'status' => $newStatus]]);
    }

    /**
     * 将已查重确认有效的原始线索幂等转入正式 crm_leads。
     * 保存 raw_id/batch_id/source 关联；同一 raw_id 不得重复生成正式线索。
     * 后续跟进只使用 crm_leads，不在 leadpool 重复建设。
     */
    public function convertToLead()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $rawId = (int)($param['raw_id'] ?? 0);
        if ($rawId <= 0) return resultArray(['error' => '参数错误']);
        try {
            $raw = Db::name('lead_raw')->where(['raw_id' => $rawId])->find();
            if (!$raw) return resultArray(['error' => '原始线索不存在']);
            if (!in_array($raw['status'], ['已查重', '已归并'])) return resultArray(['error' => '仅查重确认有效的原始线索可转入']);
            if ((int)$raw['canonical_lead_id'] > 0) {
                return resultArray(['data' => ['leads_id' => (int)$raw['canonical_lead_id'], 'note' => '该原始线索已转入正式线索，不重复生成']]);
            }
            $existLead = Db::name('crm_leads')->where('mobile', $raw['raw_mobile'])->where('mobile', '<>', '')->find();
            $now = time();
            if ($existLead) {
                Db::name('lead_raw')->where(['raw_id' => $rawId])->update(['canonical_lead_id' => $existLead['leads_id'], 'status' => '已转客户', 'update_time' => $now]);
                return resultArray(['data' => ['leads_id' => $existLead['leads_id'], 'mode' => 'linked', 'note' => '关联已有正式线索']]);
            }
            $leadsId = Db::name('crm_leads')->insertGetId([
                'name' => $raw['raw_name'] ?: '未命名',
                'mobile' => $raw['raw_mobile'],
                'source' => $raw['source'] ?: '原始数据池',
                'owner_user_id' => (int)$userInfo['id'],
                'create_user_id' => (int)$userInfo['id'],
                'create_time' => $now,
                'update_time' => $now,
            ]);
            Db::name('lead_raw')->where(['raw_id' => $rawId])->update(['canonical_lead_id' => $leadsId, 'status' => '已转客户', 'update_time' => $now]);
            return resultArray(['data' => ['leads_id' => $leadsId, 'mode' => 'created', 'raw_id' => $rawId]]);
        } catch (\Throwable $e) {
            return resultArray(['error' => 'convertToLead: ' . get_class($e) . ': ' . $e->getMessage()]);
        }
    }
}
