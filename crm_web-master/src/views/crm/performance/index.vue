<template>
  <div class="perf-page">
    <div class="pp-toolbar">
      <el-input v-model="form.user_id" size="small" placeholder="员工ID" style="width:110px"/>
      <el-input v-model="form.period" size="small" placeholder="季度 如2026Q3" style="width:140px"/>
      <el-input-number v-model="form.duty_score" :min="0" :max="100" controls-position="right" size="small" placeholder="核心职责40%"/>
      <el-input-number v-model="form.task_score" :min="0" :max="100" controls-position="right" size="small" placeholder="任务30%"/>
      <el-input-number v-model="form.quality_score" :min="0" :max="100" controls-position="right" size="small" placeholder="质量20%"/>
      <el-input-number v-model="form.collab_score" :min="0" :max="100" controls-position="right" size="small" placeholder="协作10%"/>
      <el-button type="primary" size="small" @click="saveSummary">录入汇总</el-button>
      <el-button size="small" @click="fetchList">刷新</el-button>
    </div>
    <div class="pp-weights">四权重：核心职责40% / 重点任务30% / 测试与质量20% / 协作10%；评级系数 优秀1.2 / 合格1.0 / 待改进0.6（人工确认）</div>
    <el-table :data="list" size="small" border style="margin-top:10px">
      <el-table-column label="ID" prop="perf_id" width="60"/>
      <el-table-column label="员工" prop="user_id" width="70"/>
      <el-table-column label="季度" prop="period" width="90"/>
      <el-table-column label="加权" prop="weighted_score" width="80"/>
      <el-table-column label="质量档" prop="quality_tier" width="100"/>
      <el-table-column label="评级" prop="rating" width="80"><template slot-scope="s">{{ s.row.rating }}({{ s.row.rating_factor }})</template></el-table-column>
      <el-table-column label="状态" prop="status" width="80"/>
      <el-table-column label="操作" width="120">
        <template slot-scope="s">
          <el-button v-if="s.row.status==='待确认'" type="text" size="mini" @click="rate(s.row)">评级</el-button>
        </template>
      </el-table-column>
    </el-table>
    <el-dialog title="评级(本人回避)" :visible.sync="rateDialog" width="480px" append-to-body>
      <el-form label-width="90px" size="small">
        <el-form-item label="质量三档"><el-select v-model="rateForm.quality_tier" style="width:100%"><el-option v-for="t in dict.quality_tiers" :key="t" :label="t" :value="t"/></el-select></el-form-item>
        <el-form-item label="最终评级"><el-select v-model="rateForm.rating" style="width:100%"><el-option v-for="r in dict.ratings" :key="r" :label="r+'('+dict.rating_factors[r]+')'" :value="r"/></el-select></el-form-item>
      </el-form>
      <span slot="footer"><el-button @click="rateDialog=false">取消</el-button><el-button type="primary" @click="doRate">确认</el-button></span>
    </el-dialog>
  </div>
</template>
<script>
import { performanceDictionaryAPI, performanceSummarySaveAPI, performanceSummaryListAPI, performanceRateAPI } from '@/api/crm/performance'
export default {
  name: 'PerformancePage',
  data() { return { dict: { quality_tiers: [], ratings: [], rating_factors: {} }, list: [], form: { user_id: '', period: '2026Q3', duty_score: 0, task_score: 0, quality_score: 0, collab_score: 0 }, rateDialog: false, rateForm: { perf_id: 0, quality_tier: '', rating: '' } } },
  async created() { await this.fetchDict(); await this.fetchList() },
  methods: {
    async fetchDict() { const r = await performanceDictionaryAPI({}); this.dict = r.data || r },
    async fetchList() { const r = await performanceSummaryListAPI({}); this.list = (r.data && r.data.list) || [] },
    async saveSummary() { if (!this.form.user_id || !this.form.period) { this.$message.warning('请填员工与季度'); return }; const r = await performanceSummarySaveAPI(this.form); this.$message.success('加权得分：' + (r.data && r.data.weighted_score)); this.fetchList() },
    rate(row) { this.rateForm = { perf_id: row.perf_id, quality_tier: this.dict.quality_tiers[0] || '', rating: this.dict.ratings[1] || '' }; this.rateDialog = true },
    async doRate() { try { await performanceRateAPI(this.rateForm); this.$message.success('已评级'); this.rateDialog = false; this.fetchList() } catch (e) {} }
  }
}
</script>
<style scoped>.perf-page{padding:16px}.pp-toolbar{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:6px}.pp-weights{font-size:12px;color:#606266}</style>
