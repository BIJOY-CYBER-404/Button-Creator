import React, { useState, useEffect } from "react";
import { PlusCircle, Layers, Settings, RefreshCw, Server, FolderArchive, LogOut, Zap, ShieldCheck, Home } from "lucide-react";
import { ViewTab, SiteIdentity } from "../types";

interface AdminSidebarProps {
  currentTab: ViewTab;
  onSelectTab: (tab: ViewTab) => void;
  onLogout: () => void;
}

export const AdminSidebar: React.FC<AdminSidebarProps> = ({
  currentTab,
  onSelectTab,
  onLogout,
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
  const navItems: { id: ViewTab; label: string; icon: React.ReactNode; tooltip: string }[] = [
    {
      id: "admin_flow",
      label: "Generate",
      icon: <PlusCircle className="w-5 h-5" />,
      tooltip: "Generate (+ Generator)",
    },
    {
      id: "pages_list",
      label: "Pages",
      icon: <Layers className="w-5 h-5" />,
      tooltip: "Pages (/pages)",
    },
    {
      id: "settings",
      label: "Settings",
      icon: <Settings className="w-5 h-5" />,
      tooltip: "Settings (/settings)",
    },
    {
      id: "updater",
      label: "Update",
      icon: <RefreshCw className="w-5 h-5" />,
      tooltip: "One-Click System Updater (/update.php)",
    },
    {
      id: "cpanel_hub",
      label: "cPanel",
      icon: <Server className="w-5 h-5" />,
      tooltip: "cPanel Deployment ZIP",
    },
    {
      id: "wp_plugin",
      label: "Plugin",
      icon: <FolderArchive className="w-5 h-5" />,
      tooltip: "WordPress Automation Plugin",
    },
  ];

  return (
    <aside
      id="admin-sidebar"
      className="fixed inset-y-0 left-0 w-16 sm:w-20 bg-white border-r border-[#e1e7f0] z-40 flex flex-col items-center py-4 justify-between shadow-xs select-none transition-colors"
      aria-label="Admin Navigation Sidebar"
    >
      {/* Brand Icon & Main Navigation */}
      <div className="flex flex-col items-center w-full gap-5">
        {/* Brand Logo / Site Identity */}
        <button
          onClick={() => onSelectTab("admin_flow")}
          className="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-black text-lg shadow-2xs hover:scale-105 transition-transform cursor-pointer overflow-hidden p-1"
          title={siteIdentity.site_name || "Movie Hub HQ Drive"}
          aria-label={siteIdentity.site_name || "Movie Hub HQ Drive"}
        >
          {siteIdentity.site_logo_url ? (
            <img
              src={siteIdentity.site_logo_url}
              alt={siteIdentity.site_name}
              className="w-full h-full object-contain rounded-xl"
            />
          ) : (
            <span className="text-xs font-black tracking-tight">
              {siteIdentity.site_name
                ? siteIdentity.site_name
                    .split(/\s+/)
                    .map((w) => w[0])
                    .join("")
                    .slice(0, 3)
                    .toUpperCase()
                : "MHQ"}
            </span>
          )}
        </button>

        {/* Navigation Icon Buttons */}
        <nav className="flex flex-col items-center w-full gap-2 px-1 sm:px-2">
          {navItems.map((item) => {
            const isActive = currentTab === item.id;
            return (
              <button
                key={item.id}
                id={`sidebar-btn-${item.id}`}
                onClick={() => onSelectTab(item.id)}
                title={item.tooltip}
                aria-label={item.label}
                aria-current={isActive ? "page" : undefined}
                className={`w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative ${
                  isActive
                    ? "bg-[#0b57d0] text-white shadow-xs"
                    : "text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]"
                }`}
              >
                {item.icon}
                <span
                  className={`text-[9px] font-bold mt-0.5 tracking-tight ${
                    isActive ? "text-white" : "text-[#5f6368] group-hover:text-[#0b57d0]"
                  }`}
                >
                  {item.label}
                </span>

                {/* Subtle active indicator dot for small screens */}
                {isActive && (
                  <span className="absolute -right-1 top-1/2 -translate-y-1/2 w-1 h-3 bg-[#0b57d0] rounded-l hidden" />
                )}
              </button>
            );
          })}
        </nav>
      </div>

      {/* Bottom Area: Admin Status Badge & Logout & Version */}
      <div className="flex flex-col items-center w-full gap-2 px-1 sm:px-2 pb-3">
        <div
          className="w-8 h-8 rounded-full bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center border border-[#d3e3fd]"
          title="Admin Mode Active"
        >
          <ShieldCheck className="w-4 h-4" />
        </div>

        <button
          id="sidebar-logout-btn"
          onClick={onLogout}
          className="w-10 h-10 rounded-xl flex items-center justify-center text-[#c5221f] hover:bg-[#fce8e6] transition-colors cursor-pointer"
          title="Sign Out"
          aria-label="Sign Out"
        >
          <LogOut className="w-5 h-5" />
        </button>

        <span
          className="text-[10px] font-bold font-mono text-[#5f6368] bg-[#f0f4f9] px-1.5 py-0.5 rounded border border-[#e1e7f0] select-none"
          title="Movie Hub HQ Drive Version v-3.9.8"
        >
          v-3.9.8
        </span>
      </div>
    </aside>
  );
};
