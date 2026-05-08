const app = getApp();
const api = require('../../utils/api');
const format = require('../../utils/format');

Page({
  data: {
    records: [],
    filter: '',
    type: ''
  },

  async onShow() {
    await app.ensureLogin();
    await this.loadRecords();
  },

  async loadRecords() {
    const result = await api.records({
      risk_level: this.data.filter,
      type: this.data.type,
      page_size: 50
    });
    const records = result.items.map((item) => ({
      ...item,
      riskLabel: format.riskLabel(item.risk_level),
      riskClass: format.riskClass(item.risk_level)
    }));
    this.setData({ records });
  },

  changeFilter(event) {
    this.setData({ filter: event.currentTarget.dataset.filter || '' }, () => this.loadRecords());
  },

  changeType(event) {
    const type = event.currentTarget.dataset.type || '';
    this.setData({ type: this.data.type === type ? '' : type }, () => this.loadRecords());
  },

  goReport(event) {
    wx.navigateTo({ url: `/pages/report/index?record_id=${event.currentTarget.dataset.id}` });
  }
});
