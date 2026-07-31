<template>
  <slide-view
    v-empty="!canShowDetail"
    :listener-ids="listenerIDs"
    :no-listener-ids="noListenerIDs"
    :no-listener-class="noListenerClass"
    :body-style="{padding: 0, height: '100%'}"
    xs-empty-icon="nopermission"
    xs-empty-text="暂无权限"
    @afterEnter="viewAfterEnter"
    @close="hideView">
    <div
      v-loading="loading"
      ref="crmDetailMain"
      class="detail-main">
      <flexbox
        v-if="canShowDetail && detailData"
        direction="column"
        align="stretch"
        class="d-container">
        <c-r-m-detail-head
          :detail="detailData"
          :head-details="headDetails"
          :id="id"
          :crm-type="crmType"
          :page-list="pageList"
          @pageChange="pageChange"
          @handle="detailHeadHandle"
          @close="hideView">
          <template slot="name">
            <span
              v-if="isAgentSigning(detailData)"
              class="signing-badge signing-badge--agent"
              @click.stop>
              <i class="wk wk-customer-solid" />
              代理签约
            </span>
            <el-tooltip
              v-if="isAgentSigning(detailData) && detailData.dealer_customer_id"
              :content="detailData.dealer_customer_name || ('客户#' + detailData.dealer_customer_id)"
              effect="dark"
              placement="top">
              <span
                class="signing-dealer-name"
                @click.stop="goDealerDetail(detailData.dealer_customer_id)">{{ detailData.dealer_customer_name || ('客户#' + detailData.dealer_customer_id) }}</span>
            </el-tooltip>
            <span
              v-if="!isAgentSigning(detailData)"
              class="signing-badge signing-badge--direct"
              @click.stop>
              <i class="wk wk-contract" />
              直签
            </span>
            <el-tooltip :content="detailData.star == 0 ? '添加关注' : '取消关注'" effect="dark" placement="top">
              <i
                :class="{active: detailData.star != 0}"
                class="wk wk-focus-on focus-icon"
                @click="toggleStar()" />
            </el-tooltip>
          </template>
        </c-r-m-detail-head>
        <div
          v-if="status.length > 0"
          class="busi-state-main">
          <flexbox
            :style="{'opacity' : detailData.is_end != 0 ? 1 : 1}"
            class="busi-state">
            <a
              v-for="(item, index) in status"
              :key="index">
              <el-popover
                :title="item.name"
                :content="'赢单率' + item.rate + '%'"
                placement="bottom"
                width="150"
                trigger="hover">
                <div
                  v-if="status.length -1 !=index"
                  slot="reference"
                  :class="item.class"
                  class="busi-state-item"
                  @click="handleStatuChange(item, index)">
                  {{ item.name }}
                  <div
                    v-if="index==0"
                    class="state-circle circle-left"/>
                  <div
                    v-if="index!=0"
                    class="state-arrow arrow-left"/>
                  <div class="state-arrow arrow-right"/>
                </div>
              </el-popover>

              <el-popover
                v-if="status.length -1 ==index"
                :title="item.type == 0 ? '' : item.title"
                :content="item.type == 0 ? '' : item.detail"
                placement="bottom"
                width="150"
                trigger="hover">
                <div
                  v-if="item.type == 0"
                  class="state-handel-cont">
                  <flexbox
                    v-for="(item, index) in statuHandleItems"
                    :key="index"
                    class="state-handel-item"
                    @click.native="handleStatuResult(item, index)">
                    <img
                      :src="item.img || item.thumb_img"
                      class="state-handel-item-img">
                    <div class="state-handel-item-name">{{ item.name }}</div>
                    <div class="state-handel-item-value">{{ item.value }}</div>
                  </flexbox>
                </div>
                <div
                  slot="reference"
                  :class="item.class"
                  class="busi-state-item">
                  <i
                    :class="item.overIcon"
                    style="margin-right:8px;"/>
                  {{ item.name }}
                  <div class="state-arrow arrow-left"/>
                  <div class="state-circle circle-right"/>
                </div>
              </el-popover>
            </a>
          </flexbox>
        </div>
        <div v-if="canShowDetail && detailData && isSuperAdmin && detailData.is_end == 0" class="biz-rollback-bar">
          <el-button size="mini" type="warning" plain @click="openRollbackDialog">阶段回退</el-button>
        </div>
        <flexbox class="d-container-bd" align="stretch">
          <el-tabs
            v-model="tabCurrentName"
            type="border-card"
            class="d-container-bd--left">
            <el-tab-pane
              v-for="(item, index) in tabNames"
              :key="index"
              :label="item.label"
              :name="item.name"
              lazy>
              <component
                :is="item.name"
                :detail="detailData"
                :type-list="logTyps"
                :id="id"
                :handle="activityHandle"
                :crm-type="crmType"
                :contacts-id.sync="firstContactsId"
                @handle="detailHeadHandle" />
            </el-tab-pane>
          </el-tabs>
          <transition name="slide-fade">
            <el-tabs
              v-show="showImportInfo"
              value="chiefly-contacts"
              type="border-card"
              class="d-container-bd--right">
              <el-tab-pane
                label="重要信息"
                name="chiefly-contacts"
                lazy>
                <chiefly-contacts
                  :contacts-id="firstContactsId"
                  :id="id"
                  :crm-type="crmType"/>
              </el-tab-pane>
            </el-tabs>
          </transition>
        </flexbox>
      </flexbox>
    </div>

    <el-button
      class="firse-button"
      @click="showImportInfo= !showImportInfo">重<br>要<br>信<br>息<br><i :class="{ 'is-reverse': !showImportInfo }" class="el-icon-arrow-right el-icon--right" /></el-button>
    <c-r-m-all-create
      v-if="isCreate"
      :action="{type: 'update', id: id, batchId: detailData.batchId}"
      :crm-type="crmType"
      @save-success="editSaveSuccess"
      @close="isCreate=false"/>

    <el-dialog :visible.sync="rollbackDialog" title="阶段回退" width="500px" append-to-body>
      <el-alert :closable="false" type="warning" show-icon class="rollback-alert" title="回退将处理该阶段之后产生的奖惩记录，请谨慎操作。" />
      <el-form label-width="100px" size="small" style="margin-top:12px">
        <el-form-item label="目标阶段">
          <el-select v-model="rollbackForm.target_status_id" placeholder="选择回退目标阶段" style="width:100%">
            <el-option v-for="s in rollbackStages" :key="s.status_id" :label="s.name" :value="s.status_id"/>
          </el-select>
        </el-form-item>
        <el-form-item label="回退原因">
          <el-input v-model="rollbackForm.reason" :rows="3" type="textarea" placeholder="请填写回退原因（必填）"/>
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="rollbackDialog=false">取消</el-button>
        <el-button :loading="rollbackLoading" type="warning" @click="submitRollback">确认回退</el-button>
      </span>
    </el-dialog>
  </slide-view>
