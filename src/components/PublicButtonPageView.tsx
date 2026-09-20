import React, { useEffect, useState } from "react";
import { CheckCircle2, Download, ExternalLink, ShieldCheck, Copy, ArrowLeft, Eye, Film, Menu, X, Play } from "lucide-react";
import { ButtonPage, SiteIdentity } from "../types";

interface PublicButtonPageViewProps {
  slug: string;
  onBackToAdmin?: () => void;
  onNotify?: (text: string, type: "success" | "error" | "info") => void;
}

export const PublicButtonPageView: React.FC<PublicButtonPageViewProps> = ({
  slug,
  onBackToAdmin,
  onNotify,
}) => {
  const [page, setPage] = useState<ButtonPage | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [mobileMenuOpen, setMobileMenuOpen] = useState<boolean>(false);

  const [siteIdentity, setSiteIdentity] = useState<SiteIdentity>(() => {
    const saved = localStorage.getItem("slea_site_identity");
    if (saved) {
      try {
        return JSON.parse(saved);
      } catch {
        // fallback
      }
    }
    return {
      site_name: "Movie Hub HQ Drive",
      site_logo_url: "",
      site_logo_icon: "⚡",
      site_logo_text: "MHQ",
    };
  });

  const [footerText] = useState<string>(() => {
    return (
      localStorage.getItem("slea_footer_text") ||
      "&copy; 2026 Movie Hub HQ Drive 🍿 • Made with ❤️ for Direct Episode Gateway 🎬 • All rights reserved 🚀"
    );
  });

  const defaultMenuItems = [
    { title: "Home", url: "https://moviehubhq.com/" },
    { title: "Korean Drama", url: "https://moviehubhq.com/catagory/korean/" },
    { title: "Chinese Drama", url: "https://moviehubhq.com/catagory/chinese/" },
  ];

  useEffect(() => {
    // Dynamically ensure noindex meta tag is placed in the head
    let robotsMeta = document.querySelector('meta[name="robots"]');
    if (!robotsMeta) {
      robotsMeta = document.createElement("meta");
      robotsMeta.setAttribute("name", "robots");
      document.head.appendChild(robotsMeta);
    }
    robotsMeta.setAttribute("content", "noindex, nofollow, noarchive, nosnippet, noimageindex");

    const fetchPage = async () => {
      setLoading(true);
      setError(null);
      try {
        const res = await fetch(`/api/public/pages/${encodeURIComponent(slug)}`);
        const data = await res.json();
        if (data.success && data.page) {
          setPage(data.page);
        } else {
          setError(data.error || "Episode button page not found.");
        }
      } catch (err: any) {
        setError(err.message || "Failed to load episode page.");
      } finally {
        setLoading(false);
      }
    };

    fetchPage();
  }, [slug]);

  const copyLink = (url: string, label: string) => {
    navigator.clipboard.writeText(url);
    onNotify?.(`Copied ${label} link to clipboard!`, "success");
  };

  const copyPageShareUrl = () => {
    const fullUrl = window.location.origin + `/p/${slug}`;
    navigator.clipboard.writeText(fullUrl);
    onNotify?.("Public Button Page Link copied to clipboard!", "success");
  };

  const renderServerIcon = (provider?: string, url?: string, text?: string) => {
    const haystack = `${provider || ""} ${url || ""} ${text || ""}`.toLowerCase();
    if (haystack.includes("gdrive") || haystack.includes("drive.google") || haystack.includes("google")) {
      return (
        <svg className="w-3.5 h-3.5 shrink-0" viewBox="0 0 87.3 78" fill="none">
          <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
          <path d="M43.65 25 29.9 1.2c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44C.4 50 0 51.55 0 53.1h27.5z" fill="#00ac47"/>
          <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H59.8l5.85 10.1z" fill="#ea4335"/>
          <path d="M43.65 25 57.4 1.2C56.05.4 54.5 0 52.95 0H34.35c-1.55 0-3.1.4-4.45 1.2z" fill="#00832d"/>
          <path d="m59.8 53.1-16.15-28-16.15 28z" fill="#2684fc"/>
          <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l-13.75-23.8H35.65l13.75 23.8c1.35.8 2.9 1.2 4.45 1.2h15.25c1.55 0 3.1-.4 4.45-1.1z" fill="#ffba00"/>
        </svg>
      );
    }
    if (haystack.includes("xcloud") || haystack.includes("cloud") || haystack.includes("stream")) {
      return (
        <svg className="w-3.5 h-3.5 text-[#0b57d0] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z"/>
        </svg>
      );
    }
    return (
      <svg className="w-3.5 h-3.5 text-[#0b57d0] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M12 5l7 7-7 7"/>
      </svg>
    );
  };

  if (loading) {
    return (
      <div className="w-full max-w-xl mx-auto py-16 text-center space-y-3">
        <div className="inline-block w-8 h-8 border-3 border-[#0b57d0] border-t-transparent rounded-full animate-spin"></div>
        <p className="text-sm font-medium text-[#444746]">Loading episode buttons...</p>
      </div>
    );
  }

  if (error || !page) {
    return (
      <div className="w-full max-w-xl mx-auto py-12 px-4">
        <div className="bg-white rounded-3xl p-6 border border-[#fce8e6] text-center shadow-xs space-y-4">
          <div className="w-12 h-12 rounded-full bg-[#fce8e6] text-[#c5221f] flex items-center justify-center mx-auto font-bold">
            !
          </div>
          <h2 className="text-lg font-bold text-[#1f1f1f]">404 - Episode Page Not Found</h2>
          <p className="text-xs text-[#5f6368]">{error || "This episode link page does not exist."}</p>
          {onBackToAdmin && (
            <button
              onClick={onBackToAdmin}
              className="px-4 py-2 bg-[#0b57d0] text-white rounded-full text-xs font-semibold hover:bg-[#0842a0] cursor-pointer inline-flex items-center gap-2"
            >
              <ArrowLeft className="w-3.5 h-3.5" /> Back to Admin
            </button>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="w-full max-w-xl mx-auto py-4 px-2 sm:px-4 space-y-4">
      {/* Mobile Left-Opening Half-Page Sidebar Drawer & Backdrop */}
      {mobileMenuOpen && (
        <div
          onClick={() => setMobileMenuOpen(false)}
          className="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 md:hidden transition-opacity"
          aria-hidden="true"
        />
      )}

      <aside
        className={`fixed inset-y-0 left-0 z-50 w-1/2 min-w-[240px] max-w-xs h-full bg-white border-r border-[#e0e4eb] shadow-2xl flex flex-col md:hidden transform transition-transform duration-300 ease-in-out ${
          mobileMenuOpen ? "translate-x-0" : "-translate-x-full"
        }`}
        aria-label="Mobile Navigation Sidebar"
      >
        {/* Sidebar Header: Image Logo if available, fallback to Site Text Logo */}
        <div className="p-4 border-b border-[#f0f4f9] flex items-center justify-between shrink-0">
          <div className="flex items-center min-w-0">
            {siteIdentity.site_logo_url ? (
              <img
                src={siteIdentity.site_logo_url}
                alt={siteIdentity.site_name || "Logo"}
                className="h-7 max-w-[140px] object-contain"
              />
            ) : (
              <span className="font-bold text-sm tracking-tight text-[#111827] truncate">
                {siteIdentity.site_name || "Movie Hub HQ Drive"}
              </span>
            )}
          </div>
          <button
            type="button"
            onClick={() => setMobileMenuOpen(false)}
            className="p-1.5 rounded-lg text-[#5f6368] hover:text-[#1f1f1f] hover:bg-[#f0f4f9] transition-colors cursor-pointer"
            aria-label="Close menu"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Vertical Menu Items */}
        <nav className="flex-1 overflow-y-auto p-4 flex flex-col space-y-2">
          {defaultMenuItems.map((item, idx) => (
            <a
              key={idx}
              href={item.url}
              target="_blank"
              rel="noopener noreferrer"
              onClick={() => setMobileMenuOpen(false)}
              className="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold text-[#444746] hover:text-[#0b57d0] bg-[#f8fafd] hover:bg-[#e8f0fe] border border-[#e1e7f0] transition-colors"
            >
              <span>{item.title}</span>
              <span className="text-[#a8adb7] text-xs">›</span>
            </a>
          ))}
        </nav>

        <div className="p-4 border-t border-[#f0f4f9] text-[11px] text-[#747775] text-center shrink-0">
          {siteIdentity.site_name || "Movie Hub HQ Drive"}
        </div>
      </aside>

      {/* Public Header Navigation Bar (Google Material M3 Light Theme) */}
      <header className="bg-white rounded-2xl border border-[#e0e4eb] shadow-2xs overflow-hidden">
        <div className="px-4 h-14 flex items-center justify-between gap-3">
          {/* Left Header: Mobile Hamburger Button + Logo */}
          <div className="flex items-center gap-2.5 min-w-0">
            {/* Mobile 3-Line Hamburger Button */}
            <button
              type="button"
              onClick={() => setMobileMenuOpen(true)}
              className="md:hidden p-1.5 rounded-lg bg-[#f0f4f9] hover:bg-[#e2e8f0] text-[#444746] hover:text-[#111827] transition-colors cursor-pointer"
              aria-label="Toggle navigation menu"
            >
              <Menu className="w-5 h-5" />
            </button>

            {/* Image Logo (fallback to Text Logo) */}
            <a
              href="javascript:void(0)"
              className="flex items-center group min-w-0"
            >
              {siteIdentity.site_logo_url ? (
                <img
                  src={siteIdentity.site_logo_url}
                  alt={siteIdentity.site_name || "Logo"}
                  className="h-8 max-w-[180px] object-contain"
                />
              ) : (
                <span className="font-bold text-sm sm:text-base tracking-tight text-[#111827] group-hover:text-[#0b57d0] transition-colors truncate">
                  {siteIdentity.site_name || "Movie Hub HQ Drive"}
                </span>
              )}
            </a>
          </div>

          {/* Right Header: Desktop Navigation Menu Buttons */}
          <nav className="hidden md:flex flex-row items-center gap-1.5">
            {defaultMenuItems.map((item, idx) => (
              <a
                key={idx}
                href={item.url}
                target="_blank"
                rel="noopener noreferrer"
                className="px-3 py-1.5 rounded-full text-xs font-semibold text-[#444746] hover:text-[#0b57d0] hover:bg-[#f0f4f9] transition-colors whitespace-nowrap"
              >
                {item.title}
              </a>
            ))}
          </nav>
        </div>
      </header>

      {/* Admin Quick Bar */}
      <div className="flex items-center justify-between gap-2 px-1">
        {onBackToAdmin ? (
          <button
            onClick={onBackToAdmin}
            className="text-xs font-medium text-[#444746] hover:text-[#0b57d0] flex items-center gap-1.5 cursor-pointer bg-white px-3 py-1.5 rounded-full border border-[#e1e7f0] shadow-2xs"
          >
            <ArrowLeft className="w-3.5 h-3.5" /> Admin Dashboard
          </button>
        ) : (
          <span className="text-[11px] font-mono text-[#747775]">/p/{page.slug}</span>
        )}

        <button
          onClick={copyPageShareUrl}
          className="text-xs text-[#0b57d0] hover:underline flex items-center gap-1 cursor-pointer font-medium"
        >
          <Copy className="w-3 h-3" /> Share Page
        </button>
      </div>

      {/* Information Header Card */}
      <div className="bg-white rounded-3xl p-6 border border-[#e0e4eb] text-center shadow-xs space-y-3">
        <h1 className="text-xl sm:text-2xl font-black text-[#111827] tracking-tight leading-snug">
          {page.title}
        </h1>

        {page.description ? (
          <p className="text-xs sm:text-sm text-[#5f6368] max-w-md mx-auto leading-relaxed">
            {page.description}
          </p>
        ) : (
          <p className="text-xs text-[#5f6368]">
            Click Watch Now on any episode below to begin streaming or direct mirror downloading.
          </p>
        )}
      </div>

      {/* Buttons List */}
      <div className="space-y-2.5">
        {page.buttons && page.buttons.length > 0 ? (
          page.buttons.map((btn, idx) => {
            const rawEp = btn.episode || idx + 1;
            const epNum = typeof rawEp === "number" ? String(rawEp).padStart(2, "0") : rawEp;
            const quality = btn.quality || "HD";
            const provider = btn.provider || "GDrive Server";

            return (
              <div
                key={`btn-${idx}`}
                className="bg-white hover:bg-[#fafcff] rounded-2xl p-3.5 sm:p-4 border border-[#e3e7ee] hover:border-[#0b57d0] transition-all flex items-center justify-between gap-3 shadow-2xs group"
              >
                {/* Left Column: Server Icon, Episode 01/02.., Provider, Quality */}
                <div className="flex items-center gap-3 min-w-0 flex-1">
                  <div className="w-10 h-10 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center font-extrabold text-xs shrink-0 group-hover:bg-[#0b57d0] group-hover:text-white transition-colors shadow-2xs">
                    E{epNum}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="text-sm font-bold text-[#111827] truncate group-hover:text-[#0b57d0] transition-colors flex items-center gap-1.5">
                      <span>Episode {epNum}</span>
                      {btn.text && !btn.text.toLowerCase().includes("episode") && (
                        <span className="text-xs text-[#747775] font-normal truncate hidden sm:inline">({btn.text})</span>
                      )}
                    </div>
                    <div className="flex items-center gap-2 text-[11px] text-[#5f6368] mt-0.5">
                      <div className="inline-flex items-center gap-1 font-medium">
                        {renderServerIcon(provider, btn.url, btn.text)}
                        <span>{provider}</span>
                      </div>
                      <span className="px-1.5 py-0.2 rounded bg-[#f1f3f4] text-[#3c4043] font-mono text-[10px] font-bold border border-[#e0e4eb]">
                        {quality}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Right Column: "Watch Now" Button (Google Material M3 Pill) */}
                <div className="flex items-center gap-1.5 shrink-0">
                  <button
                    type="button"
                    onClick={() => copyLink(btn.url, `Episode ${epNum}`)}
                    className="p-2 rounded-lg text-[#5f6368] hover:text-[#0b57d0] hover:bg-[#f0f4f9] border border-transparent hover:border-[#d3e3fd] cursor-pointer transition-colors"
                    title="Copy Direct Link"
                  >
                    <Copy className="w-4 h-4" />
                  </button>

                  <a
                    href={btn.url}
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    className="px-4 py-2 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer shadow-xs hover:shadow-md transition-all"
                  >
                    <Play className="w-3.5 h-3.5 fill-current" />
                    <span>Watch Now</span>
                  </a>
                </div>
              </div>
            );
          })
        ) : (
          <div className="bg-white rounded-2xl p-8 text-center text-xs text-[#747775] border border-[#e0e4eb]">
            No episode buttons currently available on this page.
          </div>
        )}
      </div>

      {/* Footer with Configured Copyright */}
      <footer className="text-center py-6 text-xs text-[#5f6368] border-t border-[#e0e4eb] mt-6">
        <div dangerouslySetInnerHTML={{ __html: footerText }} />
      </footer>
    </div>
  );
};
