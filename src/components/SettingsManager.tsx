import React, { useState, useEffect } from "react";
import { Settings, CheckCircle2, Sliders, Menu, ShieldAlert, Plus, Trash2, Code, Globe, Save, Sparkles, Image as ImageIcon } from "lucide-react";
import { SiteIdentity } from "../types";

interface MenuItem {
  title: string;
  url: string;
  target_blank: boolean;
}

interface AdSettings {
  adsense_publisher_id: string;
  adsense_auto_ads: boolean;
  banner_top: string;
  banner_middle: string;
  banner_bottom: string;
}

interface SettingsManagerProps {
  onNotify: (msg: string, type?: "success" | "error" | "info") => void;
}

export const SettingsManager: React.FC<SettingsManagerProps> = ({ onNotify }) => {
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
      site_logo_text: "MHQ"
    };
  });

  const [adSettings, setAdSettings] = useState<AdSettings>(() => {
    const saved = localStorage.getItem("slea_ad_settings");
    if (saved) {
      try {
        return JSON.parse(saved);
      } catch {
        // fallback
      }
    }
    return {
      adsense_publisher_id: "ca-pub-9876543210987654",
      adsense_auto_ads: true,
      banner_top: '<div style="padding:14px;background:#f8f9fa;border:1px dashed #cbd5e1;border-radius:12px;text-align:center;font-size:12px;color:#64748b;">📢 <strong>Sponsored Ad</strong> • 728x90 Top Banner Space</div>',
      banner_middle: '<div style="padding:12px;background:#f8f9fa;border:1px dashed #cbd5e1;border-radius:12px;text-align:center;font-size:11px;color:#64748b;">⚡ <strong>In-Between Episode Ad</strong> • Fast Mirror Links</div>',
      banner_bottom: '<div style="padding:14px;background:#f8f9fa;border:1px dashed #cbd5e1;border-radius:12px;text-align:center;font-size:12px;color:#64748b;">🎁 <strong>Bottom Sponsor Banner</strong> • 300x250 or Responsive</div>',
    };
  });

  const [menuItems, setMenuItems] = useState<MenuItem[]>(() => {
    const saved = localStorage.getItem("slea_menu_items");
    if (saved) {
      try {
        return JSON.parse(saved);
      } catch {
        // fallback
      }
    }
    return [
      { title: "Home", url: "/", target_blank: false },
      { title: "Korean Drama", url: "/korean-drama", target_blank: false },
      { title: "Chinese Drama", url: "/chinese-drama", target_blank: false },
      { title: "Anime Series", url: "/anime", target_blank: false },
      { title: "Support", url: "https://t.me/example", target_blank: true },
    ];
  });

  const [footerText, setFooterText] = useState<string>(() => {
    return (
      localStorage.getItem("slea_footer_text") ||
      "&copy; 2026 Movie Hub HQ Drive. Non-Indexable Private Media Portal. All rights reserved."
    );
  });

  const [maintenanceSettings, setMaintenanceSettings] = useState(() => {
    const saved = localStorage.getItem("slea_maintenance_settings");
    if (saved) {
      try {
        return JSON.parse(saved);
      } catch {
        // fallback
      }
    }
    return {
      enabled: false,
      message: "The website is currently undergoing scheduled maintenance. We will be back shortly!"
    };
  });

  const [newMenuTitle, setNewMenuTitle] = useState("");
  const [newMenuUrl, setNewMenuUrl] = useState("");
  const [newMenuBlank, setNewMenuBlank] = useState(false);

  // Backup & Restore State
  const [restoreScope, setRestoreScope] = useState<"auto" | "settings" | "pages" | "others">("auto");
  const [restoreMode, setRestoreMode] = useState<"merge" | "overwrite">("merge");
  const [snapshotScope, setSnapshotScope] = useState<"all" | "settings" | "pages" | "others">("all");
  const [snapshotLabel, setSnapshotLabel] = useState("");
  const [snapshots, setSnapshots] = useState<any[]>(() => {
    try {
      const raw = localStorage.getItem("slea_server_snapshots");
      return raw ? JSON.parse(raw) : [];
    } catch {
      return [];
    }
  });

  const buildLocalSettingsBundle = () => ({
    site_identity: siteIdentity,
    ad_settings: adSettings,
    menu_items: menuItems,
    footer_copyright: footerText,
    maintenance_settings: maintenanceSettings,
  });

  const applyRestoredSettingsBundle = (s: any) => {
    if (!s || typeof s !== "object") return;
    if (s.site_identity) {
      setSiteIdentity(s.site_identity);
      localStorage.setItem("slea_site_identity", JSON.stringify(s.site_identity));
      window.dispatchEvent(new Event("site_identity_updated"));
    }
    if (s.ad_settings) {
      setAdSettings((prev) => ({ ...prev, ...s.ad_settings }));
      localStorage.setItem("slea_ad_settings", JSON.stringify({ ...adSettings, ...s.ad_settings }));
    }
    if (Array.isArray(s.menu_items)) {
      setMenuItems(s.menu_items);
      localStorage.setItem("slea_menu_items", JSON.stringify(s.menu_items));
    }
    if (typeof s.footer_copyright === "string") {
      setFooterText(s.footer_copyright);
      localStorage.setItem("slea_footer_text", s.footer_copyright);
    }
    if (s.maintenance_settings) {
      setMaintenanceSettings((prev: any) => ({ ...prev, ...s.maintenance_settings }));
      localStorage.setItem("slea_maintenance_settings", JSON.stringify({ ...maintenanceSettings, ...s.maintenance_settings }));
    }
  };

  const handleExportBackup = async (scope: "all" | "settings" | "pages" | "others") => {
    try {
      const token = localStorage.getItem("slea_admin_token") || "admin_token_default_session";
      const res = await fetch("/api/backup/export", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
          "x-admin-token": token,
        },
        body: JSON.stringify({
          scope,
          settings: buildLocalSettingsBundle(),
          others: { exported_by: "admin" },
        }),
      });
      const data = await res.json();
      if (!data.success || !data.backup) {
        onNotify(data.error || "Failed to generate backup.", "error");
        return;
      }

      const blob = new Blob([JSON.stringify(data.backup, null, 2)], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `moviehub-backup-${scope}-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-")}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      onNotify(`Downloaded ${scope.toUpperCase()} backup JSON successfully!`, "success");
    } catch (e: any) {
      onNotify(`Backup export failed: ${e.message}`, "error");
    }
  };

  const handleRestoreFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
      const text = await file.text();
      const parsed = JSON.parse(text);
      await executeRestorePayload(parsed, restoreScope, restoreMode);
    } catch (err: any) {
      onNotify(`Restore failed: ${err.message || "Invalid JSON backup file"}`, "error");
    } finally {
      e.target.value = "";
    }
  };

  const executeRestorePayload = async (
    payload: any,
    scope: "auto" | "settings" | "pages" | "others",
    mode: "merge" | "overwrite"
  ) => {
    const token = localStorage.getItem("slea_admin_token") || "admin_token_default_session";
    const res = await fetch("/api/backup/restore", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
        "x-admin-token": token,
      },
      body: JSON.stringify({ backup: payload, scope, mode }),
    });
    const data = await res.json();
    if (!data.success) {
      throw new Error(data.error || "Restore failed");
    }
    if (scope === "auto" || scope === "settings") {
      const settingsData = payload?.data?.settings || payload?.settings;
      if (settingsData) {
        applyRestoredSettingsBundle(settingsData);
      }
    }
    onNotify(
      `Restored (${scope.toUpperCase()}): ${data.restored?.settings || 0} setting group(s), ${data.restored?.pages || 0} page(s), ${data.restored?.others || 0} account/analytics bundle(s)!`,
      "success"
    );
  };

  const handleCreateSnapshot = async () => {
    try {
      const token = localStorage.getItem("slea_admin_token") || "admin_token_default_session";
      const res = await fetch("/api/backup/export", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
          "x-admin-token": token,
        },
        body: JSON.stringify({
          scope: snapshotScope,
          settings: buildLocalSettingsBundle(),
          others: { exported_by: "admin" },
        }),
      });
      const data = await res.json();
      if (data.success && data.backup) {
        const snap = {
          id: `snap_${snapshotScope}_${Date.now()}`,
          scope: snapshotScope,
          label: snapshotLabel.trim() || `${snapshotScope.toUpperCase()} Snapshot`,
          created_at: new Date().toLocaleString(),
          counts: data.backup.counts,
          payload: data.backup,
        };
        const next = [snap, ...snapshots].slice(0, 15);
        setSnapshots(next);
        localStorage.setItem("slea_server_snapshots", JSON.stringify(next));
        setSnapshotLabel("");
        onNotify(`Created ${snapshotScope.toUpperCase()} snapshot successfully!`, "success");
      }
    } catch (e: any) {
      onNotify(`Snapshot error: ${e.message}`, "error");
    }
  };

  const handleSaveIdentity = (e: React.FormEvent) => {
    e.preventDefault();
    localStorage.setItem("slea_site_identity", JSON.stringify(siteIdentity));
    window.dispatchEvent(new Event("site_identity_updated"));
    onNotify("Site branding & logo updated successfully!", "success");
  };

  const handleSaveAds = (e: React.FormEvent) => {
    e.preventDefault();
    localStorage.setItem("slea_ad_settings", JSON.stringify(adSettings));
    onNotify("AdSense & Banner Ad settings saved successfully!", "success");
  };

  const handleSaveMaintenance = (e: React.FormEvent) => {
    e.preventDefault();
    localStorage.setItem("slea_maintenance_settings", JSON.stringify(maintenanceSettings));
    onNotify("Maintenance settings saved successfully!", "success");
  };

  const handleAddMenuItem = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newMenuTitle.trim() || !newMenuUrl.trim()) {
      onNotify("Please provide both menu item title and destination URL.", "error");
      return;
    }
    const updated = [...menuItems, { title: newMenuTitle.trim(), url: newMenuUrl.trim(), target_blank: newMenuBlank }];
    setMenuItems(updated);
    localStorage.setItem("slea_menu_items", JSON.stringify(updated));
    setNewMenuTitle("");
    setNewMenuUrl("");
    setNewMenuBlank(false);
    onNotify(`Added navigation item "${newMenuTitle.trim()}"`, "success");
  };

  const handleRemoveMenuItem = (index: number) => {
    const updated = menuItems.filter((_, idx) => idx !== index);
    setMenuItems(updated);
    localStorage.setItem("slea_menu_items", JSON.stringify(updated));
    onNotify("Menu item removed.", "info");
  };

  const handleSaveFooter = (e: React.FormEvent) => {
    e.preventDefault();
    localStorage.setItem("slea_footer_text", footerText);
    onNotify("Footer copyright text updated successfully!", "success");
  };

  return (
    <div className="space-y-8" id="settings-manager">
      {/* Settings Header Card */}
      <div className="bg-white rounded-3xl p-6 sm:p-7 border border-[#e0e4eb] shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div className="flex items-center gap-3.5">
          <div className="w-12 h-12 rounded-2xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-bold shadow-2xs">
            <Settings className="w-6 h-6 text-[#0b57d0]" />
          </div>
          <div>
            <h2 className="text-lg font-bold text-[#1f1f1f] leading-tight flex items-center gap-2">
              <span>Admin Configuration</span>
              <span className="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-[#e8f0fe] text-[#0b57d0] border border-[#d3e3fd]">
                Active
              </span>
            </h2>
            <p className="text-xs text-[#5f6368] mt-0.5">
              Manage AdSense & custom banners, public navigation bar, and footer copyright text.
            </p>
          </div>
        </div>

        <div className="text-xs text-[#5f6368] bg-[#f0f4f9] px-3.5 py-2 rounded-xl border border-[#e1e7f0] flex items-center gap-2">
          <Globe className="w-4 h-4 text-[#0b57d0]" />
          <span>Synced with cPanel <code>settings.php</code> specs</span>
        </div>
      </div>

      {/* 0. Site Branding & Logo Configuration Form */}
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
        <div className="flex items-center justify-between pb-4 border-b border-[#f0f4f9]">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-bold">
              <Sparkles className="w-5 h-5 text-[#0b57d0]" />
            </div>
            <div>
              <h3 className="font-bold text-base text-[#1f1f1f]">Site Branding & Logo</h3>
              <p className="text-xs text-[#5f6368]">
                Configure public website name and logo image URL.
              </p>
            </div>
          </div>
          <span className="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-[#e8f0fe] text-[#0b57d0]">
            Movie Hub HQ Drive
          </span>
        </div>

        <form onSubmit={handleSaveIdentity} className="space-y-5">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-[#444746] block">
                Website Name
              </label>
              <input
                type="text"
                value={siteIdentity.site_name}
                onChange={(e) => setSiteIdentity({ ...siteIdentity, site_name: e.target.value })}
                placeholder="Movie Hub HQ Drive"
                className="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-medium focus:border-[#0b57d0] focus:ring-1 focus:ring-[#0b57d0] outline-none"
              />
              <span className="text-[11px] text-[#5f6368] block">
                Public gateway brand title displayed on headers and sidebar.
              </span>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-[#444746] block">
                Site Logo Image URL (Optional)
              </label>
              <div className="relative">
                <input
                  type="url"
                  value={siteIdentity.site_logo_url}
                  onChange={(e) => setSiteIdentity({ ...siteIdentity, site_logo_url: e.target.value })}
                  placeholder="https://example.com/logo.png"
                  className="w-full pl-9 pr-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] focus:ring-1 focus:ring-[#0b57d0] outline-none"
                />
                <ImageIcon className="w-4 h-4 text-[#747775] absolute left-3 top-3" />
              </div>
              <span className="text-[11px] text-[#5f6368] block">
                Provide a direct image URL (PNG, SVG, WebP). When provided, text fallback is suppressed.
              </span>
            </div>

            <div className="space-y-1.5 md:col-span-2">
              <label className="text-xs font-bold text-[#444746] block">
                Brand Live Preview
              </label>
              <div className="p-4 bg-[#f8fafd] border border-[#e0e4eb] rounded-xl flex items-center justify-between min-h-[64px]">
                <div className="flex items-center gap-2.5 min-w-0">
                  {siteIdentity.site_logo_url ? (
                    <img
                      src={siteIdentity.site_logo_url}
                      alt="Logo Preview"
                      className="h-8 max-w-[180px] object-contain rounded"
                      onError={(e) => {
                        (e.target as HTMLElement).style.display = "none";
                      }}
                    />
                  ) : (
                    <span className="font-bold text-sm text-[#111827] truncate">
                      {siteIdentity.site_name || "Movie Hub HQ Drive"}
                    </span>
                  )}
                </div>
                <span className="px-2 py-0.5 rounded-md bg-[#e6f4ea] text-[#137333] text-[10px] font-bold shrink-0">
                  M3 Light Preview
                </span>
              </div>
            </div>
          </div>

          <div className="flex justify-end pt-2">
            <button
              type="submit"
              className="px-5 py-2.5 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center gap-2 shadow-xs transition-colors cursor-pointer"
            >
              <Save className="w-4 h-4" /> Save Site Branding & Logo
            </button>
          </div>
        </form>
      </div>

      {/* 🛠️ Maintenance Mode Form */}
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
        <div className="flex items-center justify-between pb-4 border-b border-[#f0f4f9]">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-[#fef7e0] text-[#b06000] flex items-center justify-center font-bold">
              <ShieldAlert className="w-5 h-5 text-[#b06000]" />
            </div>
            <div>
              <h3 className="font-bold text-base text-[#1f1f1f]">Maintenance Mode</h3>
              <p className="text-xs text-[#5f6368]">
                Temporarily restrict public access to the portal with an interactive creative screen.
              </p>
            </div>
          </div>
          <span className="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-[#fef7e0] text-[#b06000] border border-[#feebc8]">
            Status: {maintenanceSettings.enabled ? "Active" : "Inactive"}
          </span>
        </div>

        <form onSubmit={handleSaveMaintenance} className="space-y-5">
          <div className="grid grid-cols-1 md:grid-cols-12 gap-6">
            {/* Inputs Column */}
            <div className="md:col-span-7 space-y-5">
              <div className="flex items-center justify-between p-4 bg-[#f8fafd] rounded-2xl border border-[#e1e7f0]">
                <div>
                  <span className="text-xs font-bold text-[#1f1f1f] block">Enable Maintenance Mode</span>
                  <span className="text-[10px] text-[#5f6368]">Toggle to block or resume public site access instantly.</span>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={maintenanceSettings.enabled}
                    onChange={(e) => setMaintenanceSettings({ ...maintenanceSettings, enabled: e.target.checked })}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-[#444746] block">
                  Custom Maintenance Message:
                </label>
                <textarea
                  rows={4}
                  value={maintenanceSettings.message}
                  onChange={(e) => setMaintenanceSettings({ ...maintenanceSettings, message: e.target.value })}
                  placeholder="The website is currently undergoing scheduled maintenance. We will be back shortly!"
                  className="w-full px-4 py-3 rounded-2xl border border-[#c4c7c5] text-xs font-medium focus:border-[#0b57d0] focus:ring-1 focus:ring-[#0b57d0] outline-none leading-relaxed"
                />
                <span className="text-[10px] text-[#747775] block">Provide dynamic info about current system optimizations or upgrades.</span>
              </div>
            </div>

            {/* Preview Column */}
            <div className="md:col-span-5 space-y-2">
              <span className="text-[11px] font-bold uppercase text-[#5f6368] block">Public Preview Mockup:</span>
              <div className="border border-[#e0e4eb] rounded-3xl p-4 bg-[#f8fafd] space-y-3 relative overflow-hidden">
                <div className="relative w-full aspect-[4/3] rounded-xl overflow-hidden bg-[#fafbfc] border border-[#f0f4f9] flex items-center justify-center">
                  <div className="absolute inset-0 bg-[#0b57d0]/5 flex items-center justify-center text-[#0b57d0] font-mono text-[10px] text-center p-4">
                    <div className="space-y-1">
                      <div className="w-8 h-8 rounded-full border-2 border-[#0b57d0] border-t-transparent animate-spin mx-auto"></div>
                      <span className="block font-bold">Optimization Engaged</span>
                    </div>
                  </div>
                </div>
                <div className="text-center space-y-1">
                  <div className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#fef7e0] border border-[#feebc8] text-[#b06000] text-[9px] font-bold font-mono">
                    <span>SYSTEM MAINTENANCE</span>
                  </div>
                  <h4 className="text-xs font-black text-[#111827]">We'll Be Right Back</h4>
                  <p className="text-[10px] text-[#5f6368] leading-normal line-clamp-2 px-2">
                    {maintenanceSettings.message || "The website is currently undergoing scheduled maintenance. We will be back shortly!"}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div className="flex justify-end pt-2">
            <button
              type="submit"
              className="px-5 py-2.5 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center gap-2 shadow-xs transition-colors cursor-pointer"
            >
              <Save className="w-4 h-4" /> Save Maintenance Settings
            </button>
          </div>
        </form>
      </div>

      {/* 1. Google AdSense & Banner Ads Form */}
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
        <div className="flex items-center gap-3 pb-4 border-b border-[#f0f4f9]">
          <div className="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
            <Sliders className="w-5 h-5" />
          </div>
          <div>
            <h3 className="font-bold text-base text-[#1f1f1f]">AdSense & Banner Ads Configuration</h3>
            <p className="text-xs text-[#5f6368]">
              Insert Google AdSense Auto-Ads or custom responsive banner ad HTML/JavaScript code.
            </p>
          </div>
        </div>

        <form onSubmit={handleSaveAds} className="space-y-5">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-[#444746] block">
                Google AdSense Publisher ID
              </label>
              <input
                type="text"
                value={adSettings.adsense_publisher_id}
                onChange={(e) => setAdSettings({ ...adSettings, adsense_publisher_id: e.target.value })}
                placeholder="ca-pub-XXXXXXXXXXXXXXXX"
                className="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] focus:ring-1 focus:ring-[#0b57d0] outline-none"
              />
              <span className="text-[11px] text-[#5f6368] block">
                Optional: Auto-inserts <code>pagead2.googlesyndication.com</code> snippet in the page header.
              </span>
            </div>

            <div className="space-y-2 pt-5">
              <label className="flex items-center gap-2.5 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={adSettings.adsense_auto_ads}
                  onChange={(e) => setAdSettings({ ...adSettings, adsense_auto_ads: e.target.checked })}
                  className="w-4 h-4 text-[#0b57d0] rounded border-gray-300 focus:ring-[#0b57d0]"
                />
                <span className="text-xs font-bold text-[#1f1f1f]">Enable Google Auto-Ads</span>
              </label>
              <p className="text-[11px] text-[#5f6368] pl-6.5">
                Automatically places machine learning ads across optimal positions on the button page.
              </p>
            </div>
          </div>

          <div className="space-y-4 pt-2">
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-[#444746] flex items-center gap-1.5">
                <Code className="w-3.5 h-3.5 text-[#0b57d0]" />
                Top Banner Ad HTML (Placed under the navigation bar)
              </label>
              <textarea
                rows={3}
                value={adSettings.banner_top}
                onChange={(e) => setAdSettings({ ...adSettings, banner_top: e.target.value })}
                className="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] outline-none"
                placeholder="Paste HTML/JS ad code here..."
              />
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-[#444746] flex items-center gap-1.5">
                <Code className="w-3.5 h-3.5 text-[#0b57d0]" />
                In-Between Episode Buttons Ad HTML (Placed between button rows)
              </label>
              <textarea
                rows={3}
                value={adSettings.banner_middle}
                onChange={(e) => setAdSettings({ ...adSettings, banner_middle: e.target.value })}
                className="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] outline-none"
                placeholder="Paste HTML/JS ad code here..."
              />
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-[#444746] flex items-center gap-1.5">
                <Code className="w-3.5 h-3.5 text-[#0b57d0]" />
                Bottom Banner Ad HTML (Placed above footer)
              </label>
              <textarea
                rows={3}
                value={adSettings.banner_bottom}
                onChange={(e) => setAdSettings({ ...adSettings, banner_bottom: e.target.value })}
                className="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] outline-none"
                placeholder="Paste HTML/JS ad code here..."
              />
            </div>
          </div>

          <div className="flex justify-end pt-2">
            <button
              type="submit"
              className="px-5 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white text-xs font-bold shadow-xs transition-all cursor-pointer flex items-center gap-1.5"
            >
              <Save className="w-4 h-4" /> Save Ad Settings
            </button>
          </div>
        </form>
      </div>

      {/* 2. Public Header Navigation Menu */}
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
        <div className="flex items-center gap-3 pb-4 border-b border-[#f0f4f9]">
          <div className="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
            <Menu className="w-5 h-5" />
          </div>
          <div>
            <h3 className="font-bold text-base text-[#1f1f1f]">Public Header Navigation Menu</h3>
            <p className="text-xs text-[#5f6368]">
              Define links displayed across every generated button page header for visitor navigation.
            </p>
          </div>
        </div>

        {/* Existing Menu Items List */}
        <div className="space-y-2">
          {menuItems.map((item, index) => (
            <div
              key={index}
              className="flex items-center justify-between gap-3 p-3 rounded-2xl bg-[#f8fafd] border border-[#e1e7f0]"
            >
              <div className="flex items-center gap-3 min-w-0">
                <span className="w-6 h-6 rounded-full bg-white border border-[#c4c7c5] text-[10px] font-bold text-[#444746] flex items-center justify-center shrink-0">
                  {index + 1}
                </span>
                <div className="min-w-0">
                  <div className="text-xs font-bold text-[#1f1f1f] truncate">{item.title}</div>
                  <div className="text-[11px] font-mono text-[#5f6368] truncate">{item.url}</div>
                </div>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                {item.target_blank && (
                  <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                    New Tab
                  </span>
                )}
                <button
                  type="button"
                  onClick={() => handleRemoveMenuItem(index)}
                  className="p-1.5 rounded-lg text-red-600 hover:bg-red-50 transition-colors cursor-pointer"
                  title="Remove item"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </div>
          ))}
        </div>

        {/* Add New Menu Item */}
        <form onSubmit={handleAddMenuItem} className="pt-3 border-t border-[#f0f4f9] space-y-3">
          <h4 className="text-xs font-bold text-[#1f1f1f]">+ Add New Navigation Link</h4>
          <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            <div className="sm:col-span-4 space-y-1">
              <label className="text-[11px] font-semibold text-[#5f6368]">Item Title</label>
              <input
                type="text"
                placeholder="e.g. Movies"
                value={newMenuTitle}
                onChange={(e) => setNewMenuTitle(e.target.value)}
                className="w-full px-3 py-2 rounded-xl border border-[#c4c7c5] text-xs focus:border-[#0b57d0] outline-none"
              />
            </div>
            <div className="sm:col-span-5 space-y-1">
              <label className="text-[11px] font-semibold text-[#5f6368]">Target URL</label>
              <input
                type="text"
                placeholder="e.g. /movies or https://..."
                value={newMenuUrl}
                onChange={(e) => setNewMenuUrl(e.target.value)}
                className="w-full px-3 py-2 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] outline-none"
              />
            </div>
            <div className="sm:col-span-3 flex items-center justify-between sm:justify-end gap-2">
              <label className="flex items-center gap-1.5 text-xs text-[#5f6368] cursor-pointer">
                <input
                  type="checkbox"
                  checked={newMenuBlank}
                  onChange={(e) => setNewMenuBlank(e.target.checked)}
                  className="rounded text-[#0b57d0]"
                />
                <span>New Tab</span>
              </label>
              <button
                type="submit"
                className="px-4 py-2 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white text-xs font-bold transition-all cursor-pointer flex items-center gap-1"
              >
                <Plus className="w-3.5 h-3.5" /> Add
              </button>
            </div>
          </div>
        </form>
      </div>

      {/* 3. Footer Copyright Text */}
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-5">
        <div className="flex items-center gap-3 pb-4 border-b border-[#f0f4f9]">
          <div className="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
            <Globe className="w-5 h-5" />
          </div>
          <div>
            <h3 className="font-bold text-base text-[#1f1f1f]">Footer Copyright & Disclaimer</h3>
            <p className="text-xs text-[#5f6368]">
              Configures the copyright statement and legal disclaimer displayed at the bottom of public pages.
            </p>
          </div>
        </div>

        <form onSubmit={handleSaveFooter} className="space-y-4">
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-[#444746] block">Footer Content (HTML allowed)</label>
            <div className="flex flex-wrap gap-1.5 pb-2">
              <span className="text-[11px] font-bold text-[#5f6368] self-center mr-1">Quick Emojis & Symbols:</span>
              {["🍿", "🎬", "❤️", "🚀", "⭐", "🎥", "📺", "🛡️", "💬", "📅", "✨", "🔥", "⚡", "🔒", "©️"].map((emoji) => (
                <button
                  type="button"
                  key={emoji}
                  onClick={() => setFooterText((prev) => prev + " " + emoji)}
                  className="w-7 h-7 rounded-lg bg-slate-50 hover:bg-slate-100 border border-[#e0e4eb] flex items-center justify-center text-sm transition-all cursor-pointer select-none active:scale-95"
                  title={`Insert ${emoji}`}
                >
                  {emoji}
                </button>
              ))}
            </div>
            <textarea
              rows={3}
              value={footerText}
              onChange={(e) => setFooterText(e.target.value)}
              className="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] outline-none"
              placeholder="&copy; 2026 ... All rights reserved."
            />
          </div>

          <div className="flex justify-end">
            <button
              type="submit"
              className="px-5 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white text-xs font-bold shadow-xs transition-all cursor-pointer flex items-center gap-1.5"
            >
              <Save className="w-4 h-4" /> Save Footer Text
            </button>
          </div>
        </form>
      </div>

      {/* 4. Backup & Restore Center (Website Settings, Pages, and Others — Together or Separately) */}
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6" id="backup-restore-section">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-[#f0f4f9]">
          <div>
            <h3 className="font-bold text-base text-[#1f1f1f] flex items-center gap-2">
              <span>💾 Backup &amp; Restore Center</span>
              <span className="text-[11px] font-mono px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                Zero Data Loss
              </span>
            </h3>
            <p className="text-xs text-[#5f6368] mt-0.5">
              Backup and restore <strong>Website Settings</strong>, <strong>Generated Pages</strong>, and <strong>Others (Admin Accounts &amp; Analytics)</strong> all together or separately.
            </p>
          </div>
          <span className="text-[11px] font-bold px-2.5 py-1 rounded-full bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff] self-start sm:self-auto">
            Modular &amp; Update-Safe
          </span>
        </div>

        {/* 1. Download Portable JSON Backups */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h4 className="text-xs font-bold uppercase tracking-wider text-[#444746]">
              1. Download Backup (.json) — All or Separately
            </h4>
            <span className="text-[11px] text-[#5f6368]">Instant JSON export</span>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <button
              type="button"
              onClick={() => handleExportBackup("all")}
              className="p-4 rounded-2xl bg-[#f8fafd] hover:bg-[#e8f0fe] border border-[#d3e3fd] text-left transition-all flex flex-col justify-between gap-3 cursor-pointer"
            >
              <div className="space-y-1">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-extrabold text-[#0b57d0]">📦 Full Backup (All)</span>
                  <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 font-bold">ALL</span>
                </div>
                <p className="text-[11px] text-[#5f6368]">Settings + Generated Pages + Accounts &amp; Analytics.</p>
              </div>
              <span className="text-[11px] font-bold text-[#0b57d0]">⬇ Download Full Backup</span>
            </button>

            <button
              type="button"
              onClick={() => handleExportBackup("settings")}
              className="p-4 rounded-2xl bg-[#f8fafd] hover:bg-purple-50 border border-purple-200 text-left transition-all flex flex-col justify-between gap-3 cursor-pointer"
            >
              <div className="space-y-1">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-extrabold text-purple-700">⚙️ Settings Only</span>
                  <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-purple-100 text-purple-800 font-bold">SETTINGS</span>
                </div>
                <p className="text-[11px] text-[#5f6368]">Branding, Logo, Menu, Footer HTML, Ads &amp; Maintenance.</p>
              </div>
              <span className="text-[11px] font-bold text-purple-700">⬇ Download Settings Only</span>
            </button>

            <button
              type="button"
              onClick={() => handleExportBackup("pages")}
              className="p-4 rounded-2xl bg-[#f8fafd] hover:bg-emerald-50 border border-emerald-200 text-left transition-all flex flex-col justify-between gap-3 cursor-pointer"
            >
              <div className="space-y-1">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-extrabold text-emerald-700">📄 Pages Only</span>
                  <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold">PAGES</span>
                </div>
                <p className="text-[11px] text-[#5f6368]">All generated episode button pages, slugs, links &amp; views.</p>
              </div>
              <span className="text-[11px] font-bold text-emerald-700">⬇ Download Pages Only</span>
            </button>

            <button
              type="button"
              onClick={() => handleExportBackup("others")}
              className="p-4 rounded-2xl bg-[#f8fafd] hover:bg-amber-50 border border-amber-200 text-left transition-all flex flex-col justify-between gap-3 cursor-pointer"
            >
              <div className="space-y-1">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-extrabold text-amber-800">👤 Others Only</span>
                  <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold">OTHERS</span>
                </div>
                <p className="text-[11px] text-[#5f6368]">Admin accounts, permissions &amp; monthly view statistics.</p>
              </div>
              <span className="text-[11px] font-bold text-amber-800">⬇ Download Others Only</span>
            </button>
          </div>
        </div>

        {/* 2. Restore from Backup File */}
        <div className="p-5 rounded-2xl bg-[#f8fafd] border border-[#e1e7f0] space-y-4">
          <div>
            <h4 className="text-xs font-bold uppercase tracking-wider text-[#111827]">
              2. Restore from Backup File (.json) — Together or Separately
            </h4>
            <p className="text-[11px] text-[#5f6368]">
              Select what part of the backup file you want to restore (All, Settings Only, Pages Only, or Others Only).
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            <div className="sm:col-span-4 space-y-1">
              <label className="text-[11px] font-bold text-[#444746] block">What to Restore</label>
              <select
                value={restoreScope}
                onChange={(e) => setRestoreScope(e.target.value as any)}
                className="w-full px-3 py-2.5 rounded-xl bg-white border border-[#c4c7c5] text-xs font-semibold"
              >
                <option value="auto">Auto / Everything in File</option>
                <option value="settings">Website Settings Only</option>
                <option value="pages">Generated Pages Only</option>
                <option value="others">Others (Accounts &amp; Analytics) Only</option>
              </select>
            </div>

            <div className="sm:col-span-4 space-y-1">
              <label className="text-[11px] font-bold text-[#444746] block">Restore Mode</label>
              <select
                value={restoreMode}
                onChange={(e) => setRestoreMode(e.target.value as any)}
                className="w-full px-3 py-2.5 rounded-xl bg-white border border-[#c4c7c5] text-xs font-semibold"
              >
                <option value="merge">Safe Merge (Keep Existing + Update)</option>
                <option value="overwrite">Overwrite Selected Scope</option>
              </select>
            </div>

            <div className="sm:col-span-4">
              <label className="w-full px-4 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs shadow-xs transition-all cursor-pointer flex items-center justify-center gap-2">
                <span>Upload &amp; Restore JSON</span>
                <input
                  type="file"
                  accept=".json,application/json"
                  onChange={handleRestoreFileUpload}
                  className="hidden"
                />
              </label>
            </div>
          </div>
        </div>

        {/* 3. One-Click Server Snapshots */}
        <div className="p-5 rounded-2xl bg-[#f8fafd] border border-[#e1e7f0] space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h4 className="text-xs font-bold uppercase tracking-wider text-[#111827]">
                3. Instant Snapshots (Restore All or Separately Anytime)
              </h4>
              <p className="text-[11px] text-[#5f6368]">
                Create instant restore points before updates or configuration changes.
              </p>
            </div>
            <div className="flex items-center gap-2 flex-wrap">
              <select
                value={snapshotScope}
                onChange={(e) => setSnapshotScope(e.target.value as any)}
                className="px-3 py-2 rounded-xl bg-white border border-[#c4c7c5] text-xs font-semibold"
              >
                <option value="all">Scope: Full Everything</option>
                <option value="settings">Scope: Settings Only</option>
                <option value="pages">Scope: Pages Only</option>
                <option value="others">Scope: Others Only</option>
              </select>
              <input
                type="text"
                value={snapshotLabel}
                onChange={(e) => setSnapshotLabel(e.target.value)}
                placeholder="Optional snapshot note..."
                className="px-3 py-2 rounded-xl bg-white border border-[#c4c7c5] text-xs w-44"
              />
              <button
                type="button"
                onClick={handleCreateSnapshot}
                className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs cursor-pointer"
              >
                + Create Snapshot
              </button>
            </div>
          </div>

          {snapshots.length === 0 ? (
            <div className="text-center py-5 text-xs text-[#5f6368] bg-white rounded-xl border border-dashed border-[#c4c7c5]">
              No snapshots created yet. Click <strong>+ Create Snapshot</strong> above to save an instant restore point.
            </div>
          ) : (
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {snapshots.map((snap) => (
                <div
                  key={snap.id}
                  className="p-3 bg-white rounded-xl border border-[#e0e4eb] flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                >
                  <div>
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="text-xs font-bold text-[#1f1f1f]">{snap.label}</span>
                      <span className="text-[10px] font-mono px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-bold uppercase">
                        {snap.scope}
                      </span>
                      <span className="text-[10px] font-mono text-[#747775]">{snap.created_at}</span>
                    </div>
                    <div className="text-[11px] font-mono text-[#5f6368]">
                      Settings: {snap.counts?.settings || 0} • Pages: {snap.counts?.pages || 0} • Others: {snap.counts?.users || 0}
                    </div>
                  </div>
                  <div className="flex items-center gap-1.5 flex-wrap">
                    <button
                      type="button"
                      onClick={() => executeRestorePayload(snap.payload, "auto", "merge")}
                      className="px-2.5 py-1 rounded-lg bg-[#0b57d0] hover:bg-[#0842a0] text-white text-[11px] font-bold cursor-pointer"
                    >
                      Restore All
                    </button>
                    <button
                      type="button"
                      onClick={() => executeRestorePayload(snap.payload, "settings", "merge")}
                      className="px-2 py-1 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 text-[11px] font-bold cursor-pointer"
                    >
                      Settings Only
                    </button>
                    <button
                      type="button"
                      onClick={() => executeRestorePayload(snap.payload, "pages", "merge")}
                      className="px-2 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-[11px] font-bold cursor-pointer"
                    >
                      Pages Only
                    </button>
                    <button
                      type="button"
                      onClick={() => executeRestorePayload(snap.payload, "others", "merge")}
                      className="px-2 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-[11px] font-bold cursor-pointer"
                    >
                      Others Only
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        const next = snapshots.filter((s) => s.id !== snap.id);
                        setSnapshots(next);
                        localStorage.setItem("slea_server_snapshots", JSON.stringify(next));
                        onNotify("Snapshot deleted.", "info");
                      }}
                      className="px-2 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-[11px] font-bold cursor-pointer"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
