#!/usr/bin/env python3
"""
Short URL & Redirect Resolver + Shortener Bypasser
Python standard library only (socket, ipaddress, urllib, html.parser, re, json, http.cookiejar, base64).

Capabilities:
- CookieJar session persistence across hops
- SSRF protection (private IP / localhost / metadata blocked)
- HTTP redirect chain tracking (301, 302, 303, 307, 308)
- Query parameter link bypassing (?url=..., ?dest=..., ?link=..., base64 encoded targets)
- HTML Meta refresh detection (<meta http-equiv="refresh" content="...">)
- JavaScript redirect detection (window.location, location.replace, location.assign, setTimeout)
- Base64 embedded redirect unpacking (atob("..."))
- AdLinkFly / MightyScripts AJAX form bypassing (POST /links/go with session tokens)
- Multi-step Safelink Blogger / WordPress redirect bypass (intermediate steps, tokens, go.<domain>)
- Get Link / Skip Ad button candidate extraction
- Loop detection & max redirect limit
"""

import base64
import gzip
import http.cookiejar
import ipaddress
import json
import re
import socket
import sys
import time
import zlib
from html.parser import HTMLParser
from urllib.error import HTTPError, URLError
from urllib.parse import parse_qs, unquote, urlencode, urljoin, urlparse
from urllib.request import HTTPCookieProcessor, HTTPErrorProcessor, Request, build_opener

MAX_REDIRECTS = 25
TIMEOUT = 14
MAX_HTML_BYTES = 1024 * 1024

USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/124.0.0.0 Safari/537.36"
)

# =========================================================
# HTML redirect parser
# =========================================================

class RedirectParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.redirect_url = None

    def handle_starttag(self, tag, attrs):
        attrs_dict = dict(attrs)
        if tag.lower() == "meta":
            http_equiv = str(attrs_dict.get("http-equiv", "")).lower()
            content = attrs_dict.get("content")
            if http_equiv == "refresh" and content:
                match = re.search(r"url\s*=\s*(.+)$", content, re.I)
                if match:
                    self.redirect_url = match.group(1).strip(" \"'")

# =========================================================
# Security & SSRF Protection
# =========================================================

def is_target_destination(url):
    """
    Checks if the URL matches the target Blogspot destination structure:
    e.g. https://mydverse02.blogspot.com/p/flp-120926.html
         https://mydverse02.blogspot.com/p/mbmb-030826.html
         https://*.blogspot.com/p/*.html
    """
    if not url:
        return False
    try:
        p = urlparse(url)
        host = (p.hostname or "").lower()
        path = p.path or ""
        if ("blogspot." in host or "mydverse" in host) and ("/p/" in path and path.endswith(".html")):
            return True
        if re.search(r'https?://[a-zA-Z0-9.-]*blogspot\.[a-z.]+/p/[a-zA-Z0-9_-]+\.html', url, re.I):
            return True
    except Exception:
        pass
    return False

def is_blocked_host(hostname):
    if not hostname:
        return True

    hostname = hostname.lower().rstrip(".")

    blocked = {
        "localhost",
        "localhost.localdomain",
        "metadata",
        "metadata.google.internal",
    }

    if hostname in blocked:
        return True

    try:
        results = socket.getaddrinfo(hostname, None)
        for result in results:
            ip = ipaddress.ip_address(result[4][0])
            if (
                ip.is_private
                or ip.is_loopback
                or ip.is_link_local
                or ip.is_reserved
                or ip.is_multicast
                or ip.is_unspecified
            ):
                return True
    except Exception:
        return True

    return False

def validate_url(url):
    parsed = urlparse(url)

    if parsed.scheme not in ("http", "https"):
        raise ValueError("Only HTTP and HTTPS URLs are supported.")

    if not parsed.hostname:
        raise ValueError("The URL is invalid.")

    if is_blocked_host(parsed.hostname):
        raise ValueError("This destination is not allowed.")

    return url

# =========================================================
# Query Parameter Bypass (Instant)
# =========================================================