</template>

<script>
import {
  crmBusinessReadAPI,
  crmBusinessAdvanceAPI,
  crmBusinessStageRollbackAPI,
  crmBusinessStatusByIdAPI
} from '@/api/crm/business'

import SlideView from '@/components/SlideView'
import CRMDetailHead from '../components/CRMDetailHead'
import Activity from '../components/Activity' // 活动
import ChieflyContacts from '../components/ChieflyContacts' // 首要联系人
import CRMEditBaseInfo from '../components/CRMEditBaseInfo' // 商机基本信息
import RelativeContract from '../components/RelativeContract' // 相关合同
import RelativeContacts from '../components/RelativeContacts' // 相关联系人
import RelativeHandle from '../components/RelativeHandle' // 相关操作
import RelativeTeam from '../components/RelativeTeam' // 团队成员
import RelativeProduct from '../components/RelativeProduct' // 团队成员
import RelativeFiles from '../components/RelativeFiles' // 相关附件
import RelativePrint from '../components/RelativePrint' // 相关打印
import RelativeFinance from '../components/RelativeFinance' // 收支

import CRMAllCreate from '../components/CRMAllCreate' // 新建页面
import DetailMixin from '../mixins/Detail'
import { separator } from '@/filters/vueNumeralFilter/filters'

