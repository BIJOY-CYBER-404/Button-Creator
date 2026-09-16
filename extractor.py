#!/usr/bin/env python3
"""
Webpage Link & Button Extractor
Python standard library only.
Supports both CLI interactive mode and JSON API mode for the Android/Web backend.
"""

import json
import re
import sys
from html.parser import HTMLParser
from urllib.parse import urljoin, urlparse, unquote
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError

USER_AGENT = (
    "Mozilla/5.0 (Linux; Android 11) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/120.0 Safari/537.36"
)

TIMEOUT = 15

ACTION_WORDS = {
    "download", "watch", "stream", "play", "open", "direct",
    "server", "link", "links", "get", "view", "continue", "mirror",
    "xcloud", "filemoon", "streamtape", "doodstream", "mixdrop",
    "episode", "episodes", "ep", "wise", "gdrive", "drive", "mediafire", "mega"
}

VOID_ELEMENTS = {
    "area", "base", "br", "col", "embed", "hr", "img", "input",
    "link", "meta", "param", "source", "track", "wbr"
}

INTERNAL_NAV_WORDS = {
    "home", "about", "contact", "privacy", "privacy-policy",
    "cookie", "cookie-policy", "sitemap", "search", "login",
    "register", "rtl", "category", "categories", "archive",
    "next", "previous", "prev",
}

EXCLUDED_EXPLICIT_TESTS = (
    "rich results test",
    "pagespeed insights",
)

def is_blocked_test_link(text, url):
    text_lower = (text or "").lower()
    url_lower = (url or "").lower()
    for phrase in EXCLUDED_EXPLICIT_TESTS:
        if phrase in text_lower:
            return True
    if "test/rich-results" in url_lower or "rich-results" in url_lower:
        return True
    if "pagespeed.web.dev" in url_lower or "pagespeed" in url_lower:
        return True
    return False

def clean_text(text):
    return re.sub(r"\s+", " ", text or "").strip()

def valid_http_url(url):
    try:
        p = urlparse(url)
        return p.scheme in ("http", "https") and bool(p.netloc)
    except Exception:
        return False

def extract_url_from_js(value):
    if not value:
        return None
    patterns = [
        r"""(?:window\.)?location(?:\.href)?\s*=\s*['"]([^'"]+)['"]""",
        r"""window\.open\s*\(\s*['"]([^'"]+)['"]""",
        r"""(?:href|url)\s*:\s*['"]([^'"]+)['"]""",
        r"""['"]((?:https?://|/|\./|\.\./)[^'"]+)['"]""",
    ]
    for pattern in patterns:
        match = re.search(pattern, value, re.I)
        if match:
            return match.group(1).strip()
    return None

def normalized_words(value):
    value = (value or "").lower()
    return set(re.findall(r"[a-z0-9]+", value))

def same_site(url, base_url):
    try:
        a = urlparse(url)
        b = urlparse(base_url)
        return a.hostname == b.hostname
    except Exception:
        return False

def normalize_url(raw_url, base_url):
    raw_url = unquote(clean_text(raw_url))
    lower = raw_url.lower()
    if lower.startswith(("javascript:", "mailto:", "tel:", "sms:", "#")):
        return None
    try:
        absolute = urljoin(base_url, raw_url)
        if valid_http_url(absolute):
            return absolute
    except Exception:
        pass
    return None

def looks_like_action_link(item, base_url):
    text = clean_text(item.get("text", ""))
    css = f"{item.get('class', '')} {item.get('id', '')}".lower()
    url = item.get("raw_url", "")

    if is_blocked_test_link(text, url):
        return False

    # Exclude comment response and reply anchor links
    if "#respond" in url.lower() or "cancel reply" in text.lower() or "comment-reply" in css:
        return False

    words = normalized_words(f"{text} {css}")

    if words & ACTION_WORDS:
        return True

    if any(
        x in css
        for x in (
            "btn", "button", "download", "watch", "stream",
            "server", "mirror", "play", "action"
        )
    ):
        return True

    normalized = normalize_url(url, base_url)
    if normalized and not same_site(normalized, base_url):
        return True

    return False

