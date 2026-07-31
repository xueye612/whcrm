'use strict'
const fs = require('fs'); const path = require('path'); let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }
const ROOT = path.resolve(__dirname, '..')

// Verify deleted modules
check(!fs.existsSync(path.join(ROOT, 'src/api/crm/opportunity.js')), 'opportunity.js removed')
check(!fs.existsSync(path.join(ROOT, 'src/api/crm/leadpool.js')), 'leadpool.js removed')
check(fs.existsSync(path.join(ROOT, 'src/api/crm/reward.js')), 'reward.js exists')

// === Reward API has manual rule endpoints ===
const rewardApi = fs.readFileSync(path.join(ROOT, 'src/api/crm/reward.js'), 'utf8')
check(rewardApi.includes('ManualRuleListAPI'), 'reward API has manualRuleList')
check(rewardApi.includes('ManualRuleSaveAPI'), 'reward API has manualRuleSave')
check(rewardApi.includes('CandidateReadAPI'), 'reward API has candidateRead')
check(rewardApi.includes('CandidateUpdateAPI'), 'reward API has candidateUpdate')
check(rewardApi.includes('CandidateAuditListAPI'), 'reward API has candidateAuditList')

// === Reward page structure ===
const rewardVue = fs.readFileSync(path.join(ROOT, 'src/views/crm/reward/index.vue'), 'utf8')

// 3 tabs
check(rewardVue.includes('奖惩记录'), 'reward page has records tab')
check(rewardVue.includes('奖惩项目配置'), 'reward page has manual rule config tab')
check(rewardVue.includes('系统参数'), 'reward page has system config tab')

// Simplified candidate form: only project+person+date+reason
check(rewardVue.includes('manual_rule_id'), 'candidate form uses manual_rule_id')
check(rewardVue.includes('候选人员'), 'candidate form has person select')
check(rewardVue.includes('所属日期'), 'candidate form has date')
check(rewardVue.includes('事由说明'), 'candidate form has reason')
check(!rewardVue.includes('el-radio-group v-model="form.direction"'), 'candidate form does NOT have direction radio')
// 区间模式允许填写金额，固定模式不允许手改金额
check(rewardVue.indexOf('calc_mode') !== -1, 'reward page supports calc_mode (fixed/range/pool)')
check(rewardVue.indexOf('ruleOptionLabel') !== -1, 'reward page has rule option label method')
check(rewardVue.indexOf('ruleAmountDisplay') !== -1, 'reward page has rule amount display method')
check(!rewardVue.includes('queryUserList'), 'reward page does NOT call queryUserList')

// User selection uses usersListIndexAPI
check(rewardVue.includes('usersListIndexAPI'), 'reward page uses usersListIndexAPI from common.js')

// Manual rule management
check(rewardVue.includes('fetchManualRules'), 'reward page fetches manual rules')
check(rewardVue.includes('openRuleEdit'), 'reward page has rule edit')
check(rewardVue.includes('toggleRule'), 'reward page can toggle rule enabled/disabled')

// Review with self-review note
check(rewardVue.includes('openReview'), 'reward page has openReview method')
check(rewardVue.includes('isSelfCandidate'), 'reward page checks self-candidate for review')
check(rewardVue.includes('审核自己作为奖惩对象'), 'reward page requires note for self-candidate review')

// Audit timeline
check(rewardVue.includes('CandidateAuditList'), 'reward page has audit list')
check(rewardVue.includes('opLabel'), 'reward page has operation type labels')

// Edit uses can_edit from backend
check(rewardVue.includes('can_edit'), 'reward page uses backend can_edit flag')

// Config save button
check(rewardVue.includes('saveAllConfig'), 'reward page has save config button')
check(rewardVue.includes('保存配置'), 'reward page has visible save config button')

// Stats and pagination
check(rewardVue.includes('rp-stats'), 'reward page has statistics cards')
check(rewardVue.includes('rp-pager'), 'reward page has pagination')

// No longer uses ruleSave for manual projects
check(!rewardVue.includes('rewardRuleSaveAPI'), 'reward page does NOT use ruleSave for manual rules')
check(rewardVue.includes('rewardRuleListAPI'), 'reward page uses ruleListAPI for stage rules sub-tab')
check(rewardVue.includes('fetchStageRules'), 'reward page fetches stage rules')
check(rewardVue.includes('ruleSubTab'), 'reward page has sub-tabs for manual/stage rules')

// === Backend checks ===
const rewardPhp = fs.readFileSync(path.resolve(__dirname, '../../crm_php-master/application/crm/controller/Reward.php'), 'utf8')
check(rewardPhp.includes('function manualRuleList'), 'backend has manualRuleList')
check(rewardPhp.includes('function manualRuleSave'), 'backend has manualRuleSave')
check(!rewardPhp.includes("r\\.\\*"), 'backend does not use r.* in queries')
check(rewardPhp.includes('buildCandidateQuery'), 'backend uses separate Query objects')
check(rewardPhp.includes('safeInsertAudit'), 'backend has safe audit insert helper')
check(rewardPhp.includes('isSelfCandidate'), 'backend checks self-candidate in review')
check(!rewardPhp.includes('assertNotSelf'), 'backend does not blanket-block self-review')
check(rewardPhp.includes('manual_rule_id'), 'backend candidateSave uses manual_rule_id')
check(!rewardPhp.includes("'amount' => \$param['amount']"), 'backend candidateSave does not trust frontend amount')

// === Ledger statistics exists ===
check(fs.existsSync(path.join(ROOT, 'src/views/crm/ledger/statistics.vue')), 'ledger statistics page exists')

// === No queryUserList anywhere in reward ===
check(!rewardVue.includes('admin/user/queryUserList'), 'no queryUserList API call')

console.log('P5 reward frontend test passed (' + count + ' assertions)')
