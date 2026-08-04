<template>
  <div class="perf-page">
    <!-- 工具栏 -->
    <div class="pp-toolbar">
      <el-select v-model="form.user_id" :remote-method="searchUser" filterable remote reserve-keyword clearable placeholder="搜索员工姓名" size="small" style="width:180px" @change="onFilterChange">
        <el-option v-for="u in userOptions" :key="u.id" :label="u.realname + (u.s_name ? ' / ' + u.s_name : '')" :value="u.id" />
      </el-select>
      <el-select v-model="form.period" placeholder="选择季度" size="small" style="width:140px" @change="onFilterChange">
        <el-option v-for="q in quarterOptions" :key="q.value" :label="q.label" :value="q.value" />
      </el-select>
      <el-button v-if="canScoreInput" type="primary" size="small" icon="el-icon-plus" @click="openScoreInputNew">新增绩效/直接录分</el-button>
      <el-button v-if="canScoreInput" :loading="generating" type="warning" size="small" icon="el-icon-document-add" @click="generateQuarterly">批量生成</el-button>
      <el-button size="small" icon="el-icon-refresh" @click="fetchList">刷新</el-button>
    </div>

    <!-- 绩效列表 -->
    <el-table v-loading="listLoading" :data="list" size="small" border style="margin-top:10px" @row-click="selectRecord">
      <el-table-column label="员工" min-width="120" show-overflow-tooltip>
        <template slot-scope="s">
          <span class="pp-user-cell">
            <span>{{ s.row.user_name || ('用户' + s.row.user_id) }}</span>
            <span v-if="s.row.user_post" class="pp-user-post">{{ s.row.user_post }}</span>
          </span>
        </template>
      </el-table-column>
      <el-table-column label="部门" width="100" show-overflow-tooltip>
        <template slot-scope="s">{{ s.row.user_structure || '-' }}</template>
      </el-table-column>
      <el-table-column label="季度" prop="period" width="90" />
      <el-table-column label="加权得分" width="85" align="center">
        <template slot-scope="s">
          <span :class="{ 'pp-score-zero': !s.row.weighted_score }">{{ formatScore(s.row.weighted_score) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="评级" width="80" align="center">
        <template slot-scope="s">
          <el-tag v-if="s.row.rating" :type="ratingTagType(s.row.rating)" size="mini">{{ s.row.rating }}</el-tag>
          <span v-else class="pp-muted">未评级</span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="80" align="center">
        <template slot-scope="s">
          <el-tag :type="statusTagType(s.row.status)" size="mini">{{ s.row.status }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="生成方式" width="100" align="center">
        <template slot-scope="s">
          <span v-if="s.row.create_method_label" class="pp-method">{{ s.row.create_method_label }}</span>
          <span v-else class="pp-muted">-</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="300" fixed="right">
        <template slot-scope="s">
          <el-button type="text" size="mini" @click.stop="selectRecord(s.row)">详情</el-button>
          <el-button v-if="canScoreInput && s.row.user_id > 0" type="text" size="mini" @click.stop="openScoreInputFromRow(s.row)">编辑</el-button>
          <el-button v-if="s.row.status==='待确认' && canRate(s.row)" type="text" size="mini" @click.stop="rate(s.row)">评级</el-button>
          <el-button v-if="s.row.status==='已确认' && canRate(s.row)" type="text" size="mini" @click.stop="returnSummary(s.row)">退回</el-button>
          <el-button v-if="canScoreInput" type="text" size="mini" style="color:#f56c6c" @click.stop="deleteRecord(s.row)">删除</el-button>
          <el-button type="text" size="mini" @click.stop="showFacts(s.row)">绩效事实</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 空状态 -->
    <div v-if="!list.length && !listLoading" class="pp-empty-state">
      <i class="el-icon-document pp-empty-icon" />
      <div class="pp-empty-title">{{ emptyTitle }}</div>
      <div class="pp-empty-desc">{{ emptyDesc }}</div>
      <el-button v-if="canScoreInput" :loading="generating" type="primary" size="small" @click="generateQuarterly">生成本季度绩效记录</el-button>
    </div>

    <!-- 绩效详情面板 -->
    <el-dialog :visible.sync="detailDialog" :title="detailTitle" width="780px" append-to-body custom-class="pp-detail-dialog" @open="fetchDetail">
      <div v-loading="detailLoading" style="min-height:200px">
        <template v-if="summary && summary.user_id > 0">
          <!-- 员工信息区 -->
          <div class="pp-emp-info">
            <div class="pp-emp-avatar">
              <img v-if="summary.user_thumb" :src="summary.user_thumb" alt="">
              <i v-else class="el-icon-user-solid pp-emp-avatar-placeholder" />
            </div>
            <div class="pp-emp-meta">
              <div class="pp-emp-name">{{ summary.user_name || ('用户' + summary.user_id) }}</div>
              <div class="pp-emp-sub">
                <span v-if="summary.user_post">{{ summary.user_post }}</span>
                <span v-if="summary.user_structure"> / {{ summary.user_structure }}</span>
              </div>
              <div class="pp-emp-tags">
                <el-tag size="mini">{{ summary.period }}</el-tag>
                <el-tag :type="statusTagType(summary.status)" size="mini">{{ summary.status }}</el-tag>
                <el-tag v-if="summary.create_method_label" type="info" size="mini">{{ summary.create_method_label }}</el-tag>
                <el-tag v-if="summary.perf_id" type="info" size="mini">记录 #{{ summary.perf_id }}</el-tag>
              </div>
              <div class="pp-emp-creator">创建人：{{ summary.create_user_name || ('#' + summary.create_user_id) }} · {{ formatTime(summary.create_time) }}</div>
            </div>
          </div>

          <!-- 评分维度卡片 -->
          <div v-if="calculation" class="pp-dim-cards">
            <div v-for="dim in calculation.dimensions" :key="dim.key" class="pp-dim-card">
              <div class="pp-dim-label">{{ dim.label }}</div>
              <div class="pp-dim-score">{{ formatScore(dim.score) }}<span class="pp-dim-unit">分</span></div>
              <div class="pp-dim-weight">权重{{ dim.weight_pct }}%</div>
              <div class="pp-dim-contrib">贡献 {{ formatScore(dim.contribution) }}</div>
              <div class="pp-dim-facts">
                <span class="pp-fact-pos">+{{ dim.positive_count }}</span>
                <span v-if="dim.negative_count" class="pp-fact-neg">-{{ dim.negative_count }}</span>
                <span class="pp-fact-label">条事实</span>
              </div>
            </div>
          </div>

          <!-- 计算说明 -->
          <div v-if="calculation" class="pp-calc">
            <div class="pp-calc-row">
              <span class="pp-calc-label">加权得分</span>
              <span class="pp-calc-value pp-calc-big">{{ formatScore(calculation.weighted_score) }}</span>
              <span v-if="calculation.rating" class="pp-calc-label" style="margin-left:24px">评级</span>
              <span v-if="calculation.rating" class="pp-calc-value">{{ calculation.rating }}（系数{{ calculation.rating_factor }}）</span>
            </div>
            <div v-if="summary.reference_amount" class="pp-calc-row">
              <span class="pp-calc-label">岗位基准</span>
              <span class="pp-calc-value">¥{{ formatAmount(summary.quarterly_base) }}</span>
              <span class="pp-calc-label" style="margin-left:24px">参考金额</span>
              <span class="pp-calc-value">¥{{ formatAmount(summary.reference_amount) }}</span>
            </div>
            <div class="pp-calc-formula">{{ calculation.weights_formula }}</div>
            <div v-if="calculation.status_note" class="pp-calc-note">
              <i class="el-icon-info" /> {{ calculation.status_note }}
            </div>
          </div>
        </template>
        <div v-else-if="!detailLoading" class="pp-empty-state">
          <div class="pp-empty-title">绩效记录数据异常</div>
          <div class="pp-empty-desc">该记录缺少有效的员工关联，请重新生成绩效记录</div>
        </div>
      </div>
      <span slot="footer">
        <el-button size="small" @click="detailDialog = false">关闭</el-button>
        <el-button v-if="summary && summary.user_id > 0 && canScoreInput" size="small" type="primary" @click="openScoreInputFromDetail">录入维度得分</el-button>
        <el-button v-if="summary && summary.user_id > 0 && summary.status==='待确认' && canRate(summary)" size="small" type="warning" @click="rate(summary)">评级</el-button>
        <el-button v-if="summary && summary.user_id > 0" size="small" @click="showFacts(summary)">绩效事实</el-button>
      </span>
    </el-dialog>

    <!-- 维度得分录入弹窗（绑定 perf_id + 员工信息） -->
    <el-dialog :visible.sync="scoreDialog" :close-on-click-modal="false" title="录入维度得分" width="640px" append-to-body custom-class="pp-score-dialog">
      <el-form ref="scoreFormRef" :model="scoreForm" :rules="scoreRules" label-width="110px" size="small">
        <!-- 员工信息头部 -->
        <div class="pp-score-header">
          <div class="pp-score-emp">
            <span class="pp-score-name">{{ scoreForm.user_name || ('用户' + scoreForm.user_id) }}</span>
            <span v-if="scoreForm.user_post" class="pp-score-sub">{{ scoreForm.user_post }}</span>
            <span v-if="scoreForm.user_structure" class="pp-score-sub"> / {{ scoreForm.user_structure }}</span>
          </div>
          <div class="pp-score-meta">
            <el-tag size="mini">{{ scoreForm.period }}</el-tag>
            <el-tag v-if="scoreForm.perf_id" type="info" size="mini">记录 #{{ scoreForm.perf_id }}</el-tag>
            <el-tag :type="statusTagType(scoreForm.status)" size="mini">{{ scoreForm.status || '待确认' }}</el-tag>
            <el-tag v-if="scoreForm.create_method_label" type="info" size="mini">{{ scoreForm.create_method_label }}</el-tag>
          </div>
        </div>

        <!-- 维度得分（带权重和事实数量） -->
        <el-form-item label="核心职责" prop="duty_score">
          <div class="pp-score-dim-row">
            <el-input-number v-model="scoreForm.duty_score" :min="0" :max="100" controls-position="right" style="flex:1" />
            <span class="pp-score-weight">权重40%</span>
            <span class="pp-score-facts">{{ scoreForm.fact_counts.duty || 0 }} 条事实</span>
          </div>
        </el-form-item>
        <el-form-item label="重点任务" prop="task_score">
          <div class="pp-score-dim-row">
            <el-input-number v-model="scoreForm.task_score" :min="0" :max="100" controls-position="right" style="flex:1" />
            <span class="pp-score-weight">权重30%</span>
            <span class="pp-score-facts">{{ scoreForm.fact_counts.task || 0 }} 条事实</span>
          </div>
        </el-form-item>
        <el-form-item label="测试与质量" prop="quality_score">
          <div class="pp-score-dim-row">
            <el-input-number v-model="scoreForm.quality_score" :min="0" :max="100" controls-position="right" style="flex:1" />
            <span class="pp-score-weight">权重20%</span>
            <span class="pp-score-facts">{{ scoreForm.fact_counts.quality || 0 }} 条事实</span>
          </div>
        </el-form-item>
        <el-form-item label="协作" prop="collab_score">
          <div class="pp-score-dim-row">
            <el-input-number v-model="scoreForm.collab_score" :min="0" :max="100" controls-position="right" style="flex:1" />
            <span class="pp-score-weight">权重10%</span>
            <span class="pp-score-facts">{{ scoreForm.fact_counts.collab || 0 }} 条事实</span>
          </div>
        </el-form-item>

        <!-- 加权预览 -->
        <el-form-item label="加权得分预览">
          <span class="pp-score-preview">{{ previewWeighted }}</span>
        </el-form-item>

        <!-- 调整原因 -->
        <el-form-item label="调整说明">
          <el-input v-model="scoreForm.adjust_reason" :rows="2" type="textarea" placeholder="如有人工调整，请填写原因（选填，会记入审计）" />
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button size="small" @click="scoreDialog = false">取消</el-button>
        <el-button :loading="acting" size="small" type="primary" @click="doSaveSummary">确认录入</el-button>
      </span>
    </el-dialog>

    <!-- 评级弹窗 -->
    <el-dialog :visible.sync="rateDialog" :close-on-click-modal="false" title="绩效评级（本人回避）" width="480px" append-to-body>
      <el-form label-width="90px" size="small">
        <el-form-item label="质量档次"><el-select v-model="rateForm.quality_tier" style="width:100%"><el-option v-for="t in dict.quality_tiers" :key="t" :label="t" :value="t" /></el-select></el-form-item>
        <el-form-item label="最终评级"><el-select v-model="rateForm.rating" style="width:100%"><el-option v-for="r in dict.ratings" :key="r" :label="r + '(' + dict.rating_factors[r] + ')'" :value="r" /></el-select></el-form-item>
        <el-form-item label="说明"><el-input v-model="rateForm.review_note" :rows="2" type="textarea" /></el-form-item>
      </el-form>
      <span slot="footer"><el-button size="small" @click="rateDialog = false">取消</el-button><el-button :loading="acting" size="small" type="primary" @click="doRate">确认</el-button></span>
    </el-dialog>

    <!-- 绩效事实中心弹窗 -->
    <el-dialog :visible.sync="factsDialog" title="绩效事实中心" width="900px" append-to-body custom-class="pp-facts-dialog">
      <performance-fact-panel v-if="factsUserId" :user-id="factsUserId" :period="factsPeriod" :allow-review="canReviewFact(factsUserId)" />
    </el-dialog>
  </div>
</template>

<script>
import { performanceDictionaryAPI, performanceSummarySaveAPI, performanceSummaryListAPI, performanceSummaryReadAPI, performanceRateAPI, performanceGenerateQuarterlyAPI, performanceSummaryDeleteAPI } from '@/api/crm/performance'
import request from '@/utils/request'
import PerformanceFactPanel from './components/PerformanceFactPanel'

const WEIGHTS = { duty: 0.40, task: 0.30, quality: 0.20, collab: 0.10 }

export default {
  name: 'PerformancePage',
  components: { PerformanceFactPanel },
  data() {
    const now = new Date()
    const year = now.getFullYear()
    const q = Math.ceil((now.getMonth() + 1) / 3)
    return {
      dict: { quality_tiers: [], ratings: [], rating_factors: {}, summary_status: [], fact_status: [], direction: [] },
      list: [],
      listLoading: false,
      listError: false,
      form: { user_id: '', period: year + 'Q' + q },
      detailDialog: false,
      detailLoading: false,
      summary: null,
      calculation: null,
      adjustAudits: [],
      selectedPerfId: 0,
      scoreDialog: false,
      scoreForm: this.emptyScoreForm(),
      scoreRules: {
        duty_score: [{ required: true, message: '请输入核心职责得分', trigger: 'blur' }],
        task_score: [{ required: true, message: '请输入重点任务得分', trigger: 'blur' }],
        quality_score: [{ required: true, message: '请输入测试与质量得分', trigger: 'blur' }],
        collab_score: [{ required: true, message: '请输入协作得分', trigger: 'blur' }]
      },
      rateDialog: false,
      rateForm: { perf_id: 0, quality_tier: '', rating: '', review_note: '' },
      factsDialog: false,
      factsUserId: 0,
      factsPeriod: '',
      acting: false,
      generating: false,
      userOptions: [],
      currentUserId: 0,
      perms: {
        perf_view_self: false, perf_view_subordinates: false, perf_auto_aggregate: false,
        perf_fact_input: false, perf_fact_review: false, perf_score_input: false,
        perf_final_rate: false, perf_responsibility: false
      },
      quarterOptions: [
        { label: year + '年第一季度', value: year + 'Q1' },
        { label: year + '年第二季度', value: year + 'Q2' },
        { label: year + '年第三季度', value: year + 'Q3' },
        { label: year + '年第四季度', value: year + 'Q4' },
        { label: (year - 1) + '年第四季度', value: (year - 1) + 'Q4' }
      ]
    }
  },
  computed: {
    canScoreInput() { return this.perms.perf_score_input || this.isSuperAdmin },
    isSuperAdmin() { return this.currentUserId === 1 },
    detailTitle() {
      if (!this.summary) return '绩效详情'
      return '绩效详情 · ' + (this.summary.user_name || ('用户' + this.summary.user_id)) + ' · ' + this.summary.period
    },
    previewWeighted() {
      var d = parseFloat(this.scoreForm.duty_score) || 0
      var t = parseFloat(this.scoreForm.task_score) || 0
      var q = parseFloat(this.scoreForm.quality_score) || 0
      var c = parseFloat(this.scoreForm.collab_score) || 0
      return (Math.round((d * WEIGHTS.duty + t * WEIGHTS.task + q * WEIGHTS.quality + c * WEIGHTS.collab) * 100) / 100).toFixed(2)
    },
    emptyTitle() {
      if (this.listError) return '加载失败'
      if (this.form.user_id) return '当前员工本季度暂无绩效记录'
      return this.form.period + ' 尚未生成绩效记录'
    },
    emptyDesc() {
      if (this.listError) return '接口请求失败，请检查网络或权限后刷新重试'
      if (this.form.user_id) return '该员工在本周期没有绩效记录，可先生成本季度记录再录分'
      return '点击下方按钮为所有有效员工生成本季度绩效记录'
    }
  },
  async created() {
    try {
      const userRes = await request({ url: 'admin/base/loginInfo', method: 'post' })
      this.currentUserId = (userRes.data && userRes.data.userInfo && userRes.data.userInfo.id) || 0
    } catch (e) { /* 忽略 */ }
    await this.fetchDict()
    await this.fetchPerms()
    await this.fetchList()
  },
  methods: {
    emptyScoreForm() {
      return { perf_id: 0, user_id: 0, user_name: '', user_post: '', user_structure: '', period: '', status: '', create_method_label: '', duty_score: 0, task_score: 0, quality_score: 0, collab_score: 0, adjust_reason: '', fact_counts: {}}
    },
    formatScore(v) {
      var n = parseFloat(v)
      return isNaN(n) ? '0.00' : n.toFixed(2)
    },
    formatAmount(v) {
      var n = parseFloat(v)
      return isNaN(n) ? '0.00' : n.toFixed(2)
    },
    formatTime(v) {
      if (!v) return '-'
      var d = new Date(Number(v) * 1000)
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')
    },
    ratingTagType(r) {
      if (r === '优秀') return 'success'
      if (r === '合格') return ''
      if (r === '待改进') return 'danger'
      return 'info'
    },
    statusTagType(s) {
      if (s === '已确认') return 'success'
      if (s === '待确认') return 'warning'
      if (s === '已退回') return 'danger'
      return 'info'
    },
    onFilterChange() {
      this.fetchList()
    },
    async fetchDict() {
      const r = await performanceDictionaryAPI({})
      const data = (r.data && r.data) || r || {}
      this.dict = data.dictionary ? data.dictionary : data
      if (data.perms) {
        Object.keys(this.perms).forEach(k => {
          if (data.perms[k] !== undefined) this.perms[k] = !!data.perms[k]
        })
      }
      if (data.is_super_admin) {
        Object.keys(this.perms).forEach(k => { this.perms[k] = true })
      }
    },
    async fetchPerms() {
      if (this.currentUserId === 1) {
        Object.keys(this.perms).forEach(k => { this.perms[k] = true })
        return
      }
      this.perms.perf_view_self = true
    },
    async fetchList() {
      this.listLoading = true
      this.listError = false
      try {
        const params = {}
        if (this.form.period) params.period = this.form.period
        if (this.form.user_id) params.user_id = this.form.user_id
        const r = await performanceSummaryListAPI(params)
        this.list = (r.data && r.data.list) || []
      } catch (e) {
        this.listError = true
        this.list = []
      } finally {
        this.listLoading = false
      }
    },
    async generateQuarterly() {
      if (!this.form.period) { this.$message.warning('请先选择季度'); return }
      try {
        await this.$confirm('将为所有有效员工生成 ' + this.form.period + ' 的绩效记录（已存在则跳过），是否继续？', '生成季度绩效', {
          confirmButtonText: '确认生成',
          cancelButtonText: '取消',
          type: 'info'
        })
      } catch (e) { return }
      this.generating = true
      try {
        const r = await performanceGenerateQuarterlyAPI({ period: this.form.period })
        const data = r.data || r || {}
        const created = data.created_count || 0
        const skipped = data.skipped_count || 0
        this.$message.success('已生成 ' + created + ' 条记录' + (skipped > 0 ? '，跳过 ' + skipped + ' 条已存在' : ''))
        this.fetchList()
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.generating = false
      }
    },
    async searchUser(query) {
      if (!query) { this.userOptions = []; return }
      try {
        const res = await request({ url: 'admin/user/queryUserList', method: 'post', data: { realname: query, page: 1, limit: 20 }})
        this.userOptions = (res.data && res.data.list) || res.data || []
      } catch (e) { this.userOptions = [] }
    },
    selectRecord(row) {
      if (!row.perf_id || !row.user_id) {
        this.$message.warning('该绩效记录数据异常，缺少有效员工关联')
        return
      }
      this.selectedPerfId = row.perf_id
      this.detailDialog = true
    },
    async fetchDetail() {
      if (!this.selectedPerfId) return
      this.detailLoading = true
      this.summary = null
      this.calculation = null
      this.adjustAudits = []
      try {
        const r = await performanceSummaryReadAPI({ perf_id: this.selectedPerfId })
        const data = r.data || r || {}
        this.summary = data.summary || null
        this.calculation = data.calculation || null
        this.adjustAudits = data.adjust_audits || []
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.detailLoading = false
      }
    },
    // 新增绩效/直接录分：必须先选择真实员工
    openScoreInputNew() {
      if (!this.form.user_id || Number(this.form.user_id) <= 0) {
        this.$message.warning('请先在上方搜索并选择一名真实员工，再新增绩效')
        return
      }
      var u = this.userOptions.find(x => Number(x.id) === Number(this.form.user_id))
      if (!u) {
        this.$message.warning('所选员工信息不可用，请重新搜索选择')
        return
      }
      this.scoreForm = {
        perf_id: 0,
        user_id: Number(u.id),
        user_name: u.realname || '',
        user_post: u.post || '',
        user_structure: u.s_name || '',
        period: this.form.period,
        status: '待确认',
        create_method_label: '人工录入',
        duty_score: 0, task_score: 0, quality_score: 0, collab_score: 0,
        adjust_reason: '',
        fact_counts: {}
      }
      this.scoreDialog = true
      this.$nextTick(() => {
        if (this.$refs.scoreFormRef) this.$refs.scoreFormRef.clearValidate()
      })
    },
    // 从列表行打开录分（必须有有效 user_id）
    openScoreInputFromRow(row) {
      if (!row.user_id || Number(row.user_id) <= 0) {
        this.$message.warning('该记录缺少有效员工，不能录分')
        return
      }
      this.scoreForm = {
        perf_id: row.perf_id || 0,
        user_id: Number(row.user_id),
        user_name: row.user_name || '',
        user_post: row.user_post || '',
        user_structure: row.user_structure || '',
        period: row.period || this.form.period,
        status: row.status || '',
        create_method_label: row.create_method_label || '',
        duty_score: parseFloat(row.duty_score) || 0,
        task_score: parseFloat(row.task_score) || 0,
        quality_score: parseFloat(row.quality_score) || 0,
        collab_score: parseFloat(row.collab_score) || 0,
        adjust_reason: '',
        fact_counts: {}
      }
      this.loadFactCounts(row.user_id, row.period)
      this.scoreDialog = true
      this.$nextTick(() => {
        if (this.$refs.scoreFormRef) this.$refs.scoreFormRef.clearValidate()
      })
    },
    // 从详情弹窗打开录分（必须有有效 summary.user_id）
    openScoreInputFromDetail() {
      if (!this.summary || !this.summary.user_id || Number(this.summary.user_id) <= 0) {
        this.$message.warning('绩效记录缺少有效员工关联，不能录分')
        return
      }
      this.scoreForm = {
        perf_id: this.summary.perf_id || 0,
        user_id: Number(this.summary.user_id),
        user_name: this.summary.user_name || '',
        user_post: this.summary.user_post || '',
        user_structure: this.summary.user_structure || '',
        period: this.summary.period || '',
        status: this.summary.status || '',
        create_method_label: this.summary.create_method_label || '',
        duty_score: parseFloat(this.summary.duty_score) || 0,
        task_score: parseFloat(this.summary.task_score) || 0,
        quality_score: parseFloat(this.summary.quality_score) || 0,
        collab_score: parseFloat(this.summary.collab_score) || 0,
        adjust_reason: '',
        fact_counts: {}
      }
      // 从 calculation 中提取事实数量
      if (this.calculation && this.calculation.dimensions) {
        var fc = {}
        this.calculation.dimensions.forEach(function(dim) {
          fc[dim.key] = { positive: dim.positive_count, negative: dim.negative_count }
        })
        this.scoreForm.fact_counts = fc
      }
      this.scoreDialog = true
      this.$nextTick(() => {
        if (this.$refs.scoreFormRef) this.$refs.scoreFormRef.clearValidate()
      })
    },
    async loadFactCounts(userId, period) {
      try {
        const r = await request({ url: 'crm/performance/factList', method: 'post', data: { user_id: Number(userId), period: period }})
        const data = r.data || r || {}
        this.scoreForm.fact_counts = data.dimension_stats || {}
      } catch (e) { /* 忽略 */ }
    },
    doSaveSummary() {
      // 前端硬校验：禁止 user_id=0
      if (!this.scoreForm.user_id || Number(this.scoreForm.user_id) <= 0) {
        this.$message.error('员工信息无效，不能保存绩效得分')
        return
      }
      this.$refs.scoreFormRef.validate(valid => {
        if (!valid) return
        this.acting = true
        var payload = {
          user_id: Number(this.scoreForm.user_id),
          period: this.scoreForm.period,
          duty_score: this.scoreForm.duty_score,
          task_score: this.scoreForm.task_score,
          quality_score: this.scoreForm.quality_score,
          collab_score: this.scoreForm.collab_score,
          reason: this.scoreForm.adjust_reason || '录入维度得分'
        }
        performanceSummarySaveAPI(payload).then(r => {
          this.$message.success('加权得分：' + this.formatScore(r.data && r.data.weighted_score))
          this.scoreDialog = false
          this.fetchList()
          if (this.detailDialog) this.fetchDetail()
        }).catch(() => {}).finally(() => { this.acting = false })
      })
    },
    canRate(row) {
      if (Number(row.user_id) === this.currentUserId) return false
      return this.perms.perf_final_rate || this.isSuperAdmin
    },
    canReviewFact(targetUserId) {
      if (Number(targetUserId) === this.currentUserId) return false
      return this.perms.perf_fact_review || this.isSuperAdmin
    },
    rate(row) {
      if (!this.canRate(row)) { this.$message.warning('无权评级或本人回避'); return }
      this.rateForm = { perf_id: row.perf_id, quality_tier: this.dict.quality_tiers[0] || '', rating: this.dict.ratings[1] || '', review_note: '' }
      this.rateDialog = true
    },
    doRate() {
      this.acting = true
      performanceRateAPI(this.rateForm).then(() => {
        this.$message.success('已评级')
        this.rateDialog = false
        this.fetchList()
        if (this.detailDialog) this.fetchDetail()
      }).catch(() => {}).finally(() => { this.acting = false })
    },
    async returnSummary(row) {
      var reason
      try {
        var res = await this.$prompt('请填写退回原因（必填，将记入审计）：', '退回绩效', {
          confirmButtonText: '确认退回',
          cancelButtonText: '取消',
          inputType: 'textarea',
          inputPlaceholder: '请填写退回原因（必填）',
          inputValidator: function(v) { return (v && String(v).trim() !== '') ? true : '退回必须填写原因' }
        })
        reason = (res.value || '').trim()
      } catch (e) { return }
      if (!reason) { this.$message.warning('退回必须填写原因'); return }
      this.acting = true
      try {
        await request({ url: 'crm/performance/summaryReturn', method: 'post', data: { perf_id: row.perf_id, reason: reason }})
        this.$message.success('已退回')
        this.fetchList()
        if (this.detailDialog) this.fetchDetail()
      } catch (e) { /* 全局拦截器提示 */ } finally {
        this.acting = false
      }
    },
    showFacts(row) {
      if (!row.user_id || Number(row.user_id) <= 0) {
        this.$message.warning('该记录缺少有效员工')
        return
      }
      this.factsUserId = row.user_id
      this.factsPeriod = row.period
      this.factsDialog = true
    },
    deleteRecord(row) {
      if (!row.perf_id) return
      this.$confirm('确认删除该绩效记录？已评级/已确认/有关联事实的记录将无法删除。', '删除绩效', {
        confirmButtonText: '确认删除',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.acting = true
        performanceSummaryDeleteAPI({ perf_id: row.perf_id }).then(() => {
          this.$message.success('已删除')
          this.fetchList()
        }).catch(() => {}).finally(() => { this.acting = false })
      }).catch(() => {})
    }
  }
}
</script>

<style scoped>
.perf-page { padding: 16px; }
.pp-toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.pp-user-cell { display: flex; flex-direction: column; }
.pp-user-post { font-size: 11px; color: #909399; }
.pp-muted { color: #c0c4cc; font-size: 12px; }
.pp-score-zero { color: #c0c4cc; }
.pp-method { font-size: 12px; color: #909399; }
/* 空状态 */
.pp-empty-state { text-align: center; padding: 48px 20px; }
.pp-empty-icon { font-size: 48px; color: #dcdfe6; margin-bottom: 12px; }
.pp-empty-title { font-size: 15px; color: #606266; margin-bottom: 6px; }
.pp-empty-desc { font-size: 13px; color: #909399; margin-bottom: 16px; }
/* 员工信息区 */
.pp-emp-info { display: flex; gap: 14px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #ebeef5; }
.pp-emp-avatar { width: 56px; height: 56px; border-radius: 50%; overflow: hidden; flex-shrink: 0; background: #f0f2f5; display: flex; align-items: center; justify-content: center; }
.pp-emp-avatar img { width: 100%; height: 100%; object-fit: cover; }
.pp-emp-avatar-placeholder { font-size: 28px; color: #c0c4cc; }
.pp-emp-meta { flex: 1; min-width: 0; }
.pp-emp-name { font-size: 16px; font-weight: 600; color: #303133; margin-bottom: 2px; }
.pp-emp-sub { font-size: 13px; color: #606266; margin-bottom: 6px; }
.pp-emp-tags { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 4px; }
.pp-emp-creator { font-size: 12px; color: #909399; }
/* 维度卡片 */
.pp-dim-cards { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.pp-dim-card { flex: 1; min-width: 130px; background: #f7f8fa; border-radius: 8px; padding: 12px; text-align: center; }
.pp-dim-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.pp-dim-score { font-size: 22px; font-weight: 700; color: #303133; }
.pp-dim-unit { font-size: 12px; font-weight: 400; color: #909399; margin-left: 2px; }
.pp-dim-weight { font-size: 11px; color: #c0c4cc; margin-top: 2px; }
.pp-dim-contrib { font-size: 12px; color: #409eff; margin-top: 2px; }
.pp-dim-facts { font-size: 11px; color: #909399; margin-top: 4px; }
.pp-fact-pos { color: #67c23a; font-weight: 600; }
.pp-fact-neg { color: #f56c6c; font-weight: 600; margin-left: 4px; }
.pp-fact-label { margin-left: 4px; }
/* 计算说明 */
.pp-calc { background: #f7f8fa; border-radius: 8px; padding: 14px; margin-bottom: 16px; }
.pp-calc-row { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; flex-wrap: wrap; }
.pp-calc-label { font-size: 13px; color: #909399; }
.pp-calc-value { font-size: 14px; color: #303133; font-weight: 600; }
.pp-calc-big { font-size: 20px; color: #409eff; }
.pp-calc-formula { font-size: 12px; color: #909399; margin-top: 4px; }
.pp-calc-note { font-size: 12px; color: #e6a23c; margin-top: 6px; }
/* 录分弹窗 */
.pp-score-header { background: #f7f8fa; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; }
.pp-score-emp { margin-bottom: 6px; }
.pp-score-name { font-size: 15px; font-weight: 600; color: #303133; }
.pp-score-sub { font-size: 13px; color: #606266; }
.pp-score-meta { display: flex; gap: 6px; flex-wrap: wrap; }
.pp-score-dim-row { display: flex; align-items: center; gap: 10px; }
.pp-score-weight { font-size: 12px; color: #909399; white-space: nowrap; }
.pp-score-facts { font-size: 11px; color: #c0c4cc; white-space: nowrap; }
.pp-score-preview { font-size: 18px; font-weight: 700; color: #409eff; }
@media (max-width: 768px) {
  .pp-dim-cards { flex-direction: column; }
  .pp-dim-card { min-width: 100%; }
}
</style>

<style>
.pp-detail-dialog .el-dialog__body { padding: 16px 20px; }
.pp-facts-dialog .el-dialog__body { padding: 12px 16px; }
.pp-score-dialog .el-dialog__body { padding: 16px 20px; }
@media (max-width: 768px) {
  .pp-detail-dialog, .pp-facts-dialog, .pp-score-dialog { width: 95% !important; margin: 0 auto !important; }
}
</style>
