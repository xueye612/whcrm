export function getMobileViewportWidth() {
  if (typeof window === 'undefined') return 0
  if (window.visualViewport && window.visualViewport.width > 0) {
    return Math.round(window.visualViewport.width)
  }
  return Math.round(window.innerWidth)
}

export function getMobileViewportHeight() {
  if (typeof window === 'undefined') return 0
  if (window.visualViewport && window.visualViewport.height > 0) {
    return Math.round(window.visualViewport.height)
  }
  return Math.round(window.innerHeight)
}

export function syncMobileViewport(rootEl) {
  if (typeof window === 'undefined') return

  const app = document.getElementById('app')
  if (app) {
    app.style.minWidth = '0'
    app.style.minHeight = '0'
  }

  const w = getMobileViewportWidth()
  const h = getMobileViewportHeight()
  if (!w) return

  const doc = document.documentElement
  doc.style.setProperty('--mvw', `${w}px`)
  doc.style.setProperty('--mvh', `${h}px`)
  doc.style.overflowX = 'hidden'
  doc.style.width = '100%'
  doc.style.maxWidth = `${w}px`

  document.body.style.overflowX = 'hidden'
  document.body.style.width = '100%'
  document.body.style.maxWidth = `${w}px`
  document.body.style.margin = '0'

  if (app) {
    app.style.width = '100%'
    app.style.maxWidth = `${w}px`
    app.style.overflow = 'hidden'
  }

  if (rootEl) {
    rootEl.style.width = '100%'
    rootEl.style.maxWidth = `${w}px`
  }
}

export function bindMobileViewport(rootEl) {
  const run = () => syncMobileViewport(rootEl)
  run()
  window.addEventListener('resize', run)
  window.addEventListener('orientationchange', run)
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', run)
  }
  return () => {
    window.removeEventListener('resize', run)
    window.removeEventListener('orientationchange', run)
    if (window.visualViewport) {
      window.visualViewport.removeEventListener('resize', run)
    }
  }
}
