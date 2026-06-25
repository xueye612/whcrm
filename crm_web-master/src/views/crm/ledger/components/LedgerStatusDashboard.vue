<template>
  <div class="dashboard">
    <div class="dashboard-card total" @click="$emit('filter', '')">
      <div class="card-icon"><i class="el-icon-s-data"/></div>
      <div class="card-info">
        <div class="card-label">总台账</div>
        <div class="card-num">{{ stats.total }}</div>
      </div>
    </div>
    <div class="dashboard-card pending" @click="$emit('filter', '待处理')">
      <div class="card-icon"><i class="el-icon-bell"/></div>
      <div class="card-info">
        <div class="card-label">待处理</div>
        <div class="card-num">{{ stats.pending }}</div>
      </div>
    </div>
    <div class="dashboard-card processing" @click="$emit('filter', '处理中')">
      <div class="card-icon"><i class="el-icon-service"/></div>
      <div class="card-info">
        <div class="card-label">处理中</div>
        <div class="card-num">{{ stats.processing }}</div>
      </div>
    </div>
    <div class="dashboard-card release-pending" @click="$emit('filter', '待发布')">
      <div class="card-icon"><i class="el-icon-upload2"/></div>
      <div class="card-info">
        <div class="card-label">待发布</div>
        <div class="card-num">{{ stats.releasePending }}</div>
      </div>
    </div>
    <div class="dashboard-card completed" @click="$emit('filter', '已完成')">
      <div class="card-icon"><i class="el-icon-success"/></div>
      <div class="card-info">
        <div class="card-label">已完成</div>
        <div class="card-num">{{ stats.completed }}</div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LedgerStatusDashboard',
  props: {
    stats: {
      type: Object,
      default: () => ({
        total: 0,
        pending: 0,
        processing: 0,
        releasePending: 0,
        completed: 0
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.dashboard {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.dashboard-card {
  flex: 1;
  min-width: 200px;
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  display: flex;
  align-items: center;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s, box-shadow 0.2s;
  cursor: pointer;
}

.dashboard-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.card-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin-right: 16px;
}

.card-info {
  flex: 1;
}

.card-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 4px;
}

.card-num {
  font-size: 24px;
  font-weight: bold;
  color: #303133;
}

.dashboard-card.total .card-icon { background: #ecf5ff; color: #409EFF; }
.dashboard-card.pending .card-icon { background: #fef0f0; color: #F56C6C; }
.dashboard-card.processing .card-icon { background: #fdf6ec; color: #E6A23C; }
.dashboard-card.release-pending .card-icon { background: #f4ecf7; color: #9b59b6; }
.dashboard-card.completed .card-icon { background: #f0f9eb; color: #67C23A; }
</style>
