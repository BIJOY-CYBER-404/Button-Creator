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
import http.cookiejar
import ipaddress
import json
import re
import socket
import sys
import time
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

# =========================================================
# Safelink / Blogger / WordPress Shortener Multi-Step Bypass
# =========================================================

def extract_safelink_bypass(html, current_url):
    """
    Detects safelink scripts (e.g. shrt.sohojgyan, mydriveverse, mydverse)
    Handles:
    - Step 1: Array of next blog destinations + ?url=<token>
    - Step 2: Unpacking token and constructing mainUrl (https://go.<domain>/<token>)
    """
    if not html:
        return None

    parsed_cur = urlparse(current_url)
    qs = parse_qs(parsed_cur.query)
    raw_url_param = qs.get("url", [""])[0]

    # Pattern A: Next step via array of destination blogs
    # let t = ["https://mydverse.com/..."] ... window.location.href = `${n}?url=${e}`
    match_arr = re.search(r'let\s+t\s*=\s*\[\s*["\'](https?://[^"\']+)["\']', html)
    if match_arr and ("safelink" in html.lower() or "footerCountdown" in html or "headerCard" in html):
        next_base = match_arr.group(1).strip()
        if raw_url_param:
            return f"{next_base}?url={raw_url_param}"
        return next_base

    # Pattern B: mainUrl = `https://go.<domain>/${decodedUrl}`
    match_main = re.search(r'mainUrl\s*=\s*[`"\']([^`"\';]+)[`"\']', html)
    if match_main:
        template = match_main.group(1).strip()
        # Decode base64 token from query param if present
        if raw_url_param:
            try:
                unquoted = unquote(raw_url_param)
                padding = (4 - len(unquoted) % 4) % 4
                token = base64.b64decode(unquoted + ("=" * padding)).decode("utf-8", errors="ignore").strip()
            except Exception:
                token = raw_url_param
        else:
            token = ""

        if "${decodedUrl}" in template and token:
            return template.replace("${decodedUrl}", token)
        elif "${" in template and token:
            return re.sub(r"\$\{[^}]+\}", token, template)
        elif template.startswith("http://") or template.startswith("https://"):
            return template

    # Pattern C: Direct go link in script
    # window.location.href = "https://go..."
    match_go = re.search(r'["\'](https?://go\.[^"\']+)["\']', html)
    if match_go:
        candidate = match_go.group(1)
        if raw_url_param and candidate.endswith("/"):
            try:
                token = base64.b64decode(unquote(raw_url_param)).decode("utf-8", errors="ignore").strip()
                return f"{candidate}{token}"
            except Exception:
                pass

    return None

# =========================================================
# AdLinkFly / MightyScripts AJAX Form Bypasser
# =========================================================

def extract_adlinkfly_bypass(html, current_url, cookie_jar, opener):
    """
    Detects and bypasses AdLinkFly shorteners (e.g. go.sohojgyan.com, shrinkme.io)
    Finds <form id="go-link" action="/links/go">, collects tokens, waits counter,
    and sends AJAX POST to retrieve the final URL from JSON response.
    """
    if not html:
        return None

    form_match = re.search(r'<form[^>]*id=["\']go-link["\'][^>]*action=["\']([^"\']+)["\'][\s\S]*?</form>', html, re.I)
    if not form_match:
        # Also check for form with action containing /links/go
        form_match = re.search(r'<form[^>]*action=["\']([^"\']*/links/go)["\'][\s\S]*?</form>', html, re.I)

    if not form_match:
        return None

    action = form_match.group(1)
    target_action = urljoin(current_url, action)
    form_html = form_match.group(0)

    # Collect form inputs
    inputs = re.findall(r'<input[^>]+name=["\']([^"\']+)["\'][^>]*value=["\']([^"\']*)["\']', form_html)
    # Also reversed value / name
    inputs_rev = re.findall(r'<input[^>]+value=["\']([^"\']*)["\'][^>]*name=["\']([^"\']+)["\']', form_html)
    form_data = {n: v for n, v in inputs}
    for v, n in inputs_rev:
        form_data.setdefault(n, v)

    if not form_data:
        return None

    # Determine counter wait time
    counter_match = re.search(r'counter_value["\']?\s*:\s*(\d+)', html)
    wait_sec = min(int(counter_match.group(1)), 4) if counter_match else 3

    if wait_sec > 0:
        time.sleep(wait_sec)

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
                "Origin": f"{urlparse(current_url).scheme}://{urlparse(current_url).netloc}",
                "Connection": "close",
            }
        )
        resp = opener.open(req, timeout=TIMEOUT)
        body = resp.read().decode("utf-8", errors="replace")
        data = json.loads(body)

        if isinstance(data, dict):
            bypassed_url = data.get("url")
            if bypassed_url and (bypassed_url.startswith("http://") or bypassed_url.startswith("https://")):
                return bypassed_url
    except Exception:
        pass

    return None

# =========================================================
# Button / Link Candidate Detection
# =========================================================

def extract_button_bypass(html, current_url):
    """
    Finds direct Get Link or Skip Ad anchor links that lead to final destinations.
    """
    if not html:
        return None

    # Matches <a ... href="..." ...>Get Link</a> or class="get-link" / "btn-download"
    button_patterns = [
        r'<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(?:\s*<[^>]+>)*\s*(?:Get Link|Skip Ad|Proceed to link|Direct Link|Click here to continue)\s*(?:<[^>]+>)*\s*</a>',
        r'<a\s+[^>]*class=["\'][^"\']*(?:get-link|skip-ad|btn-download|download-btn)[^"\']*["\'][^>]*href=["\']([^"\']+)["\']',
    ]

    for pat in button_patterns:
        match = re.search(pat, html, re.I)
        if match:
            candidate = match.group(1).strip()
            if candidate and not candidate.startswith("javascript:") and not candidate.startswith("#"):
                abs_url = urljoin(current_url, candidate)
                parsed = urlparse(abs_url)
                if parsed.scheme in ("http", "https") and parsed.hostname and not is_blocked_host(parsed.hostname):
                    if abs_url != current_url:
                        return abs_url

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

def resolve_url(start_url):
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

            # E. Button Link Candidate (Get Link, Direct Link)
            button_url = extract_button_bypass(html, current_url)
            if button_url and button_url != current_url and button_url not in visited:
                chain.append({
                    "step": number,
                    "url": current_url,
                    "status": status,
                    "type": "Action Link Bypass"
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

    return {
        "original": start_url,
        "final": current_url,
        "redirects": max(0, len(chain) - 1),
        "chain": chain
    }

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

