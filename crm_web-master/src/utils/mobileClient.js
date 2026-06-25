export function isMobileClient() {
  if (typeof window === 'undefined') return false
  const ua = navigator.userAgent || ''
  const mobileUa = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|mobile/i.test(ua)
  const narrowViewport = window.innerWidth <= 768
  return mobileUa || narrowViewport
}

export function hasLedgerIndexAuth(authInfo) {
  return !!(authInfo && authInfo.ledger && authInfo.ledger.ledger && authInfo.ledger.ledger.index)
}

export function resolveMobileLedgerPath(authInfo) {
  if (isMobileClient() && hasLedgerIndexAuth(authInfo)) {
    return '/m/ledger'
  }
  return ''
}

export function resolvePostLoginPath(redirect, authInfo) {
  const target = redirect && redirect !== '/' ? redirect : ''
  if (target) return target
  const mobilePath = resolveMobileLedgerPath(authInfo)
  if (mobilePath) return mobilePath
  return '/'
}

export function resolveDefaultHomePath(fallback, authInfo) {
  const mobilePath = resolveMobileLedgerPath(authInfo)
  if (mobilePath) return mobilePath
  return fallback || '/'
}
