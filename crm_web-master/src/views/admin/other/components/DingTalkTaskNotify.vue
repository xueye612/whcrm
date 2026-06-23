<template>
  <div class="dingtalk-task-notify">
    <div class="dingtalk-form">
      <div class="form-title">钉钉任务通知</div>
      <el-form :model="form" label-width="120px" class="notify-form">
        <el-form-item label="Webhook URL">
          <el-input v-model.trim="form.webhook_url" placeholder="请输入钉钉群机器人 Webhook URL" />
        </el-form-item>
        <el-form-item label="Secret">
          <el-input v-model.trim="form.secret" placeholder="可选：机器人加签 Secret" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSave">保存</el-button>
          <el-button type="text" @click="handleReload">刷新</el-button>
        </el-form-item>
      </el-form>
      <div class="form-tips">
        <p>触发点：任务创建、负责人变更、状态变更。</p>
        <p>如果启用加签，请在钉钉机器人配置中同步 Secret。</p>
      </div>
    </div>
  </div>
</template>

<script>
import { dingtalkTaskNotifyReadAPI, dingtalkTaskNotifySaveAPI } from '@/api/admin/other'

export default {
  name: 'DingTalkTaskNotify',
  data() {
    return {
      form: {
        webhook_url: '',
        secret: ''
      }
    }
  },
  created() {
    this.handleReload()
  },
  methods: {
    handleReload() {
      dingtalkTaskNotifyReadAPI().then(res => {
        const data = res.data || {}
        this.form.webhook_url = data.webhook_url || ''
        this.form.secret = data.secret || ''
      })
    },
    handleSave() {
      dingtalkTaskNotifySaveAPI({
        webhook_url: this.form.webhook_url,
        secret: this.form.secret
      }).then(() => {
        this.$message.success('保存成功')
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.dingtalk-task-notify {
  padding: 20px;
}
.dingtalk-form {
  max-width: 720px;
}
.form-title {
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 16px;
}
.form-tips {
  color: #909399;
  font-size: 12px;
  margin-top: 8px;
}
</style>
