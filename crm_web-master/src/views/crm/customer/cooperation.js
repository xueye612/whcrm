export const COOPERATION_FIELDS = [
  'cooperation_type',
  'cooperation_stage',
  'discover_user_id',
  'verify_user_id',
  'verify_time',
  'verify_result',
  'verify_note'
]

export const VERIFY_FIELDS = [
  'verify_user_id',
  'verify_time',
  'verify_result',
  'verify_note'
]

export const VERIFY_REQUIRED_FIELDS = [
  'discover_user_id',
  ...VERIFY_FIELDS
]

export const HOSPITAL_TYPE = '医院客户'
export const VERIFIED_STAGE = '已核实'
export const EFFECTIVE_CONTACT_STAGE = '有效联系'
export const NEGOTIATING_STAGE = '洽谈中'
export const COOPERATION_STAGES = ['初筛', '已核实', EFFECTIVE_CONTACT_STAGE, '洽谈中', '已合作', '暂缓', '不适合', '无法联系']
export const COOPERATION_MAIN_STAGES = ['初筛', '已核实', EFFECTIVE_CONTACT_STAGE, '洽谈中', '已合作']
export const VERIFY_RESULTS = ['推荐跟进', '储备观察', '不建议联系']

/** 已进入实质跟进的客户，在详情中优先展示合作与核实信息；兼容历史“已联系”数据。 */
export function shouldPrioritizeCooperation(stage) {
  return [VERIFIED_STAGE, EFFECTIVE_CONTACT_STAGE, '已联系'].includes(stage)
}

export function hasFieldValue(value) {
  if (Array.isArray(value)) return value.length > 0
  if (value && typeof value === 'object') return Object.keys(value).length > 0
  return value !== null && value !== undefined && String(value).trim() !== ''
}

export function isCooperationEnterprise(type) {
  return hasFieldValue(type) && type !== HOSPITAL_TYPE
}

export function shouldShowVerification(form) {
  if (!isCooperationEnterprise(form.cooperation_type)) return false
  if (form.cooperation_stage === '初筛') return false
  return COOPERATION_MAIN_STAGES.slice(1).includes(form.cooperation_stage) || VERIFY_FIELDS.some(field => hasFieldValue(form[field]))
}

export function isVerificationRequired(form) {
  return isCooperationEnterprise(form.cooperation_type) && COOPERATION_MAIN_STAGES.slice(1).includes(form.cooperation_stage)
}

export function getCooperationStageTagType(stage) {
  if (stage === '已合作') return 'success'
  if (['暂缓', '初筛'].includes(stage)) return 'warning'
  if (['不适合', '无法联系'].includes(stage)) return 'danger'
  return ''
}

export function shouldShowCooperationField(field, form) {
  if (field === 'cooperation_type') return true
  if (!isCooperationEnterprise(form.cooperation_type)) return false
  if (['cooperation_stage', 'discover_user_id'].includes(field)) return true
  return VERIFY_FIELDS.includes(field) && shouldShowVerification(form)
}
