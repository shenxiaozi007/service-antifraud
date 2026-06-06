const app = getApp();
const api = require('../../utils/api');

Page({
  data: {
    user: {},
    initial: '微',
    packages: [],
    showTransactions: false,
    transactions: [],
    account: '',
    code: '',
    sendingCode: false,
    loggingIn: false,
    wechatLoggingIn: false,
    selectedPackageId: 0
  },

  async onShow() {
    try {
      const user = await app.ensureLogin();
      this.setData({
        user,
        initial: (user.nickname || '微').slice(0, 1)
      });
    } catch (error) {
      this.setData({ user: {}, initial: '微' });
    }
    const packages = await api.paymentPackages();
    this.setData({
      packages,
      selectedPackageId: this.data.selectedPackageId || (packages[0] ? packages[0].id : 0)
    });
  },

  onAccountInput(event) {
    this.setData({ account: event.detail.value });
  },

  onCodeInput(event) {
    this.setData({ code: event.detail.value });
  },

  // 方法：发送邮箱/手机号验证码，给非微信环境提供注册登录入口
  async submitSendCode() {
    if (!this.data.account) {
      wx.showToast({ title: '请输入邮箱或手机号', icon: 'none' });
      return;
    }

    this.setData({ sendingCode: true });
    try {
      const result = await api.sendCode({ account: this.data.account, scene: 'login' });
      const debug = result.debug_code ? `：${result.debug_code}` : '';
      wx.showToast({ title: `验证码已发送${debug}`, icon: 'none' });
    } finally {
      this.setData({ sendingCode: false });
    }
  },

  // 方法：验证码登录注册一体化，成功后写入公共 token 并刷新当前用户
  async submitCodeLogin() {
    if (!this.data.account || !this.data.code) {
      wx.showToast({ title: '请输入账号和验证码', icon: 'none' });
      return;
    }

    this.setData({ loggingIn: true });
    try {
      const result = await api.codeLogin({ account: this.data.account, code: this.data.code, scene: 'login' });
      wx.setStorageSync('token', result.token);
      app.globalData.user = result.user;
      this.setData({
        user: result.user,
        initial: (result.user.nickname || '微').slice(0, 1)
      });
      wx.showToast({ title: result.is_new_user ? '注册成功' : '登录成功', icon: 'success' });
    } finally {
      this.setData({ loggingIn: false });
    }
  },

  // 方法：微信小程序登录，绑定 openid 后可用于 JSAPI/小程序支付
  async submitWechatLogin() {
    this.setData({ wechatLoggingIn: true });
    try {
      const loginResult = await app.wxLogin();
      const result = await api.wechatLogin({
        code: loginResult.code || String(Date.now()),
        nickname: '微信用户'
      });
      wx.setStorageSync('token', result.token);
      app.globalData.user = result.user;
      this.setData({
        user: result.user,
        initial: (result.user.nickname || '微').slice(0, 1)
      });
      wx.showToast({ title: result.is_new_user ? '注册成功' : '登录成功', icon: 'success' });
    } finally {
      this.setData({ wechatLoggingIn: false });
    }
  },

  // 方法：选择点数套餐，下单时使用当前选中项
  selectPackage(event) {
    this.setData({ selectedPackageId: Number(event.currentTarget.dataset.id || 0) });
  },

  // 方法：支付成功或订单创建后刷新公共用户余额
  async refreshUser() {
    const user = await api.me();
    app.globalData.user = user;
    this.setData({
      user,
      initial: (user.nickname || '微').slice(0, 1)
    });
  },

  // 方法：创建微信支付订单；真实支付成功后刷新余额，mock 模式保留订单号用于联调
  async recharge() {
    if (!this.data.user || !this.data.user.id) {
      wx.showToast({ title: '请先登录', icon: 'none' });
      return;
    }

    const selected = this.data.packages.find((item) => item.id === this.data.selectedPackageId) || this.data.packages[0];
    if (!selected) {
      wx.showToast({ title: '暂无可用套餐', icon: 'none' });
      return;
    }

    const result = await api.paymentOrder({ package_id: selected.id });
    const params = result.payment_params || {};
    if (!params.mock) {
      wx.requestPayment({
        timeStamp: params.timeStamp,
        nonceStr: params.nonceStr,
        package: params.package,
        signType: params.signType,
        paySign: params.paySign,
        success: () => {
          this.refreshUser();
        }
      });
      return;
    }

    wx.showModal({
      title: '充值点数',
      content: `订单已创建：${result.order_no}`,
      showCancel: false
    });
    await this.refreshUser();
  },

  // 方法：拉取公共钱包流水，展示充值和分析扣点记录
  async goTransactions() {
    if (!this.data.user || !this.data.user.id) {
      wx.showToast({ title: '请先登录', icon: 'none' });
      return;
    }

    const result = await api.transactions();
    this.setData({
      showTransactions: true,
      transactions: result.items
    });
  },

  // 方法：跳转用户协议页面
  goAgreement() {
    wx.navigateTo({ url: '/pages/content/index?type=agreement' });
  },

  // 方法：跳转隐私政策页面
  goPrivacy() {
    wx.navigateTo({ url: '/pages/content/index?type=privacy' });
  },

  // 方法：展示客服提示，便于用户处理高风险材料
  contact() {
    wx.showModal({
      title: '联系客服',
      content: '请保留可疑材料，必要时拨打 96110 咨询。',
      showCancel: false
    });
  },

  // 方法：清理本机 token 和缓存用户，方便切换账号测试
  clearLocal() {
    wx.removeStorageSync('token');
    app.globalData.user = null;
    this.setData({ user: {}, initial: '微' });
    wx.showToast({ title: '已清除', icon: 'success' });
  }
});
