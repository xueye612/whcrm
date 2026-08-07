'use strict'

const fs = require('fs')
const path = require('path')
const vm = require('vm')

const ROOT = path.resolve(__dirname, '..')
const read = relativePath => fs.readFileSync(path.join(ROOT, relativePath), 'utf8')

function loadDefaultExport(source) {
  const sandbox = { module: { exports: {}}}
  vm.runInNewContext(source.replace(/export default/, 'module.exports ='), sandbox)
  return sandbox.module.exports
}

const districts = loadDefaultExport(read('src/components/VDistpicker/districts.js'))
const componentSource = read('src/components/CreateCom/XhCustomerAddress.vue')
const script = componentSource.match(/<script[^>]*>([\s\S]*?)<\/script>/)[1]
  .replace(/^import .*$/gm, '')
  .replace(/export default/, 'module.exports =')
const sandbox = {
  module: { exports: {}},
  DISTRICTS: districts,
  VDistpicker: {}
}
vm.runInNewContext(script, sandbox)

const methods = sandbox.module.exports.methods
const context = { areaNameMatched: methods.areaNameMatched }
const findRegion = address => methods.findRegion.call(context, address)

function assertRegion(address, expected) {
  const actual = findRegion(address)
  if (JSON.stringify(actual) !== JSON.stringify(expected)) {
    throw new Error(`${address} 识别错误：${JSON.stringify(actual)}`)
  }
}

assertRegion('山东省济南市历下区山大路143号', {
  province: '山东省', city: '济南市', area: '历下区'
})
assertRegion('山东济南历下山大路143号', {
  province: '山东省', city: '济南市', area: '历下区'
})
assertRegion('济南市历下区山大路143号', {
  province: '山东省', city: '济南市', area: '历下区'
})
assertRegion('山东省济南市山大路143号', null)
assertRegion('山大路143号', null)

console.log('客户详细地址省市区识别测试通过（5 cases）')
