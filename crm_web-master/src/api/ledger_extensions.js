import request from '@/utils/request'

export function ledgerConvertToTaskAPI(data) {
  return request({ url: 'ledger/ledger/convertToTask', method: 'post', data })
}
export function ledgerQualityCheckAPI(data) {
  return request({ url: 'ledger/ledger/qualityCheck', method: 'post', data })
}
export function ledgerStatisticsAPI(data) {
  return request({ url: 'ledger/ledger/statistics', method: 'post', data })
}
