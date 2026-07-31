<template>
  <div class="perf-fact-panel">
    <!-- 操作栏 -->
    <div class="pfp-toolbar">
      <el-button v-if="allowReview || isSelf" :loading="aggregating" type="primary" size="small" icon="el-icon-magic-stick" @click="autoAgg">自动归集</el-button>
      <el-button v-if="canAddFact" type="success" size="small" icon="el-icon-plus" @click="showAdd = true">补录绩效事实</el-button>
      <el-select v-model="filterDimension" placeholder="全部维度" size="small" clearable style="width:120px" @change="fetch">
        <el-option label="全部维度" value="" />
        <el-option v-for="(label, key) in dimensionLabels" :key="key" :label="label + ' (' + (dimensionStats[key] ? dimensionStats[key].total : 0) + ')'" :value="key" />
      </el-select>
    </div>

    <!-- 归集结果反馈 -->
    <el-alert v-if="aggResult" :title="aggResult.summary" :closable="true" type="success" show-icon style="margin-bottom:10px" @close="aggResult = null">
      <div class="pfp-agg-detail">{{ aggResult.detail }}</div>
    </el-alert>

    <!-- 维度统计概览 -->
    <div class="pfp-stats">
      <div v-for="(label, key) in dimensionLabels" :key="key" class="pfp-stat-card">
        <div class="pfp-stat-label">{{ label }}</div>
        <div class="pfp-stat-nums">
          <span class="pfp-stat-pos">{{ dimensionStats[key] ? dimensionStats[key].positive : 0 }}</span>
          <span class="pfp-stat-sep">/</span>
          <span class="pfp-stat-neg">{{ dimensionStats[key] ? dimensionStats[key].negative : 0 }}</span>
        </div>
        <div v-if="dimensionStats[key] && dimensionStats[key].pending > 0" class="pfp-stat-pending">{{ dimensionStats[key].pending }} 待审核</div>
      </div>
    </div>

    <!-- 事实列表 -->
    <el-table v-loading="loading" :data="facts" size="small" border style="margin-top:10px" element-loading-text="加载事实...">
      <el-table-column label="维度" width="90">
        <template slot-scope="s">
          <el-tag :type="dimensionColor(s.row.dimension)" size="mini">{{ s.row.dimension_label || s.row.dimension }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="方向" width="65" align="center">
        <template slot-scope="s">
          <el-tag :type="s.row.direction === '正向' ? 'success' : 'danger'" size="mini">{{ s.row.direction }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="标题" min-width="180" show-overflow-tooltip>
        <template slot-scope="s">
          <span class="pfp-title">{{ s.row.title }}</span>
          <span v-if="s.row.fact_type_label" class="pfp-type-tag">{{ s.row.fact_type_label }}</span>
        </template>
      </el-table-column>
      <el-table-column label="来源" width="120" show-overflow-tooltip>
        <template slot-scope="s">
          <div class="pfp-source">
            <span class="pfp-source-module">{{ s.row.source_module || s.row.source_type_label }}</span>
            <span v-if="s.row.source_name && s.row.source_name !== s.row.source_module" class="pfp-source-name">{{ s.row.source_name }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="采集方式" width="80" align="center">
        <template slot-scope="s">
          <el-tag :type="s.row.is_auto ? 'info' : 'warning'" size="mini">{{ s.row.is_auto ? '自动' : '人工' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="发生时间" width="100">
        <template slot-scope="s">{{ formatDate(s.row.occurred_time) }}</template>
      </el-table-column>
      <el-table-column label="提交人" width="80" show-overflow-tooltip>
        <template slot-scope="s">{{ s.row.submit_user_name || ('#' + s.row.submit_user_id) }}</template>
      </el-table-column>
      <el-table-column label="状态" width="80" align="center">
        <template slot-scope="s">
          <el-tag :type="statusType(s.row.status)" size="mini">{{ s.row.status }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="130" fixed="right">
        <template slot-scope="s">
          <el-button size="mini" type="text" @click="openDetail(s.row)">详情</el-button>
          <el-button v-if="s.row.status === '待审核' && allowReview" size="mini" type="text" @click="review(s.row, 'approve')">通过</el-button>
          <el-button v-if="s.row.status === '待审核' && allowReview" size="mini" type="text" style="color:#f56c6c" @click="review(s.row, 'reject')">驳回</el-button>
        </template>
      </el-table-column>
    </el-table>
    <div v-if="!facts.length && !loading" class="pfp-empty">暂无绩效事实，可点击"自动归集"从业务数据中采集</div>

    <!-- 补录弹窗 -->
    <el-dialog :visible.sync="showAdd" :close-on-click-modal="false" title="补录绩效事实" width="560px" append-to-body custom-class="pfp-add-dialog">
      <el-form ref="addFormRef" :model="addForm" :rules="addRules" label-width="90px" size="small">
        <el-form-item label="维度" prop="dimension">
          <el-select v-model="addForm.dimension" style="width:100%">
            <el-option label="核心职责(40%)" value="duty" />
            <el-option label="重点任务(30%)" value="task" />
            <el-option label="测试与质量(20%)" value="quality" />
            <el-option label="协作(10%)" value="collab" />
          </el-select>
        </el-form-item>
        <el-form-item label="方向" prop="direction">
          <el-radio-group v-model="addForm.direction">
            <el-radio label="正向">正向（加分）</el-radio>
            <el-radio label="负向">负向（减分）</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="类型" prop="fact_type">
          <el-input v-model="addForm.fact_type" placeholder="投标/培训/专项等" />
        </el-form-item>
        <el-form-item label="标题" prop="title">
          <el-input v-model="addForm.title" />
        </el-form-item>
        <el-form-item label="证据" prop="evidence">
          <el-input v-model="addForm.evidence" :rows="2" type="textarea" placeholder="支撑此事实的证据或说明" />
        </el-form-item>
        <el-form-item label="发生日期" prop="occurred_time">
          <el-date-picker v-model="addForm.occurred_time" type="date" value-format="yyyy-MM-dd" style="width:100%" />
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button size="small" @click="showAdd = false">取消</el-button>
        <el-button :loading="acting" size="small" type="primary" @click="addFact">提交</el-button>
      </span>
    </el-dialog>

    <!-- 事实详情弹窗 -->
    <el-dialog :visible.sync="detailVisible" title="绩效事实详情" width="600px" append-to-body custom-class="pfp-detail-dialog">
      <div v-loading="detailLoading" style="min-height:160px">
        <template v-if="detailData">
          <div class="pfp-detail-row"><span class="pfp-d-label">关联员工</span><span class="pfp-d-value">{{ detailData.user_name || ('#' + detailData.user_id) }}<template v-if="detailData.user_post"> / {{ detailData.user_post }}</template></span></div>
          <div class="pfp-detail-row"><span class="pfp-d-label">维度</span><span class="pfp-d-value">{{ detailData.dimension_label }}</span></div>
          <div class="pfp-detail-row"><span class="pfp-d-label">方向</span><span class="pfp-d-value">{{ detailData.direction }}</span></div>
          <div class="pfp-detail-row"><span class="pfp-d-label">类型</span><span class="pfp-d-value">{{ detailData.fact_type_label || detailData.fact_type }}</span></div>
          <div class="pfp-detail-row"><span class="pfp-d-label">标题</span><span class="pfp-d-value">{{ detailData.title }}</span></div>
          <div class="pfp-detail-row"><span class="pfp-d-label">来源模块</span><span class="pfp-d-value">{{ detailData.source_module || detailData.source_type_label }}</span></div>
          <div v-if="detailData.source_name && detailData.source_name !== detailData.source_module" class="pfp-detail-row"><span class="pfp-d-label">来源对象</span><span class="pfp-d-value">{{ detailData.source_name }}</span></div>
          <div v-if="detailData.source_ref" class="pfp-detail-row"><span class="pfp-d-label">来源编号</span><span class="pfp-d-value">{{ detailData.source_ref }}</span></div>
          <div class="pfp-detail-row"><span class="pfp-d-label">采集方式</span><span class="pfp-d-value">{{ detailData.is_auto ? '系统自动采集' : '人工补录' }}</span></div>
          <div class="pfp-detail-row"><span class="pfp-d-label">提交人</span><span class="pfp-d-value">{{ detailData.submit_user_name || ('#' + detailData.submit_user_id) }}</span></div>
          <div class="pfp-detail-row"><span class="pfp-d-label">发生时间</span><span class="pfp-d-value">{{ formatDate(detailData.occurred_time) }}</span></div>
          <div class="pfp-detail-row"><span class="pfp-d-label">状态</span><span class="pfp-d-value">{{ detailData.status }}</span></div>
          <div v-if="detailData.reviewer_name" class="pfp-detail-row"><span class="pfp-d-label">审核人</span><span class="pfp-d-value">{{ detailData.reviewer_name }} · {{ formatDate(detailData.review_time) }}</span></div>
          <div v-if="detailData.evidence" class="pfp-detail-row pfp-detail-full"><span class="pfp-d-label">证据</span><span class="pfp-d-value">{{ detailData.evidence }}</span></div>
          <div v-if="detailData.review_note" class="pfp-detail-row pfp-detail-full"><span class="pfp-d-label">审核备注</span><span class="pfp-d-value">{{ detailData.review_note }}</span></div>
        </template>
        <div v-else-if="!detailLoading" class="pfp-empty">加载失败</div>
      </div>
      <span slot="footer">
        <el-button size="small" @click="detailVisible = false">关闭</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { performanceFactListAPI, performanceFactDetailAPI, performanceFactReviewAPI, performanceAutoAggregateAPI, performanceAddFactAPI, performanceDictionaryAPI } from '@/api/crm/performance'

const DIM_LABEL = { duty: '核心职责', task: '重点任务', quality: '测试与质量', collab: '协作' }

export default {
  name: 'PerformanceFactPanel',
  filters: {
    fmtDate(v) {
      if (!v) return ''
      const d = new Date(Number(v) * 1000)
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')
    }
  },
  props: {
    userId: [Number, String],
    period: { type: String, default: '' },
    allowReview: { type: Boolean, default: false }
  },
  data() {
    return {
      facts: [],
      loading: false,
      aggregating: false,
      acting: false,
      aggResult: null,
      filterDimension: '',
      dimensionStats: {},
      dimensionLabels: DIM_LABEL,
      currentUserId: 0,
      showAdd: false,
      detailVisible: false,
      detailLoading: false,
      detailData: null,
      addForm: { dimension: 'task', direction: '正向', fact_type: '', title: '', evidence: '', occurred_time: '' },
      addRules: {
        dimension: [{ required: true, message: '请选择维度', trigger: 'change' }],
        direction: [{ required: true, message: '请选择方向', trigger: 'change' }],
        title: [{ required: true, message: '请填写标题', trigger: 'blur' }],
        occurred_time: [{ required: true, message: '请选择发生日期', trigger: 'change' }]
      }
    }
  },
  computed: {
    currentPeriod() {
      return this.period || (new Date().getFullYear()) + 'Q' + Math.ceil((new Date().getMonth() + 1) / 3)
    },
    isSelf() {
      return Number(this.userId) === Number(this.currentUserId)
    },
    canAddFact() {
      return this.isSelf || this.allowReview
    }
  },
  watch: {
    userId: { handler() { this.fetch() }, immediate: true }
  },
  async created() {
    try {
      // 获取当前用户
      const store = this.$store || (this.$root && this.$root.$store)
      if (store && store.state && store.state.user && store.state.user.userInfo) {
        this.currentUserId = Number(store.state.user.userInfo.id) || 0
      }
    } catch (e) { /* 忽略 */ }
    try {
      await performanceDictionaryAPI({})
    } catch (e) { /* 忽略 */ }
  },
  methods: {
    formatDate(v) {
      if (!v) return '-'
      var d = new Date(Number(v) * 1000)
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')
    },
    dimensionColor(dim) {
      return { duty: '', task: 'success', quality: 'warning', collab: 'info' }[dim] || ''
    },
    statusType(s) {
      return { '待审核': 'warning', '已通过': 'success', '已驳回': 'danger' }[s] || 'info'
    },
    async fetch() {
      if (!this.userId) return
      this.loading = true
      try {
        const params = { user_id: Number(this.userId), period: this.currentPeriod }
        if (this.filterDimension) params.dimension = this.filterDimension
        const r = await performanceFactListAPI(params)
        const data = r.data || r || {}
        this.facts = data.list || []
        this.dimensionStats = data.dimension_stats || {}
      } catch (e) {
        this.facts = []
      } finally {
        this.loading = false
      }
    },
    async autoAgg() {
      this.aggregating = true
      this.aggResult = null
      try {
        const r = await performanceAutoAggregateAPI({ user_id: Number(this.userId), period: this.currentPeriod })
        const s = (r.data && r.data.facts) || {}
        const parts = []
        parts.push('任务 ' + (s.task_done_inserted || 0) + '/' + (s.task_done_total || 0))
        parts.push('测试合格 ' + (s.test_compliant_inserted || 0) + '/' + (s.test_compliant_total || 0))
        parts.push('测试不合格 ' + (s.test_non_compliant_inserted || 0) + '/' + (s.test_non_compliant_total || 0))
        parts.push('奖励 ' + (s.reward_settled_inserted || 0) + '/' + (s.reward_settled_total || 0))
        parts.push('W/R/K ' + (s.wrk_inserted || 0))
        parts.push('台账质量 ' + (s.ledger_quality_inserted || 0) + '/' + (s.ledger_quality_total || 0))
        parts.push('项目结果 ' + (s.project_result_inserted || 0))
        const totalInserted = Object.keys(s).filter(k => k.endsWith('_inserted')).reduce((sum, k) => sum + (s[k] || 0), 0)
        this.aggResult = {
          summary: '归集完成，新增 ' + totalInserted + ' 条绩效事实（重复执行不会重复生成）',
          detail: parts.join('，')
        }
        this.$message.success('已归集 ' + totalInserted + ' 条事实')
        this.fetch()
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.aggregating = false
      }
    },
    addFact() {
      this.$refs.addFormRef.validate(valid => {
        if (!valid) return
        this.acting = true
        performanceAddFactAPI(Object.assign({ user_id: Number(this.userId), period: this.currentPeriod }, this.addForm)).then(() => {
          this.$message.success('已提交，待审核')
          this.showAdd = false
          this.addForm = { dimension: 'task', direction: '正向', fact_type: '', title: '', evidence: '', occurred_time: '' }
          this.fetch()
        }).catch(() => {}).finally(() => { this.acting = false })
      })
    },
    async openDetail(row) {
      this.detailVisible = true
      this.detailLoading = true
      this.detailData = null
      try {
        const r = await performanceFactDetailAPI({ fact_id: row.fact_id })
        this.detailData = r.data || r
      } catch (e) {
        this.detailData = row
      } finally {
        this.detailLoading = false
      }
    },
    async review(row, decision) {
      try {
        await performanceFactReviewAPI({ fact_id: row.fact_id, decision })
        this.$message.success(decision === 'approve' ? '已通过' : '已驳回')
        this.fetch()
      } catch (e) { /* 全局拦截器提示 */ }
    }
  }
}
</script>

<style scoped>
.perf-fact-panel { padding: 4px 0; }
.pfp-toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
.pfp-agg-detail { font-size: 12px; color: #606266; margin-top: 4px; }
/* 维度统计 */
.pfp-stats { display: flex; gap: 8px; flex-wrap: wrap; }
.pfp-stat-card { flex: 1; min-width: 100px; background: #f7f8fa; border-radius: 8px; padding: 8px 6px; text-align: center; }
.pfp-stat-label { font-size: 12px; color: #909399; }
.pfp-stat-nums { font-size: 16px; margin-top: 2px; }
.pfp-stat-pos { color: #67c23a; font-weight: 700; }
.pfp-stat-sep { color: #c0c4cc; margin: 0 2px; }
.pfp-stat-neg { color: #f56c6c; font-weight: 700; }
.pfp-stat-pending { font-size: 11px; color: #e6a23c; margin-top: 2px; }
/* 表格内容 */
.pfp-title { font-size: 13px; color: #303133; }
.pfp-type-tag { font-size: 11px; color: #909399; margin-left: 6px; }
.pfp-source { display: flex; flex-direction: column; }
.pfp-source-module { font-size: 12px; color: #409eff; }
.pfp-source-name { font-size: 11px; color: #909399; }
.pfp-empty { text-align: center; padding: 32px; color: #909399; font-size: 13px; }
/* 详情 */
.pfp-detail-row { display: flex; gap: 8px; margin-bottom: 8px; }
.pfp-detail-full { width: 100%; }
.pfp-d-label { min-width: 70px; color: #909399; font-size: 13px; flex-shrink: 0; }
.pfp-d-value { color: #303133; font-size: 13px; flex: 1; word-break: break-all; }
</style>

<style>
.pfp-add-dialog .el-dialog__body,
.pfp-detail-dialog .el-dialog__body { padding: 16px 20px; }
@media (max-width: 768px) {
  .pfp-add-dialog, .pfp-detail-dialog { width: 95% !important; margin: 0 auto !important; }
  .pfp-stats { flex-direction: column; }
  .pfp-stat-card { min-width: 100%; }
}
</style>