export default {
  /** 客户管理 的 商机详情 */
  name: 'BusinessDetail',
  components: {
    SlideView,
    CRMDetailHead,
    Activity,
    ChieflyContacts,
    CRMEditBaseInfo,
    RelativeContract,
    RelativeContacts,
    RelativeHandle,
    RelativeTeam,
    RelativeProduct,
    RelativeFiles,
    RelativePrint,
    RelativeFinance,
    CRMAllCreate
  },
  mixins: [DetailMixin],
  props: {
    // 详情信息id
    id: [String, Number],
    // 监听的dom 进行隐藏详情
    listenerIDs: {
      type: Array,
      default: () => {
        return ['crm-main-container']
      }
    },
    // 不监听
    noListenerIDs: {
      type: Array,
      default: () => {
        return []
      }
    },
    noListenerClass: {
      type: Array,
      default: () => {
        return ['el-table__body']
      }
    }
  },
  data() {
    return {
      // 展示加载loading
      loading: false,
      crmType: 'business',
      headDetails: [
        { title: '客户名称', value: '' },
        { title: '商机金额（元）', value: '' },
        { title: '商机组', value: '' },
        { title: '负责人', value: '' },
        { title: '创建时间', value: '' }
      ],
      tabCurrentName: 'Activity',
      // 商机状态数据
      status: [],
      // 完结状态
      statuHandleItems: [
        {
          name: '赢单',
          type: 1,
          value: '100%',
          img: require('@/assets/img/check_suc.png')
        },
        {
          name: '输单',
          type: 2,
          value: '0%',
          img: require('@/assets/img/check_fail.png')
        },
        {
          name: '无效',
          type: 3,
          value: '0%',
          img: require('@/assets/img/check_cancel.png')
        }
      ],
      // 编辑操作
      isCreate: false,
      // 展示重要信息
      showImportInfo: true,
      // 首要联系人信息
      firstContactsId: '',
      // 阶段回退
      rollbackDialog: false,
      rollbackLoading: false,
      rollbackForm: { target_status_id: '', reason: '' }
    }
  },
  computed: {
    isSuperAdmin() {
      // 前端仅控制按钮可见性，后端 stageRollback 方法会再次校验 isSuperAdministrators
      const allAuth = this.$store.getters.allAuth || {}
      return !!(allAuth.manage || (allAuth.system && allAuth.system.manage) || (allAuth.admin && allAuth.admin.manage))
    },
    rollbackStages() {
      if (!this.status || !this.status.length) return []
      const currentIdx = this.status.findIndex(s => s.class && s.class.indexOf('state-suc') >= 0)
      return this.status.filter((s, i) => i < (currentIdx >= 0 ? currentIdx : this.status.length) && s.status_id)
    },
    financeRecordAuth() {
      const allAuth = this.$store.getters.allAuth || {}
      return (allAuth.finance && allAuth.finance.record) || {}
    },
    canFinanceRecord() {
      return !!this.financeRecordAuth.index
    },
    /**
     * 活动操作
     */
    activityHandle() {
      let temps = [
        {
          type: 'task',
          label: '创建任务'
        }, {
          type: 'contacts',
          label: '创建联系人'
        }, {
          type: 'contract',
          label: '创建合同'
        }, {
          type: 'receivables',
          label: '创建回款'
        }
      ]

      if (this.canCreateFollowRecord) {
        temps = [{
          type: 'log',
          label: '写跟进'
        }].concat(temps)
      }

      return temps
    },

    /**
     * tabs
     */
    tabNames() {
      var tempsTabs = []
      tempsTabs.push({ label: '活动', name: 'Activity' })
      if (this.crm.business && this.crm.business.read) {
        tempsTabs.push({ label: '详细资料', name: 'CRMEditBaseInfo' })
      }

      if (this.crm.contacts && this.crm.contacts.index) {
        tempsTabs.push({ label: this.getTabName('联系人', this.tabsNumber.contactCount), name: 'RelativeContacts' })
      }

      if (this.crm.contract && this.crm.contract.index) {
        tempsTabs.push({ label: this.getTabName('合同', this.tabsNumber.contractCount), name: 'RelativeContract' })
      }

      if (this.crm.product && this.crm.product.index) {
        tempsTabs.push({ label: this.getTabName('产品', this.tabsNumber.productCount), name: 'RelativeProduct' })
      }
      if (this.canFinanceRecord) {
        tempsTabs.push({ label: this.getTabName('收支', this.tabsNumber.financeCount), name: 'RelativeFinance' })
      }

      tempsTabs.push({ label: this.getTabName('团队成员', this.tabsNumber.memberCount), name: 'RelativeTeam' })
      tempsTabs.push({ label: this.getTabName('附件', this.tabsNumber.fileCount), name: 'RelativeFiles' })
      tempsTabs.push({ label: '操作记录', name: 'RelativeHandle' })
      if (this.crm.business && this.crm.business.print) {
        tempsTabs.push({ label: '打印记录', name: 'RelativePrint' })
      }
      return tempsTabs
    },

    /**
     * 根据记录筛选
     */
    logTyps() {
      return [{
        icon: 'all',
        color: '#2362FB',
        command: '',
        label: '全部活动'
      }, {
        icon: 'customer',
        color: '#487DFF',
        command: 2,
        label: '客户'
      }, {
        icon: 'o-task',
        color: '#D376FF',
        command: 11,
        label: '任务'
      }, {
        icon: 'business',
        color: '#FB9323',
        command: 5,
        label: '商机'
      }, {
        icon: 'contract',
        color: '#FD5B4A',
        command: 6,
        label: '合同'
      }, {
        icon: 'contacts',
        color: '#27BA4A',
        command: 3,
        label: '联系人'
      }, {
        icon: 'receivables',
        color: '#FFB940',
        command: 7,
        label: '回款'
      }, {
        icon: 'log',
        color: '#5864FF',
        command: 8,
        label: '日志'
      }, {
        icon: 'approve',
        color: '#9376FF',
        command: 9,
        label: '审批'
      }]
    }
  },
  mounted() {},
  methods: {
    isAgentSigning(row) {
      return Number(row && row.dealer_customer_id) > 0
    },
    goDealerDetail(customerId) {
      // 跳转客户详情（商机详情显示签约代理商名称并可跳转客户详情，不显示数字 ID）
      if (!customerId) return
      this.$router.push({ name: 'customer-detail', params: { id: customerId }}).catch(() => {})
    },
    openRollbackDialog() {
      this.rollbackForm = { target_status_id: '', reason: '' }
      this.rollbackDialog = true
    },
    submitRollback() {
      if (!this.rollbackForm.target_status_id) { this.$message.warning('请选择目标回退阶段'); return }
      if (!this.rollbackForm.reason) { this.$message.warning('请填写回退原因'); return }
      this.$confirm('确认回退到所选阶段？这将处理该阶段之后产生的奖惩记录。', '确认回退', {
        confirmButtonText: '确认回退',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.rollbackLoading = true
        crmBusinessStageRollbackAPI({
          business_id: this.id,
          target_status_id: this.rollbackForm.target_status_id,
          reason: this.rollbackForm.reason
        }).then(res => {
          this.$message.success((res.data && res.data.note) || '阶段回退成功')
          this.rollbackDialog = false
          this.getDetial()
        }).catch(() => {}).finally(() => { this.rollbackLoading = false })
      }).catch(() => {})
    },

    /**
     * 详情
     */
    getDetial() {
      this.firstContactsId = ''
      this.loading = true
      // this.getBusinessStatusById()
      crmBusinessReadAPI({
        id: this.id
      })
        .then(res => {
          this.loading = false
          this.detailData = res.data
          if (this.detailData.dataAuth === 0) return

          this.firstContactsId = this.detailData.contacts_id

          this.headDetails[0].value = res.data.customer_id_info.name

          const money = res.data.money || 0
          this.headDetails[1].value = money > 0 ? separator(money) : ''
          this.headDetails[2].value = res.data.type_id_info
          // // 负责人
          this.headDetails[3].value = res.data.owner_user_name
          this.headDetails[4].value = res.data.create_time

          this.handleBusinessStatus(res.data.is_end, res.data.status_id, res.data.status_list.status, res.data.statusRemark)
        })
        .catch(() => {
          this.loading = false
          this.hideView()
        })
    },

    /**
     * 状态信息
     */
    getBusinessStatusById() {
      this.loading = true
      crmBusinessStatusByIdAPI({
        business_id: this.id
      })
        .then(res => {
          this.loading = false
          const data = res.data[0]
          this.handleBusinessStatus(data.is_end, data.statusId, data.statusList, data.statusRemark)
        })
        .catch(() => {
          this.loading = false
        })
    },

    /**
     * 关闭
     */
    hideView() {
      this.$emit('hide-view')
    },

    /**
     * 处理商机状态数据
     */
    handleBusinessStatus(is_end, statusId, statusList, statusRemark) {
      this.status = []
      if (statusList && statusList.length > 0) {
        const isdoing = is_end == 0
        let isdoingIndex = -1
        for (let index = 0; index < statusList.length; index++) {
          const item = statusList[index]
          if (isdoing) {
            if (item.status_id == statusId) {
              isdoingIndex = index
              item['class'] = 'state-suc'
            } else {
              item['class'] = isdoingIndex >= 0 ? 'state-undo' : 'state-suc'
            }
          } else {
            if (item.status_id == statusId) {
              isdoingIndex = index
              item['class'] = 'state-suc'
            } else {
              item['class'] = isdoingIndex >= 0 ? 'state-invalid' : 'state-suc'
            }
          }
          this.status.push(item)
        }

        const overItem = { type: is_end }
        if (is_end == 0) {
          overItem.name = '结束'
          overItem['overIcon'] = ['el-icon-arrow-down', 'el-icon--right']
          if (isdoingIndex == statusList.length - 1) {
            overItem['class'] = 'state-doing'
          } else {
            if (this.status.length > 0 && statusId != 0) {
              // 有推进状态 才会有下一阶段
              this.status[isdoingIndex + 1].class = 'state-doing'
            }
            overItem['class'] = 'state-undo'
          }
        } else if (is_end == 1) {
          overItem.name = '赢单'
          overItem.title = '赢单' // 详情标题 和 内容
          overItem.detail = '赢单率100%'
          overItem['overIcon'] = ['el-icon-check', 'el-icon--right']
          overItem['class'] = 'state-suc'
        } else if (is_end == 2) {
          overItem.name = '输单'
          overItem.title = '赢单率0%'
          overItem.detail = statusRemark
          overItem['overIcon'] = ['el-icon-circle-close', 'el-icon--right']
          overItem['class'] = 'state-fail'
        } else if (is_end == 3) {
          overItem.name = '无效'
          overItem.title = '赢单率0%'
          overItem.detail = statusRemark
          overItem['overIcon'] = ['el-icon-remove-outline', 'el-icon--right']
          overItem['class'] = 'state-invalid'
        }
        this.status.push(overItem)
      }
    },

    /**
     * 操作状态改变
     */
    handleStatuChange(item, index) {
      if (this.detailData.is_end != 0) {
        // 非完结状态下 可推进
        return
      }
      if (!item.isdoing) {
        var message = '确定进入' + item.name + '阶段'
        this.$confirm(message, '提示', {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        })
          .then(() => {
            this.loading = true
            crmBusinessAdvanceAPI({
              business_id: this.id,
              status_id: item.status_id
            })
              .then(res => {
                this.loading = false
                this.$message.success('操作成功')
                this.getDetial()
              })
              .catch(() => {
                this.loading = false
              })
          })
          .catch(() => {})
      }
    },

    /**
     * 完结状态结果
     */
    handleStatuResult(item, index) {
      if (this.detailData.is_end != 0) {
        // 非完结状态下 可推进
        return
      }
      /** 输单 和 无效 */
      if (item.type == 2 || item.type == 3) {
        var message = '请填写' + item.name + '原因：'
        var title = item.name + '原因'
        this.$prompt(message, title, {
          inputType: 'text',
          confirmButtonText: '确定',
          cancelButtonText: '取消'
        })
          .then(({ value }) => {
            this.loading = true
            crmBusinessAdvanceAPI({
              business_id: this.id,
              status_id: item.status_id,
              is_end: item.type,
              statusRemark: value
            })
              .then(res => {
                this.loading = false
                this.$message.success('操作成功')
                this.getDetial()
              })
              .catch(() => {
                this.loading = false
              })
          })
          .catch(() => {})
      } else {
        this.$confirm('确定将当前商机设为' + item.name + '吗?', '提示', {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        })
          .then(() => {
            this.loading = true
            crmBusinessAdvanceAPI({
              business_id: this.id,
              status_id: item.status_id,
              is_end: item.type
            })
              .then(res => {
                this.loading = false
                this.$message.success('操作成功')
                this.getDetial()
              })
              .catch(() => {
                this.loading = false
              })
          })
          .catch(() => {})
      }
    }

    // /**
    //  * 编辑成功
    //  */
    // editSaveSuccess() {
    //   this.$emit('handle', { type: 'save-success' })
    //   this.getDetial()
    // }
  }
}
</script>