class ElementCollector(HTMLParser):
    """Extract links/buttons from BODY while excluding site chrome."""

    EXCLUDED_TAGS = {"header", "footer", "nav", "aside", "script", "style", "noscript"}
    EXCLUDED_MARKERS = (
        "header", "footer", "navbar", "navigation", "nav-menu", "navmenu",
        "main-menu", "mainmenu", "menu", "menus", "sidebar", "site-header",
        "site-footer", "topbar", "top-bar", "bottombar", "bottom-bar",
        "breadcrumb", "breadcrumbs"
    )

    def __init__(self, has_explicit_body=True):
        super().__init__(convert_charrefs=True)
        self.results = []
        self._stack = []
        # If the input HTML doesn't contain a <body> tag, assume the snippet is inside the body
        self._body = not has_explicit_body
        self._had_explicit_body = has_explicit_body
        self._capture = None
        self._capture_depth = 0

    @classmethod
    def excluded_container(cls, tag, attrs):
        if tag in cls.EXCLUDED_TAGS:
            return True
        vals = [attrs.get(k, "").lower() for k in ("id", "class", "aria-label", "title", "role")]
        role = attrs.get("role", "").lower()
        if role in {"navigation", "banner", "contentinfo", "complementary", "menubar", "menu"}:
            return True
        text = " ".join(vals)
        normalized = text.replace("_", "-")
        for marker in cls.EXCLUDED_MARKERS:
            if re.search(r"(?:^|[\s-])" + re.escape(marker) + r"(?:$|[\s-])", normalized):
                return True
        return False

    def _is_excluded(self):
        return any(frame.get("excluded", False) for frame in self._stack)

    def handle_starttag(self, tag, attrs):
        tag = tag.lower()
        attrs_dict = {str(k).lower(): (v or "") for k, v in attrs}

        if tag == "body":
            self._body = True
            self._stack.append({"tag": tag, "item": None, "excluded": False})
            return

        if not self._body:
            self._stack.append({"tag": tag, "item": None, "excluded": False})
            return

        is_excluded = self._is_excluded() or self.excluded_container(tag, attrs_dict)

        item = None
        if not is_excluded:
            href = attrs_dict.get("href")
            onclick = attrs_dict.get("onclick")
            data_url = attrs_dict.get("data-url") or attrs_dict.get("data-href") or attrs_dict.get("data-link")
            formaction = attrs_dict.get("formaction")
            action = attrs_dict.get("action")
            input_type = attrs_dict.get("type", "").lower()

            if tag == "a" and href:
                item = {"type":"link","tag":"a","raw_url":href,"text":"","class":attrs_dict.get("class",""),"id":attrs_dict.get("id","")}
            elif tag == "button":
                raw = formaction or data_url or extract_url_from_js(onclick)
                if raw:
                    item = {"type":"button","tag":"button","raw_url":raw,"text":"","class":attrs_dict.get("class",""),"id":attrs_dict.get("id","")}
            elif tag == "input" and input_type in ("button", "submit"):
                raw = formaction or data_url or extract_url_from_js(onclick)
                if raw:
                    item = {"type":input_type,"tag":"input","raw_url":raw,"text":attrs_dict.get("value",""),"class":attrs_dict.get("class",""),"id":attrs_dict.get("id","")}
            elif tag == "form":
                raw = action or data_url
                if raw:
                    item = {"type":"form","tag":"form","raw_url":raw,"text":"","class":attrs_dict.get("class",""),"id":attrs_dict.get("id","")}
            elif onclick or data_url:
                raw = data_url or extract_url_from_js(onclick)
                if raw:
                    item = {"type":"clickable","tag":tag,"raw_url":raw,"text":"","class":attrs_dict.get("class",""),"id":attrs_dict.get("id","")}

        if tag in VOID_ELEMENTS:
            if item:
                self.results.append(item)
            return

        self._stack.append({"tag": tag, "item": item, "excluded": is_excluded})
        if item is not None:
            self._capture = item
            self._capture_depth = len(self._stack)

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if tag not in VOID_ELEMENTS:
            self.handle_endtag(tag)

    def handle_data(self, data):
        if not self._body or self._is_excluded() or self._capture is None:
            return
        text = clean_text(data)
        if text:
            self._capture["text"] = clean_text(self._capture.get("text", "") + " " + text)

    def handle_endtag(self, tag):
        tag = tag.lower()
        if not self._stack:
            return
        idx = None
        for i in range(len(self._stack)-1, -1, -1):
            if self._stack[i]["tag"] == tag:
                idx = i
                break
        if idx is None:
            return
        depth = idx + 1
        self._stack = self._stack[:idx]

        if self._capture is not None and depth <= self._capture_depth:
            if self._capture not in self.results:
                self.results.append(self._capture)
            self._capture = None
            self._capture_depth = 0

        if tag == "body":
            self._body = False
            self._capture = None
            self._capture_depth = 0

