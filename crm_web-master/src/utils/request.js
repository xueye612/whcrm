import axios from 'axios'
import {
  Message,
  MessageBox
} from 'element-ui'
import {
  removeAuth
} from '@/utils/auth'
import qs from 'qs'
import { debounce } from 'throttle-debounce'
import router from '../router'

/**
 * 检查dom是否忽略
 * @param {*} e
 */
const clearCacheEnterLogin = debounce(500, () => {
  removeAuth().then(() => {
    location.reload() // 为了重新实例化vue-router对象 避免bug
  }).catch(() => {
    location.reload()
  })
})

const errorMessage = debounce(500, (message, type = 'error') => {
  Message({
    message: message,
    duration: 1500,
    type: type
  })
})

const confirmMessage = debounce(1000, (message) => {
  MessageBox.confirm(message, '提示', {
    confirmButtonText: '确定',
    showCancelButton: false,
    type: 'warning'
  }).then(() => {
    clearCacheEnterLogin()
  }).catch(() => {
  })
})

axios.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8'
// 创建axios实例
let hrefs = []
if (window.location.href.indexOf('index.html') != -1) {
  hrefs = window.location.href.split('index.html')
} else {
  hrefs = window.location.href.split('#')
}
const baseURL = hrefs.length > 0 ? hrefs[0] : window.location.href
// baseURL + 'index.php/' 默认请求地址
// process.env.BASE_API 自定义请求地址

window.BASE_URL = process.env.NODE_ENV === 'production' ? baseURL + 'index.php/' : process.env.BASE_API
window.API_PROXY_TARGET = process.env.PROXY_TARGET || '/'

const service = axios.create({
  baseURL: window.BASE_URL, // api 的 base_url
  timeout: 600000 // 请求超时时间
})

// request拦截器
service.interceptors.request.use(
  config => {
    const flag = config.headers['Content-Type'] && config.headers['Content-Type'].indexOf('application/json') !== -1
    if (!flag) {
      const mult = config.headers['Content-Type'] && config.headers['Content-Type'].indexOf('multipart/form-data') !== -1
      if (mult) {
        config.data = config.data
      } else {
        config.data = qs.stringify(config.data)
      }
    } else {
      if (config.data === undefined || config.data === null) {
        // 不传参的情况下 json类型的提交数据，校准为 空对象
        config.data = {}
      }
    }
    return config
  },
  error => {
    // Do something with request error
    return Promise.reject(error)
  }
)

// response 拦截器
service.interceptors.response.use(
  response => {
    /**
     * code为非20000是抛错 可结合自己业务进行修改
     */
    const res = response.data
    const metadata = {
      status: response.status,
      contentType: response.headers['content-type'] || ''
    }
    if (res && typeof res === 'object') {
      res.__status = metadata.status
      res.__contentType = metadata.contentType
    }
    if (typeof res === 'string') {
      // 如果是纯文本响应（如"悟空软件"），可能是404或miss路由，不显示错误
      if (res.trim() === '开发软件' || res.trim().length < 20) {
        return Promise.reject(new Error('接口不存在'))
      }
      errorMessage(res)
      return Promise.reject(new Error(res))
    }
    if (response.status === 200 && response.config.responseType === 'blob') { // 文件类型特殊处理
      if (response.headers['content-disposition'] || (response.headers['content-type'] && response.headers['content-type'].indexOf('application/pdf') != -1)) {
        return response
      } else {
        const resultBlob = new Blob([response.data], { type: 'application/json' })
        const fr = new FileReader()
        fr.onload = function() {
          const result = JSON.parse(this.result)
          if (result.msg) {
            errorMessage(result.msg, result.code == 1 ? 'success' : 'error')
          }
        }
        fr.readAsText(resultBlob)
      }
    } else if (res.code !== 200) {
      // 302	登录已失效
      if (res.code === 302) {
        if (res.data.extra === 1) {
          confirmMessage(`您的账号${res.data.extraTime}在别处登录。如非本人操作，则密码可能已泄漏，建议修改密码`)
        } else {
          clearCacheEnterLogin()
        }
      } else if (res.code === 1005) {
        router.push('/welcome')
      } else if (res.code === 404) {
        // 404错误静默处理，不显示错误提示
        return Promise.reject(new Error('接口不存在'))
      } else {
        if (res.error) {
          errorMessage(res.error)
        }
      }
      return Promise.reject(res)
    } else {
      return res
    }
  },
  error => {
    if (error.response) {
      const response = error.response
      // 过滤掉纯文本响应（如"悟空软件"）
      if (response.data && typeof response.data === 'string' && response.data.trim() === '悟空软件') {
        return Promise.reject(new Error('接口不存在'))
      }
      // 404错误静默处理，不显示错误提示
      if (response.status == 404) {
        return Promise.reject(new Error('接口不存在'))
      }
      if (response.status == 500) {
        errorMessage('网络错误，请检查您的网络')
      } else if (response.data && response.data.msg) {
        errorMessage(response.data.msg)
      }
    } else if (error.message && (error.message.includes('接口不存在') || error.message.includes('404'))) {
      // 静默处理接口不存在错误
      return Promise.reject(error)
    } else if (!error.response && error.message && !error.message.includes('接口不存在')) {
      // 网络异常错误，只在非接口不存在的情况下显示
      if (error.message !== '接口不存在') {
        errorMessage('网络异常，请检查您的网络')
      }
    }
    return Promise.reject(error)
  }
)

export default service
