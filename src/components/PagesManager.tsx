import React, { useEffect, useState } from "react";
import { Copy, Eye, Trash2, ExternalLink, RefreshCw, FileText, Globe, Layers, ShieldCheck, CheckSquare, Square } from "lucide-react";
import { ButtonPage } from "../types";

interface PagesManagerProps {
  adminToken: string;
  onViewPage: (slug: string) => void;
  onNotify?: (text: string, type: "success" | "error" | "info") => void;
}

export const PagesManager: React.FC<PagesManagerProps> = ({
  adminToken,
  onViewPage,
  onNotify,
}) => {
  const [pages, setPages] = useState<ButtonPage[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [search, setSearch] = useState<string>("");
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [bulkDeleting, setBulkDeleting] = useState<boolean>(false);

  const fetchPages = async () => {
    setLoading(true);
    try {
      const res = await fetch("/api/pages", {
        headers: {
          Authorization: `Bearer ${adminToken}`,
          "x-admin-token": adminToken,
        },
      });
      const data = await res.json();
      if (data.success && Array.isArray(data.data)) {
        setPages(data.data);
      }
    } catch (err: any) {
      onNotify?.("Failed to fetch pages: " + err.message, "error");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPages();
  }, [adminToken]);

  const handleDelete = async (id: string, title: string) => {
    if (!window.confirm(`Are you sure you want to delete "${title}"?`)) return;

    try {
      const res = await fetch(`/api/pages/${encodeURIComponent(id)}`, {
        method: "DELETE",
        headers: {
          Authorization: `Bearer ${adminToken}`,
          "x-admin-token": adminToken,
        },
      });
      const data = await res.json();
      if (data.success) {
        setPages((prev) => prev.filter((p) => p.id !== id));
        setSelectedIds((prev) => {
          const next = new Set(prev);
          next.delete(id);
          return next;
        });
        onNotify?.("Page deleted successfully.", "info");
      } else {
        onNotify?.(data.error || "Failed to delete page", "error");
      }
    } catch (err: any) {
      onNotify?.("Delete failed: " + err.message, "error");
    }
  };

  const filtered = pages.filter((p) =>
    p.title.toLowerCase().includes(search.toLowerCase()) ||
    p.slug.toLowerCase().includes(search.toLowerCase())
  );

  const allFilteredSelected = filtered.length > 0 && filtered.every((p) => selectedIds.has(p.id));

  const toggleSelectAll = () => {
    if (allFilteredSelected) {
      setSelectedIds(new Set());
    } else {
      const next = new Set(selectedIds);
      filtered.forEach((p) => next.add(p.id));
      setSelectedIds(next);
    }
  };

  const toggleSelect = (id: string) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const handleBulkDelete = async () => {
    const count = selectedIds.size;
    if (count === 0) return;

    if (!window.confirm(`Are you sure you want to permanently delete ${count} selected button page${count === 1 ? "" : "s"}?`)) {
      return;
    }

    setBulkDeleting(true);
    try {
      const idsToDelete = Array.from(selectedIds);
      const res = await fetch("/api/pages/bulk-delete", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${adminToken}`,
          "x-admin-token": adminToken,
        },
        body: JSON.stringify({ ids: idsToDelete }),
      });
      const data = await res.json();
      if (data.success) {
        setPages((prev) => prev.filter((p) => !selectedIds.has(p.id)));
        setSelectedIds(new Set());
        onNotify?.(`Successfully deleted ${count} page${count === 1 ? "" : "s"}.`, "success");
      } else {
        onNotify?.(data.error || "Failed to delete selected pages", "error");
      }
    } catch (err: any) {
      onNotify?.("Bulk delete failed: " + err.message, "error");
    } finally {
      setBulkDeleting(false);
    }
  };

  const copyPageLink = (slug: string) => {
    const fullUrl = `${window.location.origin}/p/${slug}`;
    navigator.clipboard.writeText(fullUrl);
    onNotify?.("Button page link copied to clipboard!", "success");
  };

  return (
    <div className="w-full space-y-4">
      <div className="bg-white rounded-2xl p-5 sm:p-6 border border-[#e0e4eb] shadow-xs space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#f0f4f9]">
          <div className="space-y-0.5">
            <h2 className="text-base font-bold text-[#111827] flex items-center gap-2">
              <Layers className="w-4 h-4 text-[#0b57d0]" />
              <span>Active Button Pages ({pages.length})</span>
            </h2>
            <p className="text-xs text-[#5f6368]">
              Manage, preview, and bulk delete generated episode button pages.
            </p>
          </div>

          <div className="flex items-center gap-2 flex-wrap">
            {selectedIds.size > 0 && (
              <button
                onClick={handleBulkDelete}
                disabled={bulkDeleting}
                className="px-3 py-1.5 rounded-lg bg-[#ba1a1a] hover:bg-[#93000a] text-white text-xs font-semibold flex items-center gap-1.5 cursor-pointer transition-all shadow-2xs"
                title="Delete all selected pages"
              >
                <Trash2 className="w-3.5 h-3.5" />
                <span>Delete Selected ({selectedIds.size})</span>
              </button>
            )}

            <input
              type="text"
              placeholder="Filter pages..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="px-3 py-1.5 rounded-lg border border-[#c4c7c5] text-xs outline-none focus:border-[#0b57d0]"
            />
            <button
              onClick={fetchPages}
              className="p-2 rounded-lg text-[#5f6368] hover:bg-[#f0f4f9] border border-[#e1e7f0] cursor-pointer transition-colors"
              title="Refresh list"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${loading ? "animate-spin" : ""}`} />
            </button>
          </div>
        </div>

        {/* Selection bar if pages exist */}
        {filtered.length > 0 && (
          <div className="flex items-center justify-between px-3 py-2 bg-[#f8fafd] rounded-xl border border-[#e0e4eb] text-xs text-[#444746]">
            <label className="flex items-center gap-2 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={allFilteredSelected}
                onChange={toggleSelectAll}
                className="w-4 h-4 text-[#0b57d0] rounded border-[#c4c7c5] focus:ring-[#0b57d0] cursor-pointer"
              />
              <span className="font-semibold text-[11px]">
                {allFilteredSelected ? "Deselect All" : "Select All"} ({filtered.length} visible)
              </span>
            </label>

            {selectedIds.size > 0 && (
              <div className="flex items-center gap-2">
                <span className="text-[11px] font-bold text-[#0b57d0]">
                  {selectedIds.size} page{selectedIds.size === 1 ? "" : "s"} selected
                </span>
                <button
                  onClick={() => setSelectedIds(new Set())}
                  className="text-[11px] text-[#5f6368] hover:text-[#111827] underline cursor-pointer"
                >
                  Clear
                </button>
              </div>
            )}
          </div>
        )}

        {loading && pages.length === 0 ? (
          <div className="py-12 text-center text-xs text-[#747775]">
            Loading stored button pages...
          </div>
        ) : filtered.length === 0 ? (
          <div className="py-12 text-center text-xs text-[#747775] space-y-2">
            <FileText className="w-8 h-8 mx-auto text-[#c4c7c5]" />
            <p>No button pages match your filter or none created yet.</p>
          </div>
        ) : (
          <div className="space-y-3">
            {filtered.map((p) => {
              const fullUrl = `${window.location.origin}/p/${p.slug}`;
              const btnCount = p.buttons?.length || 0;
              const isSelected = selectedIds.has(p.id);

              return (
                <div
                  key={`page-row-${p.id}`}
                  className={`bg-white hover:bg-[#fdfdff] rounded-xl p-4 border transition-all shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 ${
                    isSelected ? "border-[#0b57d0] bg-[#f0f4f9]/50" : "border-[#e3e7ee] hover:border-[#c2e7ff]"
                  }`}
                >
                  <div className="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                    <input
                      type="checkbox"
                      checked={isSelected}
                      onChange={() => toggleSelect(p.id)}
                      className="mt-1 sm:mt-0 w-4 h-4 text-[#0b57d0] rounded border-[#c4c7c5] focus:ring-[#0b57d0] cursor-pointer shrink-0"
                    />

                    <div className="space-y-1 min-w-0 flex-1">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-bold text-sm text-[#111827] break-words">
                          {p.title}
                        </span>
                        <span className="px-2 py-0.5 rounded-full bg-[#e6f4ea] text-[#137333] text-[10px] font-bold shrink-0">
                          {p.views || 0} views
                        </span>
                        <span className="px-2 py-0.5 rounded-full bg-[#f0f4f9] text-[#444746] text-[10px] font-mono shrink-0">
                          {btnCount} buttons
                        </span>
                      </div>

                      <div className="flex items-center gap-2 text-[11px] text-[#747775] flex-wrap">
                        <span className="font-mono text-[#0b57d0] shrink-0">/p/{p.slug}</span>
                        <span>•</span>
                        <span className="shrink-0">{new Date(p.created_at).toLocaleDateString()}</span>
                        {p.resolved_url && (
                          <>
                            <span>•</span>
                            <span className="break-all max-w-[280px] font-mono text-[10px]">
                              {p.resolved_url}
                            </span>
                          </>
                        )}
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center gap-1.5 shrink-0 self-end sm:self-auto pl-7 sm:pl-0">
                    <button
                      onClick={() => copyPageLink(p.slug)}
                      className="px-2.5 py-1.5 rounded-lg bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] text-xs font-semibold flex items-center gap-1.5 cursor-pointer transition-colors"
                      title="Copy Public URL"
                    >
                      <Copy className="w-3.5 h-3.5" /> Copy Link
                    </button>

                    <button
                      onClick={() => onViewPage(p.slug)}
                      className="px-2.5 py-1.5 rounded-lg bg-[#e8f0fe] hover:bg-[#c2e7ff] text-[#0b57d0] text-xs font-semibold flex items-center gap-1.5 cursor-pointer transition-colors"
                    >
                      <Eye className="w-3.5 h-3.5" /> Preview
                    </button>

                    <button
                      onClick={() => handleDelete(p.id, p.title)}
                      className="p-1.5 rounded-lg text-[#c5221f] hover:bg-[#fce8e6] cursor-pointer transition-colors"
                      title="Delete Page"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};
