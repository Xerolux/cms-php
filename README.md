# Blog/CMS Plattform

Dieses Repository ist der Startpunkt für die Blog/CMS-Software gemäß den Requirements in
`Blog-CMS-Software-Requirements.md`.

## Ziel
Eine selbst-gehostete, DSGVO-konforme Blog/CMS-Plattform mit API-First-Architektur, Admin-UI
und skalierbarer Infrastruktur.

## Aktueller Stand
- Projektstruktur ist initial angelegt (Ordnerstruktur gemäß Requirements vorbereitet).
- Ein **minimaler FastAPI-Startpunkt** ist vorbereitet (`app/main.py`) mit Health-Endpoint.
  Das ist bewusst klein gehalten, damit das Team die finale Architektur noch bestimmen kann.

## Nächste Schritte (Vorschlag)
1. **Technologie-Entscheid treffen** (Python/FastAPI vs. PHP/Laravel; Vue vs. React).
   - **Entscheidung:** Wir setzen auf **Python (FastAPI)** und **Vue.js 3** sowie **PostgreSQL**.
2. **Baseline-Architektur definieren** (Ordnerstruktur, Module, Konventionen).
3. **Datenbank-Schema & Migrationen** anlegen.
4. **API-Grundgerüst** (Auth, Posts, Media) implementieren.
5. **Admin-Frontend** mit Login und Basis-Navigation starten.

Details und Kommentare zur Vorgehensweise stehen im Arbeitslog: `docs/work-log.md`.

## Lokales Starten (aktueller Python-Stub)
```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload
```

Health-Check: `http://127.0.0.1:8000/health`  
Posts-Stub: `http://127.0.0.1:8000/api/v1/posts` (GET/POST/PUT/DELETE, `offset`/`limit`)  
Categories-Stub: `http://127.0.0.1:8000/api/v1/categories` (GET/POST/PUT/DELETE, `offset`/`limit`)  
Tags-Stub: `http://127.0.0.1:8000/api/v1/tags` (GET/POST/PUT/DELETE, `offset`/`limit`)  
Users-Stub: `http://127.0.0.1:8000/api/v1/users` (GET/POST/PUT/DELETE, `offset`/`limit`)
