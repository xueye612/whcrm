<template>
  <flexbox v-loading="loading" :style="{height: contentHeight + 'px'}" class="calendar-box">
    <el-button type="warning" icon="el-icon-plus" @click="createEvents">新建日程</el-button>
    <div class="box-left">
      <div class="left-title" >
        <img width="20px" src="@/assets/img/system/app/ce_index.png" alt="">
        <xh-user-cell
          v-if="showUser"
          ref="xhuserCell"
          :value="checkedUser"
          :info-request="subUserListIndex"
          :radio="true"
          v-bind="$attrs"
          class="left-user"
          placement="bottom-start"
          @value-change="selectUser">
          <flexbox slot="reference" class="user-box">
            <span class="username">{{ checkedUser[0]?checkedUser[0].realname + '的日程': '我的日程' }}</span>
            <span :class="{ 'is-reverse' : $refs.xhuserCell && $refs.xhuserCell.showPopover }" class="el-icon-arrow-up icon"/>
          </flexbox>
        </xh-user-cell>
        <span v-else class="username">我的日历</span>
      </div>
      <div class="left-scroll">
        <el-checkbox-group v-model="checkCusList" >
          <schedule
            v-loading="scheduleLoading"
            ref="schedule"
            :list-data-type="listDataType"
            :active-time="activeTime"
            :calendar-arr="calendarArr"
            @choseDay="gotoPast"
            @changeMonth="changeMonth"/>
          <div class="left-main">
            <div class="main-title" @click="showSys = !showSys">
              <img src="@/assets/img/calendar_sys.png" alt="" width="20px">
              <span class="main-text">系统类型</span>
              <span :class="{ 'is-reverse' : showSys }" class="el-icon-arrow-up icon"/>
            </div>
            <div v-show="showGroup && showSys">

              <el-checkbox
                v-for="item in cusCheck"
                v-if="item.type === 1"
                :checked="item.is_select?true:false"
                :class="item.class"
                :label="item.name"
                :key="item.name"/>

            </div>
          </div>
          <div class="left-bottom">
            <div class="bottom-title" @click="showCus = !showCus">
              <img src="@/assets/img/calendar_cus.png" alt="" width="20px">
              <span class="main-text">自定义类型</span>
              <span :class="{ 'is-reverse' : showCus }" class="el-icon-arrow-up icon"/>
            </div>
            <div v-show="showGroup && showCus">
              <el-checkbox
                v-for="item in cusCheck"
                v-if="item.type === 2"
                :class="item.class"
                :checked="item.is_select?true:false"
                :label="item.name"
                :key="item.type_id+item.name"/>
            </div>
          </div>
        </el-checkbox-group>
      </div>
      <div class="left-bottom-text">
        <i class="el-icon-warning"/>
        <span class="text-span">自定义类型可在后台配置</span>
      </div>
    </div>
    <div class="box-right">
      <FullCalendar
        ref="fullCalendar"
        :button-text="buttonText"
        :header="{
          left: 'listDay,timeGridWeek,dayGridMonth, today',
          center: 'prevYear,prev, title, next,nextYear',
          right: ''
        }"
        :plugins="calendarPlugins"
        :weekends="calendarWeekends"
        :first-day="firstDay"
        :event-time-format="evnetTime"
        :all-day-slot="true"
        :event-limit="true"
        :events="calendarEvents"
        :event-limit-text="eventLimiTtext"
        :now-indicator="true"
        :display-event-end="false"
        :slot-label-format="{ // 周，日视图时，左侧的显示的时间格式
          hour: 'numeric',
          minute: '2-digit',
          omitZeroMinute: false,
          meridiem: 'short',
          hour12: false
        }"
        :column-format="{day: 'dddd M/d'}"
        :list-day-format="listDayFormat"
        all-day-text="全天"
        no-events-message="暂无日程"
        locale="zh-cn"
        class="calendar-main"
        week-number-calculation="ISO"
        default-view="dayGridMonth"
        @eventClick="eventClick"
        @datesRender="datesRender"
        @dateClick="handleDateClick"
      />
    </div>
    <create-event
      :show-create="showCreate"
      :select-div="selectDiv"
      :color-list="colorList"
      :cus-check="cusCheck"
      @createSuccess="createSuccess"
      @close="showCreate = false"/>
    <!-- 今日需办详情 -->
    <today-list-detail
      :id="eventId"
      :show-today-detail="showTodayDetail"
      :cus-check="cusCheck"
      :today-detail-data="todayDetailData"
      @deleteSuccess="handleSuccess"
      @createSuccess="handleSuccess"
      @close="showTodayDetail = false"/>

    <c-r-m-full-screen-detail
      :visible.sync="showFullDetail"
      :crm-type="relationCrmType"
      :id="relationID" />

    <el-dialog :visible.sync="ledgerDetailVisible" title="台账详情" width="680px">
      <div v-loading="ledgerLoading" class="ledger-detail">
        <div class="ledger-row">
          <span class="ledger-label">ID</span>
          <span class="ledger-value">{{ ledgerDetail.ledger_id }}</span>
        </div>
        <div class="ledger-row">
          <span class="ledger-label">客户</span>
          <span class="ledger-value">{{ ledgerDetail.customer_name }}</span>
        </div>
        <div class="ledger-row">
          <span class="ledger-label">反馈问题</span>
          <span class="ledger-value">{{ ledgerDetail.title }}</span>
        </div>
        <div class="ledger-row">
          <span class="ledger-label">问题分类</span>
          <span class="ledger-value">{{ ledgerDetail.category }}</span>
        </div>
        <div class="ledger-row">
          <span class="ledger-label">处理状态</span>
          <span class="ledger-value">{{ ledgerDetail.status }}</span>
        </div>
        <div class="ledger-row">
          <span class="ledger-label">反馈人</span>
          <span class="ledger-value">{{ ledgerDetail.feedback_user }}</span>
        </div>
        <div class="ledger-row">
          <span class="ledger-label">反馈渠道</span>
          <span class="ledger-value">{{ ledgerDetail.feedback_channel || '微信' }}</span>
        </div>
        <div class="ledger-row">
          <span class="ledger-label">反馈时间</span>
          <span class="ledger-value">{{ ledgerDetail.feedback_time || ledgerDetail.register_time }}</span>
        </div>
        <div class="ledger-row">
          <span class="ledger-label">登记时间</span>
          <span class="ledger-value">{{ ledgerDetail.register_time }}</span>
        </div>
        <div class="ledger-row">
          <span class="ledger-label">完成时间</span>
          <span class="ledger-value">{{ ledgerDetail.finish_time }}</span>
        </div>
        <div class="ledger-row">
          <span class="ledger-label">登记人</span>
          <span class="ledger-value">{{ ledgerDetail.register_user_name }}</span>
        </div>
        <div class="ledger-row">
          <span class="ledger-label">处理人</span>
          <span class="ledger-value">{{ ledgerDetail.handler_user_name }}</span>
        </div>
        <div class="ledger-row ledger-row--wide">
          <span class="ledger-label">问题描述</span>
          <span v-if="!ledgerDetail.description" class="ledger-value">—</span>
          <div v-else class="ledger-value ledger-rich-text" v-html="ledgerDetail.description"/>
        </div>
        <div class="ledger-row ledger-row--wide">
          <span class="ledger-label">备注</span>
          <span class="ledger-value">{{ ledgerDetail.remark }}</span>
        </div>
      </div>
      <div slot="footer" class="dialog-footer">
        <el-button @click="ledgerDetailVisible=false">关闭</el-button>
      </div>
    </el-dialog>
  </flexbox>
