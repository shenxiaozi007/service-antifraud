const request = require('./request');
const config = require('./config');

function uploadCommonFile(filePath, fileType, bizType) {
  const token = wx.getStorageSync('token');

  return new Promise((resolve, reject) => {
    wx.uploadFile({
      url: `${config.fileBaseURL}/service/api/v1/file/upload`,
      filePath,
      name: 'file',
      formData: {
        owner_project: 'antifraud',
        biz_type: bizType,
        disk: 'cloudflare_r2'
      },
      header: token ? { Authorization: `Bearer ${token}` } : {},
      success(res) {
        const body = JSON.parse(res.data || '{}');
        if (res.statusCode >= 200 && res.statusCode < 300 && body.code === 0) {
          resolve(body.data);
          return;
        }

        const message = body.message || `上传失败 ${res.statusCode}`;
        wx.showToast({ title: message, icon: 'none' });
        reject(new Error(message));
      },
      fail(error) {
        wx.showToast({ title: '文件上传失败', icon: 'none' });
        reject(error);
      }
    });
  }).then((file) => request({
    url: '/api/v1/files/register',
    method: 'POST',
    data: {
      storage_file_id: file.file_id,
      file_type: fileType,
      object_key: file.object_key,
      file_url: file.file_url,
      mime_type: file.mime_type,
      file_size: file.size
    }
  }));
}

module.exports = {
  wechatLogin(data) {
    return request({ url: '/api/v1/auth/wechat-login', method: 'POST', data });
  },

  sendCode(data) {
    return request({ url: '/api/v1/auth/send-code', method: 'POST', data });
  },

  codeLogin(data) {
    return request({ url: '/api/v1/auth/code-login', method: 'POST', data });
  },

  me() {
    return request({ url: '/api/v1/me' });
  },

  uploadToken(data) {
    return request({ url: '/api/v1/files/upload-token', method: 'POST', data });
  },
  uploadCommonFile,

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

  paymentPackages() {
    return request({ url: '/api/v1/payments/packages' });
  },

  paymentOrder(data) {
    return request({ url: '/api/v1/payments/wechat/order', method: 'POST', data });
  }
};
