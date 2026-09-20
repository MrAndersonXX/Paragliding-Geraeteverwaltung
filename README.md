# Glider Equipment Tracker

Ein Docker-basierter Web-Server für die Verwaltung und Überwachung von Gleitschirm-, Rettungsgerät-, Gurtzeug-, Helm- und Zubehör-Ausrüstung. Die Anwendung unterstützt:

- Geräteverwaltung mit Hersteller, Typ, Größe, Seriennummer, Anschaffungsdatum und Status
- Individuelle Prüfungsintervalle je Gerät
- Erfassung von Hersteller-Nachprüfungen mit Gültigkeitsdauer
- Ausmusterung von Geräten
- Dokumente mit frei konfigurierbaren Kategorien
- Mail-Benachrichtigungen bei bevorstehenden oder überfälligen Prüfungen
- Jahreskalender mit Prüfungs- und Geräteereignissen
- Kalendertermine für alle erfassten Gerätedaten und die Prüfungshistorie
- Gerätealter im Kalender-Mouseover ab dem Folgejahr der Anschaffung
- Archivierte Geräte mit weiterhin sichtbarer Historie
- Vertikaler Zeitstrahl in der Geräteansicht
- Import/Export aller Daten, Einstellungen, Benutzer, Bilder und angehängten Dateien als ein ZIP-Archiv
- Synology DiskStation 920+ kompatibles Docker-Setup mit automatischer Ordnerstruktur und Berechtigungsvergabe über Container Manager

Version: 0.5.0

## Überblick

Die Anwendung ist bewusst leichtgewichtig aufgebaut und für Synology DSM 7.x optimiert. Sie läuft als Docker-Stack mit:

- PHP-FPM App-Container
- Nginx Web-Container
- MariaDB-Container
- Redis-Container

## Layout-Prüfung

Jede Änderung an sichtbaren Inhalten, Formularen, Tabellen oder Navigation muss vor dem Merge in Desktop- und Mobilansicht geprüft werden. Dabei müssen insbesondere diese Regeln eingehalten werden:

- Gleichartige Eingabefelder haben dieselbe Höhe und bleiben innerhalb ihres Containers.
- Feldüberschriften stehen direkt und mit einheitlichem Abstand über ihrem Feld.
- Texte, Hinweis-Symbole und Bedienelemente dürfen weder überlappen noch horizontal über den Viewport hinausragen.
- Mehrspaltige Raster wechseln auf kleinen Bildschirmen in eine lesbare einspaltige Darstellung.

## Funktionsumfang

### Geräteverwaltung

Für jedes Gerät können erfasst werden:

- Name / eindeutige Kennzeichnung
- Kategorie (Gleitschirm, Rettungsgerät, Gurtzeug, Helm, Sonstiges)
- Hersteller
- Gerätetyp
- Größe
- Seriennummer
- Anschaffungsdatum
- Benutzerzuordnung
- Letzte tatsächliche Prüfung
- Nächste geplante Prüfung
- Prüfungsintervall in Monaten
- Maximal zulässige Betriebsdauer für Rettungsgeräte
- Status (aktiv, in Prüfung, ausgemustert, defekt)

### Benutzer und Rechte

