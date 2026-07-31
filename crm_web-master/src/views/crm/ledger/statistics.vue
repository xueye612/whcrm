<template>
  <div v-loading="loading" class="ledger-stats-panel">
    <!-- 第一行：主要状态卡片（可点击筛选） -->
    <div class="lsp-row lsp-main-cards">
      <div class="lsp-card lsp-card--click total" @click="emitFilter('')">
        <div class="lsp-num">{{ stats.total }}</div>
        <div class="lsp-label">总数</div>
      </div>
      <div class="lsp-card lsp-card--click pending" @click="emitFilter('待处理')">
        <div class="lsp-num">{{ stats.pending }}</div>
        <div class="lsp-label">待处理</div>
      </div>
      <div class="lsp-card lsp-card--click processing" @click="emitFilter('处理中')">
        <div class="lsp-num">{{ stats.processing }}</div>
        <div class="lsp-label">处理中</div>
      </div>
      <div class="lsp-card lsp-card--click release" @click="emitFilter('待发布')">
        <div class="lsp-num">{{ stats.release_pending }}</div>
        <div class="lsp-label">待发布</div>
      </div>
      <div class="lsp-card lsp-card--click completed" @click="emitFilter('已完成')">
        <div class="lsp-num">{{ stats.completed }}</div>
        <div class="lsp-label">已完成</div>
      </div>
      <div class="lsp-card lsp-card--click closed" @click="emitFilter('已关闭')">
        <div class="lsp-num">{{ stats.closed }}</div>
        <div class="lsp-label">已关闭</div>
      </div>
    </div>

    <!-- 第二行：紧凑的单行运营指标 -->
    <div class="lsp-inline-metrics">
      <div class="lsp-inline-metric lsp-inline-metric--open">
        <span class="lsp-aux-label">未结台账</span>
        <span class="lsp-aux-val open">{{ openCount }}</span>
        <span class="lsp-aux-note">待处理 + 处理中 + 待发布</span>
      </div>
      <div class="lsp-inline-metric lsp-inline-metric--overdue">
        <span class="lsp-aux-label">逾期台账</span>
        <span class="lsp-aux-val overdue">{{ stats.overdue_count }}</span>
        <span class="lsp-aux-note">超过 7 天未闭环</span>
      </div>
      <div class="lsp-inline-metric lsp-inline-metric--converted">
        <span class="lsp-aux-label">任务转化</span>
        <span class="lsp-aux-val converted">{{ stats.converted_count }}<small> 笔</small></span>
        <span class="lsp-aux-note">占全部 {{ stats.conversion_rate }}%</span>
      </div>
      <div class="lsp-inline-metric lsp-inline-metric--avg">
        <span class="lsp-aux-label">平均处理时长</span>
        <span class="lsp-aux-val avg">{{ stats.avg_hours }}<small> 小时</small></span>
        <span class="lsp-aux-note">仅统计已完成台账</span>
      </div>
    </div>

    <!-- 详细统计折叠区 -->
    <el-collapse v-model="activeNames" class="lsp-collapse">
      <el-collapse-item name="detail">
        <template slot="title">
          <span class="lsp-collapse-title">详细统计</span>
        </template>
        <el-row :gutter="12">
          <el-col :xs="24" :sm="8">
            <div class="lsp-summary-card">
              <div class="lsp-summary-title">按客户（Top 20）</div>
              <el-table :data="stats.by_customer" size="mini" border max-height="280">
                <el-table-column label="客户名称" prop="customer_name" show-overflow-tooltip/>
                <el-table-column label="数量" prop="cnt" width="60" align="center"/>
              </el-table>
            </div>
          </el-col>
          <el-col :xs="24" :sm="8">
            <div class="lsp-summary-card">
              <div class="lsp-summary-title">按负责人（Top 20）</div>
              <el-table :data="stats.by_handler" size="mini" border max-height="280">
                <el-table-column label="负责人" prop="handler_name" show-overflow-tooltip/>
                <el-table-column label="数量" prop="cnt" width="60" align="center"/>
              </el-table>
            </div>
          </el-col>
          <el-col :xs="24" :sm="8">
            <div class="lsp-summary-card">
              <div class="lsp-summary-title">按问题分类</div>
              <el-table :data="stats.by_category" size="mini" border max-height="280">
                <el-table-column label="分类" prop="category_name" show-overflow-tooltip/>
                <el-table-column label="数量" prop="cnt" width="60" align="center"/>
              </el-table>
            </div>
          </el-col>
        </el-row>
      </el-collapse-item>
    </el-collapse>
  </div>
</template>

<script>
import { ledgerStatisticsAPI } from '@/api/ledger_extensions'

