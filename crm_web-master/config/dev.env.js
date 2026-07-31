'use strict'
const merge = require('webpack-merge')
const prodEnv = require('./prod.env')

module.exports = merge(prodEnv, {
  NODE_ENV: '"development"',
  BASE_API: '"/api/"',
  // 调试后端地址：本机启动前端时通过环境变量 CRM_PROXY_TARGET 指定，例如 PowerShell:
  //   $env:CRM_PROXY_TARGET='<本机后端地址>'; npm run dev
  // 未设置时默认使用本地地址
  PROXY_TARGET: JSON.stringify(process.env.CRM_PROXY_TARGET || 'http://localhost')
})