- Die bestehenden Benutzer werden beim ersten Login als Administratoren übernommen.
- Das initiale Passwort für bestehende Benutzer lautet `GliderAdmin2026!` und sollte anschließend im Benutzerbereich geändert werden.
- Neue Benutzer können sich selbst mit Vorname, Nachname, E-Mail-Adresse und Passwort registrieren. Ihre E-Mail-Adresse wird mit einem sechsstelligen, 15 Minuten gültigen Bestätigungscode verifiziert.
- Nach erfolgreicher E-Mail-Bestätigung erhalten alle aktiven Administratoren eine Benachrichtigung. Ein Administrator gibt das Konto anschließend als Benutzer oder Administrator frei.
- Vor der Freigabe ist keine Anmeldung möglich; unbestätigte, wartende und deaktivierte Konten können keine Fach- oder Benutzerdaten einsehen oder ändern.
- Administratoren haben Zugriff auf alle Bereiche und Geräte.
- Normale Benutzer sehen und bearbeiten nur ihre eigenen Geräte und können neue Geräte nur sich selbst zuweisen.
- Benutzer können ihr eigenes Profil mit Passwortbestätigung deaktivieren. Dabei werden alle zugeordneten Geräte archiviert. Administratoren können deaktivierte Konten reaktivieren; die Geräte bleiben dann archiviert und dem Konto zugeordnet.
- Administratoren können deaktivierte Konten endgültig löschen. Deren archivierte Geräte, Prüfungshistorie und Dokumente bleiben erhalten, werden aber keinem Benutzer mehr zugeordnet. Das letzte aktive Administratorkonto kann nicht deaktiviert, herabgestuft oder gelöscht werden.
- Der Loginstatus wird per HttpOnly-Cookie vier Wochen gespeichert und bei jedem Öffnen der Anwendung verlängert.
- Administratoren können im Änderungsprotokoll alle fachlichen und technischen Änderungen nach Zeit, Benutzer, Bereich, Aktion und Ereignisart filtern.
- Das Änderungsprotokoll wird dauerhaft geführt und enthält unter anderem Geräte, Prüfungen, Dokumente, Benutzer, Kategorien, Typen, Einstellungen sowie erfolgreiche An- und Abmeldungen.
- Kennwörter, Passwort-Hashes, Remember-Token und SMTP-Benutzernamen werden im Änderungsprotokoll nicht gespeichert oder angezeigt.

### Prüfungslogik

- Das Anschaffungsdatum ist für jedes Gerät verpflichtend.
- Das Prüfungsintervall wird je Gerät individuell definiert.
- Ohne tatsächliche Prüfung wird die nächste geplante Prüfung aus Anschaffungsdatum und Intervall berechnet.
- Nach einer tatsächlichen Prüfung wird der nächste Termin aus dem tatsächlichen Prüfdatum und dem aktuellen Intervall berechnet.
- Beim Eintragen einer Prüfung kann das Intervall für alle zukünftigen Prüfungen dauerhaft geändert werden.
- Sowohl regelmäßige Prüfungen als auch Herstellernachprüfungen können erfasst werden.
- Wenn für ein Rettungsgerät keine Herstellernachprüfung vorliegt, wird die maximale Betriebsdauer als Ausmusterungs-Horizon berücksichtigt.
- Geräte können manuell als ausgemustert markiert werden.

### Dokumente

- Dokumente können als PDF oder andere zulässige Dateien hochgeladen werden.
- Kategorien sind frei anlegbar, z. B. Kaufbeleg, Prüfprotokoll, Herstellerinfo, Wartung, Nachprüfung, Sonstiges.

### Import/Export

- Unter `Import / Export` können Administratoren den gesamten Datenbestand (Geräte, Benutzer, Einstellungen, Dokumentkategorien, Gerätetypen, Dokumente, Prüfhistorie und Gerätebilder) als ZIP-Archiv exportieren.
- Beim Import wird das hochgeladene Archiv geprüft und der gesamte Datenbestand ersetzt; zuvor wird automatisch eine Sicherheitskopie des bisherigen Stands unter `storage/app/backups/` angelegt.
- Nach einem Import ist eine erneute Anmeldung erforderlich, da sich die Benutzerdaten geändert haben können.

### Benachrichtigungen

- E-Mail-Benachrichtigungen erfolgen über SMTP.
- Für jedes Gerät können Benachrichtigungsintervalle individuell aktiviert werden.
- Beispiel-Intervalle:
  - 30 Tage vor Fälligkeit
  - 14 Tage vor Fälligkeit
  - 7 Tage vor Fälligkeit
  - bei Überfälligkeit
  - bei Ausmusterung

### Kalender

- Die Kalenderansicht zeigt zwölf Monatszeilen mit Tagesraster.
- Anschaffung, tatsächliche Prüfungen, nächste geplante Prüfungen, Herstellerprüfungen und Ausmusterungen werden direkt aus den Gerätedaten dargestellt.
- Gespeicherte Prüfungen werden dauerhaft in der JSON-Prüfungshistorie geführt und im Kalender sowie im Zeitstrahl angezeigt.
- Bei Terminen ab dem Folgejahr der Anschaffung wird das Gerätealter zum konkreten Termin im Mouseover erklärt.
- Nach der Archivierung werden spätere aktuelle Termine ausgeblendet; vergangene Termine und die Prüfungshistorie bleiben sichtbar.

