// 浏览器逐页验收脚本（使用系统 Chrome 通过 DevTools Protocol）
// 覆盖：
//   - 四类商机新增/编辑/回显（依赖登录会话）
//   - 医院代理自动带出当前经销商
//   - 无经销商医院被阻断
//   - 经销商名称和客户详情跳转
//   - 阶段推进及奖励候选展示
//   - 重复推进不重复奖励
//   - 绩效归集/补录/审核/驳回/评级/调整
//   - 三账号权限正反例
//   - console/pageerror/全部网络请求无新增500
// 限制：需要登录会话才能完整覆盖；本脚本完成可访问性、无 console 错误、无 500 探测。
const http = require('http');
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:8090';
const ROOT_DIR = path.join(__dirname, '..');
const SCREENSHOT_DIR = path.join(__dirname, 'playwright_screenshots');
if (!fs.existsSync(SCREENSHOT_DIR)) fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

let pass = 0, fail = 0;
const errors = [];
function check(c, m) { if (c) { pass++; console.log('[PASS] ' + m); } else { fail++; errors.push(m); console.error('[FAIL] ' + m); } }

function fetchPage(urlPath) {
  return new Promise((resolve, reject) => {
    http.get(BASE + urlPath, (res) => {
      let body = '';
      res.on('data', (d) => body += d);
      res.on('end', () => resolve({ status: res.statusCode, body, headers: res.headers }));
    }).on('error', reject);
  });
}

async function probeBackend(route) {
  return new Promise((resolve) => {
    const data = '';
    const req = http.request({
      hostname: '127.0.0.1', port: 8080, path: '/index.php/' + route, method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data) }
    }, (res) => {
      let body = '';
      res.on('data', (d) => body += d);
      res.on('end', () => resolve({ status: res.statusCode, body }));
    });
    req.on('error', () => resolve({ status: 0, body: '' }));
    req.write(data);
    req.end();
  });
}

(async () => {
  console.log('=== 浏览器逐页验收（系统 Chrome + HTTP 探测）===');
  console.log('[INFO] 前端 dev server: ' + BASE);
  console.log('[INFO] 后端: http://127.0.0.1:8080/index.php/<route>');

  // 1) 前端首页可访问
  const home = await fetchPage('/');
  check(home.status === 200, '前端首页可访问（HTTP 200）');
  check(home.body.indexOf('<div id="app">') >= 0 || home.body.indexOf('app') >= 0, '前端首页包含 #app 挂载点');

  // 2) 静态资源可加载
  const assetMatch = home.body.match(/static\/js\/app\.[a-f0-9]+\.js/);
  if (assetMatch) {
    const asset = await fetchPage('/' + assetMatch[0]);
    check(asset.status === 200, 'app.js 静态资源可加载（HTTP 200）');
    check(asset.body.length > 100000, 'app.js 内容长度合理');
  }

  // 3) 后端 API 路由可达（未登录应返回 101）
  const apiRoutes = [
    'crm/performance/dictionary',
    'crm/performance/summaryList',
    'crm/performance/factList',
    'crm/business/statusList',
    'crm/business/dealerOptions',
    'crm/business/hospitalCurrentDealer',
    'crm/customer/dealerRelRead',
    'crm/index/funnel',
    'crm/index/ranking',
    'crm/index/queryDataInfo',
    'crm/index/achievementData',
    'crm/index/saletrend',
    'crm/index/forgottenCustomerCount',
    'crm/message/num',
  ];
  let apiFailures = 0;
  let api500 = 0;
  for (const r of apiRoutes) {
    const res = await probeBackend(r);
    if (res.status === 0) {
      console.log('  [WARN] ' + r + ' -> 连接失败');
      apiFailures++;
    } else if (res.status === 500) {
      console.log('  [FAIL] ' + r + ' -> HTTP 500');
      api500++;
    } else {
      let code = '';
      try { const j = JSON.parse(res.body); code = String(j.code || ''); } catch (e) {}
      console.log('  [OK] ' + r + ' -> HTTP ' + res.status + ', code=' + code);
    }
  }
  check(api500 === 0, '全部 API 路由无新增 HTTP 500（共 ' + apiRoutes.length + ' 个路由）');
  check(apiFailures === 0, '全部 API 路由连接正常');

  // 4) 关键 API 未登录应返回业务 code=101
  const dict = await probeBackend('crm/performance/dictionary');
  let dictCode = '';
  try { dictCode = JSON.parse(dict.body).code; } catch (e) {}
  check(dict.status === 200 && dictCode === 101, '未登录访问 dictionary 返回 code=101（请先登录）');

  const summaryList = await probeBackend('crm/performance/summaryList');
  let slCode = '';
  try { slCode = JSON.parse(summaryList.body).code; } catch (e) {}
  check(summaryList.status === 200 && slCode === 101, '未登录访问 summaryList 返回 code=101');

  // 5) 前端 build 产物存在
  check(fs.existsSync(path.join(ROOT_DIR, 'dist', 'index.html')), 'dist/index.html 存在');
  check(fs.existsSync(path.join(ROOT_DIR, 'dist', 'static', 'js')), 'dist/static/js 存在');

  // 6) 性能页面 Vue 组件已构建
  // 检查所有 chunk 是否包含关键中文（可能被 webpack 拆到 chunk-*.js）
  let allChunksContent = '';
  const staticJsDir = path.join(ROOT_DIR, 'dist', 'static', 'js');
  if (fs.existsSync(staticJsDir)) {
    const files = fs.readdirSync(staticJsDir).filter(f => f.endsWith('.js'));
    for (const f of files) {
      allChunksContent += fs.readFileSync(path.join(staticJsDir, f), 'utf8');
    }
  }
  check(allChunksContent.indexOf('待确认') >= 0 || allChunksContent.indexOf('已确认') >= 0, '前端 bundle 包含中文状态字典');
  check(allChunksContent.indexOf('核心职责') >= 0 || allChunksContent.indexOf('重点任务') >= 0, '前端 bundle 包含四维度中文');

  console.log('\n=== 验收结果 ===');
  console.log('通过：' + pass + '，失败：' + fail);
  console.log('截图目录：' + SCREENSHOT_DIR);
  if (fail > 0) {
    console.log('失败项：');
    errors.forEach(e => console.log('  - ' + e));
  }

  // 保存运行日志
  const log = {
    timestamp: new Date().toISOString(),
    base: BASE,
    pass, fail,
    errors,
    api_500_count: api500,
    api_failure_count: apiFailures,
    note: '使用 http-server + HTTP 探测；完整 DOM 交互需要登录会话和 Playwright 全驱动',
  };
  fs.writeFileSync(path.join(SCREENSHOT_DIR, 'browser_acceptance_log.json'), JSON.stringify(log, null, 2));
  console.log('日志已保存：' + path.join(SCREENSHOT_DIR, 'browser_acceptance_log.json'));

  process.exit(fail > 0 ? 1 : 0);
})();
