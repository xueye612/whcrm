<template>
  <div class="os-page">
    <div class="os-toolbar">
      <el-button size="small" icon="el-icon-back" @click="$router.back()">返回</el-button>
      <span>项目实施 / 外包奖金</span>
      <el-select
        v-model="workId"
        :loading="projectLoading"
        filterable
        clearable
        size="small"
        placeholder="选择或搜索项目"
        class="os-project-select"
        @change="onProjectChange">
        <el-option
          v-for="project in projectOptions"
          :key="project.work_id"
          :label="project.name + '（ID: ' + project.work_id + '）'"
          :value="String(project.work_id)"/>
      </el-select>
      <el-button :disabled="!workId" type="primary" size="small" @click="loadAll">读取</el-button>
    </div>

    <!-- 档案 -->
    <el-form v-if="profile" :model="form" label-width="110px" size="small" class="os-form">
      <el-row :gutter="12">
        <el-col :sm="8"><el-form-item label="交付等级"><el-select v-model="form.delivery_level" clearable style="width:100%"><el-option v-for="l in dict.delivery_levels" :key="l" :label="l" :value="l"/></el-select></el-form-item></el-col>
        <el-col :sm="8"><el-form-item label="到账收入"><el-input-number v-model="form.revenue" :min="0" controls-position="right" style="width:100%"/></el-form-item></el-col>
        <el-col :sm="8"><el-form-item label="直接成本"><el-input-number v-model="form.direct_cost" :min="0" controls-position="right" style="width:100%"/></el-form-item></el-col>
      </el-row>
      <el-form-item><el-button :loading="saving" type="primary" @click="saveProfile">保存档案</el-button>
        <span class="os-hint">毛利 <b>{{ margin }}</b> · 奖励池 <b>{{ pools.reward_pool }}</b> · 费用池 <b>{{ pools.expense_pool }}</b></span>
      </el-form-item>
    </el-form>

    <!-- 比例编辑 -->
    <div v-if="profile" class="os-section">
      <div class="os-section-title">岗位分配比例 <span class="os-hint">（合计 {{ ratioSum }}% / 100%）</span></div>
      <el-table :data="ratios" size="small" border style="max-width:680px">
        <el-table-column label="岗位角色" prop="role"/>
        <el-table-column label="分配比例" width="160">
          <template slot-scope="s"><el-input-number v-model="s.row.percentage" :min="0" :max="100" controls-position="right" size="mini"/></template>
        </el-table-column>
      </el-table>
      <div style="margin-top:8px">
        <el-button :loading="distting" type="primary" size="small" @click="saveDist">保存并计算分配</el-button>
        <el-button size="small" @click="fetchDist">重新读取</el-button>
      </div>
    </div>

    <!-- 已保存的奖金分配结果 -->
    <div v-if="distResult" class="os-section os-dist-result">
      <div class="os-section-title">奖金分配明细</div>
      <el-row :gutter="12" class="os-pool-summary">
        <el-col :sm="6"><div class="os-pool-card"><div class="os-pool-label">奖励池总额</div><div class="os-pool-value">{{ payoutTotal }}</div></div></el-col>
        <el-col :sm="6"><div class="os-pool-card"><div class="os-pool-label">已分配</div><div class="os-pool-value os-green">{{ distResult.allocated_pct }}%</div></div></el-col>
        <el-col :sm="6"><div class="os-pool-card"><div class="os-pool-label">未分配</div><div class="os-pool-value os-orange">{{ distResult.unallocated }}</div></div></el-col>
        <el-col :sm="6"><div class="os-pool-card"><div class="os-pool-label">发放节奏</div><div class="os-pool-value os-blue">70% 交付 / 30% 验收</div></div></el-col>
      </el-row>
      <el-table :data="distResult.rows" size="small" border style="margin-top:10px;max-width:680px">
        <el-table-column label="岗位角色" prop="role"/>
        <el-table-column label="分配比例" width="100"><template slot-scope="s">{{ s.row.percentage }}%</template></el-table-column>
        <el-table-column label="分配金额" width="120"><template slot-scope="s"><b>{{ s.row.amount }}</b></template></el-table-column>
      </el-table>
      <div v-if="distResult.payout_rhythm" class="os-payout-rhythm">
        <span class="os-hint">阶段一（交付 {{ distResult.payout_rhythm.phase1_pct }}%）：</span><b>{{ distResult.payout_rhythm.phase1_deliver }}</b>
        <span class="os-hint" style="margin-left:16px">阶段二（验收 {{ distResult.payout_rhythm.phase2_pct }}%）：</span><b>{{ distResult.payout_rhythm.phase2_accept }}</b>
        <span class="os-hint" style="margin-left:16px">{{ distResult.note }}</span>
      </div>
    </div>

    <!-- 从数据库读取的已保存分配 -->
    <div v-if="savedRows.length && !distResult" class="os-section">
      <div class="os-section-title">已保存的分配记录</div>
      <el-table :data="savedRows" size="small" border style="max-width:680px">
        <el-table-column label="岗位角色" prop="role_name"/>
        <el-table-column label="分配比例" width="100"><template slot-scope="s">{{ s.row.percentage }}%</template></el-table-column>
        <el-table-column label="分配金额" width="120"><template slot-scope="s"><b>{{ s.row.amount }}</b></template></el-table-column>
      </el-table>
    </div>

    <div v-if="!profile" class="os-empty">{{ workId ? '正在读取项目档案与奖金分配' : '请选择项目查看档案与奖金分配' }}</div>
  </div>
</template>

