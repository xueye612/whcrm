export function buildLedgerRedirectPath(ledgerId) {
  const id = String(ledgerId || '').trim()
  return id ? `/ledger/redirect/${encodeURIComponent(id)}` : '/ledger/redirect'
}

export function buildLedgerShareUrl(ledgerId, locationLike) {
  const locationRef = locationLike || (typeof window !== 'undefined' ? window.location : null)
  const hashPath = `#${buildLedgerRedirectPath(ledgerId)}`
  if (!locationRef) return hashPath

  const origin = locationRef.origin || `${locationRef.protocol}//${locationRef.host}`
  return `${origin}/${hashPath}`
}

export function copyText(text) {
  if (!text) return Promise.reject(new Error('empty text'))
  if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
    return navigator.clipboard.writeText(text)
  }
  if (typeof document === 'undefined') {
    return Promise.reject(new Error('clipboard unavailable'))
  }

  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.setAttribute('readonly', 'readonly')
  textarea.style.position = 'fixed'
  textarea.style.left = '-9999px'
  textarea.style.top = '0'
  document.body.appendChild(textarea)
  textarea.select()

  try {
    const successful = document.execCommand('copy')
    document.body.removeChild(textarea)
    return successful ? Promise.resolve() : Promise.reject(new Error('copy failed'))
  } catch (error) {
    document.body.removeChild(textarea)
    return Promise.reject(error)
  }
}

export function copyLedgerShareLink(ledgerId) {
  return copyText(buildLedgerShareUrl(ledgerId))
}
