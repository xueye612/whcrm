export const LEDGER_STATUS_OPTIONS = ['待处理', '处理中', '待验证', '待发布', '已完成', '已关闭']

export const LEDGER_CHANNEL_OPTIONS = ['微信', '电话', '现场', '转述', '其他']

export const DEFAULT_CATEGORY_OPTIONS = ['使用指导', '操作错误', '功能完善', '系统BUG', '新增需求', '三方问题', '其他问题']

export const DEFAULT_LEDGER_CATEGORY = '使用指导'

export function statusTagType(status) {
  const map = {
    '待处理': 'info',
    '处理中': 'warning',
    '待验证': 'warning',
    '待发布': '',
    '已完成': 'success',
    '已关闭': 'danger'
  }
  return map[status] || 'info'
}

export function statusTagClass(status) {
  if (status === '待发布') return 'status-tag-release'
  if (status === '已关闭') return 'status-tag-closed'
  return ''
}

export function formatContractOption(item) {
  if (!item) return null
  const contractId = item.contract_id || item.id
  const fullName = item.name || item.num || item.contract_name || item.contract_num || `合同#${contractId || ''}`
  const shortName = item.crm_defqwa || item.contract_short_name || ''
  const customerName = item.customer_name
    || (item.customer_id_info && item.customer_id_info.name)
    || ''
  const customerId = item.customer_id
    || (item.customer_id_info && item.customer_id_info.customer_id)
    || ''
  const contractDisplay = shortName || (fullName.length > 10 ? `${fullName.slice(0, 10)}...` : fullName)
  const optionLabel = customerName ? `${customerName} · ${contractDisplay}` : contractDisplay
  return Object.assign({}, item, {
    contract_id: contractId,
    customer_id: customerId,
    contract_full_name: fullName,
    contract_display_name: contractDisplay,
    customer_name: customerName,
    option_label: optionLabel
  })
}

export function getFeedbackContactLabel(item) {
  if (!item) return ''
  return item.name || item.contacts_name || item.realname || item.mobile || item.telephone || item.phone || ''
}

export function contractLabel(item) {
  if (!item) return ''
  if (item.option_label) return item.option_label
  const formatted = formatContractOption(item)
  return formatted ? formatted.option_label : ''
}

export function stripHtml(html) {
  if (!html || typeof html !== 'string') return ''
  return html
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

export function formatLedgerDate(value) {
  if (!value) return '-'
  if (typeof value === 'number') {
    const date = new Date(value * 1000)
    if (Number.isNaN(date.getTime())) return '-'
    return date.toLocaleString('zh-CN', { hour12: false })
  }
  return String(value)
}

export function buildMobileLedgerDraftKey(userId) {
  return `ledger_mobile_draft_${userId || 'guest'}`
}