<style lang="scss" scoped>
@import '../styles/crmdetail.scss';
.busi-state-main {
  margin-top: 15px;
  padding: 15px;
  border-top: 1px solid $xr-border-line-color;
  border-bottom: 1px solid $xr-border-line-color;
  background-color: white;
}

.busi-state {
  position: relative;
  padding-left: 20px;
  overflow-x: auto;
  overflow-y: hidden;
  z-index: 1;
  a {
    flex-shrink: 0;
  }
}

.busi-state-item {
  padding: 10px 30px;
  margin: 5px 0;
  height: 38px;
  position: relative;
  margin-right: 5px;
  border-top: 2px solid #ebebeb;
  border-bottom: 2px solid #ebebeb;
  .state-circle {
    width: 38px;
    height: 38px;
    border-radius: 19px;
    background-color: white;
    transform: rotate(45deg);
    position: absolute;
    z-index: -1;
  }
  .state-arrow {
    width: 38px;
    height: 38px;
    transform: scale(0.707) rotate(45deg);
    border-top: 2px solid #ebebeb;
    border-right: 2px solid #ebebeb;
    background-color: white;
    position: absolute;
  }
}

.state-undo {
  border-top: 2px solid #ebebeb;
  border-bottom: 2px solid #ebebeb;
  background-color: white;
  color: #666;
  .state-circle {
    border-color: #ebebeb;
    background-color: white;
  }
  .state-arrow {
    border-color: #ebebeb;
    background-color: white;
  }
}

