import request from '@/utils/request'

/** P2 原始数据/有效线索接口 */
export function leadpoolDictionaryAPI(data) {
  return request({ url: 'crm/leadpool/dictionary', method: 'post', data })
}
export function leadpoolBatchSaveAPI(data) {
  return request({ url: 'crm/leadpool/batchSave', method: 'post', data })
}
export function leadpoolRawSaveAPI(data) {
  return request({ url: 'crm/leadpool/rawSave', method: 'post', data })
}
export function leadpoolReadAPI(data) {
  return request({ url: 'crm/leadpool/poolRead', method: 'post', data })
}
export function leadpoolDedupeQueryAPI(data) {
  return request({ url: 'crm/leadpool/dedupeQuery', method: 'post', data })
}
export function leadpoolDedupeDecideAPI(data) {
  return request({ url: 'crm/leadpool/dedupeDecide', method: 'post', data })
}
export function leadpoolConvertToLeadAPI(data) {
  return request({ url: 'crm/leadpool/convertToLead', method: 'post', data })
}
