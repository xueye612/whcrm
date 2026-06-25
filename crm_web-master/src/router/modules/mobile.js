/** Mobile ledger routes (H5) */
const mobileRouter = {
  path: '/m',
  component: () => import('@/views/mobile/Layout'),
  hidden: true,
  meta: {
    title: '移动台账',
    requiresAuth: true
  },
  children: [
    {
      path: 'ledger',
      name: 'mobile-ledger-list',
      component: () => import('@/views/mobile/ledger/MobileLedgerList'),
      meta: {
        title: '台账列表',
        requiresAuth: true,
        permissions: ['ledger', 'ledger', 'index']
      }
    },
    {
      path: 'ledger/quick',
      name: 'mobile-ledger-quick',
      component: () => import('@/views/mobile/ledger/MobileLedgerQuick'),
      meta: {
        title: '记台账',
        requiresAuth: true,
        permissions: ['ledger', 'ledger', 'save']
      }
    },
    {
      path: 'ledger/:id',
      name: 'mobile-ledger-detail',
      component: () => import('@/views/mobile/ledger/MobileLedgerDetail'),
      meta: {
        title: '台账详情',
        requiresAuth: true,
        permissions: ['ledger', 'ledger', 'index']
      }
    }
  ]
}

export default mobileRouter
