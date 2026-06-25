const fs = require('fs')
const path = require('path')

const filePath = path.resolve(__dirname, '../src/views/login/index.vue')
let content = fs.readFileSync(filePath, 'utf8')
const hadCRLF = content.includes('\r\n')
content = content.replace(/\r\n/g, '\n')

const replacements = [
  [
    `  <div
    class="login-wrapper">`,
    `  <div
    class="login-wrapper"
    :class="{ 'login-wrapper--mobile': isMobileClient }">`
  ],
  [
    `          <component
            :is="activeCom"/>

          <div class="use-tip">`,
    `          <component
            :is="activeCom"/>

          <div v-if="isMobileClient" class="mobile-login-tip">
            已识别为移动设备，登录后将进入移动台账；如需桌面功能，可在移动台账底部进入「桌面版」。
          </div>

          <div v-if="!isMobileClient" class="use-tip">`
  ],
  [
    `import LoginByPwd from './component/LoginByPwd'

export default {`,
    `import LoginByPwd from './component/LoginByPwd'
import { isMobileClient } from '@/utils/mobileClient'

export default {`
  ],
  [
    `  data() {
    return {
      activeCom: 'LoginByPwd',
      titleMap: {
        LoginByPwd: '欢迎登录'
      }
    }
  },
  watch: {},
  created() {
  },
  methods: {}
}`,
    `  data() {
    return {
      activeCom: 'LoginByPwd',
      isMobileClient: false,
      titleMap: {
        LoginByPwd: '欢迎登录'
      }
    }
  },
  created() {
    this.syncMobileLoginClass()
    if (typeof window !== 'undefined') {
      window.addEventListener('resize', this.syncMobileLoginClass)
    }
  },
  beforeDestroy() {
    if (typeof window !== 'undefined') {
      window.removeEventListener('resize', this.syncMobileLoginClass)
    }
    const app = document.getElementById('app')
    if (app) app.classList.remove('is-mobile-login')
  },
  methods: {
    syncMobileLoginClass() {
      this.isMobileClient = isMobileClient()
      const app = document.getElementById('app')
      if (app) {
        app.classList.toggle('is-mobile-login', this.isMobileClient)
        if (this.isMobileClient) {
          app.style.minWidth = ''
          app.style.minHeight = ''
        }
      }
    }
  }
}`
  ],
  [
    `.login-wrapper {
  position: relative;
  width: 100%;
  height: 100%;
  background: url('~@/assets/login/bg.png') center no-repeat;
  background-size: cover;
  display: flex;
  flex-direction: column;
  overflow: auto;`,
    `.login-wrapper {
  position: relative;
  width: 100%;
  height: 100%;
  background: url('~@/assets/login/bg.png') center no-repeat;
  background-size: cover;
  display: flex;
  flex-direction: column;
  overflow: auto;

  &.login-wrapper--mobile {
    .container {
      margin-top: 0;
      padding: 12px;
      align-items: stretch;
    }

    .left {
      display: none;
    }

    .right {
      width: 100%;
      max-width: 420px;
      margin: 0 auto;
      padding-top: 0;
    }

    .login-main-content {
      height: auto;
      min-height: 420px;
      padding-bottom: 16px;
    }

    .top-nav {
      padding: 16px 12px 0;
    }
  }

  .mobile-login-tip {
    margin: 0 24px 12px;
    padding: 10px 12px;
    font-size: 13px;
    line-height: 1.5;
    color: #606266;
    background: #ecf5ff;
    border: 1px solid #d9ecff;
    border-radius: 6px;
  }`
  ]
]

for (const [from, to] of replacements) {
  if (!content.includes(from)) {
    console.error('Missing block in login/index.vue:', from.slice(0, 100))
    process.exit(1)
  }
  content = content.replace(from, to)
}

fs.writeFileSync(filePath, hadCRLF ? content.replace(/\n/g, '\r\n') : content, 'utf8')
console.log('login/index.vue patched')