export default {
  name: 'LedgerStatisticsPanel',
  props: {
    startDate: { type: String, default: '' },
    endDate: { type: String, default: '' }
  },
  data() {
    return {
      loading: false,
      activeNames: [],
      stats: {
        total: 0, pending: 0, processing: 0, release_pending: 0,
        completed: 0, closed: 0, overdue_count: 0, avg_hours: 0,
        converted_count: 0, conversion_rate: 0,
        by_customer: [], by_handler: [], by_category: []
      },
      fetchTimer: null
    }
  },
  computed: {
    dateRangeKey() {
      return (this.startDate || '') + '|' + (this.endDate || '')
    },
    openCount() {
      return Number(this.stats.pending) + Number(this.stats.processing) + Number(this.stats.release_pending)
    }
  },
  watch: {
    dateRangeKey() {
      this.debouncedFetch()
    }
  },
  mounted() {
    this.fetchStats()
  },
  methods: {
    emitFilter(status) {
      this.$emit('filter', status)
    },
    debouncedFetch() {
      if (this.fetchTimer) clearTimeout(this.fetchTimer)
      this.fetchTimer = setTimeout(() => {
        this.fetchStats()
      }, 50)
    },
    async fetchStats() {
      this.loading = true
      try {
        const res = await ledgerStatisticsAPI({
          start_date: this.startDate || '',
          end_date: this.endDate || ''
        })
        const d = (res && res.data) || {}
        this.stats = {
          total: d.total || 0,
          pending: d.pending || 0,
          processing: d.processing || 0,
          release_pending: d.release_pending || 0,
          completed: d.completed || 0,
          closed: d.closed || 0,
          overdue_count: d.overdue_count || 0,
          avg_hours: d.avg_hours || 0,
          converted_count: d.converted_count || 0,
          conversion_rate: d.conversion_rate || 0,
          by_customer: d.by_customer || [],
          by_handler: d.by_handler || [],
          by_category: d.by_category || []
        }
      } catch (e) {
        // 统计失败不阻塞列表使用，记录到控制台便于排查
        if (console && console.warn) console.warn('台账统计加载失败', e)
      } finally {
        this.loading = false
      }
    },
    refresh() {
      this.fetchStats()
    }
  }
}
</script>

<style scoped>
.lsp-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
.lsp-main-cards { gap: 10px; }
.lsp-card { flex: 1 1 140px; min-width: 120px; background: #fff; border: 1px solid #ebeef5; border-radius: 6px; padding: 12px 8px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.lsp-card--click { cursor: pointer; transition: transform 0.15s, box-shadow 0.15s; }
.lsp-card--click:hover { transform: translateY(-2px); box-shadow: 0 3px 8px rgba(0,0,0,0.12); }
.lsp-num { font-size: 22px; font-weight: bold; color: #303133; }
.lsp-label { font-size: 12px; color: #909399; margin-top: 2px; }
.lsp-card.total .lsp-num { color: #409EFF; }
.lsp-card.pending .lsp-num { color: #F56C6C; }
.lsp-card.processing .lsp-num { color: #E6A23C; }
.lsp-card.release .lsp-num { color: #9b59b6; }
.lsp-card.completed .lsp-num { color: #67C23A; }
.lsp-card.closed .lsp-num { color: #909399; }

.lsp-inline-metrics { display: flex; flex-wrap: nowrap; gap: 10px; margin: 0 0 10px; overflow-x: auto; }
.lsp-inline-metric { position: relative; display: flex; flex: 1 0 210px; flex-direction: column; align-items: center; justify-content: center; min-width: 0; padding: 9px 12px 8px; overflow: hidden; background: #fff; border: 1px solid #ebeef5; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); white-space: nowrap; }
.lsp-inline-metric::before { position: absolute; top: 0; right: 0; left: 0; height: 3px; content: ''; }
.lsp-inline-metric--open::before { background: #E6A23C; }
.lsp-inline-metric--overdue::before { background: #F56C6C; }
.lsp-inline-metric--converted::before { background: #9b59b6; }
.lsp-inline-metric--avg::before { background: #409EFF; }
.lsp-aux-label { font-size: 12px; line-height: 18px; color: #606266; }
.lsp-aux-val { margin: 1px 0; font-size: 18px; font-weight: 700; line-height: 22px; white-space: nowrap; }
.lsp-aux-val small { font-size: 12px; font-weight: 400; }
.lsp-aux-note { overflow: hidden; max-width: 100%; color: #a4a9b2; font-size: 11px; line-height: 16px; text-overflow: ellipsis; }
.lsp-aux-val.open { color: #E6A23C; }
.lsp-aux-val.overdue { color: #F56C6C; }
.lsp-aux-val.converted { color: #9b59b6; }
.lsp-aux-val.avg { color: #409EFF; }

.lsp-collapse { margin-top: 4px; }
.lsp-collapse-title { font-size: 13px; font-weight: 600; color: #303133; }
.lsp-summary-card { background: #fff; border-radius: 6px; padding: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.lsp-summary-title { font-size: 13px; font-weight: 600; color: #303133; margin-bottom: 6px; padding-left: 6px; border-left: 3px solid #409EFF; }

</style>
