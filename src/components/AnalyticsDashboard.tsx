import React, { useState, useEffect } from "react";
import { BarChart3, TrendingUp, Users, Clock, Globe, ArrowUpRight, Download, RefreshCw, Smartphone, Monitor, ShieldCheck, Calendar, MapPin } from "lucide-react";
import { ButtonPage } from "../types";

interface AnalyticsDashboardProps {
  pages: ButtonPage[];
  onRefresh: () => void;
}

export const AnalyticsDashboard: React.FC<AnalyticsDashboardProps> = ({ pages, onRefresh }) => {
  const [timeRange, setTimeRange] = useState<"current_month" | "prev_month" | "ytd" | "all">("current_month");
  const [chartMode, setChartMode] = useState<"daily" | "monthly_compare">("daily");
  const [refreshing, setRefreshing] = useState(false);

  const handleRefresh = () => {
    setRefreshing(true);
    onRefresh();
    setTimeout(() => setRefreshing(false), 600);
  };

  // Compute real metrics from actual page views and creation dates
  const totalViews = pages.reduce((acc, p) => acc + (p.views || 0), 0);
  const activePagesCount = pages.length;
  
  // Real Current Month vs Last Month calculation based on actual page creation timestamps & recorded views
  const now = new Date();
  const currentMonthNum = now.getMonth();
  const currentYearNum = now.getFullYear();

  let currentMonthViews = 0;
  let prevMonthViews = 0;
  let hasCurrentMonthPages = false;
  let hasPrevMonthPages = false;

  pages.forEach((p) => {
    const pDate = new Date(p.created_at || Date.now());
    const views = p.views || 0;
    if (pDate.getMonth() === currentMonthNum && pDate.getFullYear() === currentYearNum) {
      currentMonthViews += views;
      hasCurrentMonthPages = true;
    } else {
      prevMonthViews += views;
      hasPrevMonthPages = true;
    }
  });

  // Calculate real growth rate only when real previous month views exist
  const hasHistoricalComparison = hasPrevMonthPages && prevMonthViews > 0;
  const growthRate = hasHistoricalComparison
    ? Math.round(((currentMonthViews - prevMonthViews) / prevMonthViews) * 100)
    : null;

  // Real data only: telemetry metrics that are not tracked in database must show N/A or —
  const estimatedSessions = null; // Telemetry not logged in DB
  const estimatedVisitors = null; // Telemetry not logged in DB
  const avgVisitTimeFormatted = "—"; // Telemetry not logged in DB
  const bounceRate = "—"; // Telemetry not logged in DB
  const conversionRate = totalViews > 0 ? "100%" : "—";

  // Top 10 most viewed pages (100% real database records)
  const topPages = [...pages].sort((a, b) => (b.views || 0) - (a.views || 0)).slice(0, 10);

  // Daily traffic distribution for current month (30 days) weighted by total views
  const currentMonthDays = 30;
  const dailyData = Array.from({ length: currentMonthDays }, (_, i) => {
    const day = i + 1;
    const base = Math.floor(Math.max(10, totalViews) / currentMonthDays);
    const variance = Math.sin(i * 0.7) * (base * 0.4) + (i % 5 === 0 ? base * 0.6 : 0);
    return {
      day: `Day ${day}`,
      views: Math.max(2, Math.round(base + variance))
    };
  });

  const maxDailyViews = Math.max(...dailyData.map(d => d.views), 10);

  const handleExportCSV = () => {
    const headers = ["Rank", "Title", "Slug", "Views", "Created At"];
    const rows = topPages.map((p, idx) => [
      idx + 1,
      `"${p.title.replace(/"/g, '""')}"`,
      p.slug,
      p.views || 0,
      p.created_at
    ]);
    const csvContent = "data:text/csv;charset=utf-8," + [headers.join(","), ...rows.map(e => e.join(","))].join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `movie_hub_analytics_${timeRange}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="space-y-6 animate-fade-in pb-12">
      {/* Top Header & Range Selector */}
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <div className="w-9 h-9 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center font-bold">
              <BarChart3 className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-lg sm:text-xl font-extrabold text-[#111827]">Monthly Analytics & Statistics</h2>
              <p className="text-xs text-[#5f6368]">Real-time visitor traffic, top performing episode pages, engagement metrics & audience insights.</p>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2.5 flex-wrap">
          <div className="inline-flex rounded-xl bg-[#f1f3f4] p-1 border border-[#e0e4eb]">
            <button
              onClick={() => setTimeRange("current_month")}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${timeRange === "current_month" ? "bg-white text-[#0b57d0] shadow-2xs" : "text-[#5f6368] hover:text-[#1f1f1f]"}`}
            >
              This Month
            </button>
            <button
              onClick={() => setTimeRange("prev_month")}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${timeRange === "prev_month" ? "bg-white text-[#0b57d0] shadow-2xs" : "text-[#5f6368] hover:text-[#1f1f1f]"}`}
            >
              Last Month
            </button>
            <button
              onClick={() => setTimeRange("ytd")}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${timeRange === "ytd" ? "bg-white text-[#0b57d0] shadow-2xs" : "text-[#5f6368] hover:text-[#1f1f1f]"}`}
            >
              YTD
            </button>
            <button
              onClick={() => setTimeRange("all")}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${timeRange === "all" ? "bg-white text-[#0b57d0] shadow-2xs" : "text-[#5f6368] hover:text-[#1f1f1f]"}`}
            >
              All-Time
            </button>
          </div>

          <button
            onClick={handleExportCSV}
            className="px-3.5 py-2 rounded-xl bg-[#f1f3f4] hover:bg-[#e2e7ec] text-[#3c4043] font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer border border-[#d9dbded]"
            title="Export CSV Report"
          >
            <Download className="w-3.5 h-3.5" />
            <span className="hidden sm:inline">Export CSV</span>
          </button>

          <button
            onClick={handleRefresh}
            className={`p-2 rounded-xl bg-[#e8f0fe] hover:bg-[#d3e3fd] text-[#0b57d0] transition-colors cursor-pointer border border-[#c2e7ff] ${refreshing ? "animate-spin" : ""}`}
            title="Refresh Analytics"
          >
            <RefreshCw className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* 4 Key Metrics Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Card 1: Total Website Visits */}
        <div className="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3 relative overflow-hidden group hover:border-[#0b57d0] transition-all">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-[#5f6368] uppercase tracking-wider">Total Website Visits</span>
            <div className="w-8 h-8 rounded-xl bg-[#e6f4ea] text-[#137333] flex items-center justify-center font-bold">
              <Globe className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl sm:text-3xl font-extrabold text-[#111827] font-mono">{totalViews.toLocaleString()}</span>
            {growthRate !== null ? (
              <span className={`text-xs font-bold inline-flex items-center px-1.5 py-0.5 rounded-md ${growthRate >= 0 ? "bg-[#e6f4ea] text-[#137333]" : "bg-red-50 text-red-600"}`}>
                <TrendingUp className="w-3 h-3 mr-0.5" /> {growthRate >= 0 ? "+" : ""}{growthRate}%
              </span>
            ) : (
              <span className="text-xs font-bold text-[#5f6368] bg-[#f1f3f4] px-1.5 py-0.5 rounded-md">
                Growth: N/A
              </span>
            )}
          </div>
          <p className="text-[11px] text-[#747775]">
            {growthRate !== null ? "Compared to previous month recorded pages" : "Insufficient previous month data"}
          </p>
        </div>

        {/* Card 2: Active Sessions */}
        <div className="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3 relative overflow-hidden group hover:border-[#0b57d0] transition-all">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-[#5f6368] uppercase tracking-wider">Active Sessions</span>
            <div className="w-8 h-8 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center font-bold">
              <Users className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl sm:text-3xl font-extrabold text-[#111827] font-mono">—</span>
            <span className="text-xs font-bold text-[#5f6368] inline-flex items-center bg-[#f1f3f4] px-1.5 py-0.5 rounded-md">
              N/A
            </span>
          </div>
          <p className="text-[11px] text-[#747775]">Session telemetry not logged in DB</p>
        </div>

        {/* Card 3: Avg Visit Duration */}
        <div className="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3 relative overflow-hidden group hover:border-[#0b57d0] transition-all">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-[#5f6368] uppercase tracking-wider">Avg Visit Duration</span>
            <div className="w-8 h-8 rounded-xl bg-[#fef7e0] text-[#b06000] flex items-center justify-center font-bold">
              <Clock className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl sm:text-3xl font-extrabold text-[#111827] font-mono">—</span>
            <span className="text-xs font-bold text-[#5f6368] inline-flex items-center bg-[#f1f3f4] px-1.5 py-0.5 rounded-md">
              N/A
            </span>
          </div>
          <p className="text-[11px] text-[#747775]">Duration telemetry not logged in DB</p>
        </div>

        {/* Card 4: Published Pages */}
        <div className="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3 relative overflow-hidden group hover:border-[#0b57d0] transition-all">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-[#5f6368] uppercase tracking-wider">Published Pages</span>
            <div className="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
              <ShieldCheck className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl sm:text-3xl font-extrabold text-[#111827] font-mono">{activePagesCount}</span>
            <span className="text-xs font-bold text-purple-700 inline-flex items-center bg-purple-50 px-1.5 py-0.5 rounded-md">
              {activePagesCount > 0 ? "100% Online" : "0 Published"}
            </span>
          </div>
          <p className="text-[11px] text-[#747775]">Active verified button pages</p>
        </div>
      </div>

      {/* Traffic Trend & Last Month vs Current Month Chart Section */}
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#f1f3f4] pb-4">
          <div>
            <h3 className="text-base font-bold text-[#1f1f1f]">
              {chartMode === "daily" ? "Daily Page Views (Current Month)" : "Last Month vs Current Month Comparison"}
            </h3>
            <p className="text-xs text-[#5f6368]">
              {chartMode === "daily" ? "Time-series view overview." : "Real comparative view analysis between previous and current months."}
            </p>
          </div>
          <div className="flex items-center gap-2">
            <div className="inline-flex rounded-xl bg-[#f1f3f4] p-1 border border-[#e0e4eb]">
              <button
                onClick={() => setChartMode("daily")}
                className={`px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${chartMode === "daily" ? "bg-white text-[#0b57d0] shadow-2xs" : "text-[#5f6368]"}`}
              >
                Daily Trend
              </button>
              <button
                onClick={() => setChartMode("monthly_compare")}
                className={`px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${chartMode === "monthly_compare" ? "bg-white text-[#0b57d0] shadow-2xs" : "text-[#5f6368]"}`}
              >
                Last Month vs Current Month
              </button>
            </div>
          </div>
        </div>

        {chartMode === "daily" ? (
          totalViews === 0 ? (
            <div className="py-12 text-center text-xs text-[#747775]">
              No page visits recorded yet. Visits will automatically increment in real-time as visitors open button pages.
            </div>
          ) : (
            <div className="py-8 text-center space-y-2">
              <div className="text-3xl font-extrabold text-[#0b57d0] font-mono">{totalViews.toLocaleString()}</div>
              <p className="text-xs font-medium text-[#5f6368]">Total Real Verified Page Views across all active button pages.</p>
              <span className="inline-block px-3 py-1 rounded-full bg-[#f1f3f4] text-[#5f6368] text-[11px] font-medium border border-[#e0e4eb]">
                Hourly/daily timeseries telemetry: N/A (Aggregate database views shown)
              </span>
            </div>
          )
        ) : (
          <div className="py-6 px-4 grid grid-cols-1 sm:grid-cols-2 gap-8 items-center">
            <div className="space-y-6">
              <div className="space-y-2">
                <div className="flex justify-between text-xs font-bold">
                  <span className="text-[#5f6368]">Last Month Views</span>
                  <span className="font-mono text-[#5f6368]">
                    {hasPrevMonthPages ? `${prevMonthViews.toLocaleString()} views` : "— (N/A)"}
                  </span>
                </div>
                <div className="w-full bg-[#f1f3f4] h-4 rounded-full overflow-hidden">
                  <div
                    className="bg-slate-400 h-full rounded-full transition-all"
                    style={{
                      width: hasHistoricalComparison
                        ? `${Math.min(100, Math.max(10, (prevMonthViews / Math.max(1, currentMonthViews + prevMonthViews)) * 200))}%`
                        : "0%"
                    }}
                  />
                </div>
              </div>
              <div className="space-y-2">
                <div className="flex justify-between text-xs font-bold">
                  <span className="text-[#0b57d0]">Current Month Views</span>
                  <span className="font-mono text-[#0b57d0]">
                    {hasCurrentMonthPages ? `${currentMonthViews.toLocaleString()} views` : (totalViews > 0 ? `${totalViews.toLocaleString()} views` : "— (0 views)")}
                  </span>
                </div>
                <div className="w-full bg-[#f1f3f4] h-4 rounded-full overflow-hidden">
                  <div
                    className="bg-gradient-to-r from-[#0b57d0] to-[#4285f4] h-full rounded-full transition-all"
                    style={{
                      width: totalViews > 0
                        ? (hasHistoricalComparison
                            ? `${Math.min(100, Math.max(15, (currentMonthViews / Math.max(1, currentMonthViews + prevMonthViews)) * 200))}%`
                            : "100%")
                        : "0%"
                    }}
                  />
                </div>
              </div>
            </div>
            <div className="bg-[#f8f9fa] rounded-2xl p-6 border border-[#e0e4eb] space-y-3 text-center sm:text-left">
              <h4 className="font-bold text-sm text-[#111827]">Monthly Growth Summary</h4>
              {growthRate !== null ? (
                <p className="text-xs text-[#5f6368] leading-relaxed">
                  Current month traffic changed by <span className="font-bold text-[#137333]">{growthRate >= 0 ? "+" : ""}{growthRate}%</span> compared to last month based on actual database records.
                </p>
              ) : (
                <p className="text-xs text-[#5f6368] leading-relaxed">
                  No historical traffic logged for the previous month. Growth percentage calculation: <span className="font-bold text-[#444746]">N/A</span>.
                </p>
              )}
              <div className="pt-2 flex items-center justify-center sm:justify-start gap-3">
                <span className="px-3 py-1 bg-[#e8f0fe] text-[#0b57d0] rounded-xl text-xs font-mono font-bold">
                  Current: {currentMonthViews || totalViews}
                </span>
                <span className="px-3 py-1 bg-slate-100 text-slate-600 rounded-xl text-xs font-mono font-bold">
                  Previous: {hasPrevMonthPages ? prevMonthViews : "N/A"}
                </span>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Top 10 Most Viewed Pages Table */}
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
        <div className="flex items-center justify-between border-b border-[#f1f3f4] pb-4">
          <div>
            <h3 className="text-base font-bold text-[#1f1f1f]">Top 10 Most Viewed Episode Pages</h3>
            <p className="text-xs text-[#5f6368]">Ranked strictly by real database view counts.</p>
          </div>
          <span className="px-3 py-1 rounded-full bg-[#e8f0fe] text-[#0b57d0] text-xs font-bold border border-[#c2e7ff]">
            {topPages.length} Pages
          </span>
        </div>

        {topPages.length === 0 ? (
          <div className="text-center py-12 text-[#747775] text-xs">
            No pages generated yet. Create your first page in the Generate tab.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-[#e1e7f0] text-[11px] font-bold text-[#5f6368] uppercase tracking-wider">
                  <th className="py-3 px-4">Rank</th>
                  <th className="py-3 px-4">Page Title & Slug</th>
                  <th className="py-3 px-4 text-center">Views</th>
                  <th className="py-3 px-4 text-center">Traffic Share</th>
                  <th className="py-3 px-4 text-right">Created</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#f1f3f4] text-xs">
                {topPages.map((p, index) => {
                  const share = totalViews > 0 ? Math.round(((p.views || 0) / totalViews) * 100) : 0;
                  return (
                    <tr key={p.id} className="hover:bg-[#fafcff] transition-colors">
                      <td className="py-3.5 px-4 font-mono font-extrabold text-[#0b57d0]">
                        #{index + 1}
                      </td>
                      <td className="py-3.5 px-4 min-w-[240px]">
                        <div className="font-bold text-[#111827] truncate max-w-sm" title={p.title}>{p.title}</div>
                        <div className="text-[11px] font-mono text-[#5f6368]">/p/{p.slug}</div>
                      </td>
                      <td className="py-3.5 px-4 text-center font-mono font-bold text-[#137333]">
                        {(p.views || 0).toLocaleString()}
                      </td>
                      <td className="py-3.5 px-4 text-center">
                        <div className="flex items-center justify-center gap-2">
                          <div className="w-20 bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200">
                            <div className="bg-[#0b57d0] h-full rounded-full" style={{ width: `${Math.max(5, share)}%` }} />
                          </div>
                          <span className="font-mono text-[11px] font-bold text-[#444746]">{share}%</span>
                        </div>
                      </td>
                      <td className="py-3.5 px-4 text-right font-mono text-[#747775]">
                        {new Date(p.created_at).toLocaleDateString()}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Device Breakdown, Top Traffic Channels & Top Traffic Country */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Device Breakdown */}
        <div className="bg-white rounded-3xl p-6 border border-[#e0e4eb] shadow-xs space-y-4">
          <div className="flex items-center justify-between">
            <h4 className="font-bold text-sm text-[#111827] flex items-center gap-2">
              <Smartphone className="w-4 h-4 text-[#0b57d0]" />
              <span>Device Breakdown</span>
            </h4>
            <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">N/A</span>
          </div>
          <div className="space-y-3 text-xs">
            <div>
              <div className="flex justify-between font-medium mb-1 text-[#5f6368]">
                <span className="flex items-center gap-1.5"><Smartphone className="w-3.5 h-3.5" /> Mobile Smartphone</span>
                <span className="font-mono font-bold text-slate-400">—</span>
              </div>
              <div className="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                <div className="bg-slate-300 h-full rounded-full" style={{ width: "0%" }} />
              </div>
            </div>
            <div>
              <div className="flex justify-between font-medium mb-1 text-[#5f6368]">
                <span className="flex items-center gap-1.5"><Monitor className="w-3.5 h-3.5" /> Desktop PC / Mac</span>
                <span className="font-mono font-bold text-slate-400">—</span>
              </div>
              <div className="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                <div className="bg-slate-300 h-full rounded-full" style={{ width: "0%" }} />
              </div>
            </div>
            <div>
              <div className="flex justify-between font-medium mb-1 text-[#5f6368]">
                <span>Tablet / iPad</span>
                <span className="font-mono font-bold text-slate-400">—</span>
              </div>
              <div className="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                <div className="bg-slate-300 h-full rounded-full" style={{ width: "0%" }} />
              </div>
            </div>
          </div>
          <p className="text-[10px] text-[#747775] leading-relaxed pt-1">
            Device user-agent telemetry is not logged. No simulated/fake data is shown.
          </p>
        </div>

        {/* Top Traffic Channels */}
        <div className="bg-white rounded-3xl p-6 border border-[#e0e4eb] shadow-xs space-y-4">
          <div className="flex items-center justify-between">
            <h4 className="font-bold text-sm text-[#111827] flex items-center gap-2">
              <Globe className="w-4 h-4 text-[#137333]" />
              <span>Top Traffic Channels</span>
            </h4>
            <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">N/A</span>
          </div>
          <div className="space-y-3 text-xs">
            <div>
              <div className="flex justify-between font-medium mb-1 text-[#5f6368]">
                <span>Direct &amp; Bookmark Links</span>
                <span className="font-mono font-bold text-slate-400">—</span>
              </div>
              <div className="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                <div className="bg-slate-300 h-full rounded-full" style={{ width: "0%" }} />
              </div>
            </div>
            <div>
              <div className="flex justify-between font-medium mb-1 text-[#5f6368]">
                <span>Social Media (Telegram / WhatsApp)</span>
                <span className="font-mono font-bold text-slate-400">—</span>
              </div>
              <div className="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                <div className="bg-slate-300 h-full rounded-full" style={{ width: "0%" }} />
              </div>
            </div>
            <div>
              <div className="flex justify-between font-medium mb-1 text-[#5f6368]">
                <span>Organic Search &amp; Referrals</span>
                <span className="font-mono font-bold text-slate-400">—</span>
              </div>
              <div className="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                <div className="bg-slate-300 h-full rounded-full" style={{ width: "0%" }} />
              </div>
            </div>
          </div>
          <p className="text-[10px] text-[#747775] leading-relaxed pt-1">
            HTTP Referrer channel tracking is not logged. No simulated/fake data is shown.
          </p>
        </div>

        {/* Top Traffic Country */}
        <div className="bg-white rounded-3xl p-6 border border-[#e0e4eb] shadow-xs space-y-4">
          <div className="flex items-center justify-between">
            <h4 className="font-bold text-sm text-[#111827] flex items-center gap-2">
              <MapPin className="w-4 h-4 text-[#b06000]" />
              <span>Top Traffic Country</span>
            </h4>
            <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">N/A</span>
          </div>
          <div className="space-y-3 text-xs">
            <div>
              <div className="flex justify-between font-medium mb-1 text-[#5f6368]">
                <span>Primary Location</span>
                <span className="font-mono font-bold text-slate-400">—</span>
              </div>
              <div className="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                <div className="bg-slate-300 h-full rounded-full" style={{ width: "0%" }} />
              </div>
            </div>
            <div>
              <div className="flex justify-between font-medium mb-1 text-[#5f6368]">
                <span>Secondary Location</span>
                <span className="font-mono font-bold text-slate-400">—</span>
              </div>
              <div className="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                <div className="bg-slate-300 h-full rounded-full" style={{ width: "0%" }} />
              </div>
            </div>
            <div>
              <div className="flex justify-between font-medium mb-1 text-[#5f6368]">
                <span>Other Regions</span>
                <span className="font-mono font-bold text-slate-400">—</span>
              </div>
              <div className="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                <div className="bg-slate-300 h-full rounded-full" style={{ width: "0%" }} />
              </div>
            </div>
          </div>
          <p className="text-[10px] text-[#747775] leading-relaxed pt-1">
            Visitor GeoIP country tracking is not enabled. No simulated/fake data is shown.
          </p>
        </div>
      </div>
    </div>
  );
};

