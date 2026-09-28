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

  const getDefaultFutureMaintenanceTime = (minutes = 120) => {
    const future = new Date(Date.now() + minutes * 60 * 1000);
    const year = future.getFullYear();
    const month = String(future.getMonth() + 1).padStart(2, "0");
    const day = String(future.getDate()).padStart(2, "0");
    const hours = String(future.getHours()).padStart(2, "0");
    const mins = String(future.getMinutes()).padStart(2, "0");
    return {
      end_time: `${year}-${month}-${day}T${hours}:${mins}`,
      end_timestamp: future.getTime(),
    };
  };

  const [maintenanceSettings, setMaintenanceSettings] = useState<{
    enabled: boolean;
    message: string;
    end_time?: string;
    end_timestamp?: number;
  }>(() => {
    const defTime = getDefaultFutureMaintenanceTime(120);
    const saved = localStorage.getItem("slea_maintenance_settings");
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        const hasValidFuture =
          parsed.end_timestamp && Number(parsed.end_timestamp) > Date.now();
        return {
          enabled: false,
          message: "The website is currently undergoing scheduled maintenance. We will be back shortly!",
          ...parsed,
          end_time: hasValidFuture ? parsed.end_time : defTime.end_time,
          end_timestamp: hasValidFuture ? Number(parsed.end_timestamp) : defTime.end_timestamp,
        };
      } catch {
        // fallback
      }
    }
    return {
      enabled: false,
      message: "The website is currently undergoing scheduled maintenance. We will be back shortly!",
      end_time: defTime.end_time,
      end_timestamp: defTime.end_timestamp,
    };
  });

  const [countdownPreview, setCountdownPreview] = useState({
    days: "00",
    hours: "00",
    mins: "00",
    secs: "00",
    targetLabel: "No countdown configured (Back online shortly)",
  });

  const parseLocalMaintenanceDate = (str?: string): Date | null => {
    if (!str) return null;
    const clean = str.trim();
    if (!clean) return null;
    const m = clean.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?$/);
    if (m) {
      const d = new Date(
        Number(m[1]),
        Number(m[2]) - 1,
        Number(m[3]),
        Number(m[4]),
        Number(m[5]),
        Number(m[6] || 0)
      );
      if (!isNaN(d.getTime())) return d;
    }
    const d2 = new Date(clean);
    return isNaN(d2.getTime()) ? null : d2;
  };

  useEffect(() => {
    const computeCountdown = () => {
      const endStr = maintenanceSettings.end_time?.trim() || "";
      if (!endStr) {
        setCountdownPreview({
          days: "00",
          hours: "00",
          mins: "00",
          secs: "00",
          targetLabel: "No countdown configured (Back online shortly)",
        });
        return;
      }
      const targetDate = parseLocalMaintenanceDate(endStr);
      const diffMs = targetDate ? targetDate.getTime() - Date.now() : NaN;
      if (!targetDate || isNaN(diffMs) || diffMs <= 0) {
        setCountdownPreview({
          days: "00",
          hours: "00",
          mins: "00",
          secs: "00",
          targetLabel: "Time reached / Wrapping up maintenance",
        });
        return;
      }
      const totalSecs = Math.floor(diffMs / 1000);
      const days = Math.floor(totalSecs / 86400);
      const hours = Math.floor((totalSecs % 86400) / 3600);
      const mins = Math.floor((totalSecs % 3600) / 60);
      const secs = totalSecs % 60;
      let targetLabel = "Target: " + endStr.replace("T", " ");
      try {
        targetLabel =
          "Target: " +
          targetDate.toLocaleString([], {
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
          });
      } catch {
        // ignore
      }
      setCountdownPreview({
        days: String(days).padStart(2, "0"),
        hours: String(hours).padStart(2, "0"),
        mins: String(mins).padStart(2, "0"),
        secs: String(secs).padStart(2, "0"),
        targetLabel,
      });
    };

    computeCountdown();
    const timer = setInterval(computeCountdown, 1000);
    return () => clearInterval(timer);
  }, [maintenanceSettings.end_time]);

  const applyMaintenancePreset = (minutes: number) => {
    const future = new Date(Date.now() + minutes * 60 * 1000);
    const year = future.getFullYear();
    const month = String(future.getMonth() + 1).padStart(2, "0");
    const day = String(future.getDate()).padStart(2, "0");
    const hours = String(future.getHours()).padStart(2, "0");
    const mins = String(future.getMinutes()).padStart(2, "0");
    const formatted = `${year}-${month}-${day}T${hours}:${mins}`;
    setMaintenanceSettings((prev) => {
      const next = {
        ...prev,
        end_time: formatted,
        end_timestamp: future.getTime(),
      };
      localStorage.setItem("slea_maintenance_settings", JSON.stringify(next));
      return next;
    });
    onNotify(
      `Maintenance countdown set to +${minutes >= 60 ? minutes / 60 + " hour(s)" : minutes + " mins"}`,
      "info"
    );
  };

  const clearMaintenancePreset = () => {
    setMaintenanceSettings((prev) => {
      const next = {
        ...prev,
        end_time: "",
        end_timestamp: 0,
      };
      localStorage.setItem("slea_maintenance_settings", JSON.stringify(next));
      return next;
    });
    onNotify("Maintenance end time countdown cleared.", "info");
  };

  const [newMenuTitle, setNewMenuTitle] = useState("");
  const [newMenuUrl, setNewMenuUrl] = useState("");
  const [newMenuBlank, setNewMenuBlank] = useState(false);

  // Backup & Restore State
  const [downloadBackupScope, setDownloadBackupScope] = useState<"all" | "settings" | "pages" | "others">("all");
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

  const [autoDetectedSummary, setAutoDetectedSummary] = useState<string>("");

  const inspectBackupPayload = (parsed: any) => {
    if (!parsed || typeof parsed !== "object") return "Invalid Backup File";
    const dataBlock = parsed.data && typeof parsed.data === "object" ? parsed.data : parsed;
    const parts: string[] = [];
    const hasSettings =
      (dataBlock.settings && typeof dataBlock.settings === "object" && Object.keys(dataBlock.settings).length > 0) ||
      ["site_identity", "menu_items", "footer_copyright", "ad_settings", "maintenance_settings"].some((k) => k in parsed);
    const hasPages = Array.isArray(dataBlock.pages) || (Array.isArray(parsed) && parsed.length > 0 && parsed[0]?.slug);
    const hasOthers =
      (dataBlock.others && typeof dataBlock.others === "object") ||
      Array.isArray(parsed.users) ||
      Array.isArray(parsed.page_views_monthly);

    if (hasSettings) {
      const sCount = dataBlock.settings ? Object.keys(dataBlock.settings).length : 1;
      parts.push(`Website Settings (${sCount})`);
    }
    if (hasPages) {
      const pCount = Array.isArray(dataBlock.pages) ? dataBlock.pages.length : Array.isArray(parsed) ? parsed.length : 1;
      parts.push(`Generated Pages (${pCount})`);
    }
    if (hasOthers) {
      parts.push(`Others (Accounts & Analytics)`);
    }
    return parts.length > 0 ? parts.join(" • ") : "Backup Data";
  };

  const handleRestoreFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
      const text = await file.text();
      const parsed = JSON.parse(text);
      const summary = inspectBackupPayload(parsed);
      setAutoDetectedSummary(summary);
      await executeRestorePayload(parsed, "auto", "merge", summary);
    } catch (err: any) {
      onNotify(`Restore failed: ${err.message || "Invalid JSON backup file"}`, "error");
    } finally {
      e.target.value = "";
    }
  };

  const executeRestorePayload = async (
    payload: any,
    scope: "auto" | "settings" | "pages" | "others" = "auto",
    mode: "merge" | "overwrite" = "merge",
    detectedLabel?: string
  ) => {
    const token = localStorage.getItem("slea_admin_token") || "admin_token_default_session";
    const res = await fetch("/api/backup/restore", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
        "x-admin-token": token,
      },
      body: JSON.stringify({ backup: payload, scope: "auto", mode: "merge" }),
    });
    const data = await res.json();
    if (!data.success) {
      throw new Error(data.error || "Restore failed");
    }
    const settingsData = payload?.data?.settings || payload?.settings || (payload?.site_identity ? payload : null);
    if (settingsData) {
      applyRestoredSettingsBundle(settingsData);
    }
    const label = detectedLabel || inspectBackupPayload(payload);
    setAutoDetectedSummary(label);
    onNotify(
      `Auto-Detected [${label}] & Restored: ${data.restored?.settings || 0} setting group(s), ${data.restored?.pages || 0} page(s), ${data.restored?.others || 0} account/analytics bundle(s)!`,
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
    const parsedDate = parseLocalMaintenanceDate(maintenanceSettings.end_time);
    const nextSettings = {
      ...maintenanceSettings,
      end_timestamp: parsedDate ? parsedDate.getTime() : 0,
    };
    setMaintenanceSettings(nextSettings);
    localStorage.setItem("slea_maintenance_settings", JSON.stringify(nextSettings));
    onNotify("Maintenance settings & countdown timer saved successfully!", "success");
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

      {/* TOP: Backup & Restore Center (Website Settings, Pages, and Others — Together or Separately with Automatic Restore Detection) */}
      <div className="bg-white rounded-3xl p-6 sm:p-8 border-2 border-[#c2e7ff] shadow-sm space-y-6" id="backup-restore-section">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-[#f0f4f9]">
          <div>
            <h3 className="font-bold text-base text-[#1f1f1f] flex items-center gap-2">
              <span>💾 Backup &amp; Restore Center</span>
              <span className="text-[11px] font-mono px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                Auto-Detect Restore • Zero Data Loss
              </span>
            </h3>
            <p className="text-xs text-[#5f6368] mt-0.5">
              Backup <strong>Website Settings</strong>, <strong>Generated Pages</strong>, and <strong>Others (Admin Accounts &amp; Analytics)</strong> all together or separately. Restore automatically detects any backup file format!
            </p>
          </div>
          <span className="text-[11px] font-bold px-2.5 py-1 rounded-full bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff] self-start sm:self-auto">
            Modular &amp; Update-Safe
          </span>
        </div>

        {/* 1. Download Portable JSON Backups (Drop-Down Selection) */}
        <div className="p-5 rounded-2xl bg-[#f8fafd] border border-[#d3e3fd] space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <h4 className="text-xs font-bold uppercase tracking-wider text-[#111827] flex items-center gap-2 flex-wrap">
                <span>1. Download Backup (.json) — Drop-Down Selection</span>
                <span className="text-[10px] font-mono px-2 py-0.5 rounded-full bg-blue-100 text-[#0b57d0] font-bold uppercase">
                  {downloadBackupScope === "all"
                    ? "ALL • FULL BACKUP"
                    : `${downloadBackupScope.toUpperCase()} ONLY`}
                </span>
              </h4>
              <p className="text-[11px] text-[#5f6368] mt-0.5">
                {downloadBackupScope === "all" &&
                  "Includes Website Settings + Generated Episode Pages + Admin Accounts & Monthly Analytics."}
                {downloadBackupScope === "settings" &&
                  "Includes Site Branding, Logo URL, Navigation Menu, Footer HTML, AdSense & Maintenance Settings."}
                {downloadBackupScope === "pages" &&
                  "Includes all generated Episode Button Pages, Slugs, Server Links & View Counts."}
                {downloadBackupScope === "others" &&
                  "Includes Admin Accounts, Permissions & Monthly View Analytics Statistics."}
              </p>
            </div>
            <span className="text-[11px] text-[#5f6368] font-medium shrink-0">Instant JSON export</span>
          </div>

          <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-3.5 rounded-xl border border-[#e0e4eb]">
            <div className="flex-1">
              <label htmlFor="downloadBackupScopeSelect" className="sr-only">
                Select Backup Type
              </label>
              <select
                id="downloadBackupScopeSelect"
                value={downloadBackupScope}
                onChange={(e) => setDownloadBackupScope(e.target.value as any)}
                className="w-full px-3.5 py-2.5 rounded-xl bg-[#f8fafd] border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-1 focus:ring-[#0b57d0] outline-none text-xs font-bold text-[#1f1f1f] cursor-pointer"
              >
                <option value="all">
                  📦 Full Backup (All) — Settings + Generated Pages + Accounts &amp; Analytics
                </option>
                <option value="settings">
                  ⚙️ Website Settings Only — Branding, Logo, Menu, Footer HTML, Ads &amp; Maintenance
                </option>
                <option value="pages">
                  📄 Generated Pages Only — All Episode Button Pages, Slugs, Links &amp; Views
                </option>
                <option value="others">
                  👤 Others Only — Admin Accounts, Permissions &amp; Monthly View Statistics
                </option>
              </select>
            </div>
            <button
              type="button"
              onClick={() => handleExportBackup(downloadBackupScope)}
              className="px-5 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs shadow-xs transition-all cursor-pointer flex items-center justify-center gap-2 shrink-0"
            >
              <span>
                ⬇ Download{" "}
                {downloadBackupScope === "all"
                  ? "Full"
                  : downloadBackupScope.charAt(0).toUpperCase() + downloadBackupScope.slice(1)}{" "}
                Backup (.json)
              </span>
            </button>
          </div>
        </div>

        {/* 2. Restore from Backup File — Automatic Detection */}
        <div className="p-5 rounded-2xl bg-[#f8fafd] border border-[#d3e3fd] space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <h4 className="text-xs font-bold uppercase tracking-wider text-[#111827] flex items-center gap-2">
                <span>2. Restore from Backup File (.json)</span>
                <span className="text-[10px] font-mono px-2 py-0.5 rounded-full bg-blue-100 text-[#0b57d0] font-bold">
                  ✨ Auto-Detects Backup Type Automatically
                </span>
              </h4>
              <p className="text-[11px] text-[#5f6368] mt-0.5">
                Simply upload any backup JSON file (Full Backup, Settings Only, Pages Only, or Others Only). The system automatically detects what is inside and restores it safely without losing any other data.
              </p>
            </div>
          </div>

          {autoDetectedSummary && (
            <div className="p-3.5 rounded-xl bg-white border border-emerald-200 flex items-center justify-between gap-3">
              <div className="flex items-center gap-2.5">
                <span className="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm">
                  ✓
                </span>
                <div>
                  <div className="text-xs font-bold text-[#1f1f1f]">Auto-Detected Backup Content:</div>
                  <div className="text-[11px] text-emerald-700 font-mono">{autoDetectedSummary}</div>
                </div>
              </div>
              <span className="text-[10px] font-mono px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold">
                ✓ Restored Automatically
              </span>
            </div>
          )}

          <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-3.5 rounded-xl border border-[#e0e4eb]">
            <div className="text-xs text-[#5f6368]">
              Select any <code className="font-mono text-[#0b57d0]">.json</code> backup file — automatic detection &amp; safe restore runs immediately upon selection.
            </div>
            <label className="px-5 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs shadow-xs transition-all cursor-pointer flex items-center justify-center gap-2 shrink-0">
              <span>⬆ Select &amp; Auto-Restore Backup (.json)</span>
              <input
                type="file"
                accept=".json,application/json"
                onChange={handleRestoreFileUpload}
                className="hidden"
              />
            </label>
          </div>
        </div>

        {/* 3. One-Click Server Snapshots */}
        <div className="p-5 rounded-2xl bg-[#f8fafd] border border-[#e1e7f0] space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h4 className="text-xs font-bold uppercase tracking-wider text-[#111827]">
                3. Instant Snapshots (Auto-Detected One-Click Restore)
              </h4>
              <p className="text-[11px] text-[#5f6368]">
                Create instant restore points for Full Backup, Settings Only, Pages Only, or Others Only.
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
                      className="px-3 py-1.5 rounded-lg bg-[#0b57d0] hover:bg-[#0842a0] text-white text-[11px] font-bold cursor-pointer"
                    >
                      ↻ Restore (Auto-Detect)
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        const next = snapshots.filter((s) => s.id !== snap.id);
                        setSnapshots(next);
                        localStorage.setItem("slea_server_snapshots", JSON.stringify(next));
                        onNotify("Snapshot deleted.", "info");
                      }}
                      className="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-[11px] font-bold cursor-pointer"
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
                    onChange={(e) => {
                      const checked = e.target.checked;
                      const parsed = parseLocalMaintenanceDate(maintenanceSettings.end_time);
                      const hasFuture = parsed && parsed.getTime() > Date.now();
                      const def = getDefaultFutureMaintenanceTime(120);
                      const next = {
                        ...maintenanceSettings,
                        enabled: checked,
                        end_time: checked && !hasFuture ? def.end_time : maintenanceSettings.end_time,
                        end_timestamp: checked && !hasFuture ? def.end_timestamp : maintenanceSettings.end_timestamp,
                      };
                      setMaintenanceSettings(next);
                      localStorage.setItem("slea_maintenance_settings", JSON.stringify(next));
                    }}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
              </div>

              {/* End Time Countdown Configuration */}
              <div className="space-y-2 p-4 bg-[#f8fafd] rounded-2xl border border-[#e1e7f0]">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-[#1f1f1f] flex items-center gap-1.5">
                    <span>⏱️ Maintenance End Time (Countdown Timer)</span>
                  </label>
                  <span className="text-[10px] text-[#0b57d0] font-semibold bg-[#e8f0fe] px-2 py-0.5 rounded-md">
                    Live Countdown
                  </span>
                </div>
                <input
                  type="datetime-local"
                  value={maintenanceSettings.end_time || ""}
                  onChange={(e) => {
                    const val = e.target.value;
                    const parsed = parseLocalMaintenanceDate(val);
                    setMaintenanceSettings({
                      ...maintenanceSettings,
                      end_time: val,
                      end_timestamp: parsed ? parsed.getTime() : 0,
                    });
                  }}
                  className="w-full px-3.5 py-2 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] outline-none text-xs font-semibold bg-white"
                />
                <div className="flex items-center gap-1.5 flex-wrap pt-1">
                  <span className="text-[10px] font-bold text-[#5f6368] uppercase mr-1">Quick Presets:</span>
                  <button
                    type="button"
                    onClick={() => applyMaintenancePreset(30)}
                    className="px-2 py-1 rounded-lg bg-white border border-[#dadce0] hover:bg-[#e8f0fe] hover:border-[#0b57d0] text-[11px] font-bold text-[#444746] transition-all cursor-pointer"
                  >
                    +30 Mins
                  </button>
                  <button
                    type="button"
                    onClick={() => applyMaintenancePreset(60)}
                    className="px-2 py-1 rounded-lg bg-white border border-[#dadce0] hover:bg-[#e8f0fe] hover:border-[#0b57d0] text-[11px] font-bold text-[#444746] transition-all cursor-pointer"
                  >
                    +1 Hour
                  </button>
                  <button
                    type="button"
                    onClick={() => applyMaintenancePreset(180)}
                    className="px-2 py-1 rounded-lg bg-white border border-[#dadce0] hover:bg-[#e8f0fe] hover:border-[#0b57d0] text-[11px] font-bold text-[#444746] transition-all cursor-pointer"
                  >
                    +3 Hours
                  </button>
                  <button
                    type="button"
                    onClick={() => applyMaintenancePreset(360)}
                    className="px-2 py-1 rounded-lg bg-white border border-[#dadce0] hover:bg-[#e8f0fe] hover:border-[#0b57d0] text-[11px] font-bold text-[#444746] transition-all cursor-pointer"
                  >
                    +6 Hours
                  </button>
                  <button
                    type="button"
                    onClick={() => applyMaintenancePreset(720)}
                    className="px-2 py-1 rounded-lg bg-white border border-[#dadce0] hover:bg-[#e8f0fe] hover:border-[#0b57d0] text-[11px] font-bold text-[#444746] transition-all cursor-pointer"
                  >
                    +12 Hours
                  </button>
                  <button
                    type="button"
                    onClick={() => applyMaintenancePreset(1440)}
                    className="px-2 py-1 rounded-lg bg-white border border-[#dadce0] hover:bg-[#e8f0fe] hover:border-[#0b57d0] text-[11px] font-bold text-[#444746] transition-all cursor-pointer"
                  >
                    +1 Day
                  </button>
                  <button
                    type="button"
                    onClick={() => applyMaintenancePreset(2880)}
                    className="px-2 py-1 rounded-lg bg-white border border-[#dadce0] hover:bg-[#e8f0fe] hover:border-[#0b57d0] text-[11px] font-bold text-[#444746] transition-all cursor-pointer"
                  >
                    +2 Days
                  </button>
                  <button
                    type="button"
                    onClick={clearMaintenancePreset}
                    className="px-2 py-1 rounded-lg bg-white border border-[#fce8e6] hover:bg-[#fce8e6] text-[11px] font-bold text-[#c5221f] transition-all cursor-pointer"
                  >
                    Clear
                  </button>
                </div>
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
              <span className="text-[11px] font-bold uppercase text-[#5f6368] block">Public Screen Preview:</span>
              <div className="border border-[#e0e4eb] rounded-3xl p-4 bg-[#f8fafd] shadow-2xs space-y-3 relative overflow-hidden">
                <div className="relative w-full aspect-[4/3] rounded-xl overflow-hidden bg-[#fafbfc] border border-[#f0f4f9]">
                  <img
                    src="/assets/images/maintenance_illustration.jpg"
                    alt="Maintenance Illustration"
                    className="w-full h-full object-cover"
                    referrerPolicy="no-referrer"
                  />
                </div>
                <div className="text-center space-y-2">
                  <div className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#fef7e0] border border-[#feebc8] text-[#b06000] text-[9px] font-bold font-mono">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#b06000] animate-ping"></span>
                    <span>SYSTEM MAINTENANCE</span>
                  </div>
                  <h4 className="text-xs font-black text-[#111827]">We'll Be Right Back</h4>
                  <p className="text-[10px] text-[#5f6368] leading-normal line-clamp-2 px-2">
                    {maintenanceSettings.message || "The website is currently undergoing scheduled maintenance. We will be back shortly!"}
                  </p>

                  {/* Live Countdown Badges Preview */}
                  <div className="p-2.5 rounded-xl bg-white border border-[#e0e4eb] space-y-1.5 shadow-2xs">
                    <div className="text-[9px] font-bold uppercase tracking-wider text-[#0b57d0] flex items-center justify-center gap-1">
                      <span>⏱️ Estimated End Time Countdown</span>
                    </div>
                    <div className="grid grid-cols-4 gap-1 text-center font-mono">
                      <div className="bg-[#f0f4f9] rounded-lg p-1">
                        <div className="text-xs font-extrabold text-[#0b57d0]">{countdownPreview.days}</div>
                        <div className="text-[8px] text-[#5f6368] font-sans uppercase">Days</div>
                      </div>
                      <div className="bg-[#f0f4f9] rounded-lg p-1">
                        <div className="text-xs font-extrabold text-[#0b57d0]">{countdownPreview.hours}</div>
                        <div className="text-[8px] text-[#5f6368] font-sans uppercase">Hours</div>
                      </div>
                      <div className="bg-[#f0f4f9] rounded-lg p-1">
                        <div className="text-xs font-extrabold text-[#0b57d0]">{countdownPreview.mins}</div>
                        <div className="text-[8px] text-[#5f6368] font-sans uppercase">Mins</div>
                      </div>
                      <div className="bg-[#f0f4f9] rounded-lg p-1">
                        <div className="text-xs font-extrabold text-[#0b57d0]">{countdownPreview.secs}</div>
                        <div className="text-[8px] text-[#5f6368] font-sans uppercase">Secs</div>
                      </div>
                    </div>
                    <div className="text-[9px] text-[#747775] font-sans">
                      {countdownPreview.targetLabel}
                    </div>
                  </div>
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
    </div>
  );
};
