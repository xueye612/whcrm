<?php
/**
 * FinanceService: receivable -> finance_record auto-generation and offset.
 * Rules:
 * - direction = 'income' for both normal and offset (offset uses negative amount)
 * - rel_type = 'receivable' for income, 'receivable_offset' for offset
 * - check_status 2 (approved) or 7 (no-examine) triggers generation
 * - Idempotent by (receivables_id, rel_type)
 * - Failures rollback and return errors, never silently succeed
 */
namespace app\crm\logic;

use think\Db;

class FinanceService
{
    /**
     * Generate finance_record income from approved or no-examine receivable.
     * @return [bool, string|array]
     */
    public static function generateFromReceivable($receivablesId, $operatorUserId)
    {
        $receivablesId = (int)$receivablesId;
        if ($receivablesId <= 0) return [false, 'receivables_id invalid'];

        $recv = Db::name('crm_receivables')->where(['receivables_id' => $receivablesId])->find();
        if (!$recv) return [false, 'receivable not found'];

        $status = (int)$recv['check_status'];
        if ($status !== 2 && $status !== 7) return [false, 'only check_status 2 or 7 allowed'];

        // Idempotent: existing income
        $exist = Db::name('finance_record')
            ->where(['receivables_id' => $receivablesId, 'rel_type' => 'receivable'])
            ->find();
        if ($exist) return [true, ['record_id' => $exist['record_id'], 'note' => 'exists']];

        // Lookup contract -> business
        $contractId = (int)$recv['contract_id'];
        $businessId = 0;
        if ($contractId > 0) {
            $contract = Db::name('crm_contract')->where(['contract_id' => $contractId])->find();
            if ($contract) $businessId = (int)($contract['business_id'] ?? 0);
        }

        $returnTime = !empty($recv['return_time']) ? (int)$recv['return_time'] : time();
        $amount = round((float)$recv['money'], 2);
        $now = time();

        try {
            $id = Db::name('finance_record')->insertGetId([
                'direction' => 'income',
                'customer_id' => (int)$recv['customer_id'],
                'contract_id' => $contractId,
                'business_id' => $businessId,
                'plan_id' => (int)$recv['plan_id'],
                'receivables_id' => $receivablesId,
                'amount' => $amount,
                'occur_date' => date('Y-m-d', $returnTime),
                'type_id' => 0,
                'remark' => 'receivable auto #' . $receivablesId,
                'rel_type' => 'receivable',
                'register_user_id' => (int)$operatorUserId,
                'create_user_id' => (int)$operatorUserId,
                'create_time' => $now,
                'update_time' => $now,
            ]);
            return [true, ['record_id' => $id, 'amount' => $amount]];
        } catch (\Exception $e) {
            return [false, 'insert failed: ' . $e->getMessage()];
        }
    }

    /**
     * Offset (reverse) the income record when receivable is rejected/cancelled/refunded.
     * Offset uses direction='income' with negative amount, rel_type='receivable_offset'.
     * Idempotent by (receivables_id, rel_type='receivable_offset').
     */
    public static function offsetFromReceivable($receivablesId, $operatorUserId, $reason)
    {
        $receivablesId = (int)$receivablesId;
        if ($receivablesId <= 0) return [false, 'receivables_id invalid'];

        // Idempotent: existing offset
        $existOffset = Db::name('finance_record')
            ->where(['receivables_id' => $receivablesId, 'rel_type' => 'receivable_offset'])
            ->find();
        if ($existOffset) return [true, ['record_id' => $existOffset['record_id'], 'note' => 'offset exists']];

        $income = Db::name('finance_record')
            ->where(['receivables_id' => $receivablesId, 'rel_type' => 'receivable'])
            ->find();
        if (!$income) return [true, 'no income to offset'];

        $amount = round((float)$income['amount'], 2);
        $now = time();

        try {
            Db::startTrans();
            $id = Db::name('finance_record')->insertGetId([
                'direction' => 'income',
                'customer_id' => (int)$income['customer_id'],
                'contract_id' => (int)$income['contract_id'],
                'business_id' => (int)$income['business_id'],
                'plan_id' => (int)$income['plan_id'],
                'receivables_id' => $receivablesId,
                'amount' => -$amount,
                'occur_date' => date('Y-m-d'),
                'type_id' => 0,
                'remark' => 'offset #' . $receivablesId . ': ' . $reason,
                'rel_type' => 'receivable_offset',
                'register_user_id' => (int)$operatorUserId,
                'create_user_id' => (int)$operatorUserId,
                'create_time' => $now,
                'update_time' => $now,
            ]);
            // Revoke unsettled reward candidates linked to this receivable
            Db::name('reward_candidate')
                ->where('source_ref', 'receivable:' . $receivablesId)
                ->whereIn('status', ['pending_review', 'pending_special', 'approved'])
                ->update(['status' => 'offset', 'update_time' => $now]);
            Db::commit();
            return [true, ['record_id' => $id, 'amount' => -$amount]];
        } catch (\Exception $e) {
            Db::rollback();
            return [false, 'offset failed: ' . $e->getMessage()];
        }
    }
}
