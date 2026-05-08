const app = getApp();
const api = require('../../utils/api');

Page({
  data: {
    user: {},
    initial: '微',
    showTransactions: false,
    transactions: []
  },

  async onShow() {
    const user = await app.ensureLogin();
    this.setData({
      user,
      initial: (user.nickname || '微').slice(0, 1)
    });
  },

  async recharge() {
    const result = await api.paymentOrder({ package_id: 'points_100' });
    wx.showModal({
      title: '充值点数',
      content: result.message || 'MVP 暂未接入微信支付',
      showCancel: false
    });
  },

  async goTransactions() {
    const result = await api.transactions();
    this.setData({
      showTransactions: true,
      transactions: result.items
    });
  },

  goAgreement() {
    wx.navigateTo({ url: '/pages/content/index?type=agreement' });
  },

  goPrivacy() {
    wx.navigateTo({ url: '/pages/content/index?type=privacy' });
  },

  contact() {
    wx.showModal({
      title: '联系客服',
      content: '请保留可疑材料，必要时拨打 96110 咨询。',
      showCancel: false
    });
  },

  clearLocal() {
    wx.removeStorageSync('token');
    wx.removeStorageSync('dev_openid');
    wx.showToast({ title: '已清除', icon: 'success' });
  }
});
