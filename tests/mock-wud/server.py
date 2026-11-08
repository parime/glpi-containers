"""Faux What's Up Docker (WUD) pour les tests manuels et de bout en bout (données fictives).

Répond comme l'API de WUD 8.x aux seules requêtes que le plugin envoie (GET /api/app et
GET /api/containers), à partir d'un fichier JSON relu à chaque requête : on peut donc changer une
version pendant un test en modifiant le fichier. Toute autre méthode (POST, PUT, DELETE...) est
refusée (405) : le plugin ne doit jamais déclencher de mise à jour.

Authentification basique optionnelle : si MOCK_USER est défini, toute requête sans l'en-tête
Authorization attendu reçoit 401. Le mot de passe n'est jamais écrit dans le journal.

Erreurs simulées (variable MOCK_SIMULATE, ou clé « simulate » du fichier, relue à chaque requête) :
  401          refuse toutes les requêtes ;
  500          erreur interne du serveur ;
  delay:<s>    attend <s> secondes avant de répondre (délai d'attente du client).

Usage : [MOCK_USER=... MOCK_PASSWORD=...] [MOCK_SIMULATE=...] python server.py fixture.json [port]
"""
import base64
import json
import os
import sys
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

FIXTURE = sys.argv[1]
PORT = int(sys.argv[2]) if len(sys.argv) > 2 else 3000
USER = os.environ.get('MOCK_USER', '')
PASSWORD = os.environ.get('MOCK_PASSWORD', '')


def expected_authorization():
    token = base64.b64encode(f'{USER}:{PASSWORD}'.encode()).decode()
    return 'Basic ' + token


class Handler(BaseHTTPRequestHandler):
    def log_message(self, fmt, *args):
        pass

    def reply(self, status, payload):
        body = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def refuse_write(self):
        print(f'{self.command} {self.path} refused (read-only mock)', flush=True)
        self.reply(405, {'error': 'Method not allowed by the fake WUD'})

    do_POST = do_PUT = do_PATCH = do_DELETE = refuse_write

    def do_GET(self):
        path = self.path.split('?', 1)[0]
        with open(FIXTURE, encoding='utf-8') as handle:
            data = json.load(handle)
        simulate = os.environ.get('MOCK_SIMULATE', '') or str(data.get('simulate') or '')
        authorized = USER == '' or self.headers.get('Authorization') == expected_authorization()
        print(f'GET {path} auth_ok={authorized} simulate={simulate or "-"}', flush=True)
        if simulate.startswith('delay:'):
            time.sleep(float(simulate.split(':', 1)[1]))
        if simulate == '401' or not authorized:
            return self.reply(401, {'error': 'Unauthorized'})
        if simulate == '500':
            return self.reply(500, {'error': 'Internal server error (simulated)'})
        if path == '/api/app':
            return self.reply(200, data['app'])
        if path == '/api/containers':
            return self.reply(200, data['containers'])
        return self.reply(404, {'error': 'Not found'})


if __name__ == '__main__':
    ThreadingHTTPServer(('0.0.0.0', PORT), Handler).serve_forever()
