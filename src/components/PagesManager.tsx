import React, { useEffect, useState } from "react";
import { Copy, Eye, Trash2, Edit, RefreshCw, FileText, Layers, Plus, X } from "lucide-react";
import { ButtonPage, PageButton } from "../types";

interface PagesManagerProps {
  adminToken: string;
  initialEditPageId?: string | null;
  onClearEditPageId?: () => void;
  onViewPage: (slug: string) => void;
  onNotify?: (text: string, type: "success" | "error" | "info") => void;
}

export const PagesManager: React.FC<PagesManagerProps> = ({
  adminToken,
  initialEditPageId,
  onClearEditPageId,
  onViewPage,
  onNotify,
}) => {
  const [pages, setPages] = useState<ButtonPage[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [search, setSearch] = useState<string>("");
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [bulkDeleting, setBulkDeleting] = useState<boolean>(false);

  // Edit Modal State
  const [editingPage, setEditingPage] = useState<ButtonPage | null>(null);
  const [editForm, setEditForm] = useState<{
    id: string;
    title: string;
    slug: string;
    description: string;
    is_public: number;
    theme: string;
    buttons: PageButton[];
  }>({
    id: "",
    title: "",
    slug: "",
    description: "",
    is_public: 1,
    theme: "indigo",
    buttons: [],
  });
  const [savingEdit, setSavingEdit] = useState<boolean>(false);

  const fetchPages = async () => {
    setLoading(true);
    try {
      const res = await fetch("/api/pages", {
        credentials: "same-origin",
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

  // Handle initialEditPageId if navigated with specific page ID
  useEffect(() => {
    if (initialEditPageId && pages.length > 0) {
      const target = pages.find((p) => String(p.id) === String(initialEditPageId) || p.slug === String(initialEditPageId));
      if (target) {
        openEditModal(target);
      }
      onClearEditPageId?.();
    }
  }, [initialEditPageId, pages]);

  const openEditModal = (page: ButtonPage) => {
    setEditingPage(page);
    setEditForm({
      id: page.id,
      title: page.title || "",
      slug: page.slug || "",
      description: page.description || "",
      is_public: page.is_public === undefined || Boolean(Number(page.is_public)) ? 1 : 0,
      theme: page.theme || "indigo",
      buttons: Array.isArray(page.buttons) && page.buttons.length > 0
        ? [...page.buttons.map(b => ({ ...b }))]
        : [{ text: "Episode 1", url: "", quality: "720p" }],
    });
  };

  const closeEditModal = () => {
    setEditingPage(null);
  };

  const handleAddButtonRow = () => {
    const nextIdx = editForm.buttons.length + 1;
    setEditForm(prev => ({
      ...prev,
      buttons: [
        ...prev.buttons,
        { text: `Episode ${nextIdx}`, url: "", quality: "720p" }
      ]
    }));
  };

  const handleRemoveButtonRow = (index: number) => {
    setEditForm(prev => ({
      ...prev,
      buttons: prev.buttons.filter((_, i) => i !== index)
    }));
  };

  const handleButtonChange = (index: number, field: keyof PageButton, value: string) => {
    setEditForm(prev => {
      const updated = [...prev.buttons];
      updated[index] = { ...updated[index], [field]: value };
      return { ...prev, buttons: updated };
    });
  };

  const handleSaveEdit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editForm.id) return;
    setSavingEdit(true);

    try {
      const validButtons = editForm.buttons.filter(b => b.text && b.url);
      const res = await fetch("/api/pages/update", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${adminToken}`,
          "x-admin-token": adminToken,
        },
        body: JSON.stringify({
          id: editForm.id,
          title: editForm.title,
          slug: editForm.slug,
          description: editForm.description,
          is_public: editForm.is_public,
          theme: editForm.theme,
          buttons: validButtons,
        }),
      });

      const data = await res.json();
      if (data.success && data.page) {
        setPages(prev => prev.map(p => (p.id === data.page.id ? data.page : p)));
        onNotify?.("Page updated successfully!", "success");
        closeEditModal();
      } else {
        onNotify?.(data.error || "Failed to update page", "error");
      }
    } catch (err: any) {
      onNotify?.("Request failed: " + err.message, "error");
    } finally {
      setSavingEdit(false);
    }
  };

  const handleDelete = async (id: string, title: string) => {
    if (!window.confirm(`Are you sure you want to delete "${title}"?`)) return;

    try {
      const res = await fetch(`/api/pages/${encodeURIComponent(id)}`, {
        method: "DELETE",
        credentials: "same-origin",
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
        credentials: "same-origin",
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

  const handleToggleStatus = async (id: string) => {
    try {
      const res = await fetch("/api/pages/toggle-status", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${adminToken}`,
          "x-admin-token": adminToken,
        },
        body: JSON.stringify({ id }),
      });
      const data = await res.json();
      if (data.success && data.page) {
        const isPub = Number(data.page.is_public) === 1;
        setPages((prev) =>
          prev.map((p) => (p.id === id ? { ...p, is_public: isPub ? 1 : 0 } : p))
        );
        onNotify?.(
          isPub ? "Page visibility changed to Public" : "Page visibility changed to Private",
          isPub ? "success" : "info"
        );
      } else {
        onNotify?.(data.error || "Could not update page visibility", "error");
      }
    } catch (err: any) {
      onNotify?.("Toggle visibility failed: " + err.message, "error");
    }
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
              Manage, edit, preview, and delete generated episode button pages.
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

                        <button
                          type="button"
                          onClick={() => handleToggleStatus(p.id)}
                          title="Click to toggle Public / Private visibility"
                          className={`px-2 py-0.5 rounded-full text-[10px] font-bold cursor-pointer transition-colors shrink-0 ${
                            p.is_public === undefined || Boolean(Number(p.is_public))
                              ? "bg-[#e6f4ea] text-[#137333] border border-[#a8dab5]"
                              : "bg-[#fff0d4] text-[#b06000] border border-[#feebc8]"
                          }`}
                        >
                          {p.is_public === undefined || Boolean(Number(p.is_public)) ? "● Public" : "○ Private"}
                        </button>
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
                      onClick={() => openEditModal(p)}
                      className="px-2.5 py-1.5 rounded-lg bg-[#f0f4f9] hover:bg-[#e8f0fe] text-[#0b57d0] text-xs font-semibold flex items-center gap-1.5 cursor-pointer transition-colors border border-[#e0e4eb]"
                      title="Edit Page Details & Buttons"
                    >
                      <Edit className="w-3.5 h-3.5" /> Edit
                    </button>

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

      {/* Edit Page Modal */}
      {editingPage && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
          <div className="bg-white rounded-3xl border border-[#e0e4eb] shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in duration-200">
            {/* Modal Header */}
            <div className="px-6 py-4 border-b border-[#f0f4f9] flex items-center justify-between shrink-0 bg-[#f8fafd]">
              <div>
                <h3 className="text-base font-bold text-[#111827] flex items-center gap-2">
                  <span>✏️ Edit Button Page</span>
                  <span className="text-xs font-mono font-normal text-[#0b57d0]">/p/{editForm.slug}</span>
                </h3>
                <p className="text-xs text-[#5f6368]">
                  Update page title, slug, visibility status, and download button links.
                </p>
              </div>
              <button
                type="button"
                onClick={closeEditModal}
                className="p-1.5 rounded-xl text-[#5f6368] hover:text-[#111827] hover:bg-[#e0e4eb] transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Form Content */}
            <form onSubmit={handleSaveEdit} className="flex-1 overflow-y-auto p-6 space-y-4">
              <div className="space-y-1">
                <label className="text-xs font-semibold text-[#444746]">Page Title</label>
                <input
                  type="text"
                  value={editForm.title}
                  onChange={(e) => setEditForm(prev => ({ ...prev, title: e.target.value }))}
                  required
                  className="w-full px-3 py-2 rounded-xl border border-[#c4c7c5] text-xs font-medium focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-[#444746]">URL Slug</label>
                  <input
                    type="text"
                    value={editForm.slug}
                    onChange={(e) => setEditForm(prev => ({ ...prev, slug: e.target.value }))}
                    required
                    className="w-full px-3 py-2 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-[#444746]">Visibility Status</label>
                  <div className="flex items-center gap-2 pt-1">
                    <button
                      type="button"
                      onClick={() => setEditForm(prev => ({ ...prev, is_public: prev.is_public === 1 ? 0 : 1 }))}
                      className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out ${
                        editForm.is_public === 1 ? "bg-[#137333]" : "bg-slate-300"
                      }`}
                    >
                      <span
                        className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out ${
                          editForm.is_public === 1 ? "translate-x-5" : "translate-x-0"
                        }`}
                      />
                    </button>
                    <span className={`text-xs font-bold ${editForm.is_public === 1 ? "text-[#137333]" : "text-[#b06000]"}`}>
                      {editForm.is_public === 1 ? "Public (Live)" : "Private (404 for Public)"}
                    </span>
                  </div>
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-[#444746]">Description / Notice (Optional)</label>
                <textarea
                  rows={2}
                  value={editForm.description}
                  onChange={(e) => setEditForm(prev => ({ ...prev, description: e.target.value }))}
                  className="w-full px-3 py-2 rounded-xl border border-[#c4c7c5] text-xs focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none resize-none"
                />
              </div>

              {/* Episode Buttons Manager */}
              <div className="space-y-2 pt-2 border-t border-[#f0f4f9]">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-[#111827]">
                    Episode Download Buttons ({editForm.buttons.length})
                  </label>
                  <button
                    type="button"
                    onClick={handleAddButtonRow}
                    className="px-2.5 py-1 rounded-lg bg-[#e8f0fe] text-[#0b57d0] hover:bg-[#c2e7ff] text-[11px] font-bold flex items-center gap-1 cursor-pointer transition-colors"
                  >
                    <Plus className="w-3 h-3" /> Add Button
                  </button>
                </div>

                <div className="space-y-2 max-h-60 overflow-y-auto pr-1">
                  {editForm.buttons.map((btn, idx) => (
                    <div
                      key={`btn-edit-${idx}`}
                      className="grid grid-cols-12 gap-2 bg-[#f8fafd] p-2.5 rounded-xl border border-[#e0e4eb] items-center"
                    >
                      <div className="col-span-5">
                        <input
                          type="text"
                          placeholder="Button Label (Episode 1)"
                          value={btn.text}
                          onChange={(e) => handleButtonChange(idx, "text", e.target.value)}
                          required
                          className="w-full px-2.5 py-1.5 rounded-lg border border-[#c4c7c5] text-xs font-medium focus:border-[#0b57d0] outline-none"
                        />
                      </div>
                      <div className="col-span-5">
                        <input
                          type="url"
                          placeholder="Destination URL"
                          value={btn.url}
                          onChange={(e) => handleButtonChange(idx, "url", e.target.value)}
                          required
                          className="w-full px-2.5 py-1.5 rounded-lg border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] outline-none"
                        />
                      </div>
                      <div className="col-span-1">
                        <input
                          type="text"
                          placeholder="HD"
                          value={btn.quality || ""}
                          onChange={(e) => handleButtonChange(idx, "quality", e.target.value)}
                          className="w-full px-1.5 py-1.5 rounded-lg border border-[#c4c7c5] text-[11px] text-center focus:border-[#0b57d0] outline-none"
                        />
                      </div>
                      <div className="col-span-1 text-center">
                        <button
                          type="button"
                          onClick={() => handleRemoveButtonRow(idx)}
                          className="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                          title="Remove Button"
                        >
                          ✕
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Modal Footer Actions */}
              <div className="pt-4 border-t border-[#f0f4f9] flex items-center justify-end gap-2 shrink-0">
                <button
                  type="button"
                  onClick={closeEditModal}
                  className="px-4 py-2 rounded-xl text-xs font-semibold text-[#444746] hover:bg-[#f0f4f9] border border-[#e0e4eb] cursor-pointer transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={savingEdit}
                  className="px-5 py-2 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] disabled:bg-[#a8c7fa] text-white text-xs font-bold cursor-pointer transition-all shadow-xs"
                >
                  {savingEdit ? "Saving..." : "Save Changes"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
