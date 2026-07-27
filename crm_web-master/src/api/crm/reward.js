import request from '@/utils/request'
export function rewardDictionaryAPI(data) { return request({ url: 'crm/reward/dictionary', method: 'post', data }) }
export function rewardCandidateSaveAPI(data) { return request({ url: 'crm/reward/candidateSave', method: 'post', data }) }
export function rewardCandidateListAPI(data) { return request({ url: 'crm/reward/candidateList', method: 'post', data }) }
export function rewardReviewAPI(data) { return request({ url: 'crm/reward/review', method: 'post', data }) }
export function rewardBatchCreateAPI(data) { return request({ url: 'crm/reward/batchCreate', method: 'post', data }) }
export function rewardBatchSettleAPI(data) { return request({ url: 'crm/reward/batchSettle', method: 'post', data }) }
export function rewardOffsetAPI(data) { return request({ url: 'crm/reward/offset', method: 'post', data }) }
export function rewardConfigSaveAPI(data) { return request({ url: 'crm/reward/configSave', method: 'post', data }) }
export function rewardExpenseSaveAPI(data) { return request({ url: 'crm/reward/expenseSave', method: 'post', data }) }
export function rewardExpenseListAPI(data) { return request({ url: 'crm/reward/expenseList', method: 'post', data }) }
