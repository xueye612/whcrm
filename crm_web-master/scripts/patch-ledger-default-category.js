const fs = require('fs')

function patchIndex() {
  const path = 'e:/code/whcrm/crm_web-master/src/views/crm/ledger/index.vue'
  let text = fs.readFileSync(path, 'utf8').replace(/\r\n/g, '\n')
  if (!text.includes("from '@/utils/ledgerFormat'")) {
    text = text.replace(
      "import { isCompletedLedgerStatus, isClosedLedgerStatus, normalizeCompletionFields } from '@/utils/ledgerCompletion'",
      "import { DEFAULT_LEDGER_CATEGORY } from '@/utils/ledgerFormat'\nimport { isCompletedLedgerStatus, isClosedLedgerStatus, normalizeCompletionFields } from '@/utils/ledgerCompletion'"
    )
  } else if (!text.includes('DEFAULT_LEDGER_CATEGORY')) {
    text = text.replace(
      "from '@/utils/ledgerFormat'",
      "DEFAULT_LEDGER_CATEGORY } from '@/utils/ledgerFormat'"
    )
  }
  text = text.replace(
    "        category: '其他问题',",
    "        category: DEFAULT_LEDGER_CATEGORY,"
  )
  fs.writeFileSync(path, text)
  console.log('patched index.vue')
}

function patchRelativeLedger() {
  const path = 'e:/code/whcrm/crm_web-master/src/views/crm/components/RelativeLedger.vue'
  let text = fs.readFileSync(path, 'utf8').replace(/\r\n/g, '\n')
  text = text.replace(
    "import { DEFAULT_CATEGORY_OPTIONS, LEDGER_CHANNEL_OPTIONS } from '@/utils/ledgerFormat'",
    "import { DEFAULT_CATEGORY_OPTIONS, DEFAULT_LEDGER_CATEGORY, LEDGER_CHANNEL_OPTIONS } from '@/utils/ledgerFormat'"
  )
  text = text.replace(
    "        category: '其他问题',",
    "        category: DEFAULT_LEDGER_CATEGORY,"
  )
  fs.writeFileSync(path, text)
  console.log('patched RelativeLedger.vue')
}

patchIndex()
patchRelativeLedger()