def extract_query_bypass(url):
    """
    Checks if the URL contains a target URL embedded in query parameters
    e.g. ?url=https://..., ?link=aHR0cHM6..., ?destination=...
    """
    try:
        parsed = urlparse(url)
        if not parsed.query:
            return None

        qs = parse_qs(parsed.query)
        target_keys = [
            "url", "link", "dest", "destination", "target", "redirect",
            "to", "u", "out", "next", "r", "href", "download", "d", "go"
        ]

        for key in target_keys:
            vals = qs.get(key, [])
            for val in vals:
                val = val.strip()
                if not val:
                    continue

                # 1. Direct unquoted URL
                clean_val = unquote(val)
                if clean_val.startswith("http://") or clean_val.startswith("https://"):
                    p = urlparse(clean_val)
                    if p.hostname and not is_blocked_host(p.hostname) and clean_val != url:
                        return clean_val

                # 2. Base64 encoded URL
                if len(val) >= 12 and re.match(r"^[A-Za-z0-9+/=]+$", val):
                    try:
                        padding = (4 - len(val) % 4) % 4
                        padded = val + ("=" * padding)
                        decoded = base64.b64decode(padded).decode("utf-8", errors="ignore").strip()
                        if decoded.startswith("http://") or decoded.startswith("https://"):
                            p = urlparse(decoded)
                            if p.hostname and not is_blocked_host(p.hostname):
                                return decoded
                    except Exception:
                        pass
    except Exception:
        pass

    return None

# =========================================================
# JavaScript & Base64 Redirect Detection
# =========================================================

def extract_js_redirect(html):
    patterns = [
        r'window\.location(?:\.href)?\s*=\s*["\']([^"\']+)["\']',
        r'location(?:\.href)?\s*=\s*["\']([^"\']+)["\']',
        r'window\.location\.replace\(\s*["\']([^"\']+)["\']\s*\)',
        r'window\.location\.assign\(\s*["\']([^"\']+)["\']\s*\)',
        r'location\.replace\(\s*["\']([^"\']+)["\']\s*\)',
        r'location\.assign\(\s*["\']([^"\']+)["\']\s*\)',
        r'setTimeout\s*\(\s*function\s*\(\s*\)\s*\{\s*(?:window\.)?location(?:\.href)?\s*=\s*["\']([^"\']+)["\']',
    ]

    for pattern in patterns:
        match = re.search(pattern, html, re.I)
        if match:
            url_candidate = match.group(1).strip()
            if not url_candidate.startswith("javascript:"):
                return url_candidate

    # Check for atob base64 redirect e.g. window.location = atob("...")
    atob_patterns = [
        r'location(?:\.href)?\s*=\s*atob\(\s*["\']([A-Za-z0-9+/=]+)["\']\s*\)',
        r'location\.replace\(\s*atob\(\s*["\']([A-Za-z0-9+/=]+)["\']\s*\)\s*\)',
        r'window\.open\(\s*atob\(\s*["\']([A-Za-z0-9+/=]+)["\']\s*\)',
    ]
    for pattern in atob_patterns:
        match = re.search(pattern, html, re.I)
        if match:
            b64_str = match.group(1)
            try:
                decoded = base64.b64decode(b64_str).decode("utf-8", errors="ignore").strip()
                if decoded.startswith("http://") or decoded.startswith("https://"):
                    return decoded
            except Exception:
                pass

    return None

def decompress_body(raw, content_encoding=""):
    """Decompresses gzip or deflate responses when returned by proxies or CDNs."""
    if not raw:
        return b""
    try:
        if raw.startswith(b"\x1f\x8b") or "gzip" in (content_encoding or "").lower():
            return gzip.decompress(raw)
        if (
            raw.startswith(b"\x78\x9c")
            or raw.startswith(b"\x78\x01")
            or raw.startswith(b"\x78\xda")
            or "deflate" in (content_encoding or "").lower()
        ):
            return zlib.decompress(raw)
    except Exception:
        pass
    return raw

# =========================================================
# Safelink / Blogger / WordPress Shortener Multi-Step Bypass
# =========================================================

