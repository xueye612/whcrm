import { ledgerCategoryListAPI } from '@/api/admin/other'
import { ledgerIndexAPI } from '@/api/ledger/ledger'
import {
  formatLedgerDate,
  getFeedbackContactLabel,
  statusTagClass,
  statusTagType,
  stripHtml
} from '@/utils/ledgerFormat'
import { isMobileClient } from '@/utils/mobileClient'

export default {
  methods: {
    formatLedgerDate,
    getFeedbackContactLabel,
    statusTagClass,
    statusTagType,
    stripHtml,
    fetchLedgerCategoryOptions(targetKey = 'categoryOptions') {
      return ledgerCategoryListAPI()
        .then(res => {
          const list = (res.data || []).filter(item => item && String(item).trim() !== '')
          if (list.length && this[targetKey]) {
            this[targetKey] = list
          }
          return list
        })
        .catch(() => [])
    },
    fetchLedgerStats(targetKey = 'stats') {
      if (!this.canRead) return Promise.resolve()
      const p1 = ledgerIndexAPI({ page: 1, limit: 1 }).then(res => (res.data ? res.data.dataCount : 0))
      const p2 = ledgerIndexAPI({ page: 1, limit: 1, status: '待处理' }).then(res => (res.data ? res.data.dataCount : 0))
      const p3 = ledgerIndexAPI({ page: 1, limit: 1, status: '处理中' }).then(res => (res.data ? res.data.dataCount : 0))
      const p4 = ledgerIndexAPI({ page: 1, limit: 1, status: '待发布' }).then(res => (res.data ? res.data.dataCount : 0))
      const p5 = ledgerIndexAPI({ page: 1, limit: 1, status: '已完成' }).then(res => (res.data ? res.data.dataCount : 0))
      return Promise.all([p1, p2, p3, p4, p5]).then(([total, pending, processing, releasePending, completed]) => {
        const stats = { total, pending, processing, releasePending, completed }
        if (this[targetKey]) {
          this[targetKey] = stats
        }
        return stats
      })
    },
    updateMobileLedgerHint() {
      if (typeof window === 'undefined') return
      this.showMobileHint = isMobileClient()
    }
  }
}
