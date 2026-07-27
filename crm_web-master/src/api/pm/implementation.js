import request from '@/utils/request'

/**
 * P1 项目实施扩展接口
 */

export function implementationReadAPI(data) {
  return request({
    url: 'work/work/implementationRead',
    method: 'post',
    data
  })
}

export function profileUpdateAPI(data) {
  return request({
    url: 'work/work/profileUpdate',
    method: 'post',
    data
  })
}

export function milestoneSaveAPI(data) {
  return request({
    url: 'work/work/milestoneSave',
    method: 'post',
    data
  })
}

export function milestoneDeleteAPI(data) {
  return request({
    url: 'work/work/milestoneDelete',
    method: 'post',
    data
  })
}

export function contributionSaveAPI(data) {
  return request({
    url: 'work/work/contributionSave',
    method: 'post',
    data
  })
}

export function contributionDeleteAPI(data) {
  return request({
    url: 'work/work/contributionDelete',
    method: 'post',
    data
  })
}

export function knowledgeSaveAPI(data) {
  return request({
    url: 'work/work/knowledgeSave',
    method: 'post',
    data
  })
}

export function knowledgeDeleteAPI(data) {
  return request({
    url: 'work/work/knowledgeDelete',
    method: 'post',
    data
  })
}
