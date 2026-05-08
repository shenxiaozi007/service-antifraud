const content = {
  agreement: {
    title: '用户协议',
    paragraphs: [
      '防诈助手用于帮助用户识别宣传材料、聊天内容和录音中的风险信号。',
      '分析结果仅作风险提醒参考，不构成法律、投资、医疗或财务建议。',
      '用户应结合官方渠道、家人意见和专业机构判断后再做决定。'
    ]
  },
  privacy: {
    title: '隐私政策',
    paragraphs: [
      '上传的图片、录音和转写文本仅用于生成风险分析报告。',
      '历史记录默认保存，用户可主动删除记录和关联文件。',
      '后台查看敏感信息时应遵循最小必要原则，后续正式上线前需补充完整隐私政策。'
    ]
  }
};

Page({
  data: {
    title: '',
    paragraphs: []
  },

  onLoad(options) {
    const detail = content[options.type] || content.agreement;
    this.setData(detail);
    wx.setNavigationBarTitle({ title: detail.title });
  }
});
