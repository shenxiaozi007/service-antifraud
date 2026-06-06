const riskText = {
  low: '低风险',
  medium: '中风险',
  high: '高风险',
  critical: '极高风险'
};

function riskLabel(level) {
  return riskText[level] || '未知';
}

function riskClass(level) {
  if (level === 'critical') return 'critical';
  if (level === 'high') return 'high';
  if (level === 'medium') return 'medium';
  return '';
}

function durationText(seconds) {
  const value = Number(seconds || 0);
  const minute = String(Math.floor(value / 60)).padStart(2, '0');
  const second = String(value % 60).padStart(2, '0');
  return `${minute}:${second}`;
}

const statusText = {
  pending: '排队中',
  processing: '分析中',
  success: '已完成',
  failed: '分析失败',
  canceled: '已取消'
};

function analysisStatusLabel(status) {
  return statusText[status] || '处理中';
}

function analysisStatusClass(status) {
  if (status === 'failed' || status === 'canceled') return 'critical';
  if (status === 'pending' || status === 'processing') return 'medium';
  return '';
}

module.exports = {
  riskLabel,
  riskClass,
  durationText,
  analysisStatusLabel,
  analysisStatusClass
};
