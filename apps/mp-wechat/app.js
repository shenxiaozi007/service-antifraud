const api = require('./utils/api');

App({
  globalData: {
    user: null
  },

  async onLaunch() {
    await this.ensureLogin();
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

    const loginResult = await this.wxLogin();
    const openid = wx.getStorageSync('dev_openid') || `dev_${Date.now()}`;
    wx.setStorageSync('dev_openid', openid);

    const result = await api.wechatLogin({
      code: loginResult.code || String(Date.now()),
      openid,
      nickname: '微信用户'
    });

    wx.setStorageSync('token', result.token);
    this.globalData.user = result.user;
    return result.user;
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
