import React, { useState, useEffect } from "react";
import { ShieldCheck, Lock, ChevronRight } from "lucide-react";
import { ViewTab, SiteIdentity } from "../types";

interface AppHeaderProps {
  currentTab: ViewTab;
  isAdmin: boolean;
  onLogout: () => void;
  onShowLogin: () => void;
}

export const AppHeader: React.FC<AppHeaderProps> = ({
  currentTab,
  isAdmin,
  onShowLogin,
}) => {
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

  useEffect(() => {
    const handler = () => {
      const saved = localStorage.getItem("slea_site_identity");
      if (saved) {
        try {
          setSiteIdentity(JSON.parse(saved));
        } catch {
          // fallback
        }
      }
    };
    window.addEventListener("site_identity_updated", handler);
    return () => window.removeEventListener("site_identity_updated", handler);
  }, []);

  const tabTitles: Record<ViewTab, string> = {
    admin_flow: "Generate Button Page",
    pages_list: "Manage Pages (/pages)",
    settings: "Admin Settings (/settings)",
    updater: "One-Click System Updater (/update.php)",
    analytics: "Monthly Analytics & Statistics (/analytics.php)",
    cpanel_hub: "cPanel Deployment Hub",
    wp_plugin: "WordPress Automation Plugin",
  };

  return (
    <header
      id="app-header"
      className="w-full bg-[#fdfcff] text-[#1f1f1f] border-b border-[#e1e7f0] shadow-2xs select-none sticky top-0 z-30 transition-colors"
    >
      <div className="max-w-6xl mx-auto px-4 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3 min-w-0">
        {/* Text Logo & Breadcrumb */}
        <div className="flex items-center gap-2.5 min-w-0 flex-1">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2 flex-wrap sm:flex-nowrap">
              <h1 className="font-bold text-sm sm:text-base leading-snug text-[#1f1f1f] tracking-tight truncate">
                {siteIdentity.site_name || "Movie Hub HQ Drive"}
              </h1>
              {isAdmin ? (
                <div className="flex items-center gap-1.5 text-xs text-[#5f6368] min-w-0">
                  <ChevronRight className="w-3.5 h-3.5 text-[#8e918f] shrink-0" />
                  <span className="font-semibold text-[#0b57d0] truncate">{tabTitles[currentTab]}</span>
                </div>
              ) : null}
            </div>
            <p className="text-[11px] text-[#5f6368] truncate hidden sm:block">
              Shortlink Bypass • Episode Button Pages
            </p>
          </div>
        </div>

        {/* Status / Quick Action */}
        <div className="flex items-center gap-3 shrink-0">
          {isAdmin ? (
            <div className="flex items-center gap-2.5">
              <span className="text-[11px] font-bold px-2.5 py-1 rounded-full bg-[#e6f4ea] text-[#137333] border border-[#a8dab5] tracking-wide shrink-0 flex items-center gap-1">
                <ShieldCheck className="w-3.5 h-3.5" />
                <span className="hidden sm:inline">Admin Active</span>
              </span>
            </div>
          ) : (
            <button
              onClick={onShowLogin}
              className="h-9 px-3.5 rounded-lg text-xs font-bold bg-[#0b57d0] text-white hover:bg-[#0842a0] flex items-center gap-1.5 cursor-pointer shadow-xs"
            >
              <Lock className="w-3.5 h-3.5" />
              <span>Admin Login</span>
            </button>
          )}
        </div>
      </div>
    </header>
  );
};
