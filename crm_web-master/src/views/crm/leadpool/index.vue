<template>
  <div class="lead-pool-panel">
    <div class="lp-toolbar">
      <el-input v-model="newBatch.name" size="small" placeholder="批次名称" style="width:180px"/>
      <el-input v-model="newBatch.channel" size="small" placeholder="渠道" style="width:120px"/>
      <el-button type="primary" size="small" @click="createBatch">创建批次</el-button>
      <el-select v-model="rawForm.batch_id" size="small" placeholder="选择批次" style="width:160px;margin-left:12px">
        <el-option v-for="b in data.batches" :key="b.batch_id" :label="b.name" :value="b.batch_id"/>
      </el-select>
      <el-input v-model="rawForm.raw_name" size="small" placeholder="名称" style="width:140px"/>
      <el-input v-model="rawForm.raw_mobile" size="small" placeholder="手机号" style="width:140px"/>
      <el-button type="primary" size="small" @click="submitRaw">提交原始线索</el-button>
    </div>
    <div class="lp-stat">
      <el-tag v-for="(v,k) in data.stat" :key="k" size="small" style="margin-right:8px">{{ k }}: {{ v }}</el-tag>
    </div>
    <el-table :data="data.raws" size="small" border>
      <el-table-column label="ID" prop="raw_id" width="60"/>
      <el-table-column label="名称" prop="raw_name"/>
      <el-table-column label="手机号" prop="raw_mobile" width="130"/>
      <el-table-column label="状态" width="90"><template slot-scope="s"><el-tag size="mini" :type="statusTag(s.row.status)">{{ s.row.status }}</el-tag></template></el-table-column>
      <el-table-column label="操作" width="200">
        <template slot-scope="s">
          <el-button type="text" size="mini" @click="queryDedupe(s.row)">查重</el-button>
          <el-button type="text" size="mini" @click="decide(s.row,'独立')">独立</el-button>
          <el-button type="text" size="mini" @click="decide(s.row,'重复')">重复</el-button>
          <el-button v-if="dedupeCands[s.row.raw_id]" type="text" size="mini" @click="decide(s.row,'归并')">归并</el-button>
        </template>
      </el-table-column>
    </el-table>
    <el-dialog title="查重候选" :visible.sync="dedupeDialog" width="560px" append-to-body>
      <div v-if="dedupeCands[curRawId]">
        <p>标准线索候选：</p>
        <el-table :data="dedupeCands[curRawId].leads" size="mini" border>
          <el-table-column label="ID" prop="leads_id" width="60"/>
          <el-table-column label="名称" prop="name"/>
          <el-table-column label="手机" prop="mobile" width="130"/>
          <el-table-column label="操作" width="80"><template slot-scope="s"><el-button type="text" size="mini" @click="doMerge(s.row)">归并到此</el-button></template></el-table-column>
        </el-table>
        <p v-if="!dedupeCands[curRawId].leads.length" style="color:#909399">无标准线索候选，可判为独立。</p>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {
  leadpoolReadAPI, leadpoolBatchSaveAPI, leadpoolRawSaveAPI,
  leadpoolDedupeQueryAPI, leadpoolDedupeDecideAPI
} from '@/api/crm/leadpool'

export default {
  name: 'LeadPoolPanel',
  data() {
    return {
      data: { batches: [], raws: [], stat: {}, dictionary: {} },
      newBatch: { name: '', channel: '' },
      rawForm: { batch_id: '', raw_name: '', raw_mobile: '' },
      dedupeDialog: false,
      curRawId: 0,
      dedupeCands: {}
    }
  },
  created() { this.fetch() },
  methods: {
    async fetch() {
      const res = await leadpoolReadAPI({})
      this.data = res.data || res
    },
    statusTag(s) { return { 待查重: 'info', 已查重: '', 重复: 'danger', 已归并: 'warning', 已转客户: 'success' }[s] || 'info' },
    async createBatch() {
      if (!this.newBatch.name) { this.$message.warning('请填批次名称'); return }
      await leadpoolBatchSaveAPI(this.newBatch)
      this.newBatch = { name: '', channel: '' }
      this.$message.success('批次已创建'); this.fetch()
    },
    async submitRaw() {
      if (!this.rawForm.batch_id) { this.$message.warning('请选择批次'); return }
      const res = await leadpoolRawSaveAPI(this.rawForm)
      if (res.data && res.data.suspected_duplicate) this.$message.warning('疑似重复（同批次已存在）')
      else this.$message.success('已提交')
      this.rawForm.raw_name = ''; this.rawForm.raw_mobile = ''; this.fetch()
    },
    async queryDedupe(row) {
      const res = await leadpoolDedupeQueryAPI({ raw_id: row.raw_id })
      this.dedupeCands = Object.assign({}, this.dedupeCands, { [row.raw_id]: res.data.candidates })
      this.curRawId = row.raw_id; this.dedupeDialog = true
    },
    async decide(row, decision) {
      if (decision === '归并' && !this.dedupeCands[row.raw_id]) { await this.queryDedupe(row) }
      if (decision === '归并') {
        const c = this.dedupeCands[row.raw_id]
        if (!c || !c.leads.length) { this.$message.warning('无标准线索候选，无法归并'); return }
        // 默认归并到第一个候选；可在弹窗选择
        this.curRawId = row.raw_id; this.dedupeDialog = true; return
      }
      await leadpoolDedupeDecideAPI({ raw_id: row.raw_id, decision })
      this.$message.success('已决策'); this.fetch()
    },
    async doMerge(lead) {
      await leadpoolDedupeDecideAPI({ raw_id: this.curRawId, decision: '归并', canonical_lead_id: lead.leads_id })
      this.dedupeDialog = false; this.$message.success('已归并'); this.fetch()
    }
  }
}
</script>

<style scoped>
.lead-pool-panel { padding: 12px; }
.lp-toolbar { margin-bottom: 12px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.lp-stat { margin-bottom: 12px; }
</style>
