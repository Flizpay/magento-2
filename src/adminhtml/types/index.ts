export interface ConnectionStatusConfig {
  url: string;
  status: string;
}

export interface ConnectionStatusResponse {
  status: string;
  verifiedAt?: string | null;
}
