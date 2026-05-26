# PHPMyLager

## Beschreibung

PHPMyLager is an internal warehouse management system used for internal company purpose only that enables small businesses to streamline products, customers, and orders within a dedicated web application. It is designed to replace excel sheets or other forms of holding warehouse products/information, since it offers a solution for local or cloud-based deployment.

## Umfang
### Abgesprochene Funktionen
| Entity | Description| C | R | U | D |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Products** | Inventory and item management | ✅ | ✅ | ✅ | ✅ |
| **Warehouse Groups** | Logical grouping of product types | ✅ | ✅ | ✅ | ❌ |
| **Customers** | Client profiles | ✅ | ✅ | ✅ | ✅ |
| **Customer Orders** | ??? | ✅ | ✅ | ✅ | ✅ |
| **Supply Orders** | ??? | ✅ | ✅ | ✅ | ✅ |
| **Suppliers** | ??? | ✅ | ✅ | ✅ | ✅ |

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
- `.github/workflows/*` – YAML configuration for GitHub Actions pipelines for automated CI testing on PRs to `main` and `dev`
- `app/...` – The main application root directory
- `docker/...` – 
  
- `app/routes/api.php` - Definition of stateless API endpoints (JSON)
- `app/routes/web.php` - Definition of browser-based routes with session
- `app/resources/*` - Frontend assets, including Blade templates, styles, and scripts
- `app/database/*` - Database schema versioning (migrations) and initial data (seeders)
- `app/app/Models/*` - Eloquent models defining database tables and their relationships
- `app/app/Http/Controller/*` - Logic for processing requests and returning responses
- `app/app/Http/Middleware/*` - Filters for incoming request before they reach the controller

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
   **Note:** Open the `.env` file and update the variables to match your local environment.

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
Verifying Core API Endpoints and those API Endpoint test also include RBAC validation and Transaction tests:

#### Product Endpoints
| Method | Path | Action | Min. Role | Status |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/products` | List all active products | `all roles` | ✅ |
| **GET** | `/api/products/{id}` | View a specific product | `all roles` | ✅ |
| **POST** | `/api/products` | Create a new product | `admin, writer` | ✅ |
| **PUT** | `/api/products/{id}` | Update a product | `admin, writer` | ✅ |
| **DELETE** | `/api/products/{id}` | Soft-delete a product | `admin` | ✅ |

#### Warehouse Group Endpoints
| Method | Path | Action | Min. Role |  Status |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/warehouse-groups` | List all warehouse groups | `all roles` | ✅ |
| **GET** | `/api/warehouse-groups/{id}` | View a specific warehouse group | `all roles` | ✅ |
| **POST** | `/api/warehouse-groups` | Create a new warehouse group | `admin, writer` | ✅ |
| **PUT** | `/api/warehouse-groups/{id}` | Update a warehouse group name | `admin, writer` | ✅ |

#### Order Endpoints
| Method | Path | Action | Min. Role | Status | 
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/orders` | List all orders | `all roles` | ✅ |
| **GET** | `/api/orders/{id}` | View a specific order | `all roles` | ✅ |
| **POST** | `/api/orders` | Create a new order | `admin, writer` | ✅ |
| **PUT** | `/api/orders/{id}` | Update an order (requires full item list) | `admin, writer` | ✅ |
| **DELETE** | `/api/orders/{id}` | Delete an order (restores stock) | `admin` | ✅ |

### Customer Endpoints
| Method | Path | Action | Min. Role | Status |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/customers` | List all active customers | `all roles` | ✅ |
| **GET** | `/api/customers/{id}` | View a specific customer | `all roles` | ✅ |
| **POST** | `/api/customers` | Create a new customer | `admin, writer` | ✅ |
| **PUT** | `/api/customers/{id}` | Update an existing customer | `admin, writer` | ✅ |
| **DELETE** | `/api/customers/{id}` | Soft-delete a customer | `admin` | ✅ |


> Note: If the application ENV var `APP_ENV` is set to Production, RBAC validation checks will fail because the bypass script is disabled in that environment.
  
- Welche Randfälle wurden berücksichtigt? \
Foreign Key Constraint, Soft Deletes and Atomic Transactions.

### Wie wurde getestet?
- Manuell getestet:
  - ?
- Automatisiert getestet:
  - Github Action pipeline triggers the php artisan tests on every pr on main and dev and runs api unit tests

### Testen des Projekts
1. Directly through Github Actions (manually triggering), click on the Laravel CI Pipeline and click on Run Workflow and start the workflow on the main branch
2. Running tests locally bu running: `docker exec -it phpmylager_app php artisan test` and ENV variable `APP_ENV` is set to `local`
3. By reacting a pr on the main/dev branch

## Users
| Email | Role | Passwort |
| --- | --- | --- |
| admin@example.com | admin | password set in the ENV file |
| writer@example.com | writer | password set in the ENV file |
| reader@example.com | reader | password set in the ENV file |

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
