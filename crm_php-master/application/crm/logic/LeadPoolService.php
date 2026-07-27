<?php
/**
 * P2 原始数据/有效线索规则引擎：归一化查重键、状态与查重决策。
 * PHP 7.0 / ThinkPHP 5.0.24 兼容。
 */
namespace app\crm\logic;

use think\Db;

class LeadPoolService
{
    const RAW_PENDING  = '待查重';
    const RAW_CHECKED  = '已查重';
    const RAW_DUP      = '重复';
    const RAW_MERGED   = '已归并';
    const RAW_CONVERTED= '已转客户';

    const DEC_MERGE     = '归并';
    const DEC_INDEPEND  = '独立';
    const DEC_DUPLICATE = '重复';

    private static $rawStatuses = [self::RAW_PENDING, self::RAW_CHECKED, self::RAW_DUP, self::RAW_MERGED, self::RAW_CONVERTED];
    private static $decisions   = [self::DEC_MERGE, self::DEC_INDEPEND, self::DEC_DUPLICATE];

    public static function dictionary()
    {
        return [
            'raw_status' => self::$rawStatuses,
            'decision'   => self::$decisions,
        ];
    }

    /**
     * 归一化查重键：去除名称空白与手机号非数字字符后小写拼接，再 md5。
     * 使“济南国政 ”与“济南国政”、18668960636 与 186-6896-0636 视为同一主体。
     */
    public static function buildDedupeKey($name, $mobile)
    {
        $n = preg_replace('/\s+/', '', (string)$name);
        $m = preg_replace('/\D/', '', (string)$mobile);
        $m = strlen($m) > 11 ? substr($m, -11) : $m; // 取末11位
        return md5(mb_strtolower($n, 'UTF-8') . '|' . $m);
    }

    public static function isValidDecision($v) { return in_array($v, self::$decisions, true); }

    /**
     * 在标准线索(crm_leads)与同批次原始池中查找查重候选。
     * 返回 ['leads'=>[..], 'raw'=>[..]]。
     */
    public function findCandidates($dedupeKey, $excludeRawId = 0)
    {
        // 通过 dedupe_key 反查原始池，取手机号后在标准线索中近似匹配（人工决策为准）
        $row = Db::name('lead_raw')->where(['dedupe_key' => $dedupeKey])->find();
        $mobile = $row ? $row['raw_mobile'] : '';
        $candLeads = [];
        if ($mobile !== '') {
            $candLeads = Db::name('crm_leads')->where('mobile', $mobile)->field('leads_id,name,mobile,customer_id')->limit(10)->select();
        }
        $candRaw = Db::name('lead_raw')
            ->where(['dedupe_key' => $dedupeKey])
            ->where('raw_id', '<>', (int)$excludeRawId)
            ->field('raw_id,batch_id,raw_name,raw_mobile,status')->limit(20)->select();
        return ['leads' => $candLeads ?: [], 'raw' => $candRaw ?: []];
    }
}
