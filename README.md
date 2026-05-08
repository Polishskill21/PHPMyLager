# PHPMyLager

## Beschreibung

PHPMyLager is an internal warehouse management system used for internal company purpose only that enables small businesses to streamline products, customers, and orders within a dedicated web application. It is designed to replace excel sheets or other forms of holding warehouse products/information, since it offers a solution for local or cloud-based deployment.

## Umfang
### Abgesprochene Funktionen
| Entity | C | R | U | D |
| :--- | :---: | :---: | :---: | :---: |
| **Products** | ✅ | ✅ | ✅ | ✅ |
| **Warehouse Groups** | ✅ | ✅ | ✅ | ❌ |
| **Customers** | ✅ | ✅ | ✅ | ✅ |
| **Orders** | ✅ | ✅ | ✅ | ✅ |

---

#### Access Control (RBAC)
Access to the features listed above is strictly governed by the assigned user role:

* **Admin:** Full system access, including all CRUD operations and administrative tasks.
* **Writer:** Authorized to view, create, and update records (C, R, U).
* **Viewer:** Restricted to read-only access across the entire system (R).

### Optionale Funktionen
- [ ] Optionale Funktion 1
- [ ] Optionale Funktion 2
- [ ] Optionale Funktion 3

### Nicht umgesetzt / bewusst ausgelassen
- [ ] Punkt, der nicht mehr umgesetzt wurde
- [ ] Punkt, der bewusst nicht Teil des Projekts ist

## Eingesetzte Technologien
- Frontend: Blade
- Backend: PHP
- Datenbank / Speicherung: MariaDB
- Framework(s): Laravel
- Weitere Bibliotheken / Tools: ...

## Projektstruktur
Kurze Erklärung der wichtigsten Ordner und Dateien.

- `.github/workflows/*` – Github Action Pipeline file for CI on every PR on main and dev branch
- `app/...` – Beschreibung
- `docker/...` – Beschreibung

## Setup
### Voraussetzungen
- Docker installed and runnable

### Lokales Starten
????


### Start mit Container

1. **Clone the repository:**
    ```bash
   git clone [https://github.com/your-username/your-repo.git](https://github.com/your-username/your-repo.git)
   cd your-repo
   ```

2. **Configure environment variables:**
   ```bash
   cp .env.example .env
   ```

3. **Start the containers:**
   ```bash
   docker compose up -d --build
   ```

4. **Run migrations and seeders:**
   ```bash
   docker exec -it phpmylager_app php artisan migrate:fresh --seed
   ```

While running `docker compose up`, the following services are launched automatically within the isolated `phpmylager_net` network:

| Service Name | Container Name | Technology | Ports (Host:Container) | Description |
| :--- | :--- | :--- | :--- | :--- |
| **app** | `phpmylager_app` | PHP (Laravel) | Internal only | Backend application runtime. |
| **web** | `phpmylager_web` | Nginx | `8000:80` | Web server acting as the entry point. |
| **db** | `warehouse_db` | MariaDB | `33060:3306` | Persistent relational database. |

---

#### Accessing the Application

* **Web Interface:** Once everything is running, open your browser and navigate to [http://localhost:8000](http://localhost:8000).

## Tests
### Was wurde getestet?
Verifying Core API Endpoints: (create a table that has each endpoint listed and a check mark at each endpoint)

Those API Endpoint test also include RBAC validation and Transaction tests.

> Note: If the application is set to Production, RBAC validation checks will fail because the bypass script is disabled in that environment.
  
- Welche Randfälle wurden berücksichtigt?
Foreign keys, soft deletes

### Wie wurde getestet?
- Manuell getestet:
  - ...
- Automatisiert getestet:
  - Github Action pipeline triggers the php artisan tests on every pr on main and dev and runs api unit tests

### Testen des Projekts
1. Directly through Github Actions (manually triggering), click on the Laravel CI Pipeline and click on Run Workflow and start the workflow on the main branch
2. Running tests locally bu running: `docker exec -it phpmylager_app php artisan test`
3. By reacting a pr on the main branch it

## Users
<!--
| Name | Passwort |
| --- | --- |
| admin | Admin-1234! |
| user1 | User1-1234! |
| user2 | User2-1234! |
-->

## Bekannte Einschränkungen
- Welche Punkte sind noch offen?
- Welche Einschränkungen hat das Projekt?
- Was würde man in einer nächsten Version verbessern?

## Contributors
<!--
| Name | Matrikel number |
| --- | --- |
| name | matrikel no |
| name | matrikel no |
-->