### Zeitstrahl

- Die Geräteansicht zeigt alle gültigen Datumsangaben in einem vertikalen Zeitstrahl.
- Der jüngste Eintrag steht oben, der älteste unten.
- Die Prüfungshistorie wird in `storage/data/equipment.json` je Gerät gespeichert.

## Verzeichnisstruktur

```text
.
├── docker/
│   ├── nginx/
│   │   ├── Dockerfile
│   │   └── default.conf
│   └── php/
│       ├── Dockerfile
│       ├── entrypoint.sh
│       └── php.ini
├── .env.example
├── .gitignore
├── CHANGELOG.md
├── VERSION
├── composer.json
├── docker-compose.yml
├── docker-compose.synology.yml
├── README.md
├── config/
│   └── app.php
├── database/
│   ├── init.sql
│   └── migrations/
│       └── .gitkeep
├── mysql/
│   └── .gitkeep
├── public/
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── backup.php
│   └── assets/
│       └── styles.css
├── scripts/
│   └── checks.php
├── src/
│   ├── Auth.php
│   ├── Config.php
│   ├── BackupManager.php
│   └── NotificationService.php
├── redis/
│   └── .gitkeep
└── storage/
│   ├── .gitkeep
│   ├── app/
│   │   ├── .gitkeep
│   │   └── backups/
│   │       └── .gitkeep
│   └── data/
│       └── .gitkeep
```

## Voraussetzungen

- macOS 13 oder neuer mit Apple Silicon (M1/M2/M3/M4) und Docker Desktop
- Synology DiskStation DSM 7.x
- Docker und Docker Compose aktiv
- Systempfad für Docker-Volumes, z. B. `/volume1/docker/glider-tracker`
- Domain mit DNS und SSL-Zertifikat
- Mailserver oder SMTP-Endpunkt

## macOS-Installation (Apple Silicon)

Docker Desktop für Apple Silicon installieren und anschließend im Terminal im Projektordner ausführen:

```bash
cd "/Users/DEIN-BENUTZER/Paragliding Geräteverwaltung"
docker compose build --no-cache app
docker compose up -d --force-recreate
docker compose ps
```

Die Compose-Datei verwendet projekt-relative Volumes. Docker Desktop wählt auf Apple Silicon automatisch ARM64; auf der Synology wird automatisch die passende x86_64-Variante verwendet. Deshalb ist keine Plattformangabe und keine `.env`-Datei erforderlich.

