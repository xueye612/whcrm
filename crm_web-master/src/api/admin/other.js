import request from '@/utils/request'

/**
 * 设置日志欢迎语 oaCalendar/addOrUpdate
 * @param {*} data
 */
export function sysSetLogWelcomeAPI(data) {
  return request({
    url: 'admin/dailyRule/setWelcome',
    method: 'post',
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    },
    data: data
  })
}

/**
 * 设置日志欢迎语
 * @param {*} data
 */
export function sysGetLogWelcomeListAPI(data) {
  return request({
    url: 'admin/dailyRule/welcome',
    method: 'post',
    data: data
  })
}

// /**
//  * 根据id删除日志欢迎语
//  * @param {*} data
//  */
// export function sysDeleteConfigByIdAPI(data) {
//   return request({
//     url: 'sysConfig/deleteConfigById',
//     method: 'post',
//     data: data
//   })
// }

/**
 * 添加/修改日程类型
 * @param {*} data
 */
export function calendarAddOrUpdateAPI(data) {
  return request({
    url: `admin/dailyRule/${data.id ? 'setSchedule' : 'addSchedule'}`,
    method: 'post',
    data: data,
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    }
  })
}

/**
 * 查询日程类型
 * @param {*} data
 */
export function calendarQueryTypeListAPI(data) {
  return request({
    url: 'admin/dailyRule/scheduleList',
    method: 'post',
    data: data,
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    }
  })
}

/**
 * 删除日程类型
 * @param {*} typeId
 */
export function calendarDeleteAPI(data) {
  return request({
    url: `admin/dailyRule/delSchedule`,
    method: 'post',
    data
  })
}


/**
 * 查询日志规则接口
 * @param {*} data
 */
export function oaLogRuleQueryAPI(data) {
  return request({
    url: 'admin/dailyRule/workLogRule',
    method: 'post',
    data: data
  })
}

/**
 * 设置日志规则接口
 * @param {*} data
 */
export function oaLogRuleSetAPI(data) {
  return request({
    url: 'admin/dailyRule/setWorkLogRule',
    method: 'post',
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    },
    data
  })
}

/**
 * 收支分类列表
 * @param {*} data
 */
export function financeTypeListAPI(data) {
  return request({
    url: 'finance/type/index',
    method: 'post',
    data
  })
}

/**
 * 添加收支分类
 * @param {*} data
 */
export function financeTypeAddAPI(data) {
  return request({
    url: 'finance/type/save',
    method: 'post',
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    },
    data
  })
}

/**
 * 更新收支分类
 * @param {*} data
 */
export function financeTypeUpdateAPI(data) {
  return request({
    url: 'finance/type/update',
    method: 'post',
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    },
    data
  })
}

/**
 * 删除收支分类
 * @param {*} data
 */
export function financeTypeDeleteAPI(data) {
  return request({
    url: 'finance/type/delete',
    method: 'post',
    data
  })
}

/**
 * 台账问题分类列表
 * @param {*} data
 */
export function ledgerCategoryListAPI(data) {
  return request({
    url: 'crm/setting/ledgerCategoryList',
    method: 'post',
    data
  })
}

/**
 * 保存台账问题分类
 * @param {*} data
 */
export function ledgerCategorySaveAPI(data) {
  return request({
    url: 'crm/setting/ledgerCategorySave',
    method: 'post',
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    },
    data
  })
}

/**
 * 钉钉任务通知配置读取
 * @param {*} data
 */
export function dingtalkTaskNotifyReadAPI(data) {
  return request({
    url: 'crm/setting/dingtalkTaskNotifyRead',
    method: 'post',
    data
  })
}

/**
 * 钉钉任务通知配置保存
 * @param {*} data
 */
export function dingtalkTaskNotifySaveAPI(data) {
  return request({
    url: 'crm/setting/dingtalkTaskNotifySave',
    method: 'post',
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    },
    data
  })
}
