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
                # Check if input URL is already the target Blogspot destination
                if resolver.is_target_destination(url):
                    _, final_html = extractor.fetch_page(url)
                    items = extractor.extract_from_html(final_html, url, button_only=button_only) if final_html else []
                    page_title = extractor.extract_page_title(final_html, url) if final_html else extractor.derive_title_from_url(url)
                    return {
                        "success": True,
                        "resolved": True,
                        "original_url": url,
                        "final_url": url,
                        "page_title": page_title,
                        "redirects": 0,
                        "target_destination_verified": True,
                        "chain": [{
                            "step": 1,
                            "url": url,
                            "status": 200,
                            "type": "Target Destination (Blogspot Episode Page)"
                        }],
                        "items": items,
                        "count": len(items),
                        "bytes": len(final_html) if final_html else 0
                    }

                # Check if input URL is already a shortened URL
                parsed_in = urlparse(url)
                host_in = (parsed_in.hostname or "").lower()
                is_direct_shortlink = any(k in host_in for k in ["shrt.sohojgyan", "go.sohojgyan", "bit.ly", "tinyurl", "ouo.io"]) or (
                    "sohojgyan.com" in host_in and len(parsed_in.path.strip("/").split("/")) == 1
                )

                if is_direct_shortlink:
                    # Directly resolve the shortened URL with retry until target format matches
                    resolve_res = resolver.resolve_shortlink_until_target(url, max_retries=4, return_html=True)
                    final_dest_url = resolve_res["final"]
                    final_html = resolve_res.get("final_html")
                    if not final_html or len(final_html) < 200 or resolver.is_target_destination(final_dest_url):
                        try:
                            _, dest_page_html = extractor.fetch_page(final_dest_url)
                            if dest_page_html and len(dest_page_html) > len(final_html or ""):
                                final_html = dest_page_html
                        except Exception:
                            pass

                    items = extractor.extract_from_html(final_html, final_dest_url, button_only=button_only) if final_html else []
                    is_target = resolver.is_target_destination(final_dest_url)
                    page_title = extractor.extract_page_title(final_html, final_dest_url) if final_html else extractor.derive_title_from_url(final_dest_url)
                    return {
                        "success": True,
                        "resolved": True,
                        "original_url": url,
                        "final_url": final_dest_url,
                        "page_title": page_title,
                        "target_destination_verified": is_target,
                        "redirects": resolve_res.get("redirects", 0),
                        "chain": resolve_res.get("chain", []),
                        "items": items,
                        "count": len(items),
                        "bytes": len(final_html) if final_html else 0
                    }

                # Otherwise, resolve the drama / source page first to inspect content and find the shorten URL
                resolve_res = resolver.resolve_url(url, return_html=True)
                final_dest_url = resolve_res["final"]
                final_html = resolve_res.get("final_html")

                if not final_html or len(final_html) < 200:
                    try:
                        _, dest_page_html = extractor.fetch_page(final_dest_url)
                        if dest_page_html and len(dest_page_html) > len(final_html or ""):
                            final_html = dest_page_html
                    except Exception:
                        pass

                # Find shortened URLs associated with "Episode Wise Links" from the source page
                if final_html and not resolver.is_target_destination(final_dest_url):
                    import re
                    from urllib.parse import urljoin

                    def is_valid_shortlink(cand_url):
                        if not cand_url:
                            return False
                        low = cand_url.lower()
                        if any(b in low for b in ["movihubhq.com", "movihub", "payout-rates", "privacy", "terms", "contact", "about", "pages/"]):
                            return False
                        return True

                    candidates = []

                    # 1. Direct target Blogspot episode destination format (https://mydverse02.blogspot.com/p/*.html)
                    for m_target in re.finditer(r'<a\s+[^>]*href=[\'"](https?://(?:www\.)?mydverse02\.blogspot\.[a-z.]+/p/[a-zA-Z0-9_-]+\.html)[\'"]', final_html, re.I):
                        c = m_target.group(1).strip()
                        if resolver.is_target_destination(c) and c not in candidates:
                            candidates.append(c)

                    # 2. Anchor containing Episode Wise Links / Episode-Wise / Download Episodes
                    for m_ew in re.finditer(r'<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>[\s\S]*?(?:Episode[\s_-]*Wise[\s_-]*Links?|Episode[\s_-]*Wise|Download[\s_-]*Episodes?)[\s\S]*?</a>', final_html, re.I):
                        resolved_cand = urljoin(final_dest_url, m_ew.group(1).strip())
                        if is_valid_shortlink(resolved_cand) and resolved_cand not in candidates:
                            candidates.append(resolved_cand)

                    # 3. Contextual nearby container
                    m_near = re.search(r'(?:Episode[\s_-]*Wise[\s_-]*Links?|Episode[\s_-]*Wise)[\s\S]{0,300}?<a\s+[^>]*href=[\'"]([^\'"]+)[\'"]', final_html, re.I)
                    if m_near:
                        resolved_cand = urljoin(final_dest_url, m_near.group(1).strip())
                        if is_valid_shortlink(resolved_cand) and resolved_cand not in candidates:
                            candidates.append(resolved_cand)

                    # 4. Direct href matching shrt.sohojgyan.com or go.sohojgyan.com
                    for m_shrt in re.finditer(r'<a\s+[^>]*href=[\'"](https?://(?:shrt\.[a-z0-9.-]+|go\.sohojgyan\.com)/[a-zA-Z0-9_-]+)[\'"]', final_html, re.I):
                        c = m_shrt.group(1).strip()
                        if is_valid_shortlink(c) and c not in candidates:
                            candidates.append(c)

                    # 5. Direct search in scripts for https://mydverse02.blogspot.com/p/*.html
                    for m_script in re.finditer(r'[\'"](https?://(?:www\.)?mydverse02\.blogspot\.[a-z.]+/p/[a-zA-Z0-9_-]+\.html)[\'"]', final_html, re.I):
                        c = m_script.group(1).strip()
                        if resolver.is_target_destination(c) and c not in candidates:
                            candidates.append(c)

                    # After finding candidate shortened URLs, resolve with retry until destination matches Blogspot structure:
                    # (https://mydverse02.blogspot.com/p/flp-120926.html, https://mydverse02.blogspot.com/p/mbmb-030826.html)
                    for shortlink_candidate in candidates:
                        if shortlink_candidate == url:
                            continue

                        # If candidate is already the target destination
                        if resolver.is_target_destination(shortlink_candidate):
                            final_dest_url = shortlink_candidate
                            try:
                                _, sub_html = extractor.fetch_page(final_dest_url)
                                if sub_html:
                                    final_html = sub_html
                            except Exception:
                                pass
                            break

                        # Resolve shortened URL with retry until destination matches Blogspot structure
                        sub_resolve = resolver.resolve_shortlink_until_target(shortlink_candidate, max_retries=4, return_html=True)
                        sub_final_url = sub_resolve.get("final", "")
                        sub_final_html = sub_resolve.get("final_html")

                        # If matched required Blogspot structure or retries completed:
                        if resolver.is_target_destination(sub_final_url):
                            if not sub_final_html or len(sub_final_html) < 200:
                                try:
                                    _, fetched_html = extractor.fetch_page(sub_final_url)
                                    if fetched_html:
                                        sub_final_html = fetched_html
                                except Exception:
                                    pass

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
                            break

                # If final destination HTML was not captured, fetch it now
                if not final_html or len(final_html) < 200 or resolver.is_target_destination(final_dest_url):
                    try:
                        _, dest_page_html = extractor.fetch_page(final_dest_url)
                        if dest_page_html and len(dest_page_html) > len(final_html or ""):
                            final_html = dest_page_html
                    except Exception:
                        pass

                # Extract action and download links from the final destination HTML
                items = extractor.extract_from_html(final_html, final_dest_url, button_only=button_only) if final_html else []
                is_target = resolver.is_target_destination(final_dest_url)

                page_title = extractor.extract_page_title(final_html, final_dest_url) if final_html else extractor.derive_title_from_url(final_dest_url)

                return {
                    "success": True,
                    "resolved": True,
                    "original_url": url,
                    "final_url": final_dest_url,
                    "page_title": page_title,
                    "target_destination_verified": is_target,
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
                    "page_title": ext_res.get("page_title") or extractor.derive_title_from_url(ext_res.get("final_url", url)),
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
