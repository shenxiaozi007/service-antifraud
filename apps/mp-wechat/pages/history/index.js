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

  // 方法：按当前筛选条件拉取历史记录，并补充列表展示标签
  async loadRecords() {
    const result = await api.records({
      risk_level: this.data.filter,
      type: this.data.type,
      page_size: 50
    });
    const records = result.items.map((item) => ({
      ...item,
      tagLabel: item.status === 'success' ? format.riskLabel(item.risk_level) : format.analysisStatusLabel(item.status),
      tagClass: item.status === 'success' ? format.riskClass(item.risk_level) : format.analysisStatusClass(item.status),
      pointText: ['pending', 'processing'].includes(item.status) && item.frozen_points ? `冻结${item.frozen_points}点` : `${item.cost_points}点`
    }));
    this.setData({ records });
  },

  // 方法：切换风险等级筛选，高风险筛选主要用于已完成记录
  changeFilter(event) {
    this.setData({ filter: event.currentTarget.dataset.filter || '' }, () => this.loadRecords());
  },

  // 方法：切换图片/录音类型筛选，再次点击同类型时取消筛选
  changeType(event) {
    const type = event.currentTarget.dataset.type || '';
    this.setData({ type: this.data.type === type ? '' : type }, () => this.loadRecords());
  },

  // 方法：进入报告详情，处理中记录会在报告页继续轮询
  goReport(event) {
    wx.navigateTo({ url: `/pages/report/index?record_id=${event.currentTarget.dataset.id}` });
  }
});
