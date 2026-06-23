import request from '@/utils/request'

const resolveRichTextUrl = url => {
  if (!url) return url
  if (/^(https?:)?\/\//i.test(url)) return url
  if (/^(data:|blob:|mailto:|tel:)/i.test(url)) return url
  const base = window.BASE_URL || ''
  if (!base) return url
  const cleanBase = base.replace(/\/+$/, '')
  if (url.startsWith('/')) {
    return `${cleanBase}${url}`
  }
  return `${cleanBase}/${url}`
}

const normalizeRichTextUrls = html => {
  if (!html || typeof html !== 'string') return html || ''
  return html.replace(/<(img|source)\b[^>]*?\ssrc=(['"])([^'"]+)\2/gi, (match, tag, quote, src) => {
    const next = resolveRichTextUrl(src)
    if (next === src) return match
    return match.replace(src, next)
  })
}

export function ledgerIndexAPI(data) {
  return request({
    url: 'ledger/index',
    method: 'post',
    data
  })
}

export function ledgerReadAPI(data) {
  return request({
    url: 'ledger/read',
    method: 'post',
    data
  }).then(res => {
    if (res && res.data && res.data.description) {
      res.data.description = normalizeRichTextUrls(res.data.description)
    }
    return res
  })
}

export function ledgerSaveAPI(data) {
  return request({
    url: 'ledger/save',
    method: 'post',
    data,
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    }
  })
}

export function ledgerUpdateAPI(data) {
  return request({
    url: 'ledger/update',
    method: 'post',
    data,
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    }
  })
}

export function ledgerDeleteAPI(data) {
  return request({
    url: 'ledger/delete',
    method: 'post',
    data,
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    }
  })
}

export function ledgerExcelExportAPI(data) {
  return request({
    url: 'ledger/excelExport',
    method: 'post',
    data,
    responseType: 'blob',
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    }
  })
}

export function ledgerRecordListAPI(data) {
  return request({
    url: 'ledger/record/list',
    method: 'post',
    data
  })
}

export function ledgerRecordAddAPI(data) {
  return request({
    url: 'ledger/record/add',
    method: 'post',
    data,
    headers: {
      'Content-Type': 'application/json;charset=UTF-8'
    }
  })
}

export function ledgerCustomerListAPI(data) {
  return request({
    url: 'ledger/customer/list',
    method: 'post',
    data
  })
}
