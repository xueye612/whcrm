import data from './emoji-data.js'
import sanitizeHtml from './sanitize'
let emojiData = {}
Object.values(data).forEach(item => {
  emojiData = {
    ...emojiData,
    ...item
  }
})

/**
 *
 *
 * @export
 * @param {string} value
 * @returns {string}
 */

export function emoji(value) {
  if (!value) return
  Object.keys(emojiData).forEach(item => {
    value = value.replace(new RegExp(item, 'g'), createIcon(item))
  })
  // 表情替换后统一净化，保留注入的 <img>，移除 script/事件属性/危险协议，防止 XSS
  return sanitizeHtml(value)
}

function createIcon(item) {
  const value = emojiData[item]
  const path = process.env.NODE_ENV == 'development' ? '../../static/img/emoji/' : './static/img/emoji/'
  return `<img src="${path}${value}" alt="${item}" width="16px" height="16px">`
}
