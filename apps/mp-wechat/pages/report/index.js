const app = getApp();
const api = require('../../utils/api');
const format = require('../../utils/format');

Page({
  data: {
    loading: true,
    recordId: 0,
    report: {
      suggestions: [],
      risk_items: []
    },
    riskLabel: '',
    riskClass: '',
    statusLabel: '排队中',
    statusClass: 'medium',
    durationText: '00:00'
  },
  pollTimer: null,
  pollCount: 0,

  async onLoad(options) {
    await app.ensureLogin();
    const recordId = Number(options.record_id || 0);
    this.setData({ recordId });
    await this.loadReport(recordId);
  },

  onUnload() {
    this.stopPolling();
  },

  // 方法：拉取报告详情，并根据异步任务状态决定是否继续轮询
  async loadReport(recordId) {
    const report = await api.report(recordId);
    this.setData({
      report,
      loading: false,
      riskLabel: format.riskLabel(report.risk_level),
      riskClass: format.riskClass(report.risk_level),
      statusLabel: format.analysisStatusLabel(report.status),
      statusClass: format.analysisStatusClass(report.status),
      durationText: format.durationText(report.duration_seconds)
    });
    this.schedulePolling();
  },

  // 方法：pending/processing 状态每 2 秒刷新一次，直到任务完成或达到上限
  schedulePolling() {
    this.stopPolling();
    const status = this.data.report.status;
    if (!['pending', 'processing'].includes(status) || this.pollCount >= 60) {
      return;
    }

    this.pollTimer = setTimeout(async () => {
      this.pollCount += 1;
      try {
        await this.loadReport(this.data.recordId);
      } catch (error) {
        this.stopPolling();
      }
    }, 2000);
  },

  // 方法：页面退出或任务结束时清除定时器，避免重复请求报告接口
  stopPolling() {
    if (this.pollTimer) {
      clearTimeout(this.pollTimer);
      this.pollTimer = null;
    }
  },

  // 方法：失败或完成后回到对应上传页重新提交材料
  again() {
    wx.navigateTo({ url: this.data.report.type === 'audio' ? '/pages/audio/index' : '/pages/image/index' });
  },

  onShareAppMessage() {
    return {
      title: `${this.data.riskLabel}：${this.data.report.title}`,
      path: `/pages/report/index?record_id=${this.data.recordId}`
    };
  }
});
