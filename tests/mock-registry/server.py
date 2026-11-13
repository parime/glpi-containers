"""Faux registre d'images pour les tests manuels et de bout en bout (données fictives).

Répond aux seules requêtes que le plugin envoie, à partir d'un fichier JSON relu à chaque requête
(on peut donc publier une nouvelle version pendant un test en modifiant le fichier) :
  GET /v2/repositories/<espace>/<dépôt>/tags?page=&page_size=   comme Docker Hub (anonyme) ;
  GET /v2/                                                       ping du registre (API v2) ;
  GET /v2/<projet>/<dépôt>/tags/list                             comme Harbor (API v2 des registres).
Toute autre méthode (POST, PUT, DELETE...) est refusée (405) : le plugin ne doit jamais rien
modifier dans un registre. Aucune image n'est servie.

Authentification basique optionnelle pour l'API v2 (pas pour la partie Docker Hub) : si MOCK_USER
est défini, une requête sans l'en-tête attendu reçoit 401. Les dépôts de « public » répondent sans
authentification (projets publics de Harbor). Le mot de passe n'est jamais écrit dans le journal.

Erreurs simulées (variable MOCK_SIMULATE, ou clé « simulate » du fichier, relue à chaque requête) :
  429          Docker Hub refuse (limite de requêtes anonymes) ;
  500          erreur interne du serveur ;
  delay:<s>    attend <s> secondes avant de répondre (délai d'attente du client).

Usage : [MOCK_USER=... MOCK_PASSWORD=...] [MOCK_SIMULATE=...] python server.py fixture.json [port]
"""
import base64
import json
import os
import re
import sys
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlsplit

FIXTURE = sys.argv[1]
PORT = int(sys.argv[2]) if len(sys.argv) > 2 else 5000
USER = os.environ.get('MOCK_USER', '')
PASSWORD = os.environ.get('MOCK_PASSWORD', '')
HUB = re.compile(r'^/v2/repositories/(.+)/tags/?$')
TAGS = re.compile(r'^/v2/(.+)/tags/list$')


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
        self.reply(405, {'errors': [{'code': 'UNSUPPORTED', 'message': 'read-only fake registry'}]})

    do_POST = do_PUT = do_PATCH = do_DELETE = do_HEAD = refuse_write

    def do_GET(self):
        url = urlsplit(self.path)
        path = url.path
        query = parse_qs(url.query)
        with open(FIXTURE, encoding='utf-8') as handle:
            data = json.load(handle)
        simulate = os.environ.get('MOCK_SIMULATE', '') or str(data.get('simulate') or '')
        authorized = USER == '' or self.headers.get('Authorization') == expected_authorization()
        print(f'GET {path} auth_ok={authorized} simulate={simulate or "-"}', flush=True)
        if simulate.startswith('delay:'):
            time.sleep(float(simulate.split(':', 1)[1]))
        if simulate == '500':
            return self.reply(500, {'errors': [{'code': 'UNKNOWN', 'message': 'simulated'}]})

        hub = HUB.match(path)
        if hub:
            if simulate == '429':
                return self.reply(429, {'message': 'Too Many Requests (simulated)'})
            tags = data.get('hub', {}).get(hub.group(1))
            if tags is None:
                # Docker Hub répond 404 pour un dépôt absent comme pour un dépôt privé.
                return self.reply(404, {'message': 'object not found'})
            page = max(1, int((query.get('page') or ['1'])[0]))
            size = max(1, min(100, int((query.get('page_size') or ['100'])[0])))
            chunk = tags[(page - 1) * size:page * size]
            more = page * size < len(tags)
            following = f'{self.headers.get("Host", "")}{path}?page={page + 1}&page_size={size}' if more else None
            return self.reply(200, {'count': len(tags), 'next': following, 'previous': None, 'results': chunk})

        public = data.get('public', {})
        if path == '/v2/' or path == '/v2':
            if not authorized:
                return self.reply(401, {'errors': [{'code': 'UNAUTHORIZED', 'message': 'unauthorized'}]})
            return self.reply(200, {})
        listing = TAGS.match(path)
        if listing:
            name = listing.group(1)
            if name in public:
                return self.reply(200, {'name': name, 'tags': public[name]})
            if not authorized or (USER == '' and data.get('v2_requires_auth')):
                return self.reply(401, {'errors': [{'code': 'UNAUTHORIZED', 'message': 'unauthorized'}]})
            tags = data.get('v2', {}).get(name)
            if tags is None:
                return self.reply(404, {'errors': [{'code': 'NAME_UNKNOWN', 'message': 'repository name not known'}]})
            return self.reply(200, {'name': name, 'tags': tags})
        return self.reply(404, {'errors': [{'code': 'NOT_FOUND', 'message': 'not found'}]})


if __name__ == '__main__':
    ThreadingHTTPServer(('0.0.0.0', PORT), Handler).serve_forever()
