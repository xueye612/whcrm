/**
 * 知识链接 / 外链 URL 安全规范化（CommonJS，可同时被 webpack/Vue 与 Node 测试加载）。
 *
 * 与后端 ProjectService::checkKnowledgeUrl 采用同一组规则：
 *  - 仅 ASCII（拒绝原始 Unicode 主机名；国际域名须传 punycode）
 *  - 协议严格 http/https；不得含控制字符、原始空白
 *  - 主机：合法域名 / IPv4 / localhost / 合法 [IPv6] 字面量
 *  - 端口：可选，纯数字 0-65535
 *  - 拒绝 username/password userinfo
 */

/**
 * 是否含 ASCII 控制字符（含制表符/换行/NULL）。逐字符按码点判定，避免在正则中书写控制字符。
 */
function hasControlChar(str) {
  for (var i = 0; i < str.length; i++) {
    var code = str.charCodeAt(i)
    if (code <= 0x1F || code === 0x7F) return true
  }
  return false
}

/**
 * 是否含非 ASCII 字符（原始 Unicode 主机名须拒绝，国际域名需传 punycode）。
 * 逐字符按码点判定，避免在正则中书写控制字符。
 */
function hasNonAscii(str) {
  for (var i = 0; i < str.length; i++) {
    if (str.charCodeAt(i) > 0x7F) return true
  }
  return false
}

/**
 * 校验端口：空合法；非空须为 1-5 位数字且 <=65535。
 */
function checkPort(port) {
  if (port === '' || port === null || port === undefined) return true
  if (!/^\d{1,5}$/.test(String(port))) return false
  return Number(port) <= 65535
}

/**
 * 校验主机名（域名 / IPv4 / localhost，不含 IPv6 与端口）。
 */
function isValidHost(host) {
  if (typeof host !== 'string' || host === '') return false
  if (/\s/.test(host)) return false
  host = host.replace(/\.$/, '')
  if (host === '') return false
  if (host === 'localhost') return true
  if (/^\d{1,3}(\.\d{1,3}){3}$/.test(host)) {
    var bad = false
    host.split('.').forEach(function(seg) { if (Number(seg) > 255) bad = true })
    return !bad
  }
  var labels = host.split('.')
  for (var i = 0; i < labels.length; i++) {
    var l = labels[i]
    if (l === '' || l.length > 63) return false
    if (!/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?$|^[a-zA-Z0-9]$/.test(l)) return false
  }
  return true
}

/**
 * 是否为安全的绝对 http/https 地址。
 * 不只依赖前缀正则：先用标准 URL 解析器判定可解析性与协议，再校验 userinfo/主机/端口。
 */
function isSafeHttpUrl(url) {
  if (typeof url !== 'string' || url === '') return false
  if (hasControlChar(url)) return false
  var v = url.trim()
  if (v === '') return false
  // 合法 http(s) URL 不得包含原始空白
  if (/\s/.test(v)) return false
  // 拒绝原始 Unicode 主机名（new URL 会自动转 punycode，必须在转换前识别）
  if (hasNonAscii(v)) return false
  // 紧随 :// 之后必须直接是主机
  if (!/^https?:\/\/[^/?#]/i.test(v)) return false
  var parsed
  try {
    parsed = new URL(v)
  } catch (e) {
    return false
  }
  if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return false
  // 拒绝 username/password
  if (parsed.username !== '' || parsed.password !== '') return false
  if (!checkPort(parsed.port)) return false
  // IPv6 字面量：parsed.hostname 以 '[' 开头；new URL(v) 已成功解析即为权威合法性，
  // 不再用 hex/冒号规则二次校验（避免误拒 IPv4 映射 IPv6 如 [::ffff:192.0.2.1]）。
  if (parsed.hostname.length > 0 && parsed.hostname.charAt(0) === '[') {
    return true
  }
  return isValidHost(parsed.hostname)
}

/**
 * 规范化：合法则返回去首尾空白的地址，非法返回空串。
 */
function normalizeHttpUrl(url) {
  if (typeof url !== 'string' || url === '') return ''
  if (!isSafeHttpUrl(url)) return ''
  return url.trim()
}

module.exports = {
  isSafeHttpUrl: isSafeHttpUrl,
  normalizeHttpUrl: normalizeHttpUrl
}
