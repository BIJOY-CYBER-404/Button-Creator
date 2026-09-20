export interface ExtractedItem {
  type: string;
  tag: string;
  text: string;
  url: string;
  class?: string;
  id?: string;
}

export interface ExtractionResponse {
  success: boolean;
  final_url?: string;
  page_title?: string;
  bytes?: number;
  count?: number;
  items?: ExtractedItem[];
  error?: string;
}

export type ActivePage = "extractor" | "resolver";

export interface ResolveChainItem {
  step: number;
  url: string;
  status: number;
  type?: string;
}

export interface ResolveResult {
  original: string;
  final: string;
  redirects: number;
  chain: ResolveChainItem[];
  target_destination_verified?: boolean;
  attempts?: number;
}

export interface ResolveResponse {
  success: boolean;
  data?: ResolveResult;
  error?: string;
}

export interface UnifiedResult {
  success: boolean;
  resolved: boolean;
  original_url: string;
  final_url: string;
  page_title?: string;
  redirects: number;
  chain: ResolveChainItem[];
  items: ExtractedItem[];
  count: number;
  bytes?: number;
  target_destination_verified?: boolean;
  attempts?: number;
  fetch_warning?: string;
  error?: string;
}

export interface UnifiedResponse {
  success: boolean;
  data?: UnifiedResult;
  error?: string;
  resolved?: boolean;
  original_url?: string;
  final_url?: string;
  page_title?: string;
  redirects?: number;
  chain?: ResolveChainItem[];
  items?: ExtractedItem[];
  count?: number;
  bytes?: number;
  target_destination_verified?: boolean;
  attempts?: number;
  fetch_warning?: string;
}

export interface PageButton {
  text: string;
  url: string;
  quality?: string | null;
  episode?: number | null;
  provider?: string;
  is_button?: boolean;
}

export interface ButtonPage {
  id: string;
  slug: string;
  title: string;
  description?: string;
  source_url?: string;
  resolved_url?: string;
  theme?: "indigo" | "emerald" | "crimson" | "slate" | "dark";
  buttons: PageButton[];
  views: number;
  created_at: string;
  updated_at?: string;
}

export interface CreatePageResponse {
  success: boolean;
  data?: {
    id: string;
    slug: string;
    title: string;
    clean_url: string;
    view_url: string;
    resolved_url: string;
    target_valid: boolean;
    button_count: number;
    buttons: PageButton[];
    page: ButtonPage;
  };
  error?: string;
}

export type PipelineMode = "unified" | "extractor" | "resolver";

export type ViewTab = "admin_flow" | "pages_list" | "settings" | "updater" | "cpanel_hub" | "wp_plugin";

export interface SiteIdentity {
  site_name: string;
  site_logo_url: string;
  site_logo_icon: string;
  site_logo_text: string;
}