def fetch_page(url):
    request = Request(
        url,
        headers={
            "User-Agent": USER_AGENT,
            "Accept": "text/html,application/xhtml+xml",
            "Accept-Language": "en-US,en;q=0.9",
        },
    )

    with urlopen(request, timeout=TIMEOUT) as response:
        final_url = response.geturl()
        content_type = response.headers.get_content_type()
        charset = response.headers.get_content_charset() or "utf-8"
        raw = response.read()

    if content_type not in ("text/html", "application/xhtml+xml"):
        raise ValueError(f"Response is not HTML (Content-Type: {content_type})")

    html = raw.decode(charset, errors="replace")
    return final_url, html

def process_results(results, base_url, button_only=True):
    output = []
    seen = set()

    for item in results:
        url = normalize_url(item.get("raw_url", ""), base_url)
        if not url:
            continue

        text = clean_text(item.get("text", ""))
        if is_blocked_test_link(text, url):
            continue

        item_for_score = dict(item)
        item_for_score["raw_url"] = url

        if button_only and not looks_like_action_link(item_for_score, base_url):
            continue

        text_words = normalized_words(item.get("text", ""))
        css_words = normalized_words(
            f"{item.get('class', '')} {item.get('id', '')}"
        )

        if (
            button_only
            and same_site(url, base_url)
            and not (text_words | css_words) & ACTION_WORDS
            and not any(
                x in f"{item.get('class', '')} {item.get('id', '')}".lower()
                for x in ("btn", "button", "download", "watch", "stream", "server", "mirror", "play")
            )
        ):
            continue

        key = (item["type"], url, item.get("text", ""))
        if key in seen:
            continue
        seen.add(key)

        output.append({
            "type": item["type"],
            "tag": item["tag"],
            "text": item.get("text", "") or "(no text)",
            "url": url,
            "class": item.get("class", ""),
            "id": item.get("id", ""),
        })

    return output

def extract_from_html(html, base_url, button_only=True):
    has_body = "<body" in (html or "").lower()
    parser = ElementCollector(has_explicit_body=has_body)
    parser.feed(html)
    parser.close()
    return process_results(parser.results, base_url, button_only=button_only)

def run_extraction_api(url=None, html=None, base_url=None, button_only=True):
    try:
        if url:
            if not valid_http_url(url):
                return {"success": False, "error": f"Invalid URL: {url}"}
            final_url, fetched_html = fetch_page(url)
            items = extract_from_html(fetched_html, final_url, button_only=button_only)
            return {
                "success": True,
                "final_url": final_url,
                "bytes": len(fetched_html),
                "items": items,
                "count": len(items)
            }
        elif html:
            target_url = base_url or "https://example.com/page"
            items = extract_from_html(html, target_url, button_only=button_only)
            return {
                "success": True,
                "final_url": target_url,
                "bytes": len(html),
                "items": items,
                "count": len(items)
            }
        else:
            return {"success": False, "error": "No URL or HTML provided"}
    except HTTPError as e:
        return {"success": False, "error": f"HTTP Error {e.code}: {e.reason}"}
    except URLError as e:
        return {"success": False, "error": f"Network Error: {e.reason}"}
    except Exception as e:
        return {"success": False, "error": str(e)}

if __name__ == "__main__":
    # Check if run in JSON mode
    if len(sys.argv) > 1 and sys.argv[1] == "--json":
        input_data = json.loads(sys.stdin.read() if not sys.stdin.isatty() else "{}")
        url = input_data.get("url")
        html = input_data.get("html")
        base_url = input_data.get("base_url")
        button_only = input_data.get("button_only", True)
        res = run_extraction_api(url=url, html=html, base_url=base_url, button_only=button_only)
        print(json.dumps(res))
        sys.exit(0)
    elif len(sys.argv) > 1 and sys.argv[1].startswith("http"):
        target_url = sys.argv[1]
        res = run_extraction_api(url=target_url)
        print(json.dumps(res, indent=2))
        sys.exit(0)
    else:
        # Interactive CLI mode matching original script
        print("=" * 78)
        print("        WEBPAGE BUTTON LINK EXTRACTOR (Python Engine)")
        print("=" * 78)
        try:
            url = input("Paste webpage URL: ").strip()
            if valid_http_url(url):
                res = run_extraction_api(url=url)
                if res.get("success"):
                    print(f"Found {res['count']} items from {res['final_url']}:")
                    for i, it in enumerate(res["items"], 1):
                        print(f"[{i}] {it['type'].upper()} | {it['text']} -> {it['url']}")
                else:
                    print(f"Error: {res.get('error')}")
            else:
                print("Invalid URL.")
        except Exception as err:
            print(f"Error: {err}")
