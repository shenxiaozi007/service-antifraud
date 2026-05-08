const request = require('./request');

module.exports = {
  wechatLogin(data) {
    return request({ url: '/api/v1/auth/wechat-login', method: 'POST', data });
  },

  me() {
    return request({ url: '/api/v1/me' });
  },

  uploadToken(data) {
    return request({ url: '/api/v1/files/upload-token', method: 'POST', data });
  },

  createImageAnalysis(data) {
    return request({ url: '/api/v1/analysis/image', method: 'POST', data });
  },

  createAudioAnalysis(data) {
    return request({ url: '/api/v1/analysis/audio', method: 'POST', data });
  },

  report(recordId) {
    return request({ url: `/api/v1/analysis/${recordId}` });
  },

  records(params = {}) {
    const query = Object.keys(params)
      .filter((key) => params[key] !== '' && params[key] !== undefined)
      .map((key) => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
      .join('&');
    return request({ url: `/api/v1/analysis-records${query ? `?${query}` : ''}` });
  },

  deleteRecord(recordId) {
    return request({ url: `/api/v1/analysis/${recordId}`, method: 'DELETE' });
  },

  transactions() {
    return request({ url: '/api/v1/points/transactions' });
  },

  paymentOrder(data) {
    return request({ url: '/api/v1/payments/wechat/order', method: 'POST', data });
  }
};
