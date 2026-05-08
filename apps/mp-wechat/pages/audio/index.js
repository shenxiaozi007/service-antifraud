const app = getApp();
const api = require('../../utils/api');
const format = require('../../utils/format');

const recorder = wx.getRecorderManager();

Page({
  data: {
    recording: false,
    audioPath: '',
    duration: 0,
    durationText: '00:00',
    text: '',
    canSubmit: false,
    loading: false
  },

  timer: null,
  startedAt: 0,

  async onLoad() {
    await app.ensureLogin();
    recorder.onStop((res) => {
      const duration = Math.max(1, Math.ceil((Date.now() - this.startedAt) / 1000));
      this.clearTimer();
      this.setData({
        recording: false,
        audioPath: res.tempFilePath,
        duration,
        durationText: format.durationText(duration)
      }, this.refreshState);
    });
  },

  onUnload() {
    this.clearTimer();
  },

  startRecord() {
    this.startedAt = Date.now();
    this.setData({
      recording: true,
      audioPath: '',
      duration: 0,
      durationText: '00:00'
    }, this.refreshState);

    this.timer = setInterval(() => {
      const duration = Math.floor((Date.now() - this.startedAt) / 1000);
      this.setData({ duration, durationText: format.durationText(duration) });
    }, 1000);

    recorder.start({
      duration: 600000,
      sampleRate: 16000,
      numberOfChannels: 1,
      encodeBitRate: 48000,
      format: 'mp3'
    });
  },

  stopRecord() {
    recorder.stop();
  },

  resetRecord() {
    this.setData({
      audioPath: '',
      duration: 0,
      durationText: '00:00'
    }, this.refreshState);
  },

  onTextInput(event) {
    this.setData({ text: event.detail.value }, this.refreshState);
  },

  refreshState() {
    this.setData({
      canSubmit: !this.data.loading && (Boolean(this.data.audioPath) || this.data.text.length > 0)
    });
  },

  async submit() {
    if (!this.data.canSubmit) return;

    this.setData({ loading: true, canSubmit: false });
    wx.showLoading({ title: '分析中' });

    try {
      const file = await api.uploadToken({
        file_type: 'audio',
        mime_type: 'audio/mpeg',
        file_size: 1
      });

      const result = await api.createAudioAnalysis({
        file_id: file.file_id,
        duration_seconds: Math.max(this.data.duration, 1),
        text: this.data.text || '不要告诉家人，把验证码发给我'
      });

      wx.navigateTo({ url: `/pages/report/index?record_id=${result.record_id}` });
    } catch (error) {
      this.setData({ loading: false }, this.refreshState);
    } finally {
      wx.hideLoading();
    }
  },

  clearTimer() {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }
});