def extract_safelink_bypass(html, current_url):
    """
    Detects safelink scripts (e.g. shrt.sohojgyan, mydriveverse, mydverse)
    Handles:
    - Target Blogspot Destination direct detection (https://mydverse02.blogspot.com/p/*.html)
    - Step 1: Array of next blog destinations + ?url=<token>
    - Step 2: Unpacking token and constructing mainUrl (https://go.<domain>/<token>)
    - Direct base64-encoded destination URLs in query parameters
    """
    if not html:
        return None

    # Pattern 0: Direct Blogspot destination link in script or HTML
    target_match = re.search(r'["\'](https?://[a-zA-Z0-9.-]*blogspot\.[a-z.]+/p/[a-zA-Z0-9_-]+\.html)["\']', html, re.I)
    if target_match:
        target_cand = target_match.group(1).strip()
        parsed_cand = urlparse(target_cand)
        if parsed_cand.hostname and not is_blocked_host(parsed_cand.hostname) and target_cand != current_url:
            return target_cand

    parsed_cur = urlparse(current_url)
    qs = parse_qs(parsed_cur.query)

    # Collect token parameter
    raw_url_param = ""
    for k in ("url", "link", "token", "go", "safelink", "dest", "id", "data"):
        if k in qs and qs[k]:
            raw_url_param = qs[k][0].strip()
            if raw_url_param:
                break

    # If raw_url_param decodes directly to a valid destination URL, return immediately
    if raw_url_param:
        try:
            unquoted = unquote(raw_url_param)
            padding = (4 - len(unquoted) % 4) % 4
            decoded_try = base64.b64decode(unquoted + ("=" * padding)).decode("utf-8", errors="ignore").strip()
            if decoded_try.startswith(("http://", "https://")):
                p = urlparse(decoded_try)
                if p.hostname and not is_blocked_host(p.hostname) and p.hostname != parsed_cur.hostname:
                    return decoded_try
        except Exception:
            pass

    # Pattern A: Next step via array of destination blogs
    # let t = ["https://mydverse.com/..."] ... window.location.href = `${n}?url=${e}`
    # Detect arrays of URLs in script
    for arr_match in re.finditer(r'(?:let|var|const)\s+[a-zA-Z0-9_$]+\s*=\s*\[([\s\S]*?)\]', html):
        content = arr_match.group(1)
        urls = re.findall(r'["\'](https?://[^"\']+)["\']', content)
        if urls:
            valid_urls = [u for u in urls if not is_blocked_host(urlparse(u).hostname)]
            if valid_urls:
                next_base = valid_urls[0].strip()
                if raw_url_param and "url=" not in next_base:
                    sep = "&" if "?" in next_base else "?"
                    return f"{next_base}{sep}url={raw_url_param}"
                return next_base

    # Pattern B: Template string redirects:
    # const mainUrl = `https://go.sohojgyan.com/${decodedUrl}`
    match_template = re.search(
        r'(?:mainUrl|goUrl|redirectUrl|targetUrl|finalUrl|destUrl|realUrl|shortUrl)\s*=\s*[`"\']([^`"\';]+)[`"\']',
        html,
        re.I
    )
    if not match_template:
        # Also check any backtick string containing https://go...${...}
        match_template = re.search(r'`(https?://go\.[^`\s]+?\$\{[^`\}]+\}[^`\s]*)`', html)

    if match_template:
        template = match_template.group(1).strip()
        # Decode token from query param if present
        token = ""
        if raw_url_param:
            try:
                unquoted = unquote(raw_url_param)
                padding = (4 - len(unquoted) % 4) % 4
                token = base64.b64decode(unquoted + ("=" * padding)).decode("utf-8", errors="ignore").strip()
            except Exception:
                token = raw_url_param

        if token:
            replaced = re.sub(r"\$\{[^}]+\}", token, template)
            if replaced.startswith(("http://", "https://")):
                return replaced
        elif template.startswith(("http://", "https://")) and not ("${" in template):
            return template

    # Pattern C: Direct go link in script
    # window.location.href = "https://go..."
    match_go = re.search(r'["\'](https?://go\.[^"\'\s<>()]+)["\']', html)
    if match_go:
        candidate = match_go.group(1).strip()
        if raw_url_param and candidate.endswith("/"):
            try:
                token = base64.b64decode(unquote(raw_url_param)).decode("utf-8", errors="ignore").strip()
                return f"{candidate}{token}"
            except Exception:
                pass
        elif not candidate.endswith("/"):
            return candidate

    return None

# =========================================================
# AdLinkFly / MightyScripts AJAX Form Bypasser
# =========================================================

