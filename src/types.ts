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
  redirects: number;
  chain: ResolveChainItem[];
  items: ExtractedItem[];
  count: number;
  bytes?: number;
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
  redirects?: number;
  chain?: ResolveChainItem[];
  items?: ExtractedItem[];
  count?: number;
  bytes?: number;
  fetch_warning?: string;
}

export type PipelineMode = "unified" | "extractor" | "resolver";

export type ViewTab = "extractor" | "python_logic" | "html_tester";
