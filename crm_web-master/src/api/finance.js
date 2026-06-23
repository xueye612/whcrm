import request from '@/utils/request'

// Finance Type
export function financeTypeIndex(data) {
  return request({
    url: 'finance/type/index',
    method: 'post',
    data
  })
}

export function financeTypeSave(data) {
  return request({
    url: 'finance/type/save',
    method: 'post',
    data
  })
}

export function financeTypeUpdate(data) {
  return request({
    url: 'finance/type/update',
    method: 'post',
    data
  })
}

export function financeTypeDelete(data) {
  return request({
    url: 'finance/type/delete',
    method: 'post',
    data
  })
}

// Finance Record
export function financeRecordIndex(data) {
  return request({
    url: 'finance/record/index',
    method: 'post',
    data
  })
}

export function financeRecordRead(data) {
  return request({
    url: 'finance/record/read',
    method: 'post',
    data
  })
}

export function financeRecordSave(data) {
  return request({
    url: 'finance/record/save',
    method: 'post',
    data
  })
}

export function financeRecordUpdate(data) {
  return request({
    url: 'finance/record/update',
    method: 'post',
    data
  })
}

export function financeRecordDelete(data) {
  return request({
    url: 'finance/record/delete',
    method: 'post',
    data
  })
}

// Finance Plan
export function financePlanIndex(data) {
  return request({
    url: 'finance/plan/index',
    method: 'post',
    data
  })
}

export function financePlanRead(data) {
  return request({
    url: 'finance/plan/read',
    method: 'post',
    data
  })
}

export function financePlanSave(data) {
  return request({
    url: 'finance/plan/save',
    method: 'post',
    data
  })
}

export function financePlanUpdate(data) {
  return request({
    url: 'finance/plan/update',
    method: 'post',
    data
  })
}

export function financePlanDelete(data) {
  return request({
    url: 'finance/plan/delete',
    method: 'post',
    data
  })
}

// Finance Payment Method
export function financePaymentMethodIndex(data) {
  return request({
    url: 'finance/paymentmethod/index',
    method: 'post',
    data
  })
}

export function financePaymentMethodSave(data) {
  return request({
    url: 'finance/paymentmethod/save',
    method: 'post',
    data
  })
}

export function financePaymentMethodUpdate(data) {
  return request({
    url: 'finance/paymentmethod/update',
    method: 'post',
    data
  })
}

export function financePaymentMethodDelete(data) {
  return request({
    url: 'finance/paymentmethod/delete',
    method: 'post',
    data
  })
}