class FormInputParser(HTMLParser):
    """Accurately collects all hidden & text inputs from a form block."""
    def __init__(self):
        super().__init__()
        self.inputs = {}

    def handle_starttag(self, tag, attrs):
        if tag.lower() == "input":
            d = dict(attrs)
            name = d.get("name")
            if name:
                val = d.get("value", "")
                # Clean URL-encoded tokens that CakePHP or scripts pre-encoded
                if "%" in val:
                    val = unquote(val)
                self.inputs[name] = val

def extract_adlinkfly_bypass(html, current_url, cookie_jar, opener):
    """
    Detects and bypasses AdLinkFly shorteners (e.g. go.sohojgyan.com, shrinkme.io)
    Finds <form id="go-link" action="/links/go">, collects tokens, waits counter,
    and sends AJAX POST to retrieve the final URL from JSON response.
    Includes auto-retry and clock-skew tolerance.
    """
    if not html:
        return None

    form_match = re.search(r'<form[^>]*id=["\']go-link["\'][^>]*action=["\']([^"\']+)["\'][\s\S]*?</form>', html, re.I)
    if not form_match:
        form_match = re.search(r'<form[^>]*action=["\']([^"\']*/links/go)["\'][\s\S]*?</form>', html, re.I)

    if not form_match:
        return None

    action = form_match.group(1)
    target_action = urljoin(current_url, action)
    form_html = form_match.group(0)

    # Collect form inputs using robust parser
    parser = FormInputParser()
    try:
        parser.feed(form_html)
        form_data = parser.inputs
    except Exception:
        # Fallback regex
        inputs = re.findall(r'<input[^>]+name=["\']([^"\']+)["\'][^>]*value=["\']([^"\']*)["\']', form_html)
        form_data = {n: (unquote(v) if "%" in v else v) for n, v in inputs}

    if not form_data:
        return None

    # Determine counter wait time
    counter_match = re.search(r'counter_value["\']?\s*:\s*(\d+)', html)
    required_counter = int(counter_match.group(1)) if counter_match else 5
    # Wait required counter time plus small margin to avoid clock-skew rejection
    wait_sec = max(required_counter, 4) + 0.5
    time.sleep(wait_sec)

    # Try sending AJAX POST, retrying once if server indicates clock-skew / Bad Request
    parsed_curr = urlparse(current_url)
    origin = f"{parsed_curr.scheme}://{parsed_curr.netloc}"

    for attempt in range(2):
        try:
            post_bytes = urlencode(form_data).encode("utf-8")
            req = Request(
                target_action,
                data=post_bytes,
                headers={
                    "User-Agent": USER_AGENT,
                    "Accept": "application/json, text/javascript, */*; q=0.01",
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                    "X-Requested-With": "XMLHttpRequest",
                    "Referer": current_url,
                    "Origin": origin,
                    "Sec-Fetch-Dest": "empty",
                    "Sec-Fetch-Mode": "cors",
                    "Sec-Fetch-Site": "same-origin",
                    "Connection": "close",
                }
            )
            resp = opener.open(req, timeout=TIMEOUT)
            raw = resp.read()
            raw = decompress_body(raw, resp.headers.get("Content-Encoding", ""))
            body = raw.decode("utf-8", errors="replace")
            data = json.loads(body)

            if isinstance(data, dict):
                bypassed_url = data.get("url")
                if bypassed_url and (bypassed_url.startswith("http://") or bypassed_url.startswith("https://")):
                    return bypassed_url
        except Exception:
            pass

        if attempt == 0:
            time.sleep(2.0)

    return None

# =========================================================
# Button / Link Candidate Detection & Robust Element Collector
# =========================================================

KNOWN_AD_DOMAINS = {
    "doubleclick.net", "googleads.g.doubleclick.net", "adservice.google.com",
    "facebook.com", "twitter.com", "t.me", "telegram.me", "instagram.com",
    "highperformancegate.com", "topcreativeformat.com", "profitablecpmrate.com",
    "adsterra.com", "monetag.com", "outbrain.com", "taboola.com"
}

VOID_ELEMENTS = {
    "area", "base", "br", "col", "embed", "hr", "img", "input",
    "link", "meta", "param", "source", "track", "wbr"
}

