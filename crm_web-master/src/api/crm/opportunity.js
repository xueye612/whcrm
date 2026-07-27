import request from '@/utils/request'

/** P3 行业机会接口 */
export function opportunityDictionaryAPI(data) { return request({ url: 'crm/opportunity/dictionary', method: 'post', data }) }
export function opportunitySaveAPI(data) { return request({ url: 'crm/opportunity/oppSave', method: 'post', data }) }
export function opportunityReadAPI(data) { return request({ url: 'crm/opportunity/oppRead', method: 'post', data }) }
export function opportunityListAPI(data) { return request({ url: 'crm/opportunity/oppList', method: 'post', data }) }
export function opportunityStageAdvanceAPI(data) { return request({ url: 'crm/opportunity/stageAdvance', method: 'post', data }) }
