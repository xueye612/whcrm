function resolveNow(now) {
  if (typeof now === 'function') return now()
  return now || ''
}

export function isCompletedLedgerStatus(status) {
  return String(status || '') === '已完成'
}

export function normalizeCompletionFields(form, now) {
  const next = Object.assign({}, form || {})
  if (isCompletedLedgerStatus(next.status)) {
    if (!next.finish_time) {
      next.finish_time = resolveNow(now)
    }
    next.reply_content = next.reply_content || ''
    return next
  }

  next.finish_time = ''
  next.reply_content = ''
  return next
}