def clean_text(text):
    if not text:
        return ""
    return re.sub(r"\s+", " ", str(text)).strip()

def extract_url_from_js(code):
    if not code:
        return None
    match = re.search(r"""(?:window\.open|location\.href\s*=|\.assign|\.replace)\s*\(\s*['"]([^'"]+)['"]""", code)
    if match:
        return match.group(1).strip()
    match2 = re.search(r"""['"](https?://[^'"]+)['"]""", code)
    if match2:
        return match2.group(1).strip()
    return None

class RobustElementCollector(HTMLParser):
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
        self._body = not has_explicit_body
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

def extract_button_bypass(html, current_url):
    """
    Finds direct Get Link, Episode Wise Links, or Skip Ad anchor links that lead to final destinations.
    Ignores advertisements, sponsors, and AdLinkFly pages.
    """
    if not html:
        return None

    # Never extract buttons from AdLinkFly pages — their buttons are ad popups
    if 'id="go-link"' in html or '/links/go' in html:
        return None

    # If current page is already the target blogspot destination format, do NOT navigate away
    if is_target_destination(current_url):
        return None

    # 1. Use Robust Element Collector to find real content buttons/links
    collector = RobustElementCollector(has_explicit_body="<body" in html.lower())
    try:
        collector.feed(html)
        collector.close()
    except Exception:
        pass

    candidates = []
    current_parsed = urlparse(current_url)
    gdrive_count = 0

    for item in collector.results:
        raw_url = item.get("raw_url", "").strip()
        if not raw_url or raw_url.startswith(("javascript:", "#", "mailto:", "tel:")):
            continue
        if "#respond" in raw_url.lower() or "cancel reply" in item.get("text", "").lower() or "comment-reply" in item.get("class", "").lower():
            continue

        abs_url = urljoin(current_url, raw_url)
        parsed = urlparse(abs_url)
        if parsed.scheme not in ("http", "https") or not parsed.hostname or is_blocked_host(parsed.hostname):
            continue
        if parsed.hostname in KNOWN_AD_DOMAINS:
            continue
        if abs_url == current_url:
            continue

        css = item.get("class", "").lower()
        text = item.get("text", "")
        text_lower = text.lower()
        hostname_lower = parsed.hostname.lower()

        if "drive.google.com" in hostname_lower or "mega.nz" in hostname_lower or "mediafire.com" in hostname_lower:
            gdrive_count += 1

        score = 0

        # Priority 0: Exact target Blogspot destination structure (e.g. https://mydverse02.blogspot.com/p/flp-120926.html)
        if is_target_destination(abs_url):
            score += 500

        # Priority 1: Shortener domains or known link redirectors
        if any(marker in hostname_lower for marker in ("shrt.", "sohojgyan", "tinyurl", "bit.ly", "goo.gl", "is.gd", "cutt.ly", "shrink", "ouo.")):
            score += 200

        # Priority 2: Key classes like btn-slide, get-link, etc.
        if "btn-slide" in css:
            score += 160
        elif any(c in css for c in ("get-link", "skip-ad", "btn-download", "download-btn", "btn-primary", "btn-success")):
            score += 100
        elif "btn" in css or "button" in css:
            score += 40

        # Priority 3: Action phrases in text
        if "episode wise" in text_lower or "episode links" in text_lower:
            score += 140
        elif any(w in text_lower for w in ("get link", "skip ad", "proceed", "continue to link", "direct link", "click here to continue", "fast download")):
            score += 90
        elif any(w in text_lower for w in ("download", "episodes", "episode", "links", "link", "mirror", "server")):
            score += 40

        # Priority 4: Cross-domain redirection
        if parsed.hostname != current_parsed.hostname:
            score += 25

        if score >= 60:
            label = text if text else ("Target Blogspot Destination" if is_target_destination(abs_url) else ("Button Link" if "btn" in css else "Action Link"))
            candidates.append((score, abs_url, label))

    # If page contains 2 or more direct destination file downloads (e.g. multi-episode blogspot page)
    # and NONE is an explicit shortener, treat the current page as the multi-episode destination
    if gdrive_count >= 2 and not any(score >= 200 for score, _, _ in candidates):
        return None

    if candidates:
        candidates.sort(key=lambda x: x[0], reverse=True)
        top = candidates[0]
        return (top[1], top[2])

    # Fallback to regex patterns if collector didn't find candidate
    button_patterns = [
        r'<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(?:\s*<[^>]+>)*\s*(?:Episode Wise Links|Get Link|Skip Ad|Proceed to link|Direct Link|Click here to continue)\s*(?:<[^>]+>)*\s*</a>',
        r'<a\s+[^>]*class=["\'][^"\']*(?:btn-slide|get-link|skip-ad|btn-download|download-btn)[^"\']*["\'][^>]*href=["\']([^"\']+)["\']',
    ]

    for pat in button_patterns:
        match = re.search(pat, html, re.I)
        if match:
            candidate = match.group(1).strip()
            if candidate and not candidate.startswith(("javascript:", "#", "mailto:", "tel:")):
                abs_url = urljoin(current_url, candidate)
                parsed = urlparse(abs_url)
                if parsed.scheme in ("http", "https") and parsed.hostname and not is_blocked_host(parsed.hostname):
                    if parsed.hostname in KNOWN_AD_DOMAINS:
                        continue
                    if abs_url != current_url:
                        return (abs_url, "Button Link")

    return None

