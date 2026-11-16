"""Loopback-only fixture server. Exposes exactly the fixture and production JS.
Stops automatically after five minutes; never deploy or start this in GLPI.
"""
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path
import time

repo = Path(__file__).resolve().parents[1]
class Preview(BaseHTTPRequestHandler):
    def do_GET(self):
        route = self.path.split('?', 1)[0]
        files = {'/tests/fixtures/bar.html': ('tests/fixtures/bar.html', 'text/html; charset=utf-8'),
                 '/public/js/editcontrol.js': ('public/js/editcontrol.js', 'application/javascript')}
        if route == '/favicon.ico':
            self.send_response(204); self.end_headers(); return
        if route not in files:
            self.send_error(404); return
        path, mime = files[route]
        self.send_response(200); self.send_header('Content-Type', mime)
        self.send_header('Cache-Control', 'no-store'); self.end_headers()
        self.wfile.write((repo / path).read_bytes())
    def log_message(self, *args): pass

server = HTTPServer(('127.0.0.1', 18769), Preview)
server.timeout = 1
deadline = time.time() + 300
try:
    while time.time() < deadline: server.handle_request()
finally: server.server_close()
