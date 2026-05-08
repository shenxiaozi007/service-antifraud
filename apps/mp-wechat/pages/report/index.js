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
    durationText: '00:00'
  },

  async onLoad(options) {
    await app.ensureLogin();
    const recordId = Number(options.record_id || 0);
    this.setData({ recordId });
    await this.loadReport(recordId);
  },

  async loadReport(recordId) {
    const report = await api.report(recordId);
    this.setData({
      report,
      loading: false,
      riskLabel: format.riskLabel(report.risk_level),
      riskClass: format.riskClass(report.risk_level),
      durationText: format.durationText(report.duration_seconds)
    });
  },

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
