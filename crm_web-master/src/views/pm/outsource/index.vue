<template>
  <div class="os-page">
    <div class="os-toolbar">
      <span>项目实施/外包</span>
      <el-input v-model="workId" size="small" placeholder="项目ID(work_id)" style="width:160px"/>
      <el-button type="primary" size="small" @click="fetchProfile">读取</el-button>
    </div>
    <el-form v-if="profile" :model="form" label-width="120px" size="small" style="max-width:760px;margin-top:12px">
      <el-form-item label="交付等级"><el-select v-model="form.delivery_level" clearable style="width:200px"><el-option v-for="l in dict.delivery_levels" :key="l" :label="l" :value="l"/></el-select></el-form-item>
      <el-form-item label="到账收入"><el-input-number v-model="form.revenue" :min="0" controls-position="right"/></el-form-item>
      <el-form-item label="直接成本"><el-input-number v-model="form.direct_cost" :min="0" controls-position="right"/></el-form-item>
      <el-form-item label="需求基线"><el-input v-model="form.requirement_baseline" type="textarea" :rows="2"/></el-form-item>
      <el-form-item label="奖金池比例"><span>奖励{{ form.reward_pct }}% / 商务费用{{ form.expense_pct }}%（默认2%/3%，可改）</span></el-form-item>
      <el-form-item><el-button type="primary" :loading="saving" @click="saveProfile">保存档案(自动算毛利/池)</el-button>
        <span style="margin-left:12px;color:#909399">毛利={{ margin }} 奖励池={{ pools.reward_pool }} 费用池={{ pools.expense_pool }}</span>
      </el-form-item>
    </el-form>
    <div v-if="profile" style="margin-top:8px">
      <h4 style="margin:8px 0">实施三级比例分配（默认 技术与项目负责人40/客户成功工程师28/研发负责人25/总经理兼产品负责人5/市场运营专员2，合计≤100，不足不自动分配）</h4>
      <el-table :data="ratios" size="small" border style="max-width:680px">
        <el-table-column label="角色"><template slot-scope="s">{{ s.row.role }}</template></el-table-column>
        <el-table-column label="比例%" width="160"><template slot-scope="s"><el-input-number v-model="s.row.percentage" :min="0" :max="100" controls-position="right" size="mini"/></template></el-table-column>
      </el-table>
      <div style="margin-top:8px">合计：{{ ratioSum }}% <span v-if="ratioSum>100" style="color:#f56c6c">（超过100%）</span></div>
      <el-button type="primary" size="small" style="margin-top:8px" :loading="distting" @click="saveDist">保存分配</el-button>
      <el-button size="small" style="margin-top:8px" @click="fetchDist">读取分配</el-button>
    </div>
  </div>
</template>
<script>
import { outsourceDictionaryAPI, outsourceProjectSaveAPI, outsourceProjectReadAPI, outsourceDistributeSaveAPI, outsourceDistributeReadAPI } from '@/api/work/outsource'
export default {
  name: 'OutsourcePage',
  data() { return { workId: '', profile: null, dict: { delivery_levels: [], default_distribution: [] }, form: {}, margin: 0, pools: { reward_pool: 0, expense_pool: 0 }, ratios: [], saving: false, distting: false } },
  computed: { ratioSum() { return this.ratios.reduce((s, r) => s + (Number(r.percentage) || 0), 0) } },
  async created() { const r = await outsourceDictionaryAPI({}); this.dict = r.data || r; this.ratios = (this.dict.default_distribution || []).map(x => ({ role: x.role, percentage: x.percentage })) },
  methods: {
    async fetchProfile() { if (!this.workId) return; const r = await outsourceProjectReadAPI({ work_id: Number(this.workId) }); const d = r.data || r; this.profile = d.profile; if (this.profile) { this.form = { delivery_level: this.profile.delivery_level || '', revenue: Number(this.profile.revenue) || 0, direct_cost: Number(this.profile.direct_cost) || 0, requirement_baseline: this.profile.requirement_baseline || '', reward_pct: Number(this.profile.reward_pct) || 2, expense_pct: Number(this.profile.expense_pct) || 3 }; this.margin = this.profile.gross_margin; this.pools = { reward_pool: this.profile.reward_pool, expense_pool: this.profile.expense_pool } } else { this.form = { delivery_level: '', revenue: 0, direct_cost: 0, requirement_baseline: '', reward_pct: 2, expense_pct: 3 }; this.profile = {} } },
    async saveProfile() { this.saving = true; try { const r = await outsourceProjectSaveAPI(Object.assign({ work_id: Number(this.workId) }, this.form)); const d = r.data || {}; this.margin = d.gross_margin; this.pools = { reward_pool: d.reward_pool, expense_pool: d.expense_pool }; this.$message.success('已保存') } finally { this.saving = false } },
    async saveDist() { if (this.ratioSum > 100) { this.$message.error('总比例超过100%'); return }; this.distting = true; try { const r = await outsourceDistributeSaveAPI({ work_id: Number(this.workId), ratios: JSON.stringify(this.ratios) }); this.$message.success('已保存分配：' + JSON.stringify(r.data)) } finally { this.distting = false } },
    async fetchDist() { const r = await outsourceDistributeReadAPI({ work_id: Number(this.workId) }); const rows = (r.data && r.data.rows) || []; if (rows.length) this.ratios = rows.map(x => ({ role: x.role_name, percentage: Number(x.percentage) })) }
  }
}
</script>
<style scoped>.os-page{padding:16px}.os-toolbar{display:flex;gap:8px;align-items:center}</style>
