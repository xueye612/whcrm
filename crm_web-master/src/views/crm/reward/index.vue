<template>
  <div class="reward-page">
    <div class="rp-toolbar">
      <el-select v-model="form.source_type" size="small" placeholder="来源(决定固定金额)" style="width:200px">
        <el-option v-for="(amt,k) in dict.fixed_amounts" :key="k" :label="k+' ('+amt+'元)'" :value="k"/>
      </el-select>
      <el-input v-model="form.user_id" size="small" placeholder="候选人ID" style="width:110px"/>
      <el-input v-model="form.reason" size="small" placeholder="事由" style="width:200px"/>
      <el-button type="primary" size="small" @click="createCand">新建候选</el-button>
      <el-button size="small" @click="fetchList">刷新</el-button>
    </div>
    <div class="rp-config">
      <span>配置状态：</span>
      <el-tag v-for="(v,k) in config" :key="k" size="mini" :type="v==='待配置'?'warning':'success'" style="margin-right:8px">{{ k }}: {{ v }}</el-tag>
      <span v-if="hasPendingConfig" style="color:#e6a23c;font-size:12px;margin-left:8px">存在"待配置"项：相关金额/上限/拆分不会自动计算，需后台配置后生效</span>
    </div>
    <el-table :data="list" size="small" border style="margin-top:10px">
      <el-table-column label="ID" prop="cand_id" width="60"/>
      <el-table-column label="来源" prop="source_type" width="140"/>
      <el-table-column label="候选人" prop="user_id" width="80"/>
      <el-table-column label="金额" prop="amount" width="90"/>
      <el-table-column label="状态" width="90"><template slot-scope="s"><el-tag size="mini" :type="statusTag(s.row.status)">{{ s.row.status }}</el-tag></template></el-table-column>
      <el-table-column label="事由" prop="reason"/>
      <el-table-column label="操作" width="160">
        <template slot-scope="s">
          <el-button v-if="s.row.status==='待审核'" type="text" size="mini" @click="review(s.row,'approve')">通过</el-button>
          <el-button v-if="s.row.status==='待审核'" type="text" size="mini" style="color:#f56c6c" @click="review(s.row,'reject')">驳回</el-button>
          <el-button v-if="s.row.status==='已通过'" type="text" size="mini" @click="offset(s.row)">冲销</el-button>
        </template>
      </el-table-column>
    </el-table>
    <div style="margin-top:10px">
      <el-button type="success" size="small" @click="batchCreate">生成结算批次(已通过)</el-button>
    </div>
  </div>
</template>
<script>
import { rewardDictionaryAPI, rewardCandidateSaveAPI, rewardCandidateListAPI, rewardReviewAPI, rewardBatchCreateAPI, rewardOffsetAPI } from '@/api/crm/reward'
export default {
  name: 'RewardPage',
  data() { return { dict: { fixed_amounts: {} }, config: {}, list: [], form: { source_type: '', user_id: '', reason: '' } } },
  computed: { hasPendingConfig() { return Object.values(this.config).some(v => v === '待配置') } },
  async created() { await this.fetchDict(); await this.fetchList() },
  methods: {
    async fetchDict() { const r = await rewardDictionaryAPI({}); const d = r.data || r; this.dict = d.data || d; this.config = d.config || {} },
    async fetchList() { const r = await rewardCandidateListAPI({}); this.list = (r.data && r.data.list) || [] },
    statusTag(s) { return { 待审核: 'info', 已通过: 'success', 已驳回: 'danger', 已结算: '', 已冲销: 'warning' }[s] || 'info' },
    async createCand() { if (!this.form.source_type || !this.form.user_id) { this.$message.warning('请填来源与候选人'); return }; await rewardCandidateSaveAPI(this.form); this.$message.success('已创建'); this.form.reason = ''; this.fetchList() },
    async review(row, d) { try { await rewardReviewAPI({ cand_id: row.cand_id, decision: d }); this.$message.success('已审核'); this.fetchList() } catch (e) {} },
    async offset(row) { this.$prompt('冲销金额', '冲销', {}).then(async ({ value }) => { await rewardOffsetAPI({ cand_id: row.cand_id, offset_amount: value }); this.$message.success('已冲销'); this.fetchList() }).catch(() => {}) },
    async batchCreate() { try { const r = await rewardBatchCreateAPI({}); this.$message.success('已生成结算批次：' + JSON.stringify(r.data)); this.fetchList() } catch (e) {} }
  }
}
</script>
<style scoped>.reward-page{padding:16px}.rp-toolbar{display:flex;gap:8px;align-items:center;margin-bottom:8px}.rp-config{font-size:12px;color:#606266}</style>
