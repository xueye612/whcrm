const fs = require('fs')
const path = require('path')

const filePath = path.resolve(__dirname, '../src/views/login/component/LoginByPwd.vue')
let content = fs.readFileSync(filePath, 'utf8')
const hadCRLF = content.includes('\r\n')
content = content.replace(/\r\n/g, '\n')

if (!content.includes("import { resolvePostLoginPath } from '@/utils/mobileClient'")) {
  content = content.replace(
    "import { debounce } from 'throttle-debounce'",
    "import { debounce } from 'throttle-debounce'\nimport { resolvePostLoginPath } from '@/utils/mobileClient'"
  )
}

const oldBlock = `      this.$store
        .dispatch('Login', this.form)
        .then(res => {
          this.$router.push({ path: this.redirect || '/' })
        })
        .catch(() => {
          loading.close()
        })`

const newBlock = `      this.$store
        .dispatch('Login', this.form)
        .then(res => {
          if (res.data && res.data.hasOwnProperty('companyList')) {
            loading.close()
            return
          }
          return this.$store.dispatch('getAuth').then(auth => {
            const path = resolvePostLoginPath(this.redirect, auth)
            this.$router.push({ path })
          }).catch(() => {
            this.$router.push({ path: this.redirect || '/' })
          })
        })
        .catch(() => {})
        .finally(() => {
          loading.close()
        })`

if (!content.includes(oldBlock)) {
  console.error('Missing login handler block')
  process.exit(1)
}
content = content.replace(oldBlock, newBlock)

fs.writeFileSync(filePath, hadCRLF ? content.replace(/\n/g, '\r\n') : content, 'utf8')
console.log('LoginByPwd.vue patched')
