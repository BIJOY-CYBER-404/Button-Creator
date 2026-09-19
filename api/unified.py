import os
import sys
import json
from http.server import BaseHTTPRequestHandler

# Ensure all possible module locations are added to sys.path
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
for p in [current_dir, parent_dir, os.getcwd(), "/var/task", "/var/task/api"]:
    if p and os.path.exists(p) and p not in sys.path:
        sys.path.insert(0, p)

try:
    import unified_engine
except ImportError:
    # Try relative import or parent import
    sys.path.append(parent_dir)
    import unified_engine

class handler(BaseHTTPRequestHandler):
    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Requested-With")
        self.send_header("Content-Length", "0")
        self.end_headers()

    def do_POST(self):
        try:
            try:
                content_length = int(self.headers.get("Content-Length", 0))
            except Exception:
                content_length = 0

            post_data = "{}"
            if content_length > 0:
                post_data = self.rfile.read(content_length).decode("utf-8", errors="replace")
            else:
                # Try reading whatever is available if content-length was not provided
                try:
                    post_data = self.rfile.read().decode("utf-8", errors="replace")
                except Exception:
                    post_data = "{}"

            try:
                body = json.loads(post_data or "{}")
            except Exception:
                body = {}

            url = body.get("url")
            html = body.get("html")
            base_url = body.get("base_url")
            button_only = body.get("button_only", True)
            auto_resolve = body.get("auto_resolve", True)

            result = unified_engine.run_unified_pipeline(
                url=url,
                html=html,
                base_url=base_url,
                button_only=button_only,
                auto_resolve=auto_resolve
            )

            response_bytes = json.dumps(result).encode("utf-8")
            status_code = 200 if result.get("success") else 400

            self.send_response(status_code)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(response_bytes)))
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Connection", "close")
            self.end_headers()
            self.wfile.write(response_bytes)

        except Exception as exc:
            err_payload = json.dumps({
                "success": False,
                "error": f"Server processing error: {str(exc)}"
            }).encode("utf-8")
            self.send_response(500)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(err_payload)))
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Connection", "close")
            self.end_headers()
            self.wfile.write(err_payload)

    def do_GET(self):
        # Provide helpful status if accessed via GET
        payload = json.dumps({
            "status": "active",
            "endpoint": "/api/unified",
            "method": "POST",
            "description": "Unified Link Resolver & Button Extractor Pipeline"
        }).encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(payload)))
        self.send_header("Access-Control-Allow-Origin", "*")
        self.end_headers()
        self.wfile.write(payload)
