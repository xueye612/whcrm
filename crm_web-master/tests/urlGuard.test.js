'use strict'
/**
 * 知识链接 URL 渲染防御测试（纯 Node）。
 *
 * 直接 require 生产实现 src/utils/urlGuard.js，覆盖历史脏数据：
 * 仅允许绝对 http/https，拒绝 javascript:/data:/vbscript:/协议相对地址及控制字符绕过。
 * 并校验实施面板渲染期使用 safeUrl()（:href 绑定前再次校验）。
 *
 * 运行：node tests/urlGuard.test.js
 */
const path = require('path')
const fs = require('fs')
const urlGuard = require(path.resolve(__dirname, '..', 'src', 'utils', 'urlGuard.js'))
const { isSafeHttpUrl, normalizeHttpUrl } = urlGuard

let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }

check(typeof isSafeHttpUrl === 'function' && typeof normalizeHttpUrl === 'function', '应加载真实 urlGuard 方法')

// 合法（与后端同一组测试向量）
check(isSafeHttpUrl('https://example.com') === true, 'https://example.com 合法')
check(isSafeHttpUrl('http://example.com/path?x=1') === true, 'http://example.com/path?x=1 合法')
check(isSafeHttpUrl('HTTP://EXAMPLE.COM') === true, '大小写混合 http(s) 合法')
check(isSafeHttpUrl('https://xn--fsqu00a.xn--0zwm56d') === true, 'punycode 域名 合法')
check(isSafeHttpUrl('http://1.2.3.4') === true, 'IPv4 合法')
check(isSafeHttpUrl('http://localhost') === true, 'localhost 合法')
check(isSafeHttpUrl('http://example.com:8080') === true, '合法端口 合法')
check(isSafeHttpUrl('http://[::1]') === true, '合法 IPv6 [::1]')
check(isSafeHttpUrl('http://[2001:db8::1]') === true, '合法 IPv6 [2001:db8::1]')
check(isSafeHttpUrl('http://[2001:db8:0:0:0:0:2:1]') === true, '合法 IPv6 全格式 [2001:db8:0:0:0:0:2:1]')
check(isSafeHttpUrl('http://[::ffff:192.0.2.1]') === true, '合法 IPv4 映射 IPv6 [::ffff:192.0.2.1]')
check(isSafeHttpUrl('http://[::ffff:192.0.2.1]:8080/path') === true, '合法 IPv4 映射 IPv6 + 端口 + 路径')
check(normalizeHttpUrl('  https://a.com  ') === 'https://a.com', '规范化去首尾空白')

// 非法（与后端同一组向量）
check(isSafeHttpUrl('http://[notipv6]') === false, '非法 IPv6 [notipv6]')
check(isSafeHttpUrl('http://[:::1]') === false, '非法 IPv6 [:::1]')
check(isSafeHttpUrl('http://[1:2:3]') === false, '非法 IPv6 [1:2:3] 组数不足')
check(isSafeHttpUrl('http://[2001:db8::1') === false, '非法 IPv6 未闭合方括号')
check(isSafeHttpUrl('http://2001:db8::1') === false, '非法 IPv6 未加方括号')
check(isSafeHttpUrl('http://[::1]]/') === false, '非法 IPv6 多余方括号')
check(isSafeHttpUrl('https://user:pass@example.com') === false, 'userinfo 被拒')
check(isSafeHttpUrl('https://例子.测试') === false, '原始 Unicode 主机被拒（须 punycode）')
check(isSafeHttpUrl('https://example..com') === false, '连续点 example..com 被拒')
check(isSafeHttpUrl('https://example.com:65536') === false, '端口超 65535 被拒')
check(isSafeHttpUrl('https://exa mple.com') === false, '主机含空格被拒')

// 主机名/空白/端口补充拒绝
check(isSafeHttpUrl('https:// example.com') === false, ':// 后前置空格被拒')
check(isSafeHttpUrl('https://.') === false, 'https://. 被拒')
check(isSafeHttpUrl('https://example.com:abc') === false, '非数字端口 :abc 被拒')
check(isSafeHttpUrl('http://example.com:99999') === false, '端口超 65535 被拒')

// 主机名为空 / 缺主机名必须拒绝
check(isSafeHttpUrl('http://') === false, 'http:// 缺主机名被拒')
check(isSafeHttpUrl('https://?x') === false, 'https://?x 缺主机名被拒')
check(isSafeHttpUrl('http:///path') === false, 'http:///path 缺主机名被拒')

// 危险协议
check(isSafeHttpUrl('javascript:alert(1)') === false, 'javascript: 被拒')
check(isSafeHttpUrl('JaVaScRiPt:alert(1)') === false, '大小写 javascript: 被拒')
check(isSafeHttpUrl('data:text/html,<script>') === false, 'data: 被拒')
check(isSafeHttpUrl('vbscript:msgbox') === false, 'vbscript: 被拒')

// 协议相对地址 / 普通相对路径
check(isSafeHttpUrl('//evil.com/x') === false, '协议相对地址被拒')
check(isSafeHttpUrl('/relative/path') === false, '相对路径被拒')
check(isSafeHttpUrl('ftp://a.com') === false, 'ftp 被拒')

// 控制字符 / NULL 字节 / 前置空白绕过
check(isSafeHttpUrl(' javascript:alert(1)') === false, '前置空格 javascript: 被拒')
check(isSafeHttpUrl('\tjavascript:alert(1)') === false, '前置制表符 javascript: 被拒')
check(isSafeHttpUrl('\njavascript:alert(1)') === false, '前置换行 javascript: 被拒')
check(isSafeHttpUrl('java\tscript:alert(1)') === false, '内嵌控制字符被拒')
check(isSafeHttpUrl('https://a.com\x00') === false, '尾部空字节被拒')
check(normalizeHttpUrl('javascript:alert(1)') === '', '危险地址规范化为空串')
check(normalizeHttpUrl('http://') === '', '缺主机名规范化为空串')
check(normalizeHttpUrl('https://example..com') === '', '连续点规范化为空串')
check(normalizeHttpUrl('') === '', '空地址规范化为空串')

// 实施面板渲染期使用 safeUrl（:href 绑定前再次校验），历史非法记录显示“无效地址”
const panelSrc = fs.readFileSync(path.join(__dirname, '..', 'src/views/pm/project/components/ProjectImplementationPanel.vue'), 'utf8')
check(panelSrc.indexOf('normalizeHttpUrl') !== -1, '面板应导入 normalizeHttpUrl')
check(/safeUrl\(s\.row\.url\)/.test(panelSrc), '地址列渲染期应调用 safeUrl')
check(panelSrc.indexOf('无效地址') !== -1, '历史非法地址应显示“无效地址”文本')
// 不应再直接绑定原始 url 到 href
check(!/:href="s\.row\.url"/.test(panelSrc), '不得直接绑定原始 url 到 href')

console.log('urlGuard test passed (' + count + ' assertions)')
