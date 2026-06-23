'use strict'
const merge = require('webpack-merge')
const prodEnv = require('./prod.env')

module.exports = merge(prodEnv, {
  NODE_ENV: '"development"',
  BASE_API: '"/api/"',
  // 调试地址，需要调试时请取消注释并修改为正确的后端地址
  PROXY_TARGET: '"https://s.u956.com"'
})
