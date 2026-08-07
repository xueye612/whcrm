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
const rewardRoutes = fs.readFileSync(path.resolve(__dirname, '../../crm_php-master/config/route_crm.php'), 'utf8')
check(rewardApi.includes('ManualRuleListAPI'), 'reward API has manualRuleList')
check(rewardApi.includes('ManualRuleSaveAPI'), 'reward API has manualRuleSave')
check(rewardApi.includes('CandidateReadAPI'), 'reward API has candidateRead')
check(rewardApi.includes('CandidateUpdateAPI'), 'reward API has candidateUpdate')
check(rewardApi.includes('CandidateDeleteAPI'), 'reward API has candidateDelete')
check(rewardRoutes.includes('crm/reward/candidateDelete'), 'reward candidateDelete route is registered')
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
check(rewardVue.includes('添加奖励'), 'candidate form has explicit reward entry')
check(rewardVue.includes('添加处罚'), 'candidate form has explicit penalty entry')
check(rewardVue.includes('candidateManualRules'), 'candidate form filters projects by reward or penalty direction')
check(rewardVue.includes('onCandidateDirectionChange'), 'switching reward/penalty resets the selected project')
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
check(rewardVue.includes('can_delete'), 'reward page uses backend can_delete flag')
check(rewardVue.includes('deleteCandidate'), 'reward page has controlled candidate delete action')
check(rewardVue.includes('必须填写删除原因'), 'candidate delete requires a reason')

// Config save button
check(rewardVue.includes('saveAllConfig'), 'reward page has save config button')
check(rewardVue.includes('保存配置'), 'reward page has visible save config button')

// Stats and pagination
check(rewardVue.includes('rp-stats'), 'reward page has statistics cards')
check(rewardVue.includes('rp-pager'), 'reward page has pagination')

// === Manual rules use rewardManualRuleSaveAPI; stage rules legitimately use rewardRuleSaveAPI ===
// 注意：不能用 includes('rewardRuleSaveAPI') 判定，因为该串是 rewardManualRuleSaveAPI 的子串。
// 正确口径：人工规则保存函数 saveRule 必须调用 rewardManualRuleSaveAPI；
// rewardRuleSaveAPI( 仅允许出现在阶段规则保存（saveStageRule）上下文。
function extractMethod(src, name) {
  const m = src.match(new RegExp('(?:async\\s+)?' + name + '\\s*\\([^)]*\\)\\s*\\{([\\s\\S]*?)\\n    (?:async\\s+)?[a-zA-Z_$][\\w$]*\\s*[(:]'))
  return m ? m[1] : ''
}
const saveRuleBody = extractMethod(rewardVue, 'saveRule')
check(saveRuleBody.indexOf('rewardManualRuleSaveAPI') !== -1, '人工规则保存函数 saveRule 必须调用 rewardManualRuleSaveAPI')
check(saveRuleBody.indexOf('rewardRuleSaveAPI(') === -1, '人工规则保存不得误用阶段规则接口 rewardRuleSaveAPI')
const saveStageRuleBody = extractMethod(rewardVue, 'saveStageRule')
check(saveStageRuleBody.indexOf('rewardRuleSaveAPI(') !== -1, '阶段规则保存使用 rewardRuleSaveAPI')
check(rewardVue.includes('rewardRuleListAPI'), 'reward page uses ruleListAPI for stage rules sub-tab')
check(rewardVue.includes('fetchStageRules'), 'reward page fetches stage rules')
check(rewardVue.includes('ruleSubTab'), 'reward page has sub-tabs for manual/stage rules')

// === Backend checks ===
const rewardPhp = fs.readFileSync(path.resolve(__dirname, '../../crm_php-master/application/crm/controller/Reward.php'), 'utf8')
check(rewardPhp.includes('function manualRuleList'), 'backend has manualRuleList')
check(rewardPhp.includes('function manualRuleSave'), 'backend has manualRuleSave')
check(rewardPhp.includes('function candidateDelete'), 'backend has candidateDelete')
check(rewardPhp.includes('canManageCandidate'), 'backend validates candidate management permission')
check(rewardPhp.includes('canDeleteCandidate'), 'backend validates independent candidate delete permission')
check(rewardPhp.includes("'candidatedelete'"), 'backend checks reward/candidateDelete role permission')
check(rewardPhp.includes("'operation_type' => 'delete'"), 'backend preserves delete audit')
check(rewardPhp.includes('已结算批次中的记录不能直接删除'), 'backend blocks deletion after batch settlement')
check(rewardPhp.includes('isRewardVisibilityAdmin'), 'backend has dedicated reward visibility administrator check')
check(rewardPhp.includes('15628812133'), 'only the designated reward administrator account can view all records')
check(rewardPhp.includes('candidateVisibleToUser'), 'backend protects candidate detail and operations with relation scope')
check(rewardPhp.includes("whereOr('r.create_user_id'"), 'candidate list includes records created by current user')
check(rewardPhp.includes("whereOr('r.reviewer_user_id'"), 'candidate list includes records reviewed by current user')
check(rewardPhp.includes('relatedCandidateSummary'), 'ordinary user summary is scoped to related candidates')
check(rewardPhp.includes("(string)$batch['status'] === '已结算'"), 'settled batch candidates remain protected from deletion')
check(rewardPhp.includes("Db::name('reward_batch')->where(['batch_id' => $batchId])->update(['total_amount'"), 'deleting from pending batch recalculates its total')
check(rewardPhp.includes("Db::name('reward_offset')->where(['cand_id' => $candId])->delete()"), 'deleting an offset candidate cleans linked offset rows after audit snapshot')
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