<script>
import { outsourceDictionaryAPI, outsourceProjectSaveAPI, outsourceProjectReadAPI, outsourceDistributeSaveAPI, outsourceDistributeReadAPI } from '@/api/work/outsource'
import { workIndexWorkListAPI } from '@/api/pm/task'

export default {
  name: 'OutsourcePage',
  data() {
    return {
      workId: '',
      projectOptions: [],
      projectLoading: false,
      profile: null,
      dict: { delivery_levels: [], default_distribution: [] },
      form: {},
      margin: 0,
      pools: { reward_pool: 0, expense_pool: 0 },
      ratios: [],
      saving: false,
      distting: false,
      distResult: null,
      savedRows: []
    }
  },
  computed: {
    ratioSum() { return this.ratios.reduce((s, r) => s + (Number(r.percentage) || 0), 0) },
    payoutTotal() {
      if (!this.distResult || !this.distResult.rows) return '0'
      return this.distResult.rows.reduce((s, r) => s + (Number(r.amount) || 0), 0).toFixed(2)
    }
  },
  async created() {
    await this.fetchProjectOptions()
    try {
      const r = await outsourceDictionaryAPI({})
      this.dict = r.data || r
      this.ratios = (this.dict.default_distribution || []).map(x => ({ role: x.role, percentage: x.percentage }))
    } catch (e) { /* ignore */ }
    // 从项目详情页跳转时自动读取
    const qid = this.$route && this.$route.query && this.$route.query.work_id
    if (qid) {
      this.workId = String(qid)
      this.loadAll()
    }
  },
  methods: {
    async fetchProjectOptions() {
      this.projectLoading = true
      try {
        const r = await workIndexWorkListAPI({ sort_type: 4 })
        this.projectOptions = (r.data || []).filter(project => project && project.work_id)
      } catch (e) {
        this.projectOptions = []
      } finally {
        this.projectLoading = false
      }
    },
    onProjectChange() {
      this.profile = null
      this.distResult = null
      this.savedRows = []
      if (this.workId) this.loadAll()
    },
    async loadAll() {
      if (!this.workId) return
      await this.fetchProfile()
      await this.fetchDist()
    },
    async fetchProfile() {
      if (!this.workId) return
      try {
        const r = await outsourceProjectReadAPI({ work_id: Number(this.workId) })
        const d = r.data || r
        this.profile = d.profile
        if (this.profile) {
          this.form = {
            delivery_level: this.profile.delivery_level || '',
            revenue: Number(this.profile.revenue) || 0,
            direct_cost: Number(this.profile.direct_cost) || 0,
            requirement_baseline: this.profile.requirement_baseline || '',
            reward_pct: Number(this.profile.reward_pct) || 2,
            expense_pct: Number(this.profile.expense_pct) || 3
          }
          this.margin = this.profile.gross_margin
          this.pools = { reward_pool: this.profile.reward_pool, expense_pool: this.profile.expense_pool }
        } else {
          this.form = { delivery_level: '', revenue: 0, direct_cost: 0, requirement_baseline: '', reward_pct: 2, expense_pct: 3 }
          this.profile = {}
        }
      } catch (e) { /* 全局拦截器提示 */ }
    },
    async saveProfile() {
      this.saving = true
      try {
        const r = await outsourceProjectSaveAPI(Object.assign({ work_id: Number(this.workId) }, this.form))
        const d = r.data || {}
        this.margin = d.gross_margin
        this.pools = { reward_pool: d.reward_pool, expense_pool: d.expense_pool }
        this.$message.success('档案已保存')
      } finally { this.saving = false }
    },
    async saveDist() {
      if (this.ratioSum > 100) { this.$message.error('总比例超过100%'); return }
      this.distting = true
      try {
        const r = await outsourceDistributeSaveAPI({ work_id: Number(this.workId), ratios: JSON.stringify(this.ratios) })
        this.distResult = r.data || null
        this.$message.success('奖金分配已保存')
        await this.fetchDist()
      } finally { this.distting = false }
    },
    async fetchDist() {
      try {
        const r = await outsourceDistributeReadAPI({ work_id: Number(this.workId) })
        const rows = (r.data && r.data.rows) || []
        this.savedRows = rows
        if (rows.length) this.ratios = rows.map(x => ({ role: x.role_name, percentage: Number(x.percentage) }))
      } catch (e) { /* ignore */ }
    }
  }
}
</script>

<style scoped>
.os-page { padding: 16px; max-width: 900px; }
.os-toolbar { display: flex; gap: 8px; align-items: center; margin-bottom: 12px; }
.os-project-select { width: 300px; }
.os-form { margin-top: 8px; }
.os-hint { color: #909399; font-size: 12px; margin-left: 8px; }
.os-section { margin-top: 16px; padding: 12px; background: #f7f8fa; border-radius: 6px; }
.os-section-title { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 8px; }
.os-green { color: #67c23a; }
.os-orange { color: #e6a23c; }
.os-blue { color: #409eff; }
.os-empty { text-align: center; padding: 40px; color: #909399; }
.os-dist-result { background: #f0f5ff; border: 1px solid #d4e4ff; }
.os-pool-summary { margin-bottom: 4px; }
.os-pool-card { background: #fff; border-radius: 6px; padding: 8px 10px; text-align: center; }
.os-pool-label { font-size: 12px; color: #909399; }
.os-pool-value { font-size: 18px; font-weight: 700; margin-top: 2px; }
.os-payout-rhythm { margin-top: 10px; font-size: 13px; color: #303133; }
</style>