</template>

<script>
import FullCalendar from '@fullcalendar/vue'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import timelinePlugin from '@fullcalendar/timeline'
import listPlugin from '@fullcalendar/list'
import Schedule from './Schedule'
import TodayListDetail from './components/TodayListDetail'
import calendarColor from '@/views/admin/other/components/calendarColor.js'
import XhUserCell from '@/components/CreateCom/XhUserCell'
import {
  canlendarQueryListAPI,
  canlendarQueryTypeListAPI,
  canlendarEventCrmAPI,
  canlendarEventTaskAPI,
  canlendarUpdateTypeAPI
} from '@/api/calendar'
import { systemUserQueryAuthUserList } from '@/api/calendar'
import { ledgerReadAPI } from '@/api/ledger/ledger'
import moment from 'moment'
// import { getMaxIndex } from '@/utils'
import CreateEvent from './components/CreateEvent'
// must manually include stylesheets for each plugin
import '@fullcalendar/core/main.css'
import '@fullcalendar/daygrid/main.css'
import '@fullcalendar/timegrid/main.css'
import '@fullcalendar/list/main.css'
export default {
  components: {
    FullCalendar, // make the <FullCalendar> tag available
    Schedule,
    CreateEvent,
    TodayListDetail,
    XhUserCell,
    CRMFullScreenDetail: () =>
      import('@/components/CRMFullScreenDetail')
  },
  data() {
    return {
      loading: false,
      eventId: '',
      contentHeight: document.documentElement.clientHeight - 80,
      // 浣犻渶瑕佺敤鍒扮殑鎻掍欢
      calendarPlugins: [
        // plugins must be defined in the JS
        dayGridPlugin,
        timeGridPlugin,
        timelinePlugin,
        listPlugin,
        interactionPlugin // needed for dateClick
      ],
      // 鏄惁灞曠ず鍛ㄥ叚鍛ㄦ棩
      calendarWeekends: true,
      // 榛樿閫変腑褰撳ぉ
      calendarEvents: [],
      // 瀛樺偍鎵€鏈変簨浠讹紝鏂逛究绛涢€?
      calendarList: [],
      colorList: calendarColor.colorList,
      // 鎸夐挳鏂囧瓧
      buttonText: {
        month: '月',
        week: '周',
        day: '日',
        today: '今天'
      },
      evnetTime: {
        hour: 'numeric',
        minute: '2-digit',
        hour12: false
      },
      firstDay: 1, // 鎶婃瘡鍛ㄨ缃负浠庡懆涓€寮€濮?
      scheduleLoading: false,
      calendarArr: [],
      // 棣栨閫変腑涓嶈繘琛岃烦杞棩鍒楄〃鎿嶄綔
      isFirstToDay: true,
      // 瑙嗗浘褰撳墠鎵€灞曠ず鐨勬椂闂达細 淇濊瘉鏈堬紝骞村垏鎹㈡椂锛岃鍥炬洿鏂板紩璧风殑鎶ラ敊
      currentTime: '',
      // 鍌ㄥ瓨褰撳墠娲诲姩鐨勬椂闂达紝淇濊瘉鏈堜唤鍒囨崲鏃讹紝灏忔棩鍘嗚窡闅忓垏鎹?
      currentActiveTime: '',
      checkSysList: [
      ],
      sysCheck: [
        { label: '分配的任务' },
        { label: '需联系的客户' },
        { label: '即将到期的合同' },
        { label: '计划回款' }
      ],
      checkCusList: [],
      dayEventList: [],
      cusCheck: [],
      showGroup: false,
      showCreate: false,
      choseTitle: '',
      showTodayDetail: false,
      todayDetailData: {},
      selectDiv: null,
      typeIds: [],
      // 鍌ㄥ瓨鏄剧ず鏃ユ湡鐨勫紑濮嬫椂闂村拰缁撴潫鏃堕棿
      activeTime: {},
      listDataType: '',
      // 浠婃棩鏄剧ず鐨勮仈绯?
      todaySchedule: [],
      // 鐩稿叧鐨勭郴缁熻仈绯诲瓧娈?
      needData: {
        leadsTimeList: [],
        customerTimeList: [],
        endContractTimeList: [],
        receiveContractTimeList: [],
        businessTimeList: [],
        dealBusinessTimeList: [],
        ledgerList: []
      },
      ledgerDetailVisible: false,
      ledgerDetail: {},
      ledgerLoading: false,
      checkedUser: [],
      // 鐢ㄤ簬鍕鹃€変粎鍓╀竴涓椂锛屾殏鏃朵繚瀛樺嬀閫夋暟鎹?
      copyCheckCusList: [],
      showUser: true,
      showpover: false,
      showSys: true,
      showCus: true,
      taskList: [],
      showFullDetail: false,
      relationCrmType: 'task',
      relationID: '',
      selectSysList: [],
      sysTypeId: [],
      firstEnter: true
    }
  },
  computed: {
    subUserListIndex() {
      return systemUserQueryAuthUserList
    },
    showUserPover() {
      return this.$refs.xhuserCell && this.$refs.xhuserCell.showPopover
    }
  },
  watch: {
    checkCusList: {
      handler(val, oldval) {
        if (val.length === 1) {
          this.copyCheckCusList = val
          this.customFifter(val)
        } else if (val.length === 0) {
          if (this.copyCheckCusList.length === 1) {
            this.checkCusList = this.copyCheckCusList
            this.$message.error('请至少选中一个类型')
          }
        } else {
          this.customFifter(val)
        }
      },
      deep: true,
      immediate: true
    }

  },
  mounted() {
    window.onresize = () => {
      this.contentHeight = document.documentElement.clientHeight - 80
    }
    this.showUserSelect()
    this.addBus()
  },

  destroyed() {
    this.$bus.off('handleSuccess')
  },
  /**
   * 璺敱杩涘叆鍓嶅垏鎹㈤《閮ㄥ鑸潯
   */
  beforeRouteEnter(to, from, next) {
    next(
      vm => {
        vm.$store.commit('SET_NAVACTIVEINDEX', to.fullPath)
      }
    )
  },

  /**
   *  璺敱鏇存柊
   */
  beforeRouteLeave(to, from, next) {
    // this.updateList()
    next()
  },

  methods: {

    /**
     * 娣诲姞鐩戝惉浜嬩欢
     */
    addBus() {
      this.$bus.on('handleSuccess', () => {
        this.getCusCheck()
      })
    },

    /**
     * 鏌ヨ鍒楄〃
     */
    getList() {
      this.loading = true
      this.activeTime.typeIds = null
      this.$refs.schedule.getDateList({
        user_id: this.activeTime.userId,
        end_time: this.activeTime.endTime / 1000,
        star_time: this.activeTime.startTime / 1000
      })
      canlendarQueryListAPI({
        user_id: this.activeTime.userId,
        end_time: this.activeTime.endTime / 1000,
        star_time: this.activeTime.startTime / 1000
      }).then(res => {
        this.calendarEvents = []
        this.dayEventList = res.data
        this.handleShowData()
        this.loading = false
      }).catch(() => {
        this.loading = false
      })
    },

    /**
     * 鏌ヨ鑷畾涔夋棩绋嬬被鍨?
     */
    getCusCheck() {
      this.loading = true
      this.typeIds = []
      this.checkSysList = []
      this.calendarEvents = []
      // this.checkCusList = []
      this.showGroup = false
      this.sysTypeId = []
      const crmTypeObj = {
        '1': 'task',
        '2': 'customer',
        '3': 'contract',
        '4': 'receiveContract', // 璁″垝鍥炴
        '5': 'leads',
        '6': 'business',
        '7': 'dealBusiness', // 棰勮鎴愪氦鍟嗘満
        '8': 'ledger' // 瀹㈡埛鍙拌处
      }
      canlendarQueryTypeListAPI({
        user_id: this.activeTime.userId
      }).then(res => {
        const rawList = (res.data && res.data.list) ? res.data.list : []
        const hasCustomerLedger = rawList.some(item => item.name === '客户台账' || item.name === '台账')
        const cusCheck = rawList.filter(item => {
          if (item.name === '台账' && hasCustomerLedger) return false
          return true
        })
        // .map(item => {
        //   if (item.is_select) {
        //     item.select = true
        //   } else {
        //     item.select = false
        //   }
        //   return item
        // }) || []
        this.todaySchedule = []
        cusCheck.forEach(item => {
          if (item.is_select) {
            this.typeIds.push(item.schedule_id)
          }
          if (item.type === 1) {
            this.sysTypeId.push(
              { typeId: item.schedule_id, name: item.name, crmType: crmTypeObj[item.color] }
            )

            if (item.color === '1') {
              item.class = 'color_8'
            } else if (item.color === '2') {
              item.class = 'color_1'
            } else if (item.color === '3') {
              item.class = 'color_5'
            } else if (item.color === '4') {
              item.class = 'color_11'
            } else if (item.color === '5') {
              item.class = 'color_3'
            } else if (item.color === '6') {
              item.class = 'color_6'
            } else if (item.color === '7') {
              item.class = 'color_7'
            } else if (item.color === '8') {
              item.class = 'color_9'
            }
          } else {
            this.colorList.forEach((color, index) => {
              if (item.color === color) {
                item.class = `color_${index + 1}`
                item.color = color
              }
            })
          }
        })
        this.activeTime.typeIds = this.typeIds
        this.cusCheck = cusCheck
        this.getTodayTypeList()
      }).catch(() => {
        this.loading = false
      })
    },

    /**
     * 缂栬緫宸︿晶澶氶€夋鍒楄〃
     */
    updateList() {
      // 鍙敤浜庤褰曪紝鍘婚櫎loading鏁堟灉锛屼繚璇佸墠绔棤鐥曚繚瀛?
      this.activeTime.typeIds = this.typeIds
      if (this.typeIds.length === 0) {
        return
      }
      canlendarUpdateTypeAPI({ schedule_id: this.typeIds, userId: this.activeTime.userId }).then(res => {
      }).catch(() => {
      })
    },

    /**
     * 鏍煎紡鍖栨棩鍒楄〃宸︿晶鐨勬椂闂?
     */
    listDayFormat(data) {
      const timestamp = new Date(data.date.marker).getTime()
      const dateTime = moment(timestamp).format('ll')
      const week = moment(timestamp).format('dddd').replace('星期', '周')
      const dataValue = week + '  ' + dateTime
      return dataValue
    },

    /**
     * 周点击
     */
    toggleWeekends() {
      this.calendarWeekends = !this.calendarWeekends // update a property
    },

    /**
     * 璺宠浆鍒版煇澶?
     */
    gotoPast(date, boolean) {
      // 鑾峰彇鏃ュ巻瀵硅薄
      if (this.isFirstToDay) {
        this.isFirstToDay = false
        return
      }
      const timestamp = new Date(date).getTime()
      const newDate = moment(timestamp).format('YYYY-MM-DD')
      this.selectDiv = newDate
      const calendarApi = this.$refs.fullCalendar.getApi() // from the ref="..."
      if (calendarApi) {
        if (!boolean) {
          calendarApi.changeView('listDay')
        }
        calendarApi.gotoDate(newDate)
      }
    },

    /**
     * 澶╃偣鍑?
     */

    handleDateClick(arg) {
      if (this.selectDiv === arg.dateStr) {
        this.showCreate = true
      } else {
        this.selectDiv = arg.dateStr
        // 娉ㄥ紑浼氱敓鎴愭彁绀烘枃瀛?
        // const div = document.createElement('div')
        // div.style.position = 'absolute'
        // div.style.left = arg.jsEvent.clientX + 20 + 'px'
        // div.style.top = arg.jsEvent.clientY - 20 + 'px'
        // div.style.border = '1px solid #999'
        // div.style.backgroundColor = '#999'
        // div.style.padding = '10px'
        // div.style.fontSize = '12px'
        // div.style.zIndex = getMaxIndex()
        // div.style.boxShadow = ''
        // div.innerHTML = '鍙屽嚮鏂板缓'
        // div.style.color = '#333'
        // div.className = 'create__event?'
        // if (document.getElementsByClassName('create__event?')[0]) {
        //   const oldDiv = document.getElementsByClassName('create__event?')[0]
        //   document.documentElement.removeChild(oldDiv)
        // }
        // document.documentElement.appendChild(div)
        // console.log(arg, 'arg')
      }
      const td = document.getElementsByClassName('select-day')
      if (td && td.length) {
        td[0].classList.remove('select-day')
      }
      arg.dayEl.classList.add('select-day')
      // if (arg.dateStr) {
      //   this.$refs.schedule.selectDay(arg.dateStr, true)
      // }
    },

    /**
     * 灞曠ず鏇村鐨勬枃瀛?
     */
    eventLimiTtext(data) {
      return `查看剩余的${data}条`
    },

    /**
     *  鏃ユ湡娓叉煋鏃讹紝瑙﹀彂鐨勪簨浠讹紝榛樿浼犲叆info,涓€涓寘鍚玽iew鍜宔l鐨勫璞?
     */
    datesRender(info) {
      // 鍙湁鍗曟棩鍒楄〃鏃犳暟鎹椂锛屾墠鍒涘缓img
      if (info.view.type === 'listDay') {
        if (info.el.textContent === '鏆傛棤鏃ョ▼') {
          const img = document.createElement('img')
          // 鍐檌d淇濊瘉鍙垱寤轰竴娆?
          img.id = 'emityImg'
          img.src = require('@/assets/img/empty.png')
          // 淇濊瘉涓€瀹氭槸鏆傛棤鏃ョ▼鑰屼笉鏄湁浜涗簨浠跺彨鍋氭殏鏃犳棩绋?
          const div = document.getElementsByClassName('fc-list-empty-wrap1')[0]
          if (div) {
            div.insertBefore(img, div.children[0])
          }
        }
      } else if (info.view.type === 'dayGridMonth') {
        if (this.currentTime === info.view.title) {
          if (this.selectDiv && document.querySelector('td[data-date="' + this.selectDiv + '"]')) {
          // 保证切换模式时，关联的日期被选中
            document.querySelector('td[data-date="' + this.selectDiv + '"]').classList.add('select-day')
          }
        } else {
          // this.$refs.schedule.selectMouth(info.view.activeStart)
          this.currentTime = info.view.title
        }
        if (this.activeTime.startTime !== new Date(info.view.activeStart).getTime()) {
          this.activeTime.startTime = new Date(info.view.activeStart).getTime()
          this.activeTime.endTime = new Date(info.view.activeEnd).getTime()
          // 浼樺寲 鍙湁鏈堝垏鎹㈡墠浼氬埛鏂板垪琛?
          const leadTime = this.activeTime.endTime - this.activeTime.startTime
          if (leadTime > 24 * 60 * 60 * 1000) {
            this.getCusCheck()
          }
        }
      } else if (info.view.type === 'timeGridWeek') {
        // 鍛ㄩ€昏緫澶勭悊
      }
      this.listDataType = info.view.type
    },

    /**
     * 鐐瑰嚮浜嬩欢
     */
    eventClick(data) {
      if (data.event.extendedProps && data.event.extendedProps.crmType === 'ledger') {
        this.openLedgerDetail(data.event.id)
        return
      }
      if (data.event.extendedProps && data.event.extendedProps.typeId == -2) {
        this.relationID = data.event.id
        this.relationCrmType = 'task'
        setTimeout(() => {
          this.showFullDetail = true
        }, 200)
        return
      }
      this.eventId = data.event.id
      this.todayDetailData = {
        startTime: data.event.start || '',
        endTime: data.event.end || data.event.start,
        id: data.event.id,
        title: data.event.title,
        userId: this.activeTime.userId,
        groupId: data.event.groupId,
        backgroundColor: data.event.backgroundColor
      }
      // 涓嶆槸缁勪欢鑷甫鐨勫瓧娈甸兘浼氳鎻掑叆鍒癳ntendedProps閲岄潰
      if (data.event.extendedProps) {
        this.todayDetailData.name = data.event.extendedProps.name
        this.todayDetailData.createTime = data.event.extendedProps.createTime
        this.todayDetailData.headTitle = data.event.title
        this.todayDetailData.crmType = data.event.extendedProps.crmType
        this.todayDetailData.typeId = data.event.extendedProps.typeId || 3
      }
      this.showTodayDetail = true
    },

    /**
     * 閫変腑鏌愬ぉ
     */
    clickDay(data) {
      console.log(data)
    },

    /**
     * 閫変腑鏌愭湀
     */
    changeMonth(data, boolean) {
      this.gotoPast(data, true)
    },

    /**
     * 绛涢€?
     */
    customFifter(data) {
      this.typeIds = []
      const list = []
      data.forEach(item => {
        this.cusCheck.forEach(element => {
          if (item === element.name) {
            this.typeIds.push(element.type_id || element.schedule_id)
            list.push({ typeId: element.type_id || element.schedule_id, title: item })
          }
        })
      })

      this.updateEvent(list)
    },

    /**
     * 绛涢€夊畬鎴愬悗澶勭悊鐨勫嚱鏁?
     */
    updateEvent(data) {
      const list = []
      this.calendarList.forEach(item => {
        data.forEach(element => {
          if (element.typeId === item.groupId) {
            list.push(item)
          }
        })
      })
      this.updateList()
      this.calendarEvents = list
    },

    /**
     * 鏂板缓鏃ョ▼
     */
    createEvents() {
      this.selectDiv = ''
      this.showCreate = true
    },

    /**
     * 鏂板缓鏃ョ▼
     */
    handleSure(data, color) {
      let endTime = moment(data.end_time).format('YYYY-MM-DD HH:mm:ss')
      if (moment(data.end_time).format('YYYY-MM-DD HH:mm:ss').includes('00:00:00')) {
        endTime = moment(data.end_time - 0 + 1000).format('YYYY-MM-DD HH:mm:ss')
      }
      this.calendarEvents.push({
        title: data.title,
        crmType: data.crmType,
        start: moment(data.start_time).format('YYYY-MM-DD HH:mm:ss'),
        id: data.event_id || data.eventId,
        color: color,
        typeId: data.groupId,
        groupId: data.type_id || data.typeId,
        end: endTime
      })
    },

    /**
     * 鏂板缓鎴栬€呯紪杈戞垚鍔熺殑鍥炶皟
     */
    createSuccess() {
      this.showCreate = false
      this.getCusCheck()
    },

    /**
     * 鍒犻櫎/缂栬緫鎴愬姛鐨勫洖璋?
     */
    handleSuccess() {
      this.showTodayDetail = false
      this.getCusCheck()
    },

    /**
     * 閫夋嫨鍛樺伐
     */
    selectUser(data) {
      this.checkedUser = data.value
      this.copyCheckCusList = []
      if (data.value.length) {
        this.activeTime.userId = data.value.map(item => {
          return item.id
        }).join(',')
      } else {
        this.activeTime.userId = ''
        return
      }
      this.getCusCheck()
    },

    /**
     * 灞曠ず鍛樺伐閫夋
     */
    showUserSelect() {
      systemUserQueryAuthUserList().then(res => {
        if (res.data.length === 0) {
          this.showUser = false
        } else {
          this.showUser = true
        }
      }).catch(() => {})
    },

    /**
     * 鑾峰彇浠婃棩闇€瑕佸睍绀虹殑鏃ョ▼
     */
    getTodayTypeList() {
      this.loading = true
      const params = {
        start_time: this.activeTime.startTime,
        end_time: this.activeTime.endTime,
        user_id: this.activeTime.userId
      }
      canlendarEventCrmAPI(params).then(res => {
        const resData = res.data || {}
        this.needData = {
          leadsTimeList: resData.leads || [],
          customerTimeList: resData.customer || [],
          endContractTimeList: resData.contract || [],
          receiveContractTimeList: resData.receivables || [],
          businessTimeList: resData.businessNext || [],
          dealBusinessTimeList: resData.business || [],
          ledgerList: resData.ledger_list || []
        }
        this.todaySchedule = this.handleData(this.cusCheck)
        if (this.selectSysList.includes('1')) {
          this.getTask()
        } else {
          this.taskList = []
          this.getList()
        }
        // this.loading = false
      }).catch(() => {})
    },

    /**
     * 鑾峰彇鍒嗛厤缁欐垜鐨勪换鍔?
     */
    getTask() {
      this.taskList = []
      const params = {
        start_time: this.activeTime.startTime / 1000,
        end_time: this.activeTime.endTime / 1000,
        user_id: this.activeTime.userId
      }
      canlendarEventTaskAPI(params).then(res => {
        const resData = res.data || []
        this.taskList = resData.map(item => {
          return {
            title: item.name,
            start_time: moment(item.start_time - 0 + 1000).format('YYYY-MM-DD HH:mm:ss'),
            id: item.task_id || item.taskId,
            eventId: item.task_id || item.taskId,
            color: '#AEA1EA',
            groupId: -2,
            typeId: this.sysTypeId[0].typeId,
            end_time: moment(item.stop_time - 0 + 1000).format('YYYY-MM-DD HH:mm:ss')
          }
        })
        this.getList()
      }).catch(() => {})
    },

    /**
     * 灏嗛渶瑕佸睍绀虹殑鏃ョ▼鎷兼帴鍏ユ棩绋嬪睍绀虹殑鏁扮粍
     * color 1 鍒嗛厤缁欐垜鐨勪换鍔?2 闇€鑱旂郴鐨勫鎴?3 鍗冲皢鍒版湡鐨勫悎鍚?4 闇€瑕佸洖娆剧殑鍚堝悓
     */
    handleData(list) {
      this.selectSysList = []
      const dataList = []
      list.forEach(item => {
        if (item.type === 1) {
          this.selectSysList.push(item.color)
        }
      })

      const leadsObj = this.sysTypeId.find(item => item.crmType === 'leads') || {}
      this.needData.leadsTimeList.forEach(date => {
        dataList.push({
          title: '需联系的线索',
          start_time: date,
          eventId: -1,
          color: '#58DADA',
          crmType: leadsObj.crmType,
          typeId: leadsObj.typeId,
          groupId: leadsObj.typeId,
          end_time: date
        })
      })

      const customerObj = this.sysTypeId.find(item => item.crmType === 'customer') || {}
      this.needData.customerTimeList.forEach(date => {
        dataList.push({
          title: '需联系的客户',
          start_time: date,
          eventId: -1,
          color: '#53D397',
          crmType: customerObj.crmType,
          typeId: customerObj.typeId,
          groupId: customerObj.typeId,
          end_time: date
        })
      })

      const businessObj = this.sysTypeId.find(item => item.crmType === 'business') || {}
      this.needData.businessTimeList.forEach(date => {
        dataList.push({
          title: '需联系的商机',
          start_time: date,
          eventId: -1,
          color: '#4586FF',
          crmType: businessObj.crmType,
          typeId: businessObj.typeId,
          groupId: businessObj.typeId,
          end_time: date
        })
      })

      const dealBusinessObj = this.sysTypeId.find(item => item.crmType === 'dealBusiness') || {}
      this.needData.dealBusinessTimeList.forEach(date => {
        dataList.push({
          title: '预计成交的商机',
          start_time: date,
          eventId: -1,
          color: '#8983F3',
          crmType: dealBusinessObj.crmType,
          typeId: dealBusinessObj.typeId,
          groupId: dealBusinessObj.typeId,
          end_time: date
        })
      })

      const endContractObj = this.sysTypeId.find(item => item.crmType === 'contract') || {}
      this.needData.endContractTimeList.forEach(date => {
        dataList.push({
          title: '即将到期的合同',
          start_time: date,
          eventId: -1,
          color: '#3498DB',
          crmType: endContractObj.crmType,
          typeId: endContractObj.typeId,
          groupId: endContractObj.typeId,
          end_time: date
        })
      })

      const receiveContractObj = this.sysTypeId.find(item => item.crmType === 'receiveContract') || {}
      this.needData.receiveContractTimeList.forEach(date => {
        dataList.push({
          title: '璁″垝鍥炴',
          start_time: date,
          eventId: -1,
          color: '#FF6F6F',
          crmType: receiveContractObj.crmType,
          typeId: receiveContractObj.typeId,
          groupId: receiveContractObj.typeId,
          end_time: date
        })
      })

      const ledgerObj = this.sysTypeId.find(item => item.crmType === 'ledger') || {}
      this.needData.ledgerList.forEach(item => {
        dataList.push({
          title: item.title,
          start_time: item.event_time,
          eventId: item.ledger_id,
          color: '#FF6699',
          crmType: ledgerObj.crmType,
          typeId: ledgerObj.typeId,
          groupId: ledgerObj.typeId,
          end_time: item.event_time
        })
      })

      return dataList
    },

    /**
     * 鎷兼帴灞曠ず鏁版嵁
     */
    handleShowData() {
      const list = [
        ...this.dayEventList, ...this.todaySchedule, ...this.taskList
      ]
      list.forEach(item => {
        this.handleSure(item, item.color)
      })
      this.calendarList = this.calendarEvents
      this.showGroup = true
      this.customFifter(this.checkCusList)
    },
    normalizeHtmlImages(html) {
      if (!html) return ''
      const base = (window && window.BASE_URL) ? window.BASE_URL.replace(/\/$/, '') : ''
      if (!base) return html
      const baseLower = base.toLowerCase()
      return html.replace(/<img\b[^>]*\bsrc=(['"])(.*?)\1[^>]*>/gi, (match, quote, src) => {
        const value = (src || '').trim()
        if (!value) return match
        const lower = value.toLowerCase()
        if (
          lower.startsWith('http://') ||
          lower.startsWith('https://') ||
          lower.startsWith('data:') ||
          lower.startsWith('blob:') ||
          lower.startsWith('//') ||
          lower.startsWith(baseLower)
        ) {
          return match
        }
        const fixed = value.startsWith('/') ? `${base}${value}` : `${base}/${value}`
        return match.replace(src, fixed)
      })
    },
    openLedgerDetail(ledgerId) {
      if (!ledgerId) return
      this.ledgerDetailVisible = true
      this.ledgerLoading = true
      ledgerReadAPI({ id: ledgerId }).then(res => {
        this.ledgerDetail = res.data || {}
        this.ledgerDetail.description = this.normalizeHtmlImages(this.ledgerDetail.description)
      }).finally(() => {
        this.ledgerLoading = false
      })
    }

  }
}
</script>

<style lang="scss" scoped>
@import './style/color.scss';
@import './style/fullCalendar.scss';
.calendar-box {
  padding: 5px 5px 10px;
  min-width: 1200px;
  overflow-y: hidden;
  overflow-x: auto;
  font-size: 14px;
  position: relative;
}
.el-button{
  position: absolute;
  top: 17px;
  right: 40px;
  background-color: #FF6A00;
  border-color: #ff6a00;
}
.box-left {
  width: 280px;
  background-color: #fff;
  height: 100%;
  border:1px solid rgba(230,230,230,1);
  border-radius:4px;
  margin-right: 20px;
  flex-shrink: 0;
  .left-scroll{
    height: calc(100% - 100px);
    overflow-y: auto;
  }
  .left-bottom-text{
    color: #999;
    width: 240px;
    height: 20px;
    margin-top: 20px;
    margin-bottom: 10px;
    font-size: 12px;
    .text-span{
      display: inline-block;
      width: 200px;
      letter-spacing: 2px;
    }
    i{
      display: inline-block;
      vertical-align: top;
      margin-left: 10px;
    }
  }
  .left-title{
    width: 100%;
    display: flex;
    border-bottom: 1px solid rgb(239,239,239);
    height: 50px;
    line-height: 50px;
    .title-text{
      font-size: 16px;
      color: #323232;
      display: inline-block;
      width: 120px;
      font-weight: bolder;
    }
    img{
      vertical-align: sub;
      margin-left: 16px;
      margin-right: 10px;
      height: 20px;
      margin-top: 14px;
    }
    .left-user{
      margin-top: 7px;
      width: 180px;
    }
  }
  .left-main{
    width: 100%;
    padding: 0px;
    color:#666666;

    // border-bottom: 1px solid rgb(239,239,239);
    // border-top: 1px solid rgb(239,239,239);
    .main-title:hover{
      background-color: #EDF2FF;
    }
    .main-title{
      font-size: 12px;
      margin-top: 10px;
      display: flex;
      padding: 15px;
      width: 100%;
      height: 45px;
      cursor: pointer;

      .main-text{
        display: inline-block;
        color: #333;
        font-weight: bolder;
        width: 240px;
      }
       img {
        width: 18px;
        height: 18px;
        margin-top: -2px;
        margin-right: 10px;
      }
    }
  }
  .left-bottom{
    width: 100%;
     padding: 0px;
    .bottom-title:hover{
      background-color: #EDF2FF;
      }
    .bottom-title{
      color:#666666;
      font-size: 12px;
      padding: 15px;
      display: flex;
      height: 45px;
      cursor: pointer;

       .main-text{
        display: inline-block;
        color: #333;
        font-weight: bolder;
        width: 240px;
      }
      img {
        width: 18px;
        height: 18px;
        margin-top: -2px;
        margin-right: 10px;
      }
    }
  }
   ::v-deep.el-checkbox{
      height: 40px;
      line-height: 40px;
      padding-left: 15px;
      display: block;
      .el-checkbox__label{
        padding-left: 15px;
        font-size: 13px;
      }
   }
   ::v-deep.el-checkbox:hover{
     width: 100%;
     background-color: #F6F8FA;
   }
}
.box-right{
  background-color: #fff;
  height: 100%;
  width: 100%;
  overflow: hidden;
  border:1px solid #f3f3f3;
  padding: 10px 0px 0px;
}
.ledger-detail{
  padding: 10px 20px;
}
.ledger-row{
  display: flex;
  padding: 6px 0;
}
.ledger-row--wide{
  align-items: flex-start;
}
.ledger-label{
  width: 90px;
  color: #909399;
  flex-shrink: 0;
}
.ledger-value{
  color: #303133;
  word-break: break-all;
}
.ledger-rich-text img{
  max-width: 100%;
  height: auto;
}
.user-box {
    width: unset;
    height: 36px;
    background-color: white;
    margin-right: 10px;
    display: flex;
    cursor: pointer;
    .user-icon {
      background: $xr-color-primary;
      color: white;
      border-radius: 50%;
      width: 28px;
      height: 28px;
      line-height: 28px;
      text-align: center;
    }
    .username {
      font-size: 13px;
      display: inline-block;
      text-overflow: ellipsis;
      overflow: hidden;
      margin-right: 3px;
      white-space: nowrap;
    }
  }
::v-deep.select-day{
  background-color: #4983EF !important;
  opacity: 0.04 !important;
}
.el-icon-arrow-up {
  color: #333;
  font-size: 14px;
  transition: transform .3s;
  transform: rotate(180deg);
  cursor: pointer;
  margin-right: 10px;
}
.el-icon-arrow-up.is-reverse {
  transform: rotate(0deg);
}
</style>

