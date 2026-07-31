import request from '@/utils/request'

/** P6 瀛ｅ害缁╂晥鎺ュ彛锛圴3 鏀跺彛锛氬彴璐﹁川閲忛棶棰樼‘璁ゆ祦绋嬨€佽矗浠昏瀹氬鏍搞€佽皟鏁村璁°€佸弬鑰冪粨鏋滐級 */
export function performanceDictionaryAPI(data) { return request({ url: 'crm/performance/dictionary', method: 'post', data }) }
export function performanceSummarySaveAPI(data) { return request({ url: 'crm/performance/summarySave', method: 'post', data }) }
export function performanceGenerateQuarterlyAPI(data) { return request({ url: 'crm/performance/generateQuarterly', method: 'post', data }) }
export function performanceSummaryDeleteAPI(data) { return request({ url: 'crm/performance/summaryDelete', method: 'post', data }) }
export function performanceSummaryReadAPI(data) { return request({ url: 'crm/performance/summaryRead', method: 'post', data }) }
export function performanceSummaryListAPI(data) { return request({ url: 'crm/performance/summaryList', method: 'post', data }) }
export function performanceSummaryReturnAPI(data) { return request({ url: 'crm/performance/summaryReturn', method: 'post', data }) }
export function performanceSummaryRecommitAPI(data) { return request({ url: 'crm/performance/summaryRecommit', method: 'post', data }) }
export function performanceRateAPI(data) { return request({ url: 'crm/performance/rate', method: 'post', data }) }
export function performanceCaseSaveAPI(data) { return request({ url: 'crm/performance/caseSave', method: 'post', data }) }
export function performanceCaseListAPI(data) { return request({ url: 'crm/performance/caseList', method: 'post', data }) }
export function performanceCaseReviewAPI(data) { return request({ url: 'crm/performance/caseReview', method: 'post', data }) }
export function performanceAutoAggregateAPI(data) { return request({ url: 'crm/performance/autoAggregate', method: 'post', data }) }
export function performanceAddFactAPI(data) { return request({ url: 'crm/performance/addFact', method: 'post', data }) }
export function performanceFactListAPI(data) { return request({ url: 'crm/performance/factList', method: 'post', data }) }
export function performanceFactDetailAPI(data) { return request({ url: 'crm/performance/factDetail', method: 'post', data }) }
export function performanceFactReviewAPI(data) { return request({ url: 'crm/performance/factReview', method: 'post', data }) }
export function performanceLedgerQualitySaveAPI(data) { return request({ url: 'crm/performance/ledgerQualitySave', method: 'post', data }) }
export function performanceLedgerQualityReviewAPI(data) { return request({ url: 'crm/performance/ledgerQualityReview', method: 'post', data }) }
export function performanceLedgerQualityListAPI(data) { return request({ url: 'crm/performance/ledgerQualityList', method: 'post', data }) }
