const api = require('./utils/api');

App({
  globalData: {
    user: null
  },

  async onLaunch() {
    try {
      await this.ensureLogin();
    } catch (error) {
      this.globalData.user = null;
    }
  },

  async ensureLogin() {
    const token = wx.getStorageSync('token');
    if (token) {
      try {
        const user = await api.me();
        this.globalData.user = user;
        return user;
      } catch (error) {
        wx.removeStorageSync('token');
      }
    }

    throw new Error('请先登录');
  },

  wxLogin() {
    return new Promise((resolve) => {
      wx.login({
        success: resolve,
        fail: () => resolve({ code: String(Date.now()) })
      });
    });
  }
});