.state-doing {
  border-top: 2px solid #00CA9D;
  border-bottom: 2px solid #00CA9D;
  background-color: white;
  color: #00CA9D;
  .circle-left {
    border-color: #00CA9D;
    background-color: white;
  }
  .circle-right {
    border-color: #00CA9D;
    background-color: white;
  }
  .arrow-left {
    border-color: #00CA9D;
    background-color: white;
  }
  .arrow-right {
    border-color: #00CA9D;
    background-color: white;
  }
}

.state-suc {
  border-top: 2px solid #00CA9D;
  border-bottom: 2px solid #00CA9D;
  background-color: #00CA9D;
  color: white;
  .circle-left {
    border-color: #00CA9D;
    background-color: #00CA9D;
  }
  .circle-right {
    border-color: #00CA9D;
    background-color: #00CA9D;
  }
  .arrow-left {
    border-color: #00CA9D;
    background-color: white;
  }
  .arrow-right {
    border-color: #00CA9D;
    background-color: #00CA9D;
  }
}

.state-fail {
  border-top: 2px solid #ff6767;
  border-bottom: 2px solid #ff6767;
  background-color: #ff6767;
  color: white;
  .circle-left {
    border-color: #ff6767;
    background-color: #ff6767;
  }
  .circle-right {
    border-color: #ff6767;
    background-color: #ff6767;
  }
  .arrow-left {
    border-color: #ff6767;
    background-color: white;
  }
  .arrow-right {
    border-color: #ff6767;
    background-color: #ff6767;
  }
}

