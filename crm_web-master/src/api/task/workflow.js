import request from '@/utils/request'

/**
 * P0 任务工作流、W/R/K、轻量测试 API
 */

// W/R/K 字典（前后端共享）
export function wrkDictionaryAPI() {
  return request({
    url: 'work/task/wrkDictionary',
    method: 'post',
    data: {}
  })
}

// 读取任务工作流与测试信息
export function workflowReadAPI(data) {
  return request({
    url: 'work/task/workflowRead',
    method: 'post',
    data
  })
}

// 评估：待评估 → 待处理
export function evaluateTaskAPI(data) {
  return request({ url: 'work/task/evaluate', method: 'post', data })
}

// 开始处理：待处理 → 处理中（需初始 W/R/K）
export function startProcessTaskAPI(data) {
  return request({ url: 'work/task/startProcess', method: 'post', data })
}

// 提交内部验收：处理中 → 待内部验收
export function submitAcceptanceAPI(data) {
  return request({ url: 'work/task/submitAcceptance', method: 'post', data })
}

// 内部验收通过
export function acceptancePassAPI(data) {
  return request({ url: 'work/task/acceptancePass', method: 'post', data })
}

// 内部验收退回
export function acceptanceReturnAPI(data) {
  return request({ url: 'work/task/acceptanceReturn', method: 'post', data })
}

// 申请发布（门禁检查）
export function applyReleaseAPI(data) {
  return request({ url: 'work/task/applyRelease', method: 'post', data })
}

// 确认发布：待发布 → 待客户验证
export function confirmReleaseAPI(data) {
  return request({ url: 'work/task/confirmRelease', method: 'post', data })
}

// 客户确认 → 已完成
export function customerConfirmAPI(data) {
  return request({ url: 'work/task/customerConfirm', method: 'post', data })
}

// 客户退回 → 处理中
export function customerReturnAPI(data) {
  return request({ url: 'work/task/customerReturn', method: 'post', data })
}

// 直接完成
export function completeTaskAPI(data) {
  return request({ url: 'work/task/completeTask', method: 'post', data })
}

// 设置辅助状态（阻塞/暂缓/取消/重复/无需处理）
export function setAuxStatusAPI(data) {
  return request({ url: 'work/task/setAuxStatus', method: 'post', data })
}

// 有审计地豁免发布/客户验证
export function setReleaseExemptionAPI(data) {
  return request({ url: 'work/task/setReleaseExemption', method: 'post', data })
}

// 更新 W/R/K
export function updateWrkAPI(data) {
  return request({ url: 'work/task/updateWrk', method: 'post', data })
}

// 发起测试
export function initiateTestAPI(data) {
  return request({ url: 'work/task/initiateTest', method: 'post', data })
}

// 测试人员提交结果
export function submitTestAPI(data) {
  return request({ url: 'work/task/submitTest', method: 'post', data })
}

// 研发负责人评定
export function reviewTestAPI(data) {
  return request({ url: 'work/task/reviewTest', method: 'post', data })
}

// 测试任务列表
export function testListAPI(data) {
  return request({ url: 'work/task/testList', method: 'post', data })
}
