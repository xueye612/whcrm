/**
 * 统一、可靠的 HTML 白名单净化（CommonJS，可同时被 webpack/Vue 与 Node 测试加载）。
 *
 * 保留业务需要的基础格式、链接与图片，移除 script、事件属性(on*)、
 * iframe 及 javascript:/data:/vbscript: 等危险协议。
 *
 * 任何来自用户或后端的富文本，在 v-html 渲染前都应经过本函数。
 */
const xss = require('xss')

const WHITE_LIST = {
  a: ['href', 'title', 'target', 'rel'],
  abbr: ['title'],
  b: [],
  blockquote: ['cite'],
  br: [],
  code: [],
  em: [],
  i: [],
  img: ['src', 'alt', 'width', 'height', 'title'],
  li: [],
  ol: [],
  p: [],
  pre: [],
  s: [],
  small: [],
  span: ['style', 'class'],
  strong: [],
  sub: [],
  sup: [],
  u: [],
  h1: [],
  h2: [],
  h3: [],
  h4: [],
  h5: [],
  h6: [],
  table: [],
  thead: [],
  tbody: [],
  tr: [],
  td: ['colspan', 'rowspan'],
  th: ['colspan', 'rowspan'],
  hr: [],
  div: ['style', 'class'],
  font: ['color', 'size', 'face'],
  ul: [],
  dl: [],
  dt: [],
  dd: []
}

const XSS_OPTIONS = {
  whiteList: WHITE_LIST,
  // 移除不在白名单中的标签但保留其内部文本，避免丢失正文
  stripIgnoreTag: true,
  // 对危险标签整体丢弃其内容（含正文）
  stripIgnoreTagBody: ['script', 'style', 'xml', 'iframe', 'noscript', 'template', 'object', 'embed'],
  safeAttrValue: function(tag, name, value, cssFilter) {
    if ((name === 'href' || name === 'src') && value !== undefined && value !== null) {
      const v = String(value).trim().toLowerCase()
      if (
        v.indexOf('javascript:') === 0 ||
        v.indexOf('vbscript:') === 0 ||
        v.indexOf('data:') === 0
      ) {
        return ''
      }
    }
    return xss.safeAttrValue(tag, name, value, cssFilter)
  }
}

const filter = new xss.FilterXSS(XSS_OPTIONS)

function sanitizeHtml(html) {
  if (html === null || html === undefined || html === '') return ''
  return filter.process(String(html))
}

module.exports = sanitizeHtml
module.exports.sanitizeHtml = sanitizeHtml
module.exports.DEFAULT_WHITE_LIST = WHITE_LIST
module.exports.default = sanitizeHtml
