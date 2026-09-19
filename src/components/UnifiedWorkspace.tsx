import React, { useState, useEffect } from "react";
import {
  Copy,
  Download,
  Check,
  ExternalLink,
  Loader2,
  SlidersHorizontal,
  FileCode,
  RotateCcw,
  ArrowUpRight,
  Route,
  Zap,
  Filter,
  Search,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  Share2,
  Layers,
  ArrowRight,
  ShieldCheck,
  AlertTriangle,
  FileText
} from "lucide-react";
import {
  ExtractedItem,
  ResolveChainItem,
  UnifiedResponse,
  UnifiedResult,
  PipelineMode
} from "../types";
import { parseHTMLClientSide, isBlockedTestLink } from "../utils/pythonExtractor";
import { safeFetchJson } from "../utils/safeFetch";
import { SAMPLES } from "../data/samples";
import { EpisodeButtonsGenerator } from "./EpisodeButtonsGenerator";

interface UnifiedWorkspaceProps {
  onNotify: (text: string, type?: "success" | "error" | "info") => void;
  onOpenPythonLogic?: () => void;
  initialUrl?: string;
  initialMode?: PipelineMode;
}

export const UnifiedWorkspace: React.FC<UnifiedWorkspaceProps> = ({
  onNotify,
  onOpenPythonLogic,
  initialUrl,
  initialMode = "unified",
}) => {
  const [pipelineMode, setPipelineMode] = useState<PipelineMode>(initialMode);
  const [urlInput, setUrlInput] = useState<string>(initialUrl || "");
  const [htmlInput, setHtmlInput] = useState<string>("");
  const [inputTab, setInputTab] = useState<"url" | "html">("url");
  const [buttonOnly, setButtonOnly] = useState<boolean>(true);
  const [loading, setLoading] = useState<boolean>(false);
  const [loadingStage, setLoadingStage] = useState<string>("");
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Unified execution result
  const [result, setResult] = useState<UnifiedResult | null>(null);
  const [showRedirectChain, setShowRedirectChain] = useState<boolean>(false);
  const [showExtractedLinks, setShowExtractedLinks] = useState<boolean>(false);
  const [searchQuery, setSearchQuery] = useState<string>("");

  // Copy states
  const [copiedItemIndex, setCopiedItemIndex] = useState<number | null>(null);
  const [copiedChainIndex, setCopiedChainIndex] = useState<number | null>(null);
  const [copiedAllUrls, setCopiedAllUrls] = useState<boolean>(false);
  const [copiedFinalUrl, setCopiedFinalUrl] = useState<boolean>(false);

  // Auto-run if initialUrl provided
  useEffect(() => {
    if (initialUrl && initialUrl.trim()) {
      setUrlInput(initialUrl.trim());
      handleExecute(initialUrl.trim(), undefined, pipelineMode);
    }
  }, [initialUrl]);

  const handleExecute = async (
    overrideUrl?: string,
    overrideHtml?: string,
    modeToUse?: PipelineMode
  ) => {
    const activePipeline = modeToUse || pipelineMode;
    const targetUrl = (overrideUrl !== undefined ? overrideUrl : urlInput).trim();
    const targetHtml = overrideHtml !== undefined ? overrideHtml : htmlInput;

    setErrorMessage(null);

    if (inputTab === "url" && !overrideHtml) {
      if (!/^https?:\/\//i.test(targetUrl)) {
        setErrorMessage("Please enter a valid http:// or https:// URL.");
        onNotify("Enter a valid http:// or https:// URL", "error");
        return;
      }
    } else {
      if (!targetHtml.trim()) {
        setErrorMessage("Please paste HTML content to process.");
        onNotify("HTML snippet is empty", "error");
        return;
      }
    }

    setLoading(true);

    if (activePipeline === "unified") {
      setLoadingStage("Bypassing shorteners & tracing redirects...");
    } else if (activePipeline === "resolver") {
      setLoadingStage("Tracing shortlink hops & interstitials...");
    } else {
      setLoadingStage("Extracting action & button links...");
    }

    try {
      if (activePipeline === "resolver" && inputTab === "url" && !overrideHtml) {
        // Run Resolver endpoint
        const fetchRes = await safeFetchJson<{
          success: boolean;
          data?: {
            original: string;
            final: string;
            redirects: number;
            chain: ResolveChainItem[];
          };
          error?: string;
        }>("/api/resolve", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ url: targetUrl }),
        });

        if (!fetchRes.ok || !fetchRes.data?.success || !fetchRes.data.data) {
          throw new Error(
            fetchRes.error ||
              fetchRes.data?.error ||
              "Failed to resolve URL."
          );
        }
        const resolveData = fetchRes.data.data;
        const unifiedData: UnifiedResult = {
          success: true,
          resolved: true,
          original_url: resolveData.original,
          final_url: resolveData.final,
          redirects: resolveData.redirects,
          chain: resolveData.chain,
          items: [],
          count: 0,
        };
        setResult(unifiedData);
        setShowRedirectChain(false);
        setStatusMessage(
          `Resolved! ${resolveData.redirects} redirect hop${
            resolveData.redirects === 1 ? "" : "s"
          } bypassed.`
        );
        onNotify(
          `Resolved ${resolveData.redirects} redirect${
            resolveData.redirects === 1 ? "" : "s"
          }`,
          "success"
        );
      } else {
        // Run Unified Pipeline endpoint
        const isUnified = activePipeline === "unified";
        const fetchRes = await safeFetchJson<UnifiedResponse>("/api/unified", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            url: inputTab === "url" && !overrideHtml ? targetUrl : undefined,
            html: inputTab === "html" || overrideHtml ? targetHtml : undefined,
            base_url: targetUrl || "https://example.com/page",
            button_only: buttonOnly,
            auto_resolve: isUnified,
          }),
        });

        if (!fetchRes.ok || !fetchRes.data || !fetchRes.data.success) {
          throw new Error(
            fetchRes.error ||
              fetchRes.data?.error ||
              "Execution failed on server."
          );
        }

        const data: UnifiedResponse = fetchRes.data;

        // Clean & filter test links
        const cleanItems = (data.items || []).filter(
          (it) => !isBlockedTestLink(it.text, it.url)
        );

        const fullResult: UnifiedResult = {
          success: true,
          resolved: Boolean(data.resolved),
          original_url: data.original_url || targetUrl,
          final_url: data.final_url || targetUrl,
          redirects: data.redirects || 0,
          chain: data.chain || [],
          items: cleanItems,
          count: cleanItems.length,
          bytes: data.bytes,
          fetch_warning: data.fetch_warning,
        };

        setResult(fullResult);
        setShowRedirectChain(false);
        setShowExtractedLinks(false);

        if (fullResult.resolved && fullResult.redirects > 0) {
          setStatusMessage(
            `Success: ${fullResult.redirects} redirect${
              fullResult.redirects === 1 ? "" : "s"
            } bypassed & ${cleanItems.length} action link${
              cleanItems.length === 1 ? "" : "s"
            } extracted!`
          );
          onNotify(
            `Bypassed ${fullResult.redirects} hops and extracted ${cleanItems.length} links`,
            "success"
          );
        } else {
          setStatusMessage(
            `Extracted ${cleanItems.length} action & download link${
              cleanItems.length === 1 ? "" : "s"
            }.`
          );
          onNotify(`Found ${cleanItems.length} links`, "success");
        }
      }
    } catch (err: any) {
      // Fallback: If client is dealing with raw HTML offline
      if (inputTab === "html" || overrideHtml) {
        try {
          const clientParsed = parseHTMLClientSide(
            targetHtml,
            targetUrl || "https://example.com"
          );
          const filtered = clientParsed.filter(
            (it) => !isBlockedTestLink(it.text, it.url)
          );
          const fallbackResult: UnifiedResult = {
            success: true,
            resolved: false,
            original_url: targetUrl || "raw_html",
            final_url: targetUrl || "https://example.com",
            redirects: 0,
            chain: [],
            items: filtered,
            count: filtered.length,
            bytes: targetHtml.length,
          };
          setResult(fallbackResult);
          setStatusMessage(
            `Extracted ${filtered.length} link${
              filtered.length === 1 ? "" : "s"
            } using in-browser fallback engine.`
          );
          onNotify(`Parsed ${filtered.length} links offline`, "info");
        } catch {
          setErrorMessage(err.message || "Failed to process content.");
          onNotify(err.message || "Processing error", "error");
        }
      } else {
        setErrorMessage(err.message || "Failed to resolve or extract.");
        onNotify(err.message || "Error", "error");
      }
    } finally {
      setLoading(false);
      setLoadingStage("");
    }
  };

  const handleCopyFinalUrl = async () => {
    if (!result?.final_url) return;
    try {
      await navigator.clipboard.writeText(result.final_url);
      setCopiedFinalUrl(true);
      onNotify("Final URL copied to clipboard", "success");
      setTimeout(() => setCopiedFinalUrl(false), 1800);
    } catch {
      onNotify("Failed to copy URL", "error");
    }
  };

  const handleCopyAllUrls = async () => {
    if (!result?.items || result.items.length === 0) return;
    try {
      const text = result.items.map((it) => it.url).join("\n");
      await navigator.clipboard.writeText(text);
      setCopiedAllUrls(true);
      onNotify(`Copied all ${result.items.length} URLs to clipboard`, "success");
      setTimeout(() => setCopiedAllUrls(false), 1800);
    } catch {
      onNotify("Failed to copy all URLs", "error");
    }
  };

  const handleCopyItemUrl = async (url: string, index: number) => {
    try {
      await navigator.clipboard.writeText(url);
      setCopiedItemIndex(index);
      onNotify("URL copied to clipboard", "success");
      setTimeout(() => setCopiedItemIndex(null), 1500);
    } catch {
      onNotify("Failed to copy", "error");
    }
  };

  const handleCopyChainStep = async (url: string, index: number) => {
    try {
      await navigator.clipboard.writeText(url);
      setCopiedChainIndex(index);
      onNotify(`Step ${index + 1} URL copied`, "success");
      setTimeout(() => setCopiedChainIndex(null), 1500);
    } catch {
      onNotify("Failed to copy", "error");
    }
  };

  const handleResolveSingleItem = (shortUrl: string) => {
    setUrlInput(shortUrl);
    setInputTab("url");
    setPipelineMode("unified");
    handleExecute(shortUrl, undefined, "unified");
    onNotify(`Resolving selected shortlink...`, "info");
  };

  const handleExportJson = () => {
    if (!result) return;
    const blob = new Blob([JSON.stringify(result, null, 2)], {
      type: "application/json",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `resolved-links-${Date.now()}.json`;
    a.click();
    URL.revokeObjectURL(url);
    onNotify("JSON results exported", "success");
  };

  const handleReset = () => {
    setUrlInput("");
    setHtmlInput("");
    setResult(null);
    setErrorMessage(null);
    setStatusMessage(null);
    setSearchQuery("");
  };

  // Filtered items based on search
  const filteredItems = (result?.items || []).filter((it) => {
    if (!searchQuery.trim()) return true;
    const q = searchQuery.toLowerCase();
    return (
      it.text.toLowerCase().includes(q) ||
      it.url.toLowerCase().includes(q) ||
      (it.type || "").toLowerCase().includes(q)
    );
  });

  const isShortlink = (url: string) => {
    return (
      url.includes("shrt.") ||
      url.includes("bit.ly") ||
      url.includes("tinyurl.com") ||
      url.includes("sohojgyan") ||
      url.includes("ouo.io") ||
      url.includes("short.") ||
      url.includes("linkvertise") ||
      url.includes("adf.ly")
    );
  };

  return (
    <div id="unified-workspace-container" className="space-y-5">
      {/* Top Controls Card (M3 Elevated Surface Card) */}
      <div
        id="unified-control-card"
        className="bg-[#fdfcff] rounded-[28px] m3-elevation-1 border border-[#e1e7f0] overflow-hidden transition-shadow hover:m3-elevation-2"
      >
        {/* Card Header */}
        <div className="px-5 sm:px-7 pt-6 pb-4 flex flex-wrap items-center justify-between gap-4 border-b border-[#f0f4f9]">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-2xl bg-[#d3e3fd] text-[#041e49] flex items-center justify-center shrink-0">
              <Zap className="w-5 h-5 text-[#0b57d0]" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-base sm:text-lg font-semibold tracking-tight text-[#1f1f1f]">
                  Resolver & Link Extractor
                </h2>
                <span className="text-[11px] font-medium px-2 py-0.5 rounded-full bg-[#c2e7ff] text-[#001d35]">
                  Auto-Bypass
                </span>
              </div>
              <p className="text-xs text-[#444746] hidden sm:block mt-0.5">
                Resolve shortlinks, bypass safelinks, and extract final episode & download links
              </p>
            </div>
          </div>

          {/* M3 Segmented Button for Mode Switcher */}
          <div className="flex items-center p-1 bg-[#f0f4f9] rounded-full border border-[#e1e7f0]/60">
            <button
              id="mode-btn-unified"
              onClick={() => setPipelineMode("unified")}
              className={`px-3.5 py-1.5 rounded-full text-xs font-medium flex items-center gap-1.5 cursor-pointer transition-all ${
                pipelineMode === "unified"
                  ? "bg-[#0b57d0] text-white shadow-xs"
                  : "text-[#444746] hover:text-[#1f1f1f]"
              }`}
              title="Resolve shorteners and extract links from final destination"
            >
              <Zap className="w-3.5 h-3.5" />
              <span>Resolve & Extract</span>
            </button>
            <button
              id="mode-btn-extractor"
              onClick={() => setPipelineMode("extractor")}
              className={`px-3.5 py-1.5 rounded-full text-xs font-medium flex items-center gap-1.5 cursor-pointer transition-all ${
                pipelineMode === "extractor"
                  ? "bg-[#0b57d0] text-white shadow-xs"
                  : "text-[#444746] hover:text-[#1f1f1f]"
              }`}
              title="Extract links directly without following redirect hops"
            >
              <Download className="w-3.5 h-3.5" />
              <span>Extract Only</span>
            </button>
            <button
              id="mode-btn-resolver"
              onClick={() => setPipelineMode("resolver")}
              className={`px-3.5 py-1.5 rounded-full text-xs font-medium flex items-center gap-1.5 cursor-pointer transition-all ${
                pipelineMode === "resolver"
                  ? "bg-[#0b57d0] text-white shadow-xs"
                  : "text-[#444746] hover:text-[#1f1f1f]"
              }`}
              title="Trace redirect chain and shortlinks without extracting page contents"
            >
              <Route className="w-3.5 h-3.5" />
              <span>Resolve Only</span>
            </button>
          </div>
        </div>

        {/* Input Form Body */}
        <div className="p-5 sm:p-7 space-y-5">
          {/* M3 Filter Chips for URL vs HTML */}
          <div className="flex items-center justify-between gap-3 flex-wrap">
            <div className="flex items-center gap-2">
              <button
                id="input-tab-url"
                onClick={() => setInputTab("url")}
                className={`text-xs font-medium px-4 py-2 rounded-full transition-colors cursor-pointer flex items-center gap-2 ${
                  inputTab === "url"
                    ? "bg-[#d3e3fd] text-[#041e49] border border-[#a8c7fa]"
                    : "bg-[#f0f4f9] text-[#444746] hover:bg-[#e9eef6] border border-transparent"
                }`}
              >
                <Route className="w-3.5 h-3.5 text-[#0b57d0]" />
                <span>Webpage or Shortlink URL</span>
              </button>
              <button
                id="input-tab-html"
                onClick={() => setInputTab("html")}
                className={`text-xs font-medium px-4 py-2 rounded-full transition-colors cursor-pointer flex items-center gap-2 ${
                  inputTab === "html"
                    ? "bg-[#d3e3fd] text-[#041e49] border border-[#a8c7fa]"
                    : "bg-[#f0f4f9] text-[#444746] hover:bg-[#e9eef6] border border-transparent"
                }`}
              >
                <FileCode className="w-3.5 h-3.5 text-[#0b57d0]" />
                <span>Raw HTML Content</span>
              </button>
            </div>

            {/* M3 Switch / Checkbox: Action links only */}
            {pipelineMode !== "resolver" && (
              <label
                id="label-button-only"
                className="flex items-center gap-2 text-xs font-medium text-[#444746] hover:text-[#1f1f1f] cursor-pointer select-none bg-[#f8fafd] px-3 py-1.5 rounded-full border border-[#e1e7f0]"
              >
                <input
                  id="checkbox-button-only"
                  type="checkbox"
                  checked={buttonOnly}
                  onChange={(e) => setButtonOnly(e.target.checked)}
                  className="rounded border-[#747775] text-[#0b57d0] focus:ring-[#0b57d0] h-4 w-4 cursor-pointer accent-[#0b57d0]"
                />
                <span className="hidden sm:inline">Action & download links only</span>
                <span className="sm:hidden">Action links</span>
              </label>
            )}
          </div>

          {/* URL Input Box - M3 Outlined Text Field */}
          {inputTab === "url" ? (
            <div className="space-y-2">
              <div className="relative flex items-center">
                <input
                  id="input-target-url"
                  type="url"
                  value={urlInput}
                  onChange={(e) => setUrlInput(e.target.value)}
                  onKeyDown={(e) => e.key === "Enter" && handleExecute()}
                  placeholder={
                    pipelineMode === "unified"
                      ? "Enter drama post, article, or shortlink (e.g. https://mydverse.com/... or https://shrt.sohojgyan.com/...)"
                      : pipelineMode === "resolver"
                      ? "Enter shortened URL (e.g. https://shrt.sohojgyan.com/Ij03ndJ)"
                      : "Enter webpage URL to extract links from..."
                  }
                  className="w-full pl-4 pr-20 py-3.5 text-sm bg-white border border-[#747775] hover:border-[#1f1f1f] focus:border-[#0b57d0] focus:border-2 rounded-2xl focus:outline-none transition-all font-mono text-[#1f1f1f] placeholder:text-[#747775]"
                />
                {urlInput && (
                  <button
                    id="btn-clear-url"
                    onClick={() => setUrlInput("")}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[#444746] hover:text-[#1f1f1f] bg-[#f0f4f9] hover:bg-[#e9eef6] px-2.5 py-1 rounded-full cursor-pointer transition-colors"
                  >
                    Clear
                  </button>
                )}
              </div>

              {/* Quick Sample Links for One-Click Testing */}
              <div className="flex items-center gap-1.5 flex-wrap pt-1 text-xs">
                <span className="text-[#747775] font-medium text-[11px]">Quick Samples:</span>
                <button
                  type="button"
                  onClick={() => {
                    const u = "https://shrt.sohojgyan.com/Ij03ndJ";
                    setUrlInput(u);
                    handleExecute(u);
                  }}
                  className="px-2.5 py-1 rounded-full bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] border border-[#e1e7f0] cursor-pointer transition-colors text-[11px]"
                >
                  ⚡ Shortlink 1 (Ij03ndJ)
                </button>
                <button
                  type="button"
                  onClick={() => {
                    const u = "https://shrt.sohojgyan.com/AAhg";
                    setUrlInput(u);
                    handleExecute(u);
                  }}
                  className="px-2.5 py-1 rounded-full bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] border border-[#e1e7f0] cursor-pointer transition-colors text-[11px]"
                >
                  ⚡ Shortlink 2 (AAhg)
                </button>
                <button
                  type="button"
                  onClick={() => {
                    const u = "https://mydverse.com/2026/09/fanletter-please-korean-drama-in-hindi/";
                    setUrlInput(u);
                    handleExecute(u);
                  }}
                  className="px-2.5 py-1 rounded-full bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] border border-[#e1e7f0] cursor-pointer transition-colors text-[11px]"
                >
                  🎬 Fanletter Please (Post)
                </button>
                <button
                  type="button"
                  onClick={() => {
                    const u = "https://mydverse.com/2026/09/the-tale-of-lady-ok-korean-drama-in-hindi/";
                    setUrlInput(u);
                    handleExecute(u);
                  }}
                  className="px-2.5 py-1 rounded-full bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] border border-[#e1e7f0] cursor-pointer transition-colors text-[11px]"
                >
                  🎬 Tale of Lady Ok (Post)
                </button>
                <button
                  type="button"
                  onClick={() => {
                    const u = "https://mydverse02.blogspot.com/p/flp-120926.html";
                    setUrlInput(u);
                    handleExecute(u);
                  }}
                  className="px-2.5 py-1 rounded-full bg-[#e8f0fe] hover:bg-[#c2e7ff] text-[#0b57d0] border border-[#a8c7fa] cursor-pointer transition-colors text-[11px]"
                >
                  🎯 Destination Target
                </button>
              </div>
            </div>
          ) : (
            /* HTML Input Box - M3 Outlined Text Area */
            <div className="space-y-2">
              <textarea
                id="textarea-html-content"
                rows={6}
                value={htmlInput}
                onChange={(e) => setHtmlInput(e.target.value)}
                placeholder="Paste HTML source code containing <a href>, <button>, <input> download buttons..."
                className="w-full p-4 text-xs bg-white border border-[#747775] hover:border-[#1f1f1f] focus:border-[#0b57d0] focus:border-2 rounded-2xl focus:outline-none transition-all font-mono text-[#1f1f1f] placeholder:text-[#747775]"
              />
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="text-xs font-medium text-[#747775]">Sample templates:</span>
                  {SAMPLES.filter((s) => s.html).map((sample) => (
                    <button
                      key={sample.id}
                      id={`btn-sample-html-${sample.id}`}
                      onClick={() => {
                        setHtmlInput(sample.html);
                        handleExecute(undefined, sample.html);
                      }}
                      className="text-xs bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] px-3 py-1 rounded-full border border-[#e1e7f0] cursor-pointer transition-colors"
                    >
                      {sample.name}
                    </button>
                  ))}
                </div>
                {htmlInput && (
                  <button
                    onClick={() => setHtmlInput("")}
                    className="text-xs text-[#444746] hover:text-[#1f1f1f] cursor-pointer px-2 py-1 rounded-full"
                  >
                    Clear HTML
                  </button>
                )}
              </div>
            </div>
          )}

          {/* Action Buttons & Status (M3 Bottom Row) */}
          <div className="pt-3 flex flex-wrap items-center justify-between gap-3 border-t border-[#f0f4f9]">
            <div className="text-xs text-[#444746] truncate flex items-center gap-2">
              {loading ? (
                <div className="flex items-center gap-2 text-[#0b57d0] font-medium">
                  <Loader2 className="w-4 h-4 animate-spin shrink-0" />
                  <span>{loadingStage || "Processing pipeline..."}</span>
                </div>
              ) : errorMessage ? (
                <div className="flex items-center gap-1.5 text-[#ba1a1a] font-medium truncate">
                  <AlertTriangle className="w-4 h-4 shrink-0" />
                  <span className="truncate">{errorMessage}</span>
                </div>
              ) : statusMessage ? (
                <div className="flex items-center gap-1.5 text-[#00639b] font-medium truncate">
                  <CheckCircle2 className="w-4 h-4 shrink-0 text-[#0b57d0]" />
                  <span className="truncate">{statusMessage}</span>
                </div>
              ) : (
                <span className="text-[#747775]">
                  {pipelineMode === "unified"
                    ? "Ready: Resolves redirect chains, bypasses safelinks & extracts links"
                    : pipelineMode === "resolver"
                    ? "Ready: Traces HTTP 301/302, AdLinkFly, Blogger and WordPress safelinks"
                    : "Ready: Parses HTML elements & extracts buttons"}
                </span>
              )}
            </div>

            <div className="flex items-center gap-2.5 shrink-0">
              {result && (
                <button
                  id="btn-reset-workspace"
                  onClick={handleReset}
                  className="h-10 px-4 text-xs font-medium text-[#444746] hover:text-[#1f1f1f] bg-[#f0f4f9] hover:bg-[#e9eef6] rounded-full transition-colors cursor-pointer flex items-center gap-1.5"
                  title="Reset workspace"
                >
                  <RotateCcw className="w-4 h-4" />
                  <span className="hidden sm:inline">Reset</span>
                </button>
              )}

              {/* M3 Filled Button for Primary Execution */}
              <button
                id="btn-execute-pipeline"
                onClick={() => handleExecute()}
                disabled={loading}
                className="h-10 px-6 bg-[#0b57d0] hover:bg-[#0842a0] active:bg-[#041e49] disabled:opacity-50 text-white text-xs font-medium rounded-full m3-elevation-1 hover:m3-elevation-2 transition-all flex items-center gap-2 cursor-pointer"
              >
                {loading ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    <span>Processing...</span>
                  </>
                ) : (
                  <>
                    {pipelineMode === "unified" ? (
                      <>
                        <Zap className="w-4 h-4 text-[#c2e7ff]" />
                        <span>Resolve & Extract</span>
                      </>
                    ) : pipelineMode === "resolver" ? (
                      <>
                        <Route className="w-4 h-4 text-[#c2e7ff]" />
                        <span>Trace Redirects</span>
                      </>
                    ) : (
                      <>
                        <Download className="w-4 h-4 text-[#c2e7ff]" />
                        <span>Extract Links</span>
                      </>
                    )}
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Results Section */}
      {result && (
        <div id="unified-results-wrapper" className="space-y-5">
          {/* 1. Pipeline Summary Banner (M3 Elevated Card) */}
          <div
            id="pipeline-summary-card"
            className="bg-[#fdfcff] rounded-[28px] m3-elevation-1 border border-[#e1e7f0] p-5 sm:p-6 space-y-4"
          >
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#f0f4f9] pb-4">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-full bg-[#c2e7ff] text-[#001d35] flex items-center justify-center shrink-0">
                  <Check className="w-4 h-4 stroke-[2.5]" />
                </div>
                <div>
                  <h3 className="text-base font-semibold text-[#1f1f1f]">
                    {result.resolved && result.redirects > 0
                      ? "Resolution & Extraction Complete"
                      : result.resolved
                      ? "Resolution Complete"
                      : "Extraction Complete"}
                  </h3>
                  <p className="text-xs text-[#444746] mt-0.5">
                    Pipeline successfully executed via Python backend
                  </p>
                </div>
              </div>

              {/* M3 Badges & Chips */}
              <div className="flex items-center gap-2 flex-wrap">
                {result.resolved && (
                  <span
                    id="chip-redirects-count"
                    className="text-xs font-medium px-3 py-1 rounded-full bg-[#e8def8] text-[#1d192b] border border-[#d0bcff]/50"
                  >
                    {result.redirects} Redirect Hop{result.redirects === 1 ? "" : "s"} Bypassed
                  </span>
                )}
                {pipelineMode !== "resolver" && (
                  <span
                    id="chip-extracted-count"
                    className="text-xs font-medium px-3 py-1 rounded-full bg-[#d3e3fd] text-[#041e49] border border-[#a8c7fa]/60"
                  >
                    {result.count} Action Link{result.count === 1 ? "" : "s"} Found
                  </span>
                )}
                {result.bytes && result.bytes > 0 && (
                  <span className="text-xs font-mono text-[#444746] bg-[#f0f4f9] px-2.5 py-1 rounded-full border border-[#e1e7f0]">
                    {(result.bytes / 1024).toFixed(1)} KB
                  </span>
                )}
              </div>
            </div>

            {/* Source & Destination URLs */}
            <div className="grid grid-cols-1 gap-2.5 pt-1 text-xs font-mono">
              {result.original_url && result.original_url !== result.final_url && (
                <div className="flex items-start gap-2.5 bg-[#f8fafd] p-3 rounded-2xl border border-[#e1e7f0] text-[#444746]">
                  <span className="font-semibold text-[10px] text-[#747775] uppercase tracking-wider shrink-0 mt-0.5">
                    Original URL:
                  </span>
                  <span className="break-all select-all text-[#1f1f1f]">{result.original_url}</span>
                </div>
              )}

              <div className="flex items-center justify-between gap-3 bg-[#e8f0fe] p-3.5 rounded-2xl border border-[#c2e7ff] text-[#041e49]">
                <div className="flex items-start gap-2.5 min-w-0">
                  <span className="font-semibold text-[10px] text-[#0b57d0] uppercase tracking-wider shrink-0 mt-0.5">
                    Final Destination:
                  </span>
                  <span className="break-all font-medium select-all text-[#041e49]">
                    {result.final_url}
                  </span>
                </div>
                <div className="flex items-center gap-1.5 shrink-0">
                  <button
                    id="btn-copy-final-url"
                    onClick={handleCopyFinalUrl}
                    className="p-2 bg-white hover:bg-[#d3e3fd] text-[#0b57d0] rounded-full border border-[#c2e7ff] transition-colors cursor-pointer"
                    title="Copy final URL"
                  >
                    {copiedFinalUrl ? (
                      <Check className="w-3.5 h-3.5 text-[#0b57d0]" />
                    ) : (
                      <Copy className="w-3.5 h-3.5" />
                    )}
                  </button>
                  <a
                    id="btn-visit-final-url"
                    href={result.final_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="p-2 bg-white hover:bg-[#d3e3fd] text-[#0b57d0] rounded-full border border-[#c2e7ff] transition-colors cursor-pointer"
                    title="Open in new tab"
                  >
                    <ExternalLink className="w-3.5 h-3.5" />
                  </a>
                </div>
              </div>
            </div>
          </div>

          {/* 2. Interactive Hop-by-Hop Redirect Chain (Accordion) */}
          {result.chain && result.chain.length > 0 && (
            <div
              id="redirect-chain-card"
              className="bg-[#fdfcff] rounded-[28px] m3-elevation-1 border border-[#e1e7f0] overflow-hidden"
            >
              <button
                id="btn-toggle-redirect-chain"
                onClick={() => setShowRedirectChain(!showRedirectChain)}
                className="w-full px-5 py-4 sm:px-6 flex items-center justify-between text-left hover:bg-[#f8fafd] transition-colors cursor-pointer border-b border-[#f0f4f9]"
              >
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-full bg-[#e8def8] text-[#1d192b] flex items-center justify-center shrink-0">
                    <Route className="w-4 h-4 text-[#6750a4]" />
                  </div>
                  <span className="font-semibold text-sm sm:text-base text-[#1f1f1f]">
                    Redirect & Bypass Chain
                  </span>
                  <span className="text-xs font-medium px-2.5 py-0.5 rounded-full bg-[#f0f4f9] text-[#444746] border border-[#e1e7f0]">
                    {result.chain.length} Hops
                  </span>
                </div>
                <div className="flex items-center gap-1.5 text-[#444746] text-xs font-medium">
                  <span>{showRedirectChain ? "Collapse" : "Expand"}</span>
                  {showRedirectChain ? (
                    <ChevronUp className="w-4 h-4" />
                  ) : (
                    <ChevronDown className="w-4 h-4" />
                  )}
                </div>
              </button>

              {showRedirectChain && (
                <div className="p-5 sm:p-6 space-y-3 bg-[#f8fafd]">
                  <p className="text-xs text-[#444746]">
                    Every intermediate hop captured, bypassed, and resolved by the Python engine:
                  </p>

                  <div className="space-y-2.5">
                    {result.chain.map((hop, idx) => (
                      <div
                        key={idx}
                        id={`hop-step-${hop.step}`}
                        className="p-3.5 bg-white rounded-2xl border border-[#e1e7f0] flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-[#a8c7fa] transition-colors shadow-2xs"
                      >
                        <div className="flex items-start gap-3 min-w-0">
                          <span className="w-6 h-6 rounded-full bg-[#e8def8] text-[#1d192b] font-semibold text-xs flex items-center justify-center shrink-0 mt-0.5">
                            {hop.step}
                          </span>
                          <div className="min-w-0 space-y-1">
                            <div className="flex items-center gap-2 flex-wrap">
                              <span
                                className={`text-[10px] font-semibold px-2 py-0.5 rounded-full font-mono ${
                                  hop.status >= 300 && hop.status < 400
                                    ? "bg-[#ffdad6] text-[#410002]"
                                    : "bg-[#d3e3fd] text-[#041e49]"
                                }`}
                              >
                                {hop.status}
                              </span>
                              {hop.type && (
                                <span className="text-[11px] font-medium text-[#444746] bg-[#f0f4f9] px-2.5 py-0.5 rounded-full border border-[#e1e7f0]">
                                  {hop.type}
                                </span>
                              )}
                            </div>
                            <p className="font-mono text-xs text-[#1f1f1f] break-all select-all">
                              {hop.url}
                            </p>
                          </div>
                        </div>

                        <div className="flex items-center gap-1.5 self-end sm:self-center shrink-0">
                          <button
                            id={`btn-copy-hop-${hop.step}`}
                            onClick={() => handleCopyChainStep(hop.url, idx)}
                            className="p-2 text-[#444746] hover:text-[#1f1f1f] bg-[#f0f4f9] hover:bg-[#e9eef6] rounded-full border border-[#e1e7f0] transition-colors cursor-pointer"
                            title="Copy this hop URL"
                          >
                            {copiedChainIndex === idx ? (
                              <Check className="w-3.5 h-3.5 text-[#0b57d0]" />
                            ) : (
                              <Copy className="w-3.5 h-3.5" />
                            )}
                          </button>
                          <a
                            href={hop.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="p-2 text-[#444746] hover:text-[#1f1f1f] bg-[#f0f4f9] hover:bg-[#e9eef6] rounded-full border border-[#e1e7f0] transition-colors cursor-pointer"
                            title="Visit URL in new tab"
                          >
                            <ExternalLink className="w-3.5 h-3.5" />
                          </a>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}

          {/* 3. Extracted Action & Download Links Grid / Table */}
          {pipelineMode !== "resolver" && (
            <div
              id="extracted-links-card"
              className="bg-[#fdfcff] rounded-[28px] m3-elevation-1 border border-[#e1e7f0] overflow-hidden"
            >
              {/* Header with Search, Filter & Collapse Toggle */}
              <div className="p-4 sm:p-5 border-b border-[#f0f4f9] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <button
                  id="btn-toggle-extracted-links"
                  type="button"
                  onClick={() => setShowExtractedLinks((prev) => !prev)}
                  className="flex items-center gap-3 text-left cursor-pointer hover:opacity-85 transition-opacity select-none group"
                  aria-expanded={showExtractedLinks}
                >
                  <div className="w-8 h-8 rounded-full bg-[#d3e3fd] text-[#041e49] flex items-center justify-center shrink-0">
                    <Download className="w-4 h-4 text-[#0b57d0]" />
                  </div>
                  <div>
                    <h3 className="font-semibold text-sm sm:text-base text-[#1f1f1f] flex items-center gap-2">
                      <span>Extracted Action & Download Links</span>
                    </h3>
                  </div>
                  <span className="text-xs font-medium px-2.5 py-0.5 rounded-full bg-[#c2e7ff] text-[#001d35]">
                    {result.items.length} Total
                  </span>
                  <div className="flex items-center gap-1 text-[#444746] text-xs font-medium ml-1">
                    <span>{showExtractedLinks ? "Collapse" : "Expand"}</span>
                    {showExtractedLinks ? (
                      <ChevronUp className="w-4 h-4" />
                    ) : (
                      <ChevronDown className="w-4 h-4" />
                    )}
                  </div>
                </button>

                {/* Search & Actions */}
                <div className="flex items-center gap-2 flex-wrap">
                  {showExtractedLinks && (
                    <div className="relative">
                      <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-[#747775]" />
                      <input
                        id="input-filter-links"
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Filter links..."
                        className="pl-9 pr-3 py-1.5 text-xs bg-[#f0f4f9] border border-[#e1e7f0] rounded-full focus:outline-none focus:border-[#0b57d0] focus:border-2 w-36 sm:w-44 text-[#1f1f1f]"
                      />
                    </div>
                  )}

                  <button
                    id="btn-copy-all-links"
                    onClick={handleCopyAllUrls}
                    className="px-3.5 py-1.5 text-xs font-medium text-[#041e49] bg-[#d3e3fd] hover:bg-[#c2e7ff] rounded-full transition-colors flex items-center gap-1.5 cursor-pointer"
                    title="Copy all link URLs (one per line)"
                  >
                    {copiedAllUrls ? (
                      <Check className="w-3.5 h-3.5 text-[#0b57d0]" />
                    ) : (
                      <Copy className="w-3.5 h-3.5" />
                    )}
                    <span>Copy All</span>
                  </button>

                  <button
                    id="btn-export-json"
                    onClick={handleExportJson}
                    className="px-3.5 py-1.5 text-xs font-medium text-[#444746] bg-[#f0f4f9] hover:bg-[#e9eef6] rounded-full border border-[#e1e7f0] flex items-center gap-1.5 cursor-pointer transition-colors"
                    title="Export full JSON payload"
                  >
                    <Download className="w-3.5 h-3.5" />
                    <span>JSON</span>
                  </button>
                </div>
              </div>

              {/* Items List (Collapsible) */}
              {showExtractedLinks && (
                <div className="p-5 sm:p-6">
                  {filteredItems.length === 0 ? (
                    <div className="text-center py-10 text-[#747775] text-xs">
                      {result.items.length === 0
                        ? "No action links found on the destination page."
                        : "No links match your search query."}
                    </div>
                  ) : (
                    <div className="space-y-2.5">
                      {filteredItems.map((item, idx) => {
                        const isShort = isShortlink(item.url);
                        return (
                          <div
                            key={idx}
                            id={`extracted-link-${idx}`}
                            className="p-3.5 bg-[#f8fafd] hover:bg-white rounded-2xl border border-[#e1e7f0] hover:border-[#a8c7fa] transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs hover:m3-elevation-1"
                          >
                            <div className="min-w-0 space-y-1">
                              <div className="flex items-center gap-2 flex-wrap">
                                <span className="text-[10px] font-mono font-medium uppercase bg-[#e9eef6] text-[#444746] px-2 py-0.5 rounded-md">
                                  {`<${item.tag.toUpperCase()}>`}
                                </span>
                                <span className="text-xs font-semibold text-[#1f1f1f]">
                                  {item.text || "(no text)"}
                                </span>
                                {isShort && (
                                  <span className="text-[10px] font-medium px-2 py-0.5 rounded-full bg-[#fedfd8] text-[#5c1d00] flex items-center gap-1">
                                    <span>Shortlink</span>
                                  </span>
                                )}
                              </div>
                              <p className="font-mono text-xs text-[#444746] break-all select-all">
                                {item.url}
                              </p>
                            </div>

                            <div className="flex items-center gap-1.5 self-end sm:self-center shrink-0">
                              {isShort && (
                                <button
                                  id={`btn-resolve-item-${idx}`}
                                  onClick={() => handleResolveSingleItem(item.url)}
                                  className="px-3 py-1 text-xs font-medium bg-[#d3e3fd] hover:bg-[#c2e7ff] text-[#041e49] rounded-full flex items-center gap-1 cursor-pointer transition-colors"
                                  title="Resolve this shortlink directly"
                                >
                                  <Zap className="w-3 h-3 text-[#0b57d0]" />
                                  <span>Resolve</span>
                                </button>
                              )}

                              <button
                                id={`btn-copy-item-${idx}`}
                                onClick={() => handleCopyItemUrl(item.url, idx)}
                                className="p-2 text-[#444746] hover:text-[#1f1f1f] bg-white hover:bg-[#f0f4f9] rounded-full border border-[#e1e7f0] transition-colors cursor-pointer"
                                title="Copy URL"
                              >
                                {copiedItemIndex === idx ? (
                                  <Check className="w-3.5 h-3.5 text-[#0b57d0]" />
                                ) : (
                                  <Copy className="w-3.5 h-3.5" />
                                )}
                              </button>

                              <a
                                href={item.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="p-2 text-[#444746] hover:text-[#1f1f1f] bg-white hover:bg-[#f0f4f9] rounded-full border border-[#e1e7f0] transition-colors cursor-pointer"
                                title="Open in new tab"
                              >
                                <ExternalLink className="w-3.5 h-3.5" />
                              </a>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          {/* 4. Episode Buttons Generator */}
          {result.items && result.items.length > 0 && pipelineMode !== "resolver" && (
            <div id="episode-generator-wrapper" className="pt-2">
              <EpisodeButtonsGenerator
                items={result.items}
                onNotify={onNotify}
              />
            </div>
          )}
        </div>
      )}
    </div>
  );
};
