'use strict'
/**
 * 统一测试运行器：执行 tests/ 下全部 *.test.js，任一失败即以非零状态退出。
 * 只读验证：不携带任何 --fix，不修改源码。
 *
 * 运行：npm run test:unit
 */
const { execFileSync } = require('child_process')
const fs = require('fs')
const path = require('path')

const dir = __dirname
const files = fs.readdirSync(dir)
  .filter(f => /^[\w.-]+\.test\.js$/.test(f))
  .sort()

if (!files.length) {
  console.error('No *.test.js files found under ' + dir)
  process.exit(1)
}

let failed = 0
const node = process.execPath
files.forEach(f => {
  const full = path.join(dir, f)
  process.stdout.write('--- ' + f + ' ---\n')
  try {
    execFileSync(node, [full], { stdio: 'inherit' })
  } catch (e) {
    failed++
    process.stdout.write('>>> FAILED: ' + f + '\n')
  }
})

process.stdout.write('\n' + (files.length - failed) + '/' + files.length + ' test files passed\n')
process.exit(failed === 0 ? 0 : 1)
