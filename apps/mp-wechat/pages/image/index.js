const app = getApp();
const api = require('../../utils/api');

Page({
  data: {
    images: [],
    text: '',
    canSubmit: false,
    loading: false
  },

  async onLoad() {
    await app.ensureLogin();
  },

  chooseImage() {
    wx.chooseMedia({
      count: 3 - this.data.images.length,
      mediaType: ['image'],
      sourceType: ['album', 'camera'],
      success: (res) => {
        const next = this.data.images.concat(res.tempFiles.map((file) => ({
          path: file.tempFilePath,
          size: file.size || 1
        }))).slice(0, 3);
        this.setData({ images: next }, this.refreshState);
      }
    });
  },

  removeImage(event) {
    const index = Number(event.currentTarget.dataset.index);
    const images = this.data.images.filter((_, current) => current !== index);
    this.setData({ images }, this.refreshState);
  },

  onTextInput(event) {
    this.setData({ text: event.detail.value }, this.refreshState);
  },

  refreshState() {
    this.setData({
      canSubmit: this.data.images.length > 0 && !this.data.loading
    });
  },

  async submit() {
    if (!this.data.canSubmit) return;

    this.setData({ loading: true, canSubmit: false });
    wx.showLoading({ title: '分析中' });

    try {
      const fileIds = [];
      for (const image of this.data.images) {
        const file = await api.uploadToken({
          file_type: 'image',
          mime_type: 'image/jpeg',
          file_size: image.size || 1
        });
        fileIds.push(file.file_id);
      }

      const result = await api.createImageAnalysis({
        file_ids: fileIds,
        text: this.data.text || '保证收益，稳赚不赔，名额有限'
      });

      wx.navigateTo({ url: `/pages/report/index?record_id=${result.record_id}` });
    } catch (error) {
      this.setData({ loading: false }, this.refreshState);
    } finally {
      wx.hideLoading();
    }
  }
});
