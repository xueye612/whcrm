<template>
  <div class="ledger-redirect" />
</template>

<script>
import { isMobileClient } from '@/utils/mobileClient'

export default {
  name: 'DeviceLedgerRedirect',
  created() {
    const id = this.$route.params.id || this.$route.query.ledger_id || this.$route.query.id || ''
    const ledgerId = String(id || '').trim()
    if (isMobileClient()) {
      this.$router.replace(ledgerId ? `/m/ledger/${ledgerId}` : '/m/ledger')
      return
    }
    this.$router.replace({
      path: '/crm/ledger',
      query: ledgerId ? { ledger_id: ledgerId, desktop: 1 } : { desktop: 1 }
    })
  }
}
</script>
