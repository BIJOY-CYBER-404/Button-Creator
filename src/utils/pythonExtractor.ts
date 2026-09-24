import { ExtractedItem } from "../types";

export const ACTION_WORDS = new Set([
  "download",
  "watch",
  "stream",
  "play",
  "open",
  "direct",
  "server",
  "link",
  "get",
  "view",
  "continue",
  "mirror",
  "xcloud",
  "filemoon",
  "streamtape",
  "doodstream",
  "mixdrop",
]);

export const INTERNAL_NAV_WORDS = new Set([
  "home",
  "about",
  "contact",
  "privacy",
  "privacy-policy",
  "cookie",
  "cookie-policy",
  "sitemap",
  "search",
  "login",
  "register",
  "rtl",
  "category",
  "categories",
  "archive",
  "next",
  "previous",
  "prev",
]);

export const EXCLUDED_EXPLICIT_TESTS = [
  "rich results test",
  "pagespeed insights",
];

export function isBlockedTestLink(
  text: string | null | undefined,
  url: string | null | undefined
): boolean {
  const textLower = (text || "").toLowerCase();
  const urlLower = (url || "").toLowerCase();
  for (const phrase of EXCLUDED_EXPLICIT_TESTS) {
    if (textLower.includes(phrase)) {
      return true;
    }
  }
  if (urlLower.includes("test/rich-results") || urlLower.includes("rich-results")) {
    return true;
  }
  if (urlLower.includes("pagespeed.web.dev") || urlLower.includes("pagespeed")) {
    return true;
  }
  return false;
}

export const EXCLUDED_TAGS = new Set([
  "HEADER",
  "FOOTER",
  "NAV",
  "ASIDE",
  "SCRIPT",
  "STYLE",
  "NOSCRIPT",
]);

export const EXCLUDED_MARKERS = [
  "header",
  "footer",
  "navbar",
  "navigation",
  "nav-menu",
  "navmenu",
  "main-menu",
  "mainmenu",
  "menu",
  "menus",
  "sidebar",
  "site-header",
  "site-footer",
  "topbar",
  "top-bar",
  "bottombar",
  "bottom-bar",
  "breadcrumb",
  "breadcrumbs",
];

export function cleanText(value: string | null | undefined): string {
  return String(value || "")
    .replace(/\s+/g, " ")
    .trim();
}

export function words(value: string | null | undefined): Set<string> {
  const matches = cleanText(value).toLowerCase().match(/[a-z0-9]+/g);
  return new Set(matches || []);
}

export function sameSite(a: string, b: string): boolean {
  try {
    return new URL(a).hostname === new URL(b).hostname;
  } catch {
    return false;
  }
}

