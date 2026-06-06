import type { AnalysisStatus, RiskLevel } from '@/types/api';

const riskText: Record<RiskLevel, string> = {
  low: '低风险',
  medium: '中风险',
  high: '高风险',
  critical: '极高风险'
};

export function riskLabel(level: RiskLevel | string): string {
  return riskText[level as RiskLevel] || '未知';
}

export function riskClass(level: RiskLevel | string): string {
  if (level === 'critical') return 'critical';
  if (level === 'high') return 'high';
  if (level === 'medium') return 'medium';
  return '';
}

export function durationText(seconds: number | string | undefined): string {
  const value = Number(seconds || 0);
  const minute = String(Math.floor(value / 60)).padStart(2, '0');
  const second = String(value % 60).padStart(2, '0');
  return `${minute}:${second}`;
}

const statusText: Record<AnalysisStatus, string> = {
  pending: '排队中',
  processing: '分析中',
  success: '已完成',
  failed: '分析失败',
  canceled: '已取消'
};

export function analysisStatusLabel(status: AnalysisStatus | string): string {
  return statusText[status as AnalysisStatus] || '处理中';
}

export function analysisStatusClass(status: AnalysisStatus | string): string {
  if (status === 'failed' || status === 'canceled') return 'critical';
  if (status === 'pending' || status === 'processing') return 'medium';
  return '';
}