Die Anwendung ist unter [http://localhost:8282](http://localhost:8282) erreichbar. Logs und ein Schreibtest:

```bash
docker compose logs -f app
docker compose exec app sh -lc 'touch /var/www/html/storage/data/.test && rm /var/www/html/storage/data/.test'
```

Die Emoticon-Auswahl wird beim App-Image-Build aus der offiziellen aktuellen Unicode-Datei geladen und nach Unicode-Haupt- und Untergruppen kategorisiert. Nach einem neuen Unicode-Release das App-Image mit `--no-cache` neu bauen.

Der Compose-Stack besteht bewusst nur aus PHP-FPM und Nginx. Die Anwendung speichert ihre Daten als JSON in `storage/data`; MariaDB und Redis werden für diesen MVP nicht benötigt.

Zum Beenden:

```bash
docker compose down
```

## Synology-Deployment (Container Manager, empfohlen)

Für die Synology DS920+ liegt `docker-compose.synology.yml` bereit. Sie baut zwei eigenständige, in sich abgeschlossene Images (App und Nginx) und benötigt außer dem Projektordner selbst **keine** Bind-Mounts für `public`, `src`, `config` oder `storage`. Die persistenten Daten (Geräte, Benutzer, Einstellungen, Dokumente, Bilder) liegen in einem von Docker verwalteten benannten Volume, das beim ersten Start automatisch angelegt wird; die passenden Verzeichnisse und Berechtigungen darin erstellt der App-Container selbständig über seinen Entrypoint. Damit entfallen die DSM-ACL-Anpassungen und manuellen `chmod`-Schritte, die für das klassische Bind-Mount-Setup weiter unten beschrieben sind.

1. Projektarchiv wie gewohnt nach `/volume1/docker/glider-tracker` laden (Schritt 1–2 im klassischen Setup weiter unten gelten unverändert, da für den Image-Build weiterhin der vollständige Quellcode benötigt wird).
2. In Container Manager ein neues Projekt anlegen, als Pfad `/volume1/docker/glider-tracker` und als Compose-Datei `docker-compose.synology.yml` auswählen (oder per SSH: `docker compose -f docker-compose.synology.yml up -d --build`).
3. Container Manager baut `glider-tracker-app` (PHP-FPM, Code aus dem Repository gebacken) und `glider-tracker-nginx` (Nginx mit gebackenem `public`-Verzeichnis) und legt automatisch das Volume für `storage` an.
4. Webzugriff unter `http://<synology-ip>:8282`, bzw. über einen Reverse Proxy auf `nginx:80`.

Datensicherung und -wiederherstellung laufen über die Weboberfläche unter `Import / Export` (nur für Administratoren) und erzeugen bzw. lesen ein ZIP-Archiv mit allen Geräten, Benutzern, Einstellungen, Kategorien, Gerätetypen, Dokumenten und Gerätebildern. Ein direkter Dateisystemzugriff auf das Docker-Volume ist dafür nicht nötig; für ein manuelles Rohdaten-Backup des Volumes genügt z. B.:

```bash
docker run --rm -v glider-tracker_glider_storage:/data -v "$PWD":/backup alpine \
  tar czf /backup/glider-storage-backup.tar.gz -C /data .
```

Der Name des Volumes richtet sich nach dem Projektnamen (Standard: Ordnername, hier `glider-tracker`); mit `docker volume ls` lässt sich der tatsächliche Name prüfen.

Bei Code-Änderungen im Repository müssen beide Images neu gebaut werden:

```bash
docker compose -f docker-compose.synology.yml build --no-cache
docker compose -f docker-compose.synology.yml up -d --force-recreate
```

## Synology-Deployment (klassisches Bind-Mount-Setup)

1. Ordner erstellen:

```bash
mkdir -p /volume1/docker/glider-tracker
cd /volume1/docker/glider-tracker
```

2. Das GitHub-Projekt ohne Git direkt als Archiv in diesen Ordner laden. Die Compose-Datei verwendet projekt-relative Mounts, daher funktioniert der Stack unabhängig vom konkreten Synology-Pfad:

```bash
curl -fL https://codeload.github.com/MrAndersonXX/Paragliding-Ger-teverwaltung/tar.gz/refs/heads/main -o /tmp/glider-tracker.tar.gz
tar -xzf /tmp/glider-tracker.tar.gz --strip-components=1 -C /volume1/docker/glider-tracker
rm -f /tmp/glider-tracker.tar.gz
```

Die Compose-Datei baut das PHP-Image anschließend aus dem lokal entpackten Projekt. Die `.gitkeep`-Dateien sorgen dafür, dass ansonsten leere Verzeichnisse beim Archiv erhalten bleiben.

Gerätetypen werden unter `Gerätetypen` verwaltet. Dort können neue Werte angelegt werden; sie erscheinen anschließend automatisch im Gerätetyp-Dropdown.

Wenn das GitHub-Repository privat ist, liefert GitHub ohne Anmeldung `404 Not Found`. Dann auf der DiskStation einen GitHub-Token mit Leserechten für das Repository verwenden:

```bash
read -s GITHUB_TOKEN
curl -fL -H "Authorization: Bearer ${GITHUB_TOKEN}" \
  https://codeload.github.com/MrAndersonXX/Paragliding-Ger-teverwaltung/tar.gz/refs/heads/main \
  -o /tmp/glider-tracker.tar.gz
unset GITHUB_TOKEN
tar -xzf /tmp/glider-tracker.tar.gz --strip-components=1 -C /volume1/docker/glider-tracker
rm -f /tmp/glider-tracker.tar.gz
```

Alternativ das Repository in GitHub im Browser öffnen, über `Code` und `Download ZIP` herunterladen und das ZIP per File Station nach `/volume1/docker/glider-tracker` übertragen.

3. Eine `.env`-Datei ist nicht erforderlich. App-Name, Zeitzone, SMTP-Zugang und Kalenderzugang werden nach dem Start direkt unter `Einstellungen` beziehungsweise `Kalender` im Tool eingegeben und persistent gespeichert.

Alternativ kann das Projekt auf einem Rechner mit TAR-Unterstützung als Archiv übertragen werden:

```bash
tar --exclude='.DS_Store' -czf glider-equipment-tracker.tar.gz .
```

Fälligkeitsmails werden über den zugeordneten Benutzer versendet. Für einen täglichen Versand kann im Synology-Aufgabenplaner dieses Kommando ausgeführt werden:

```bash
cd /volume1/docker/glider-tracker
docker compose exec -T app php scripts/send-reminders.php
```

Ein Gerät muss dafür einem Benutzer zugeordnet sein und die Benachrichtigung bei Überfälligkeit aktiviert haben.

Das Archiv auf der DiskStation nach `/volume1/docker/glider-tracker` entpacken. Vor dem Start müssen mindestens diese Dateien vorhanden sein:

```text
/volume1/docker/glider-tracker/docker-compose.yml
/volume1/docker/glider-tracker/docker/php/Dockerfile
/volume1/docker/glider-tracker/docker/nginx/default.conf
/volume1/docker/glider-tracker/config/app.php
/volume1/docker/glider-tracker/database/init.sql
/volume1/docker/glider-tracker/public/index.php
```

Die folgenden Ordner werden als projekt-relative Docker-Volume-Quellen verwendet und sind deshalb ebenfalls Bestandteil des Projektpakets:

```text
/volume1/docker/glider-tracker/public
/volume1/docker/glider-tracker/src
/volume1/docker/glider-tracker/config
/volume1/docker/glider-tracker/storage
```

Die Ordner `storage`, `public`, `src` und `config` werden als relative Mounts aus dem Projektordner verwendet.

4. Container starten:

```bash
docker compose down
docker compose build --no-cache app
docker compose up -d --force-recreate
```

Der PHP-Container bereitet den gemounteten Ordner `/volume1/docker/glider-tracker/storage` beim Start automatisch für den Webprozess vor. Dadurch können `storage/data/equipment.json`, `document_categories.json` und `settings.json` angelegt und geändert werden.

Falls die Synology-ACL den Zugriff des Docker-Daemons blockiert, müssen die Berechtigungen des gemeinsamen Ordners `docker/glider-tracker` in DSM für den verwendeten Docker-Benutzer beziehungsweise die Docker-Gruppe auf Lesen und Schreiben gesetzt werden.

Vor dem Start kann der Dockerfile-Pfad geprüft werden:

```bash
test -f docker/php/Dockerfile && echo "Dockerfile vorhanden"
test -f docker/nginx/default.conf && echo "Nginx-Konfiguration vorhanden"
```

Falls du eine ältere Projektkopie verwendest, muss der versteckte Ordner `.docker` entweder vollständig mitkopiert werden oder durch den sichtbaren Ordner `docker` aus der aktuellen Projektversion ersetzt werden.

Bei bereits vorhandenen Containern ist ein Neustart allein nicht ausreichend, wenn das Image noch den alten Entrypoint enthält. In diesem Fall `docker compose down` und anschließend `docker compose up -d --build --force-recreate` ausführen.

Falls die JSON-Dateien bereits mit falschen Synology-Rechten angelegt wurden, einmalig auf der DiskStation ausführen:

```bash
cd /volume1/docker/glider-tracker
chmod -R a+rwX storage
docker compose down
docker compose build --no-cache app
docker compose up -d --force-recreate
```

Danach werden die persistenten Dateien unter `storage/data/` von PHP-FPM beschreibbar angelegt.

Den Schreibtest und den tatsächlich verwendeten Container-Benutzer kannst du anschließend prüfen:

```bash
docker compose logs app
docker compose exec app id
docker compose exec app sh -lc 'touch /var/www/html/storage/data/.manual-write-test && rm /var/www/html/storage/data/.manual-write-test'
```

Der erwartete Benutzer ist `uid=0(root)`. Schlägt der manuelle Schreibtest trotz `uid=0` fehl, blockiert die DSM-ACL den Shared Folder. In diesem Fall im DSM für den Shared Folder `docker` beziehungsweise den Unterordner `glider-tracker` dem Docker-Dienst Lesen und Schreiben erlauben und den obigen Start wiederholen.

Der PHP-FPM-Master startet als Container-Root, die PHP-FPM-Worker laufen mit dem vorgesehenen Benutzer `www-data`. Der Entrypoint bereitet den gemounteten Storage vor und testet den Schreibzugriff vor dem Start. Für eine öffentlich zugängliche Installation sollte zusätzlich ein eigener DSM-Shared-Folder ohne restriktive ACL eingerichtet werden.

5. Logs prüfen:

```bash
docker compose logs -f
```

6. Webzugriff:

- über den internen Reverse Proxy auf die Domain oder direkt über das Synology-Portal
- Beispiel: `https://tracker.example.com`
- Für einen direkten Zugriff ohne Reverse Proxy: `http://<synology-ip>:8282`

## Konfiguration der Mailfunktion

Die Mail-Konfiguration wird in der `.env` hinterlegt. Beispiel:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.example.com
MAIL_PORT=587
MAIL_USERNAME=benutzer
MAIL_PASSWORD=passwort
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tracker@example.com
MAIL_FROM_NAME="Glider Equipment Tracker"
```

Die E-Mail-Tests sollten über die Anwendung selbst erfolgen. Die App erwartet ein funktionierendes SMTP-Setup auf dem Mailserver.

## Standard-Datenmodell

Die Datenbank enthält typischerweise Tabellen wie:

- `equipment`
- `equipment_documents`
- `document_categories`
- `inspection_intervals`
- `inspection_history`
- `notifications`
- `users`

Ein komplettes Schema liegt in `database/init.sql` vor.

## Beispiel-Benachrichtigungslogik

Das System prüft regelmäßig:

- Geräte mit nächster Prüfung innerhalb eines konfigurierten Zeitfensters
- Überfällige Prüfungen
- Geräte mit Ablauf der maximalen Betriebsdauer
- Ausgemusterte Geräte

## Betriebshinweise für Synology

- Lokale Docker-Volumes sollten auf `/volume1/docker/glider-tracker/...` liegen.
- Für Reverse Proxy und SSL ist der interne Synology Reverse Proxy geeignet.
- In DSM können die Container mit einem eigenen Docker-Netzwerk gearbeitet werden.
- Die persistenten Daten der MariaDB liegen im Volume unter `/volume1/docker/glider-tracker/mysql`.

## Sicherheitsmaßnahmen

- `.env` niemals in Git committen.
- Zugangsdaten nur per Docker-Umgebungsvariablen oder `docker secret` verwalten.
- Mail- und Kalender-Zugangsdaten nicht im Frontend sichtbar machen.
- Für Produktivbetrieb eine eigene non-root-Umgebung und ein Backup-Plan ergänzen.
- Die Konfigurationsdatei verwendet `getenv()` mit projektinternem Fallback und benötigt keine Laravel-Funktion `env()`.
- Alle in Compose verwendeten Host-Volume-Ordner sind im Projekt enthalten, damit der Ordnertransfer auf die Synology vollständig bleibt.

## Roadmap

- Version 0.1.0: Grundgerüst, Docker-Setup, Geräteübersicht, Mail-Konfiguration, Kalender-Integration, Dokumentenkategorien
- Version 0.2.0: vollständiger CRUD-Bereich für Geräte und Prüfungen
- Version 0.3.0: Benutzerrollen, Rechteverwaltung und Audit-Logs
- Version 0.4.0: CSV-Export, PDF-Dokumentation und Erinnerungs-Reports
- Version 1.0.0: produktionsreife Freigabe für operative Nutzung

## Lizenz

Dieses Projekt dient als generische Grundlage für einen internen Betrieb. Die Nutzung ist frei, sofern die Dokumentation und die Sicherheitsprinzipien eingehalten werden.
