import type { RiskLevel } from '@/types/api';

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
