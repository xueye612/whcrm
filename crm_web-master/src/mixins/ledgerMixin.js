import { ledgerCategoryListAPI } from '@/api/admin/other'
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
    updateMobileLedgerHint() {
      if (typeof window === 'undefined') return
      this.showMobileHint = isMobileClient()
    }
  }
}
