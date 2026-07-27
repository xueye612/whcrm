import request from '@/utils/request'

/** P4 外包项目接口 */
export function outsourceDictionaryAPI(data) { return request({ url: 'work/outsource/dictionary', method: 'post', data }) }
export function outsourceProjectSaveAPI(data) { return request({ url: 'work/outsource/projectSave', method: 'post', data }) }
export function outsourceProjectReadAPI(data) { return request({ url: 'work/outsource/projectRead', method: 'post', data }) }
export function outsourceDistributeSaveAPI(data) { return request({ url: 'work/outsource/distributeSave', method: 'post', data }) }
export function outsourceDistributeReadAPI(data) { return request({ url: 'work/outsource/distributeRead', method: 'post', data }) }