.state-invalid {
  border-top: 2px solid #ebebeb;
  border-bottom: 2px solid #ebebeb;
  background-color: #ebebeb;
  color: #666;
  .state-circle {
    border-color: #ebebeb;
    background-color: #ebebeb;
  }
  .state-arrow {
    border-color: #ebebeb;
    background-color: #ebebeb;
  }

  .circle-left {
    border-color: #ebebeb;
    background-color: #ebebeb;
  }
  .circle-right {
    border-color: #ebebeb;
    background-color: #ebebeb;
  }
  .arrow-left {
    border-color: #ebebeb;
    background-color: white;
  }
  .arrow-right {
    border-color: #ebebeb;
    background-color: #ebebeb;
  }
}

.arrow-right {
  z-index: 1;
  top: -2px;
  right: -19px;
}
.arrow-left {
  top: -2px;
  left: -18px;
}
.circle-right {
  border-top: 2px solid #ebebeb;
  border-right: 2px solid #ebebeb;
  z-index: 1;
  top: -2px;
  right: -15.5px;
}
.circle-left {
  border-left: 2px solid #ebebeb;
  border-bottom: 2px solid #ebebeb;
  top: -2px;
  left: -17px;
}

/** 状态操作布局 */
.state-handel-cont {
  font-size: 13px;
  color: #333;
  .state-handel-item {
    padding: 10px 0;
    cursor: pointer;
    .state-handel-item-img {
      width: 16px;
      height: 16px;
      border-radius: 8px;
      margin-right: 8px;
      flex-shrink: 0;
      display: block;
    }
    .state-handel-item-name {
      flex: 1;
    }
    .state-handel-item-value {
      flex-shrink: 0;
    }
  }
  .state-handel-item:hover {
    background-color: #f7f8fa;
  }
}

.biz-rollback-bar {
  padding: 4px 15px;
  text-align: right;
}
.signing-badge {
  display: inline-flex;
  align-items: center;
  height: 22px;
  padding: 0 7px;
  margin-left: 8px;
  border-radius: 3px;
  font-size: 12px;
  line-height: 1;
  white-space: nowrap;
  flex-shrink: 0;
  i {
    margin-right: 3px;
    font-size: 12px;
  }
}
.signing-badge--agent {
  color: #F59A23;
  background: #FDF6EC;
  border: 1px solid #F5DAB1;
}
.signing-badge--direct {
  color: #00A870;
  background: #E8F8F2;
  border: 1px solid #B7E8D5;
}
.signing-dealer-name {
  margin-left: 6px;
  font-size: 12px;
  color: #606266;
  cursor: pointer;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex-shrink: 1;
}
.signing-dealer-name:hover {
  color: #2362FB;
}
.rollback-alert {
  margin-bottom: 8px;
}
</style>