# =========================================================
# NoAutoRedirect Handler for urllib
# =========================================================

class NoRedirectHandler(HTTPErrorProcessor):
    """Prevents automatic redirection so we can capture each step in the chain."""
    def http_response(self, request, response):
        return response
    https_response = http_response

# =========================================================
# Main Resolver & Bypasser
# =========================================================

def resolve_url(start_url, max_redirects=MAX_REDIRECTS, return_html=False):
    start_url = validate_url(start_url)
    
    # Cookie jar preserves session tokens across redirects and AJAX requests
    cookie_jar = http.cookiejar.CookieJar()
    opener = build_opener(HTTPCookieProcessor(cookie_jar), NoRedirectHandler)

    current_url = start_url
    visited = set()
    chain = []

    for number in range(1, MAX_REDIRECTS + 1):
        current_url = validate_url(current_url)

        if current_url in visited:
            break

        visited.add(current_url)

        # -------------------------------------------------
        # 1. Check Query Parameter Bypass (instant link extraction)
        # -------------------------------------------------
        query_bypass = extract_query_bypass(current_url)
        if query_bypass and query_bypass not in visited:
            chain.append({
                "step": number,
                "url": current_url,
                "status": 200,
                "type": "Query Parameter Bypass"
            })
            current_url = query_bypass
            continue

        # -------------------------------------------------
        # 2. Perform HTTP Request
        # -------------------------------------------------
        referer = chain[-1]["url"] if chain else None
        headers = {
            "User-Agent": USER_AGENT,
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Accept-Language": "en-US,en;q=0.9",
            "Connection": "close",
        }
        if referer:
            headers["Referer"] = referer

        req = Request(current_url, headers=headers)

        try:
            response = opener.open(req, timeout=TIMEOUT)
            status = response.getcode()
            resp_headers = response.headers
        except HTTPError as exc:
            status = exc.code
            resp_headers = exc.headers
            response = exc
        except (URLError, TimeoutError, socket.error) as exc:
            raise RuntimeError(f"Connection failed: {exc}")

        # -------------------------------------------------
        # 3. Standard HTTP redirects (301, 302, 303, 307, 308)
        # -------------------------------------------------
        if status in (301, 302, 303, 307, 308):
            chain.append({
                "step": number,
                "url": current_url,
                "status": status,
                "type": f"HTTP {status} Redirect"
            })
            location = resp_headers.get("Location")
            try:
                response.close()
            except Exception:
                pass

            if not location:
                break

            next_url = urljoin(current_url, location)
            current_url = next_url
            continue

        # -------------------------------------------------
        # 4. Content Inspection for HTML / JS / Shortener Bypasses
        # -------------------------------------------------
        content_type = resp_headers.get("Content-Type", "").lower()
        html = ""

        if "text/html" in content_type or "application/xhtml" in content_type or not content_type:
            try:
                raw = response.read(MAX_HTML_BYTES)
                raw = decompress_body(raw, resp_headers.get("Content-Encoding", ""))
                html = raw.decode("utf-8", errors="replace")
            except Exception:
                html = ""
            finally:
                try:
                    response.close()
                except Exception:
                    pass

            # A. Meta Refresh
            parser = RedirectParser()
            try:
                parser.feed(html)
            except Exception:
                pass

            if parser.redirect_url:
                next_url = urljoin(current_url, parser.redirect_url)
                if next_url != current_url and next_url not in visited:
                    chain.append({
                        "step": number,
                        "url": current_url,
                        "status": status,
                        "type": "Meta Refresh"
                    })
                    current_url = next_url
                    continue

            # If current_url matches target destination structure (e.g. https://mydverse02.blogspot.com/p/flp-120926.html),
            # this is the intended final destination page holding the episode links.
            if is_target_destination(current_url):
                chain.append({
                    "step": number,
                    "url": current_url,
                    "status": status,
                    "type": "Target Destination (Blogspot Episode Page)"
                })
                break

            # B. AdLinkFly / MightyScripts AJAX Bypass (/links/go)
            adlinkfly_url = extract_adlinkfly_bypass(html, current_url, cookie_jar, opener)
            if adlinkfly_url and adlinkfly_url != current_url and adlinkfly_url not in visited:
                chain.append({
                    "step": number,
                    "url": current_url,
                    "status": status,
                    "type": "AdLinkFly Engine Bypass"
                })
                current_url = adlinkfly_url
                continue

            # C. Multi-Stage Safelink (Blogger / WordPress Interstitials)
            safelink_url = extract_safelink_bypass(html, current_url)
            if safelink_url and safelink_url != current_url and safelink_url not in visited:
                chain.append({
                    "step": number,
                    "url": current_url,
                    "status": status,
                    "type": "Safelink Interstitial Bypass"
                })
                current_url = safelink_url
                continue

            # D. JavaScript Redirects & atob base64
            js_url = extract_js_redirect(html)
            if js_url:
                next_url = urljoin(current_url, js_url)
                if next_url != current_url and next_url not in visited:
                    chain.append({
                        "step": number,
                        "url": current_url,
                        "status": status,
                        "type": "JavaScript Redirect"
                    })
                    current_url = next_url
                    continue

            # E. Button Link Candidate (Get Link, Episode Wise Links, Direct Link)
            button_res = extract_button_bypass(html, current_url)
            if button_res:
                if isinstance(button_res, tuple):
                    button_url, button_label = button_res
                else:
                    button_url, button_label = button_res, "Action Button"
                if button_url and button_url != current_url and button_url not in visited:
                    chain.append({
                        "step": number,
                        "url": current_url,
                        "status": status,
                        "type": f"Action Link Bypass: {button_label}" if button_label else "Action Link Bypass"
                    })
                    current_url = button_url
                    continue

        try:
            response.close()
        except Exception:
            pass

        # If no further redirects or bypasses were triggered, this is the destination
        chain.append({
            "step": number,
            "url": current_url,
            "status": status,
            "type": "Destination Page"
        })
        break
    else:
        raise RuntimeError("Too many redirects / hops.")

    result_dict = {
        "original": start_url,
        "final": current_url,
        "redirects": max(0, len(chain) - 1),
        "chain": chain
    }
    if return_html:
        result_dict["final_html"] = html
    return result_dict

def run_resolver_api(url):
    if not url:
        return {"success": False, "error": "Enter a shortened URL."}
    if len(url) > 4096:
        return {"success": False, "error": "URL is too long."}

    try:
        result = resolve_url(url)
        return {"success": True, "data": result}
    except ValueError as exc:
        return {"success": False, "error": str(exc)}
    except RuntimeError as exc:
        return {"success": False, "error": str(exc)}
    except Exception as exc:
        return {"success": False, "error": f"Resolution failed: {str(exc)}"}

if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "--json":
        input_data = json.loads(sys.stdin.read() if not sys.stdin.isatty() else "{}")
        url = input_data.get("url", "").strip()
        res = run_resolver_api(url)
        print(json.dumps(res))
        sys.exit(0)
    elif len(sys.argv) > 1:
        target_url = sys.argv[1]
        res = run_resolver_api(target_url)
        print(json.dumps(res, indent=2))
        sys.exit(0)
    else:
        url = input("Enter URL to resolve: ").strip()
        print(json.dumps(run_resolver_api(url), indent=2))

