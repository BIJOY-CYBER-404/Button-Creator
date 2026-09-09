import os
import sys
import json
from http.server import BaseHTTPRequestHandler

# Add parent directory to sys.path so extractor module can be imported
parent_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

import extractor

class handler(BaseHTTPRequestHandler):
    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        self.end_headers()

    def do_POST(self):
        content_length = int(self.headers.get("Content-Length", 0))
        post_data = self.rfile.read(content_length).decode("utf-8") if content_length > 0 else "{}"

        try:
            body = json.loads(post_data)
        except Exception:
            body = {}

        url = body.get("url")
        html = body.get("html")
        base_url = body.get("base_url")
        button_only = body.get("button_only", True)

        result = extractor.run_extraction_api(
            url=url,
            html=html,
            base_url=base_url,
            button_only=button_only
        )

        response_bytes = json.dumps(result).encode("utf-8")

        status_code = 200 if result.get("success") else 400
        self.send_response(status_code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.end_headers()
        self.wfile.write(response_bytes)
