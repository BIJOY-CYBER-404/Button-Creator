import React, { useState } from "react";
import {
  Link2,
  Sparkles,
  Copy,
  ExternalLink,
  CheckCircle2,
  AlertCircle,
  Clock,
  ShieldCheck,
  Film,
  Eye,
  RefreshCw,
  Share2,
  Layers,
  ArrowRight
} from "lucide-react";
import { ButtonPage, PageButton, CreatePageResponse } from "../types";

interface AdminFlowGeneratorProps {
  adminToken: string;
  onPageCreated: (page: ButtonPage) => void;
  onViewPage: (slug: string) => void;
  onNotify?: (text: string, type: "success" | "error" | "info") => void;
}

export const AdminFlowGenerator: React.FC<AdminFlowGeneratorProps> = ({
  adminToken,
  onPageCreated,
  onViewPage,
  onNotify,
}) => {
  const [shortenUrl, setShortenUrl] = useState<string>("");
  const [customTitle, setCustomTitle] = useState<string>("");
  const [theme, setTheme] = useState<"indigo" | "emerald" | "crimson" | "slate" | "dark">("indigo");
  const [loading, setLoading] = useState<boolean>(false);
  const [step, setStep] = useState<"idle" | "resolving" | "extracting" | "creating" | "done">("idle");
  const [error, setError] = useState<string | null>(null);
  const [createdResult, setCreatedResult] = useState<CreatePageResponse["data"] | null>(null);

  const sampleShortlinks = [
    { label: "Shortlink 1 (Ij03ndJ)", url: "https://shrt.sohojgyan.com/Ij03ndJ" },
    { label: "Shortlink 2 (AAhg)", url: "https://shrt.sohojgyan.com/AAhg" },
    { label: "Target 1 (flp-120926)", url: "https://mydverse02.blogspot.com/p/flp-120926.html" },
    { label: "Target 2 (mbmb-030826)", url: "https://mydverse02.blogspot.com/p/mbmb-030826.html" },
    { label: "Drama Post", url: "https://mydverse.com/2026/09/fanletter-please-korean-drama-in-hindi/" },
  ];

  const handleGenerate = async (e?: React.FormEvent, overrideUrl?: string) => {
    if (e) e.preventDefault();
    const urlToProcess = overrideUrl || shortenUrl.trim();
    if (!urlToProcess) {
      onNotify?.("Please paste a shortened URL first.", "error");
      return;
    }

    setLoading(true);
    setError(null);
    setCreatedResult(null);
    setStep("resolving");

    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
      controller.abort();
    }, 35000);

    try {
      // Step 1: Trigger Resolve & Page Generation via server API
      const res = await fetch("/api/pages/create-from-shortlink", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${adminToken}`,
          "x-admin-token": adminToken,
        },
        body: JSON.stringify({
          url: urlToProcess,
          title_override: customTitle.trim() || undefined,
          theme,
        }),
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      const rawText = await res.text();
      let data: CreatePageResponse;
      try {
        data = JSON.parse(rawText);
      } catch (jsonErr) {
        if (res.status === 401) {
          throw new Error("Admin session expired. Please re-enter your admin password.");
        } else if (res.status >= 500) {
          throw new Error(`Server error (${res.status}): Engine timed out or encountered an internal error.`);
        } else {
          throw new Error("Unexpected server response: " + rawText.substring(0, 120));
        }
      }

      if (!res.ok || !data.success || !data.data) {
        throw new Error(data.error || "Failed to resolve and generate button page.");
      }

      setStep("done");
      setCreatedResult(data.data);
      onPageCreated(data.data.page);
      onNotify?.("✓ Episode Button Page created successfully!", "success");
    } catch (err: any) {
      clearTimeout(timeoutId);
      let msg = err.message || "An unexpected error occurred during processing.";
      if (err.name === "AbortError" || err.message?.includes("aborted")) {
        msg = "Request timed out after 35 seconds. The target shortlink host or destination did not respond in time. Please verify the URL or try again.";
      }
      setError(msg);
      setStep("idle");
      onNotify?.(msg, "error");
    } finally {
      setLoading(false);
    }
  };

  const copyLink = (linkToCopy: string) => {
    navigator.clipboard.writeText(linkToCopy);
    onNotify?.("Link copied to clipboard!", "success");
  };

  return (
    <div className="w-full space-y-6">
      {/* Main Generator Card */}
      <div className="bg-white rounded-2xl p-5 sm:p-7 border border-[#e0e4eb] shadow-xs space-y-5">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-[#f0f4f9]">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <span className="w-2.5 h-2.5 rounded-full bg-[#0b57d0] animate-pulse"></span>
              <h2 className="text-base sm:text-lg font-bold text-[#111827]">
                Shorten URL → Resolve → Extract → Create Page Flow
              </h2>
            </div>
            <p className="text-xs text-[#5f6368]">
              Automated 4-step pipeline to resolve shortlinks into Blogspot episode destinations, extract download buttons, and publish non-indexable public pages.
            </p>
          </div>
          <span className="text-[11px] font-semibold text-[#0b57d0] bg-[#e8f0fe] px-2.5 py-1 rounded-full self-start sm:self-auto shrink-0 border border-[#c2e7ff]">
            cPanel Admin Workflow
          </span>
        </div>

        {/* Input Form */}
        <form onSubmit={(e) => handleGenerate(e)} className="space-y-4">
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-[#444746] flex items-center justify-between">
              <span>Paste Shortened URL or Source Link:</span>
              <span className="text-[11px] text-[#747775] font-normal">
                AdLinkFly, Sohojgyan, Drama posts, Blogspot
              </span>
            </label>
            <div className="flex items-center gap-2">
              <div className="relative flex-1">
                <input
                  type="url"
                  value={shortenUrl}
                  onChange={(e) => setShortenUrl(e.target.value)}
                  placeholder="e.g. https://shrt.sohojgyan.com/Ij03ndJ or https://shrt.sohojgyan.com/AAhg"
                  required
                  className="w-full pl-9 pr-4 py-3 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-xs sm:text-sm font-medium font-mono text-[#1f1f1f] transition-all"
                />
                <Link2 className="w-4 h-4 text-[#747775] absolute left-3 top-3.5" />
              </div>

              <button
                type="submit"
                disabled={loading || !shortenUrl.trim()}
                className="px-5 py-3 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] disabled:bg-[#a8c7fa] text-white font-bold text-xs sm:text-sm flex items-center gap-2 cursor-pointer shrink-0 shadow-xs transition-all"
              >
                {loading ? (
                  <>
                    <RefreshCw className="w-4 h-4 animate-spin" />
                    <span>Processing...</span>
                  </>
                ) : (
                  <>
                    <Sparkles className="w-4 h-4" />
                    <span className="hidden sm:inline">Resolve & Create Page</span>
                    <span className="sm:hidden">Generate</span>
                  </>
                )}
              </button>
            </div>
          </div>

          {/* Quick Samples */}
          <div className="flex items-center gap-1.5 flex-wrap pt-0.5 text-xs">
            <span className="text-[#747775] font-medium text-[11px]">Quick Samples:</span>
            {sampleShortlinks.map((s, idx) => (
              <button
                key={`samp-${idx}`}
                type="button"
                onClick={() => {
                  setShortenUrl(s.url);
                  handleGenerate(undefined, s.url);
                }}
                className="px-2.5 py-1 rounded-full bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] border border-[#e1e7f0] cursor-pointer transition-colors text-[11px]"
              >
                {s.label}
              </button>
            ))}
          </div>

          {/* Optional Page Customization Accordion */}
          <div className="pt-2">
            <details className="text-xs group">
              <summary className="font-semibold text-[#5f6368] hover:text-[#0b57d0] cursor-pointer list-none flex items-center gap-1.5 select-none">
                <span className="transition-transform group-open:rotate-90">▸</span>
                <span>Optional Page Customization (Title, Theme)</span>
              </summary>
              <div className="pt-3 pl-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-[11px] font-semibold text-[#444746] block">
                    Custom Page Title (Optional)
                  </label>
                  <input
                    type="text"
                    value={customTitle}
                    onChange={(e) => setCustomTitle(e.target.value)}
                    placeholder="Auto-detected from Blogspot title if left blank"
                    className="w-full px-3 py-2 rounded-lg border border-[#c4c7c5] text-xs outline-none focus:border-[#0b57d0]"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-[11px] font-semibold text-[#444746] block">
                    Button Theme Color
                  </label>
                  <select
                    value={theme}
                    onChange={(e: any) => setTheme(e.target.value)}
                    className="w-full px-3 py-2 rounded-lg border border-[#c4c7c5] text-xs outline-none focus:border-[#0b57d0] bg-white"
                  >
                    <option value="indigo">Indigo Blue (Standard)</option>
                    <option value="emerald">Emerald Green</option>
                    <option value="crimson">Crimson Red</option>
                    <option value="slate">Slate Minimal</option>
                    <option value="dark">Dark Cinema</option>
                  </select>
                </div>
              </div>
            </details>
          </div>
        </form>

        {/* Live Processing Indicator */}
        {loading && (
          <div className="bg-[#f8fafd] border border-[#c2e7ff] rounded-xl p-4 space-y-3 animate-pulse">
            <div className="flex items-center gap-2 text-xs font-bold text-[#0b57d0]">
              <RefreshCw className="w-3.5 h-3.5 animate-spin" />
              <span>Step 1/3: Resolving Shortened URL & Bypassing Gateway...</span>
            </div>
            <p className="text-xs text-[#5f6368]">
              Following redirects, resolving AdLinkFly tokens, and verifying Blogspot destination structure...
            </p>
          </div>
        )}

        {/* Error Notification */}
        {error && (
          <div className="bg-[#fce8e6] text-[#c5221f] text-xs p-3.5 rounded-xl flex items-start gap-2.5 border border-[#fad2cf]">
            <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
            <div className="space-y-1">
              <span className="font-bold">Error Processing URL:</span>
              <p className="text-[11px]">{error}</p>
            </div>
          </div>
        )}

        {/* SUCCESS RESULT: The Output Requested by User! */}
        {createdResult && !loading && (
          <div className="bg-[#f6faf7] border border-[#a8dab5] rounded-2xl p-5 space-y-4">
            <div className="flex items-center justify-between flex-wrap gap-2">
              <div className="flex items-center gap-2 text-xs font-bold text-[#137333]">
                <CheckCircle2 className="w-4 h-4 text-[#137333]" />
                <span>Page Created & Ready to Share!</span>
              </div>
            </div>

            {/* Public Link Box */}
            <div className="space-y-1.5 min-w-0">
              <label className="text-xs font-bold text-[#111827]">
                Public Button Page Link:
              </label>
              <div className="flex items-center gap-2 bg-white rounded-xl p-2 border border-[#c4e3cb] shadow-2xs flex-wrap sm:flex-nowrap min-w-0">
                <input
                  type="text"
                  readOnly
                  value={createdResult.clean_url}
                  className="flex-1 min-w-0 px-2 py-1 font-mono text-xs sm:text-sm font-bold text-[#0b57d0] bg-transparent outline-none truncate select-all"
                />
                <div className="flex items-center gap-2 shrink-0">
                  <button
                    type="button"
                    onClick={() => copyLink(createdResult.clean_url)}
                    className="px-3 py-1.5 rounded-lg bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer shadow-2xs transition-colors shrink-0"
                  >
                    <Copy className="w-3.5 h-3.5" /> Copy Link
                  </button>
                  <button
                    type="button"
                    onClick={() => onViewPage(createdResult.slug)}
                    className="px-3 py-1.5 rounded-lg bg-[#e8f0fe] hover:bg-[#d3e3fd] text-[#0b57d0] border border-[#c2e7ff] font-bold text-xs flex items-center gap-1.5 cursor-pointer transition-colors shrink-0"
                  >
                    <Eye className="w-3.5 h-3.5" /> View Page
                  </button>
                </div>
              </div>
            </div>

            {/* Extraction Metadata Info */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-[#444746] bg-white/70 rounded-xl p-3 border border-[#e0e4eb]">
              <div className="min-w-0">
                <span className="text-[#747775]">Title:</span>{" "}
                <span className="font-bold text-[#1f1f1f] truncate block">{createdResult.title}</span>
              </div>
              <div className="min-w-0">
                <span className="text-[#747775]">Extracted Buttons:</span>{" "}
                <span className="font-bold text-[#137333] block">{createdResult.button_count} episodes</span>
              </div>
            </div>

            {/* Button Preview List */}
            <div className="space-y-2 pt-1">
              <div className="text-xs font-bold text-[#444746] flex items-center justify-between">
                <span>Extracted Buttons Preview ({createdResult.buttons.length}):</span>
                <button
                  onClick={() => onViewPage(createdResult.slug)}
                  className="text-[11px] font-semibold text-[#0b57d0] hover:underline flex items-center gap-1"
                >
                  <span>Open Full Public Page</span>
                  <ExternalLink className="w-3 h-3" />
                </button>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {createdResult.buttons.slice(0, 6).map((btn, idx) => (
                  <div
                    key={`prev-btn-${idx}`}
                    className="bg-white p-2.5 rounded-xl border border-[#e0e4eb] flex items-center justify-between gap-2 text-xs"
                  >
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="w-6 h-6 rounded-md bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center font-bold text-[10px] shrink-0">
                        E{btn.episode || idx + 1}
                      </span>
                      <span className="font-semibold text-[#1f1f1f] truncate text-[11px]">
                        {btn.text}
                      </span>
                    </div>
                    {btn.quality && (
                      <span className="px-1.5 py-0.5 rounded bg-[#f0f4f9] text-[#444746] text-[10px] font-mono font-bold shrink-0">
                        {btn.quality}
                      </span>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
