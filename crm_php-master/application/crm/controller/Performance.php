<?php
// +----------------------------------------------------------------------
// | P6 季度绩效：四权重汇总、质量三档、评级限制、本人回避；责任认定独立(不自动扣款)
// +----------------------------------------------------------------------
namespace app\crm\controller;

use app\crm\logic\PerformanceService;
use think\Db;
use think\Request;
use think\Hook;
use app\admin\controller\ApiCommon;

class Performance extends ApiCommon
{
    public function _initialize()
    {
        $action = ['permission' => [''], 'allow' => ['dictionary']];
        Hook::listen('check_auth', $action);
        if (!in_array(strtolower(Request::instance()->action()), $action['permission'])) {
            parent::_initialize();
        }
    }

    public function dictionary()
    {
        return resultArray(['data' => PerformanceService::dictionary()]);
    }

    /** 保存绩效汇总：录入四项分值，自动计算加权得分 */
    public function summarySave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $userId = (int)($param['user_id'] ?? 0);
        $period = trim((string)($param['period'] ?? ''));
        if ($userId <= 0 || $period === '') return resultArray(['error' => 'user_id 与 period 必填']);
        foreach (['duty_score','task_score','quality_score','collab_score'] as $f) {
            $v = (float)($param[$f] ?? 0);
            if ($v < 0 || $v > 100) return resultArray(['error' => '各项分值应在 0-100']);
        }
        $weighted = PerformanceService::weightedScore($param['duty_score'], $param['task_score'], $param['quality_score'], $param['collab_score']);
        $now = time();
        $exist = Db::name('performance')->where(['user_id' => $userId, 'period' => $period])->find();
        $row = [
            'duty_score' => (float)$param['duty_score'], 'task_score' => (float)$param['task_score'],
            'quality_score' => (float)$param['quality_score'], 'collab_score' => (float)$param['collab_score'],
            'weighted_score' => $weighted, 'update_time' => $now,
        ];
        if ($exist) {
            Db::name('performance')->where(['perf_id' => $exist['perf_id']])->update($row);
            $id = $exist['perf_id'];
        } else {
            $row['user_id'] = $userId; $row['period'] = $period; $row['status'] = '待确认';
            $row['create_user_id'] = (int)$userInfo['id']; $row['create_time'] = $now;
            $id = Db::name('performance')->insertGetId($row);
        }
        return resultArray(['data' => ['perf_id' => $id, 'weighted_score' => $weighted]]);
    }

    public function summaryRead()
    {
        $param = $this->param;
        $row = Db::name('performance')->where(['perf_id' => (int)($param['perf_id'] ?? 0)])->find();
        return resultArray(['data' => ['summary' => $row, 'dictionary' => PerformanceService::dictionary()]]);
    }

    public function summaryList()
    {
        $param = $this->param;
        $q = Db::name('performance');
        if (!empty($param['period'])) $q->where(['period' => $param['period']]);
        if (!empty($param['user_id'])) $q->where(['user_id' => (int)$param['user_id']]);
        $list = $q->order('perf_id desc')->limit(200)->select();
        return resultArray(['data' => ['list' => $list]]);
    }

    /** 评级：质量三档 + 最终评级(1.2/1.0/0.6)；本人回避；仅待确认可评 */
    public function rate()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $perfId = (int)($param['perf_id'] ?? 0);
        $tier = trim((string)($param['quality_tier'] ?? ''));
        $rating = trim((string)($param['rating'] ?? ''));
        if ($perfId <= 0) return resultArray(['error' => '参数错误']);
        if (!PerformanceService::isValidTier($tier)) return resultArray(['error' => '质量三档必须为：完成良好/基本完成/需要改进']);
        if (!PerformanceService::isValidRating($rating)) return resultArray(['error' => '评级必须为：优秀/合格/待改进']);
        $p = Db::name('performance')->where(['perf_id' => $perfId])->find();
        if (!$p) return resultArray(['error' => '绩效汇总不存在']);
        if ($p['status'] !== '待确认') return resultArray(['error' => '仅待确认绩效可评定']);
        if (!PerformanceService::assertNotSelf($p['user_id'], $userInfo['id'])) return resultArray(['error' => '本人回避：不能评定自己的绩效']);
        Db::name('performance')->where(['perf_id' => $perfId])->update([
            'quality_tier' => $tier, 'rating' => $rating,
            'rating_factor' => PerformanceService::ratingFactor($rating),
            'reviewer_user_id' => (int)$userInfo['id'], 'review_time' => time(),
            'review_note' => trim((string)($param['review_note'] ?? '')),
            'status' => '已确认', 'update_time' => time(),
        ]);
        return resultArray(['data' => ['perf_id' => $perfId, 'rating' => $rating, 'rating_factor' => PerformanceService::ratingFactor($rating)]]);
    }

    /** 责任认定（独立，不自动扣款） */
    public function caseSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $userId = (int)($param['user_id'] ?? 0);
        if ($userId <= 0 || trim((string)($param['title'] ?? '')) === '') return resultArray(['error' => 'user_id 与 title 必填']);
        $now = time();
        $id = Db::name('responsibility_case')->insertGetId([
            'user_id' => $userId, 'period' => trim((string)($param['period'] ?? '')),
            'title' => trim((string)$param['title']), 'severity' => trim((string)($param['severity'] ?? '')),
            'description' => trim((string)($param['description'] ?? '')), 'status' => '认定中',
            'create_user_id' => (int)$userInfo['id'], 'create_time' => $now, 'update_time' => $now,
        ]);
        return resultArray(['data' => ['case_id' => $id, 'note' => '责任认定独立流程，不通过系统自动扣款']]);
    }

    public function caseList()
    {
        $list = Db::name('responsibility_case')->order('case_id desc')->limit(200)->select();
        return resultArray(['data' => ['list' => $list]]);
    }
}
