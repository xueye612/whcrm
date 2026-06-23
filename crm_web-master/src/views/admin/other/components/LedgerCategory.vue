<template>
  <div class="ledger-category">
    <div class="section-header">
      <span class="title">台账问题分类</span>
      <el-button type="primary" size="mini" @click="saveCategories">保存</el-button>
    </div>
    <div class="section-body">
      <div class="tag-list">
        <el-tag
          v-for="(item, index) in categories"
          :key="item + index"
          closable
          @close="removeCategory(index)">
          {{ item }}
        </el-tag>
      </div>
      <div class="tag-input">
        <el-input
          v-model.trim="newCategory"
          size="small"
          placeholder="输入分类名称"
          @keyup.enter.native="addCategory" />
        <el-button size="small" @click="addCategory">添加</el-button>
      </div>
      <div class="tip">可在这里维护台账问题分类，台账页面会自动同步。</div>
    </div>
  </div>
</template>

<script>
import { ledgerCategoryListAPI, ledgerCategorySaveAPI } from '@/api/admin/other'

export default {
  name: 'LedgerCategory',
  data() {
    return {
      loading: false,
      categories: [],
      newCategory: ''
    }
  },
  created() {
    this.fetchCategories()
  },
  methods: {
    fetchCategories() {
      this.loading = true
      ledgerCategoryListAPI()
        .then(res => {
          this.categories = (res.data || []).filter(item => item && String(item).trim() !== '')
        })
        .finally(() => {
          this.loading = false
        })
    },
    addCategory() {
      const value = (this.newCategory || '').trim()
      if (!value) return
      if (this.categories.includes(value)) {
        this.$message.warning('分类已存在')
        return
      }
      this.categories.push(value)
      this.newCategory = ''
    },
    removeCategory(index) {
      this.categories.splice(index, 1)
    },
    saveCategories() {
      this.loading = true
      ledgerCategorySaveAPI({ value: this.categories })
        .then(() => {
          this.$message.success('保存成功')
          this.fetchCategories()
        })
        .finally(() => {
          this.loading = false
        })
    }
  }
}
</script>

<style lang="scss" scoped>
.ledger-category {
  padding: 16px;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;

  .title {
    font-size: 14px;
    font-weight: 600;
    color: #333;
  }
}

.section-body {
  .tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
  }

  .tag-input {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
  }

  .tip {
    color: #909399;
    font-size: 12px;
  }
}
</style>
