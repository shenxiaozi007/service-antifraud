export type RiskLevel = 'low' | 'medium' | 'high' | 'critical';
export type AnalysisType = 'image' | 'audio';

export interface ApiResponse<T> {
  code: number;
  message: string;
  data: T;
}

export interface UserInfo {
  id: number;
  nickname: string | null;
  avatar_url: string | null;
  points_balance: number;
  status?: number;
}

export interface LoginResponse {
  token: string;
  user: UserInfo;
}

export interface UploadTokenResponse {
  file_id: number;
  upload_url: string;
  storage_key: string;
}

export interface RiskItem {
  category: string;
  severity: RiskLevel | string;
  description: string;
  evidence_text: string | null;
}

export interface FileAsset {
  id: number;
  file_type: AnalysisType;
  file_url: string | null;
  mime_type: string | null;
  file_size: number;
}

export interface AnalysisRecord {
  id: number;
  type: AnalysisType;
  title: string;
  risk_level: RiskLevel;
  risk_score: number;
  summary: string;
  suggestions: string[];
  status: string;
  cost_points: number;
  image_count: number;
  duration_seconds: number;
  analyzed_at: string | null;
  created_at: string | null;
  risk_items?: RiskItem[];
  files?: FileAsset[];
  disclaimer?: string;
}

export interface PageResult<T> {
  items: T[];
  total: number;
  page: number;
  page_size: number;
}

export interface CreateAnalysisResponse {
  record_id: number;
  status: string;
  frozen_points: number;
  cost_points: number;
  report: AnalysisRecord;
}

export interface PointTransaction {
  id: number;
  amount: number;
  balance_after: number;
  type: string;
  status: string;
  remark: string | null;
  created_at: string | null;
}