export function extractUrlFromJS(value: string | null | undefined): string | null {
  if (!value) return null;
  const patterns = [
    /(?:window\.)?location(?:\.href)?\s*=\s*['"]([^'"]+)['"]/i,
    /window\.open\s*\(\s*['"]([^'"]+)['"]/i,
    /(?:href|url)\s*:\s*['"]([^'"]+)['"]/i,
    /['"]((?:https?:\/\/|\/|\.\/|\.\.\/)[^'"]+)['"]/i,
  ];
  for (const re of patterns) {
    const m = String(value).match(re);
    if (m) return m[1].trim();
  }
  return null;
}

function excludedContainer(el: Element): boolean {
  if (!el || el.nodeType !== 1) return false;
  const tag = el.tagName.toUpperCase();
  if (EXCLUDED_TAGS.has(tag)) return true;

  const role = (el.getAttribute("role") || "").toLowerCase();
  if (
    [
      "navigation",
      "banner",
      "contentinfo",
      "complementary",
      "menubar",
      "menu",
    ].includes(role)
  ) {
    return true;
  }

  const values = [
    el.id || "",
    typeof el.className === "string" ? el.className : "",
    el.getAttribute("aria-label") || "",
    el.getAttribute("title") || "",
  ]
    .join(" ")
    .toLowerCase();

  const tokens = new Set(values.split(/[\s_]+/).filter(Boolean));
  for (const marker of EXCLUDED_MARKERS) {
    if (tokens.has(marker)) return true;
    if (
      values.includes(marker) &&
      [
        "header",
        "footer",
        "menu",
        "menus",
        "navigation",
        "navbar",
        "sidebar",
        "nav-menu",
      ].includes(marker)
    ) {
      return true;
    }
  }
  return false;
}

function insideExcluded(el: Element): boolean {
  let p: Element | null = el;
  while (p && p.nodeType === 1) {
    if (excludedContainer(p)) return true;
    p = p.parentElement;
  }
  return false;
}

function rawTarget(el: Element): string | null {
  const tag = el.tagName.toLowerCase();
  if (tag === "a") {
    return el.getAttribute("href");
  }
  return (
    el.getAttribute("formaction") ||
    el.getAttribute("data-url") ||
    el.getAttribute("data-href") ||
    el.getAttribute("data-link") ||
    el.getAttribute("data-target") ||
    extractUrlFromJS(el.getAttribute("onclick") || "")
  );
}

export function isCandidate(el: Element, baseUrl: string): boolean {
  const text = cleanText(
    (el as HTMLElement).innerText ||
      el.textContent ||
      (el as HTMLInputElement).value ||
      ""
  );
  const css = [
    typeof el.className === "string" ? el.className : "",
    el.id || "",
    el.getAttribute("role") || "",
    ...Array.from(el.querySelectorAll("*")).map((child) =>
      typeof child.className === "string" ? child.className : ""
    ),
  ]
    .join(" ")
    .toLowerCase();

  const target = rawTarget(el);
  if (!target) return false;

  if (isBlockedTestLink(text, target)) return false;

  // Exclude icons
  if (/\b(icon|icons|fa|fab|fas|far|svg-icon|social|share)\b/i.test(css) && (!text || text.length <= 2)) {
    return false;
  }

  // Check nav words
  const cleanTextLower = text.toLowerCase().replace(/[\s_]+/g, "-");
  if (INTERNAL_NAV_WORDS.has(cleanTextLower) || INTERNAL_NAV_WORDS.has(text.toLowerCase())) {
    return false;
  }

  // Check button classes or episode container indicators
  if (
    /\b(btn|button|download-btn|dl-btn|action-btn|ep[a-z0-9_-]*|dlink|download-button|post-button|server-btn|drive-btn|cloud-btn|btn-download|btn-stream|btn-watch|btn-episode|btn-primary|btn-success|btn-secondary|btn-info|btn-dark)\b/i.test(
      css
    )
  ) {
    return true;
  }

  // Check parent episode container
  if (el.closest(".EpItem, .EpList, .EpName, [class*='ep-item'], [class*='epitem'], [class*='eplist'], [class*='episode'], [class*='download-item'], [class*='download-box']")) {
    return true;
  }

  try {
    const absolute = new URL(target, baseUrl).href;
    if (isBlockedTestLink(text, absolute)) return false;
    
    // Known media host
    if (
      /drive\.google\.|mega\.nz|mega\.io|mediafire\.|xcloud\.|hubcloud\.|gdtot\.|filepress\.|katdrive\.|gdflix\.|fastdl\.|streamtape\.|filemoon\.|dood\.|vidhide\.|streamwish\.|terabox\.|mp4upload\.|vidoza\./i.test(
        absolute
      )
    ) {
      return true;
    }
  } catch {
    return false;
  }

  const combinedWords = new Set([...words(text), ...words(css)]);
  if ([...combinedWords].some((x) => ACTION_WORDS.has(x))) return true;

  return false;
}

export function parseHTMLClientSide(html: string, baseUrl: string): ExtractedItem[] {
  const parser = new DOMParser();
  const doc = parser.parseFromString(html, "text/html");

  const selector = [
    "a[href]",
    "button",
    "input[type='button']",
    "input[type='submit']",
    "form[action]",
    "[onclick]",
    "[data-url]",
    "[data-href]",
    "[data-link]",
    "[data-target]",
  ].join(",");

  const all = Array.from(doc.querySelectorAll(selector));
  const out: ExtractedItem[] = [];
  const seen = new Set<string>();

  for (const el of all) {
    if (insideExcluded(el)) continue;

    let raw = rawTarget(el);
    if (!raw) continue;

    raw = raw.trim();
    if (/^(javascript:|mailto:|tel:|sms:|#)/i.test(raw)) continue;

    let absolute: string;
    try {
      absolute = new URL(raw, baseUrl).href;
    } catch {
      continue;
    }

    if (!/^https?:\/\//i.test(absolute)) continue;
    if (!isCandidate(el, baseUrl)) continue;

    let text = cleanText(
      (el as HTMLElement).innerText ||
        el.textContent ||
        (el as HTMLInputElement).value ||
        ""
    );

    if (isBlockedTestLink(text, absolute)) continue;

    // Check surrounding EpItem or EpName
    const epItemParent = el.closest(".EpItem, [class*='ep-item'], [class*='epitem']");
    if (epItemParent) {
      const epNameEl = epItemParent.querySelector(".EpName, [class*='epname'], [class*='ep-name']");
      if (epNameEl) {
        const epName = cleanText(epNameEl.textContent);
        if (epName && !text.toLowerCase().includes("episode") && !text.toLowerCase().includes("ep")) {
          text = `${epName} - ${text || "Download"}`;
        }
      }
    }

    const tag = el.tagName.toLowerCase();
    const type =
      tag === "button"
        ? "button"
        : tag === "input"
        ? "input"
        : tag === "form"
        ? "form"
        : "link";

    const key = `${type}|${absolute}|${text}`;
    if (seen.has(key)) continue;
    seen.add(key);

    out.push({
      type,
      tag,
      text: text || "(no text)",
      url: absolute,
      class: typeof el.className === "string" ? el.className : "",
      id: el.id || "",
    });
  }

  return out;
}

export function extractPageTitleClientSide(html: string, url: string = ""): string {
  if (!html) {
    return deriveTitleFromUrlClientSide(url);
  }
  try {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, "text/html");

    // 1. <title> tag
    const titleEl = doc.querySelector("title");
    let title = titleEl?.textContent?.trim() || "";

    // 2. OpenGraph / Twitter meta tags
    if (!title || /^(?:Blogger|Blogspot|Home|Untitled)$/i.test(title)) {
      const og = doc.querySelector('meta[property="og:title"]')?.getAttribute("content") ||
                 doc.querySelector('meta[name="twitter:title"]')?.getAttribute("content");
      if (og?.trim()) title = og.trim();
    }

    // 3. Post / H1 headings
    if (!title || /^(?:Blogger|Blogspot|Home|Untitled)$/i.test(title)) {
      const h1 = doc.querySelector("h1.post-title, h1.entry-title, .post-title, h1");
      const h1Text = h1?.textContent?.trim();
      if (h1Text) title = h1Text;
    }

    if (title) {
      title = title.replace(/\s+/g, " ");
      title = title.replace(/\s*[-|–—:]\s*(?:Blogger|Blogspot|Watch Online|Download|HD Movies|MovieHubHQ|MovieHub).*$/i, "");
      title = title.replace(/\s*[-|–—:]\s*Home$/i, "");
      title = title.trim();
    }

    if (!title || /^(?:Blogger|Blogspot|Home|Untitled)$/i.test(title)) {
      return deriveTitleFromUrlClientSide(url);
    }
    return title;
  } catch {
    return deriveTitleFromUrlClientSide(url);
  }
}

export function deriveTitleFromUrlClientSide(url: string): string {
  if (!url) return "Episode Download Links";
  const blogspotMatch = url.match(/\/p\/([a-zA-Z0-9_-]+)\.html/i);
  if (blogspotMatch && blogspotMatch[1]) {
    return blogspotMatch[1]
      .replace(/[-_]+/g, " ")
      .replace(/\b\w/g, (c) => c.toUpperCase())
      .trim();
  }
  try {
    const parsed = new URL(url);
    const parts = parsed.pathname.split("/").filter(Boolean);
    const last = parts[parts.length - 1];
    if (last) {
      const clean = last.replace(/\.(html|php|asp)$/i, "").replace(/[-_]+/g, " ");
      return clean.replace(/\b\w/g, (c) => c.toUpperCase()).trim();
    }
  } catch {}
  return "Episode Download Links";
}

