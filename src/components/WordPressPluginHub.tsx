import React, { useState } from "react";
import {
  Download,
  Check,
  Copy,
  Clock,
  Zap,
  FolderArchive,
  Terminal,
  FileCode2,
  ExternalLink,
  ChevronDown,
  ChevronUp,
  AlertCircle,
  PlayCircle,
  Settings,
  ShieldCheck,
} from "lucide-react";

interface WordPressPluginHubProps {
  onNotify: (text: string, type?: "success" | "error" | "info") => void;
  onSimulateSample?: (url: string) => void;
}

export const WordPressPluginHub: React.FC<WordPressPluginHubProps> = ({
  onNotify,
  onSimulateSample,
}) => {
  const [activeTab, setActiveTab] = useState<"overview" | "features" | "install" | "code">(
    "overview"
  );
  const [copiedZipUrl, setCopiedZipUrl] = useState(false);
  const [expandedFile, setExpandedFile] = useState<string>("main");

  const sampleUrl =
    "https://mydverse.com/2026/09/our-universe-korean-drama-in-hindi-dubbed/";

  const handleCopyDownloadUrl = async () => {
    try {
      const url = `${window.location.origin}/api/download-plugin-zip`;
      await navigator.clipboard.writeText(url);
      setCopiedZipUrl(true);
      onNotify("Plugin .zip direct URL copied to clipboard!", "success");
      setTimeout(() => setCopiedZipUrl(false), 2000);
    } catch {
      onNotify("Failed to copy URL", "error");
    }
  };

  return (
    <div
      id="wordpress-plugin-hub"
      className="bg-[#fdfcff] rounded-[28px] m3-elevation-1 border border-[#e1e7f0] overflow-hidden mb-6"
    >
      {/* Top Banner / Announcement */}
      <div className="p-6 sm:p-7 border-b border-[#f0f4f9] bg-gradient-to-r from-[#e8def8]/40 via-[#d3e3fd]/30 to-[#fdfcff]">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="flex items-start sm:items-center gap-3.5">
            <div className="w-12 h-12 rounded-2xl bg-[#6750a4] text-white flex items-center justify-center shrink-0 shadow-sm">
              <FolderArchive className="w-6 h-6" />
            </div>
            <div>
              <div className="flex items-center gap-2 flex-wrap">
                <h2 className="text-base sm:text-lg font-semibold text-[#1f1f1f]">
                  Source Link & Episode Button Automator
                </h2>
                <span className="text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-[#e8def8] text-[#4a4458]">
                  WordPress Plugin v2.0.0
                </span>
                <span className="text-[11px] font-medium px-2 py-0.5 rounded-full bg-[#dcfce7] text-[#14532d]">
                  "Ready" Status Support
                </span>
                <span className="text-[11px] font-medium px-2 py-0.5 rounded-full bg-[#c2e7ff] text-[#001d35]">
                  WP Automatic Ready
                </span>
                <span className="text-[11px] font-medium px-2 py-0.5 rounded-full bg-[#e8def8] text-[#4a4458]">
                  Strict Data Isolation
                </span>
              </div>
              <p className="text-xs sm:text-sm text-[#444746] mt-0.5">
                Engineered for WP Automatic & RSS feeds with 10+ pending posts: bypasses shorteners/redirects, generates isolated Gutenberg Custom HTML blocks, and marks posts as Ready without mixing buttons.
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2 shrink-0">
            <a
              id="btn-download-plugin-zip"
              href="/api/download-plugin-zip"
              download="source-link-episode-automator.zip"
              onClick={() => onNotify("Downloading plugin zip file...", "info")}
              className="px-4 py-2.5 bg-[#0b57d0] hover:bg-[#0842a0] text-white rounded-full text-xs font-semibold flex items-center gap-2 shadow-xs transition-all active:scale-95 cursor-pointer"
            >
              <Download className="w-4 h-4" />
              <span>Download .ZIP</span>
            </a>

            <button
              id="btn-copy-plugin-link"
              onClick={handleCopyDownloadUrl}
              className="p-2.5 bg-white hover:bg-[#f0f4f9] text-[#444746] border border-[#e1e7f0] rounded-full transition-colors cursor-pointer"
              title="Copy direct download link"
            >
              {copiedZipUrl ? (
                <Check className="w-4 h-4 text-emerald-600" />
              ) : (
                <Copy className="w-4 h-4" />
              )}
            </button>
          </div>
        </div>

        {/* Tab Pills */}
        <div className="flex items-center gap-1.5 mt-5 overflow-x-auto pb-1 scrollbar-none">
          {[
            { id: "overview", label: "Overview & Workflow" },
            { id: "features", label: "Features & Cron Options" },
            { id: "install", label: "Installation Guide" },
            { id: "code", label: "PHP Source Code" },
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`px-3.5 py-1.5 rounded-full text-xs font-medium cursor-pointer transition-all shrink-0 ${
                activeTab === tab.id
                  ? "bg-[#1f1f1f] text-white shadow-xs"
                  : "bg-white/80 text-[#444746] hover:bg-white border border-[#e1e7f0]"
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>
      </div>

      {/* Tab Contents */}
      <div className="p-6">
        {/* TAB 1: OVERVIEW */}
        {activeTab === "overview" && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="p-4 bg-[#f8fafd] rounded-2xl border border-[#e1e7f0]">
                <div className="w-8 h-8 rounded-full bg-[#d3e3fd] text-[#0b57d0] flex items-center justify-center font-bold text-xs mb-2">
                  1
                </div>
                <h4 className="text-xs font-semibold text-[#1f1f1f]">
                  RSS Importer Ingests Post
                </h4>
                <p className="text-[11px] text-[#444746] mt-1 leading-relaxed">
                  Your existing tool imports drama posts and sets them to <strong>"Pending"</strong> with an ending hyperlink:{" "}
                  <code className="bg-[#e9eef6] px-1 py-0.5 rounded text-[10px] break-all">
                    Source Link
                  </code>
                  .
                </p>
              </div>

              <div className="p-4 bg-[#f8fafd] rounded-2xl border border-[#e1e7f0]">
                <div className="w-8 h-8 rounded-full bg-[#e8def8] text-[#6750a4] flex items-center justify-center font-bold text-xs mb-2">
                  2
                </div>
                <h4 className="text-xs font-semibold text-[#1f1f1f]">
                  Auto-Resolve & Extract
                </h4>
                <p className="text-[11px] text-[#444746] mt-1 leading-relaxed">
                  The plugin runs in background via <strong>WP-Cron</strong> (or manual click), traces all redirects & shorteners, and scrapes destination episode links.
                </p>
              </div>

              <div className="p-4 bg-[#f8fafd] rounded-2xl border border-[#e1e7f0]">
                <div className="w-8 h-8 rounded-full bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-bold text-xs mb-2">
                  3
                </div>
                <h4 className="text-xs font-semibold text-[#1f1f1f]">
                  Replace HTML & Mark Ready
                </h4>
                <p className="text-[11px] text-[#444746] mt-1 leading-relaxed">
                  The plugin replaces the "Source Link" hyperlink in-place with styled mobile-width buttons, and switches status from <strong>Pending &rarr; Ready</strong> (with identical permissions as Pending). If processing fails, it remains in <strong>Pending</strong>.
                </p>
              </div>
            </div>

            {/* Quick Test Trigger */}
            <div className="p-4 bg-[#edf4ff] rounded-2xl border border-[#a8c7fa] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div className="space-y-0.5">
                <span className="text-[11px] font-bold text-[#0b57d0] uppercase tracking-wide">
                  Live Sample Test
                </span>
                <p className="text-xs font-medium text-[#1f1f1f] font-mono break-all">
                  {sampleUrl}
                </p>
              </div>

              {onSimulateSample && (
                <button
                  type="button"
                  onClick={() => {
                    onSimulateSample(sampleUrl);
                    onNotify("Loaded sample into Web Workspace simulator below!", "info");
                  }}
                  className="px-4 py-2 bg-[#0b57d0] hover:bg-[#0842a0] text-white rounded-full text-xs font-medium flex items-center gap-1.5 shrink-0 shadow-xs cursor-pointer"
                >
                  <PlayCircle className="w-3.5 h-3.5" />
                  <span>Simulate in Web App</span>
                </button>
              )}
            </div>
          </div>
        )}

        {/* TAB 2: FEATURES */}
        {activeTab === "features" && (
          <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div className="p-3.5 bg-[#f8fafd] rounded-xl border border-[#e1e7f0]">
                <div className="flex items-center gap-2">
                  <ShieldCheck className="w-4 h-4 text-emerald-600" />
                  <h4 className="text-xs font-semibold text-[#1f1f1f]">
                    Strict Multi-Post Isolation (WP Automatic)
                  </h4>
                </div>
                <p className="text-[11px] text-[#444746] mt-1">
                  Handles 10+ pending posts safely. Each post is locked via atomic post meta (<code className="text-[10px]">_slea_processing_lock</code>) and batch mutex (<code className="text-[10px]">slea_batch_running_lock</code>) ensuring buttons never mix between posts.
                </p>
              </div>

              <div className="p-3.5 bg-[#f8fafd] rounded-xl border border-[#e1e7f0]">
                <div className="flex items-center gap-2">
                  <Clock className="w-4 h-4 text-[#0b57d0]" />
                  <h4 className="text-xs font-semibold text-[#1f1f1f]">
                    How Often Should the Tool Run
                  </h4>
                </div>
                <p className="text-[11px] text-[#444746] mt-1">
                  Configure scheduling directly in settings: <strong>Every 1 min</strong>, <strong>2 min</strong>, <strong>5 min</strong>, <strong>10 min</strong>, <strong>15 min</strong>, <strong>30 min</strong>, <strong>Hourly</strong>, or <strong>Custom Minutes</strong>. No manual crontab required.
                </p>
              </div>

              <div className="p-3.5 bg-[#f8fafd] rounded-xl border border-[#e1e7f0]">
                <div className="flex items-center gap-2">
                  <Zap className="w-4 h-4 text-[#6750a4]" />
                  <h4 className="text-xs font-semibold text-[#1f1f1f]">
                    How Many Times Should the Tool Run
                  </h4>
                </div>
                <p className="text-[11px] text-[#444746] mt-1">
                  Specify target execution count (e.g. <strong>5</strong>, <strong>10</strong>, <strong>20</strong>, <strong>50</strong> runs). Once reached, the scheduler automatically pauses. Set to <strong>0</strong> for unlimited continuous background execution.
                </p>
              </div>

              <div className="p-3.5 bg-[#f8fafd] rounded-xl border border-[#e1e7f0]">
                <div className="flex items-center gap-2">
                  <FileCode2 className="w-4 h-4 text-[#0b57d0]" />
                  <h4 className="text-xs font-semibold text-[#1f1f1f]">
                    Gutenberg Custom HTML Block
                  </h4>
                </div>
                <p className="text-[11px] text-[#444746] mt-1">
                  Outputs clean <code className="text-[10px]">&lt;!-- wp:html --&gt;</code> blocks stamped with <code className="text-[10px]">data-slea-post-id</code> attributes, fully compatible with WordPress block and classic editors.
                </p>
              </div>
            </div>
          </div>
        )}

        {/* TAB 3: INSTALLATION */}
        {activeTab === "install" && (
          <div className="space-y-4 text-xs text-[#444746]">
            <ol className="list-decimal pl-4 space-y-2.5">
              <li>
                Click the <strong>Download .ZIP</strong> button above or download{" "}
                <code className="bg-[#e9eef6] px-1.5 py-0.5 rounded text-[#0b57d0]">
                  source-link-episode-automator.zip
                </code>
                .
              </li>
              <li>
                In your WordPress Dashboard, navigate to{" "}
                <strong>Plugins &rarr; Add New Plugin &rarr; Upload Plugin</strong>.
              </li>
              <li>
                Select the downloaded <code className="bg-[#e9eef6] px-1 py-0.5 rounded">.zip</code> file and click <strong>Install Now</strong>, then click <strong>Activate Plugin</strong>.
              </li>
              <li>
                A new admin page will appear in your sidebar:{" "}
                <strong>Episode Automator</strong>.
              </li>
              <li>
                Verify your desired schedule in <strong>Settings</strong> (e.g. Every 15 minutes, 5 posts per pass), test with the built-in URL sandbox, and let it run automatically!
              </li>
            </ol>
          </div>
        )}

        {/* TAB 4: CODE VIEW */}
        {activeTab === "code" && (
          <div className="space-y-3">
            <div className="flex items-center gap-2 flex-wrap text-xs">
              {[
                { id: "main", label: "source-link-episode-automator.php" },
                { id: "processor", label: "class-slea-processor.php" },
                { id: "cron", label: "class-slea-cron.php" },
                { id: "resolver", label: "class-slea-resolver.php" },
                { id: "extractor", label: "class-slea-extractor.php" },
                { id: "buttons", label: "class-slea-button-generator.php" },
              ].map((f) => (
                <button
                  key={f.id}
                  onClick={() => setExpandedFile(f.id)}
                  className={`px-3 py-1 rounded-lg font-mono text-[11px] transition-colors cursor-pointer ${
                    expandedFile === f.id
                      ? "bg-[#0b57d0] text-white"
                      : "bg-[#f0f4f9] text-[#444746] hover:bg-[#e1e7f0]"
                  }`}
                >
                  {f.label}
                </button>
              ))}
            </div>

            <div className="p-4 bg-[#1f1f1f] text-[#f0f4f9] rounded-2xl font-mono text-xs overflow-x-auto max-h-80 leading-relaxed border border-[#333]">
              {expandedFile === "main" && (
                <pre>{`// Main Plugin Header
/*
 * Plugin Name: Source Link & Episode Button Automator
 * Plugin URI:  https://mydverse.com/
 * Description: Automatically detects "Source Link" in pending RSS imported posts,
 *              resolves redirects and bypasses shortlinks, extracts episode buttons,
 *              replaces the Source Link with responsive episode buttons HTML,
 *              and auto-publishes on schedule (WP-Cron) or manual batch trigger.
 * Version:     1.0.0
 */`}</pre>
              )}

              {expandedFile === "processor" && (
                <pre>{`// SLEA_Processor::process_post($post_id)
// 1. Detect "Source Link" regex / anchor text in post_content
// 2. Resolve URL via SLEA_Resolver (HTTP 3xx, meta refresh, JS location)
// 3. Extract episode buttons via SLEA_Extractor
// 4. Generate responsive mobile-width buttons HTML (alternating blue)
// 5. Replace <p><a ...>Source Link</a></p> with buttons HTML
// 6. Change post status to 'publish' and update post!`}</pre>
              )}

              {expandedFile === "cron" && (
                <pre>{`// SLEA_Cron
// Custom intervals: 5min, 15min, 30min, hourly
add_filter('cron_schedules', ['SLEA_Cron', 'register_schedules']);
add_action('slea_run_pending_post_automator', ['SLEA_Cron', 'execute_cron']);

// Executes batch processing in background on schedule`}</pre>
              )}

              {expandedFile === "resolver" && (
                <pre>{`// SLEA_Resolver::resolve_url($url)
// Follows hops, stores session cookies, extracts meta refresh,
// parses JS window.location & obfuscated atob() base64 targets.`}</pre>
              )}

              {expandedFile === "extractor" && (
                <pre>{`// SLEA_Extractor::extract_links($html, $base_url, $button_only)
// Uses DOMDocument with regex fallback to isolate action / episode links.`}</pre>
              )}

              {expandedFile === "buttons" && (
                <pre>{`// SLEA_Button_Generator::generate_html($items)
// Responsive mobile width (calc(100% - 40px), max-width 440px)
// 20px Left/Right margins, Auto height
// Blue combo: Alternating filled (#2563eb) & outlined (rgba(37,99,235,0.08))
// - Session End - red footer`}</pre>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
