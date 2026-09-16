#!/usr/bin/env python3
"""
Unified Link Resolver & Extractor Engine
Merges URL Shortener / Redirect Resolving and Action Link Extraction into a seamless pipeline.
"""

import sys
import json
from urllib.parse import urlparse
import resolver
import extractor

def run_unified_pipeline(url=None, html=None, base_url=None, button_only=True, auto_resolve=True):
    if not url and not html:
        return {"success": False, "error": "Please provide a valid URL or HTML snippet."}

    try:
        # Case 1: URL provided
        if url:
            url = url.strip()
            if not extractor.valid_http_url(url):
                return {"success": False, "error": f"Invalid URL scheme or format: {url}"}

            # If auto_resolve is enabled, resolve all redirect hops first
            if auto_resolve:
                resolve_res = resolver.resolve_url(url, return_html=True)
                final_dest_url = resolve_res["final"]
                final_html = resolve_res.get("final_html")

                # If final destination HTML was captured in memory during resolution, reuse it!
                if not final_html or len(final_html) < 200:
                    try:
                        final_dest_url, final_html = extractor.fetch_page(final_dest_url)
                    except Exception as fetch_err:
                        pass

                # Check if this source page contains an "Episode Wise Links" shortlink (e.g. shrt.sohojgyan.com/<token>)
                shortlink_candidate = None
                if final_html:
                    import re
                    # 1. Direct href matching shrt.sohojgyan.com
                    m_shrt = re.search(r'<a\s+[^>]*href=[\'"](https?://shrt\.sohojgyan\.com/[a-zA-Z0-9_-]+)[\'"]', final_html, re.I)
                    if m_shrt:
                        shortlink_candidate = m_shrt.group(1).strip()
                    else:
                        # 2. Anchor containing Episode Wise Links
                        m_ew = re.search(r'<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>[\s\S]*?(?:Episode\s*Wise|Episode-Wise)[\s\S]*?</a>', final_html, re.I)
                        if m_ew:
                            from urllib.parse import urljoin
                            shortlink_candidate = urljoin(final_dest_url, m_ew.group(1).strip())

                # If an Episode Wise Links shortlink was identified on the source page, resolve that shortened URL
                if shortlink_candidate and shortlink_candidate != url:
                    sub_resolve = resolver.resolve_url(shortlink_candidate, return_html=True)
                    sub_final_url = sub_resolve["final"]
                    sub_final_html = sub_resolve.get("final_html")
                    if not sub_final_html or len(sub_final_html) < 200:
                        try:
                            sub_final_url, sub_final_html = extractor.fetch_page(sub_final_url)
                        except Exception:
                            pass

                    if sub_final_html and len(sub_final_html) >= 200:
                        # Prepend chain and set destination
                        combined_chain = list(resolve_res.get("chain", []))
                        combined_chain.append({
                            "step": len(combined_chain) + 1,
                            "url": shortlink_candidate,
                            "status": 200,
                            "type": "Identified Episode Wise Shortened URL"
                        })
                        for sc in sub_resolve.get("chain", []):
                            combined_chain.append({
                                "step": len(combined_chain) + 1,
                                "url": sc.get("url"),
                                "status": sc.get("status"),
                                "type": sc.get("type")
                            })

                        final_dest_url = sub_final_url
                        final_html = sub_final_html
                        resolve_res["redirects"] = len(combined_chain) - 1
                        resolve_res["chain"] = combined_chain

                # Extract action and download links from the final destination HTML
                items = extractor.extract_from_html(final_html, final_dest_url, button_only=button_only) if final_html else []

                return {
                    "success": True,
                    "resolved": True,
                    "original_url": url,
                    "final_url": final_dest_url,
                    "redirects": resolve_res.get("redirects", 0),
                    "chain": resolve_res.get("chain", []),
                    "items": items,
                    "count": len(items),
                    "bytes": len(final_html) if final_html else 0
                }

            else:
                # Direct extraction without resolver hops
                ext_res = extractor.run_extraction_api(url=url, button_only=button_only)
                if not ext_res.get("success"):
                    return ext_res
                return {
                    "success": True,
                    "resolved": False,
                    "original_url": url,
                    "final_url": ext_res.get("final_url", url),
                    "redirects": 0,
                    "chain": [
                        {
                            "step": 1,
                            "url": ext_res.get("final_url", url),
                            "status": 200,
                            "type": "Direct HTTP Fetch"
                        }
                    ],
                    "items": ext_res.get("items", []),
                    "count": ext_res.get("count", 0),
                    "bytes": ext_res.get("bytes", 0)
                }

        # Case 2: Raw HTML snippet provided
        elif html:
            target_url = (base_url or "https://example.com/page").strip()
            items = extractor.extract_from_html(html, target_url, button_only=button_only)
            return {
                "success": True,
                "resolved": False,
                "original_url": target_url,
                "final_url": target_url,
                "redirects": 0,
                "chain": [],
                "items": items,
                "count": len(items),
                "bytes": len(html)
            }

    except Exception as e:
        return {"success": False, "error": str(e)}

if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "--json":
        raw_input = sys.stdin.read() if not sys.stdin.isatty() else "{}"
        params = json.loads(raw_input or "{}")
        result = run_unified_pipeline(
            url=params.get("url"),
            html=params.get("html"),
            base_url=params.get("base_url"),
            button_only=params.get("button_only", True),
            auto_resolve=params.get("auto_resolve", True)
        )
        print(json.dumps(result))
        sys.exit(0)
    elif len(sys.argv) > 1 and sys.argv[1].startswith("http"):
        target_url = sys.argv[1]
        result = run_unified_pipeline(url=target_url)
        print(json.dumps(result, indent=2))
        sys.exit(0)
    else:
        print("Unified Link Resolver & Extractor Engine")
        print("Usage: python3 unified_engine.py <URL>")
