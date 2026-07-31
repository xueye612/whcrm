import request from '@/utils/request'

/** Performance facts */
export function perfAutoAggregateAPI(data) { return request({ url: 'crm/performance/autoAggregate', method: 'post', data }) }
export function perfAddFactAPI(data) { return request({ url: 'crm/performance/addFact', method: 'post', data }) }
export function perfFactListAPI(data) { return request({ url: 'crm/performance/factList', method: 'post', data }) }
export function perfFactReviewAPI(data) { return request({ url: 'crm/performance/factReview', method: 'post', data }) }

/** Ledger conversion + quality */
export function ledgerConvertToTaskAPI(data) { return request({ url: 'ledger/ledger/convertToTask', method: 'post', data }) }
export function ledgerQualityCheckAPI(data) { return request({ url: 'ledger/ledger/qualityCheck', method: 'post', data }) }
