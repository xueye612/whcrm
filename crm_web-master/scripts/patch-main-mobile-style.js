const fs = require('fs')
const path = require('path')

const filePath = path.resolve(__dirname, '../src/main.js')
let content = fs.readFileSync(filePath, 'utf8')
if (!content.includes("import '@/styles/mobile-route.scss'")) {
  content = content.replace(
    "import '@/styles/index.scss' // global css",
    "import '@/styles/index.scss' // global css\nimport '@/styles/mobile-route.scss'"
  )
  fs.writeFileSync(filePath, content, 'utf8')
  console.log('main.js updated')
} else {
  console.log('main.js already has mobile-route import')
}
