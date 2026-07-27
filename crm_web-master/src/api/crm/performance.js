import request from '@/utils/request'

/** P6 季度绩效接口 */
export function performanceDictionaryAPI(data) { return request({ url: 'crm/performance/dictionary', method: 'post', data }) }
export function performanceSummarySaveAPI(data) { return request({ url: 'crm/performance/summarySave', method: 'post', data }) }
export function performanceSummaryReadAPI(data) { return request({ url: 'crm/performance/summaryRead', method: 'post', data }) }
export function performanceSummaryListAPI(data) { return request({ url: 'crm/performance/summaryList', method: 'post', data }) }
export function performanceRateAPI(data) { return request({ url: 'crm/performance/rate', method: 'post', data }) }
export function performanceCaseSaveAPI(data) { return request({ url: 'crm/performance/caseSave', method: 'post', data }) }
export function performanceCaseListAPI(data) { return request({ url: 'crm/performance/caseList', method: 'post', data }) }
