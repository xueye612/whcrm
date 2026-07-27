<template>
  <div class="opp-panel">
    <div class="op-toolbar">
      <el-select v-model="form.source_type" size="small" placeholder="类型" @change="onTypeChange" style="width:120px">
        <el-option v-for="t in dict.source_types" :key="t" :label="t" :value="t"/>
      </el-select>
      <el-input v-model="form.name" size="small" placeholder="机会名称" style="width:200px"/>
      <el-button type="primary" size="small" @click="createOpp">创建机会</el-button>
      <el-button size="small" @click="fetchList">刷新列表</el-button>
    </div>
    <el-table :data="list" size="small" border style="margin-top:10px">
      <el-table-column label="ID" prop="opp_id" width="60"/>
      <el-table-column label="类型" prop="source_type" width="80"/>
      <el-table-column label="名称" prop="name"/>
      <el-table-column label="当前阶段" prop="current_stage" width="110"/>
      <el-table-column label="状态" prop="status" width="80"/>
      <el-table-column label="操作" width="100"><template slot-scope="s"><el-button type="text" size="mini" @click="openDetail(s.row)">详情/推进</el-button></template></el-table-column>
    </el-table>
    <el-dialog title="机会详情与阶段推进" :visible.sync="detailDialog" width="640px" append-to-body>
      <div v-if="detail">
        <p>类型：{{ detail.opportunity.source_type }} | 当前阶段：{{ detail.opportunity.current_stage }} | 累计奖励：{{ detail.opportunity.reward_total }} 元</p>
        <p>阶段进度：</p>
        <el-table :data="detail.stages" size="mini" border>
          <el-table-column label="阶段" prop="stage"/>
          <el-table-column label="奖励" prop="reward_amount" width="100"/>
          <el-table-column label="证据" prop="evidence_note"/>
        </el-table>
        <div style="margin-top:12px">
          <el-select v-model="advanceForm.stage" size="small" placeholder="选择阶段" style="width:160px">
            <el-option v-for="s in (dict.stages[detail.opportunity.source_type]||[])" :key="s" :label="s" :value="s"/>
          </el-select>
          <el-input v-model="advanceForm.evidence_note" size="small" placeholder="阶段证据" style="width:280px;margin-left:8px"/>
          <el-button type="primary" size="small" style="margin-left:8px" @click="advance">推进</el-button>
        </div>
      </div>
    </el-dialog>
  </div>
</template>
<script>
import { opportunityListAPI, opportunitySaveAPI, opportunityReadAPI, opportunityStageAdvanceAPI } from '@/api/crm/opportunity'
export default {
  name: 'OpportunityPanel',
  data() { return { dict: { source_types: [], stages: {} }, list: [], form: { source_type: '', name: '' }, detailDialog: false, detail: null, advanceForm: { stage: '', evidence_note: '' } } },
  async created() { await this.fetchList() },
  methods: {
    async fetchList() { const res = await opportunityListAPI({}); const d = res.data || res; this.list = d.list || []; this.dict = d.dictionary || this.dict },
    onTypeChange() {},
    async createOpp() { if (!this.form.source_type || !this.form.name) { this.$message.warning('请填类型与名称'); return }; await opportunitySaveAPI(this.form); this.$message.success('已创建'); this.form.name = ''; this.fetchList() },
    async openDetail(row) { const res = await opportunityReadAPI({ opp_id: row.opp_id }); this.detail = res.data; this.advanceForm = { stage: '', evidence_note: '' }; this.detailDialog = true },
    async advance() { if (!this.detail || !this.advanceForm.stage) { this.$message.warning('请选择阶段'); return }; await opportunityStageAdvanceAPI({ opp_id: this.detail.opportunity.opp_id, stage: this.advanceForm.stage, evidence_note: this.advanceForm.evidence_note }); this.$message.success('已推进'); const res = await opportunityReadAPI({ opp_id: this.detail.opportunity.opp_id }); this.detail = res.data }
  }
}
</script>
<style scoped>.opp-panel{padding:12px}.op-toolbar{display:flex;gap:8px;align-items:center}</style>
