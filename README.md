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
| **Customer Orders** | Outbound sales orders | ✅ | ✅ | ✅ | ✅ |
| **Purchase Orders** | Inbound supplier orders | ✅ | ✅ | ✅ | ✅ |
| **Suppliers** | Supplier profiles | ✅ | ✅ | ✅ | ✅ |

#### Access Control (RBAC)
Access to the features listed above is strictly governed by the assigned user role:

* **Admin:** Full system access, including all CRUD operations and administrative tasks.
* **Writer:** Authorized to view, create, and update records (C, R, U).
* **Viewer:** Restricted to read-only access across the entire system (R).

#### Rate Limiting
To protect the application against brute-force attempts and request floods, the routes are rate limited. Limits are keyed per authenticated user (falling back to the client IP for guests), so one user's activity never throttles another. When a limit is exceeded the server responds with HTTP `429 Too Many Requests` — a JSON message for API calls, a styled error page for browser requests — including the standard `Retry-After` / `X-RateLimit-*` headers.

| Scope | Limit | Keyed by | Applies to |
| :--- | :---: | :--- | :--- |
| **API** | 120 req / min | User ID (or IP for guests) | All authenticated `/api/*` endpoints |
| **Login** | 5 attempts / min | Email + IP | `POST /login` (brute-force guard) |
| **Status** | 30 req / min | IP | Public `GET /api/status` health check |

The limiters are defined in `app/app/Providers/AppServiceProvider.php`

### Optionale Funktionen
- [x] Save order as PDF

### Nicht umgesetzt / bewusst ausgelassen
- [x] No standalone warehouse group deletion

## Eingesetzte Technologien
- Frontend: Blade templates, Tailwind CSS 4, Vite 8 (asset bundling)
- Backend: PHP 8.3+ (container runtime image 8.4), Laravel 13
- Datenbank / Speicherung: MariaDB (`lts`);
- Framework(s): Laravel 13 (Eloquent ORM, Blade)
- Weitere Bibliotheken / Tools: Vite 8, PHPUnit 12

## Projektstruktur
- `.github/workflows/*` – YAML configuration for GitHub Actions pipelines for automated CI testing on PRs to `main` and `dev`
- `Dockerfile` / `Dockerfile.prod` – Local/dev image (PHP 8.4 + Node) and the production image (PHP 8.4, pre-built assets)
- `docker-compose.yaml` / `docker-compose.prod.yaml` – Service definitions for the local and production stacks
- `docker/...` – Container support files: `nginx/default.conf`, `php/conf.d/local.ini`, and `php/entrypoint.sh` (startup script)
- `app/...` – The main Laravel application root directory
- `app/routes/api.php` - Definition of the JSON API endpoints (session-authenticated, RBAC-gated)
- `app/routes/web.php` - Definition of browser-based routes with session (renders the Blade pages)
- `app/resources/*` - Frontend assets: Blade templates (incl. `views/partials/rows/*` for load-more rows), Tailwind styles, and scripts
- `app/database/*` - Database schema versioning (migrations), seeders, and model factories
- `app/app/Models/*` - Eloquent models defining database tables and their relationships
- `app/app/Http/Controllers/*` - Feature-grouped request handlers (Products, Orders, PurchaseOrders, Suppliers, Customers, WarehouseGroups, Auth)
- `app/app/Http/Middleware/*` - Filters for incoming requests before they reach the controller (RBAC, JSON responses, dev auth bypass)
- `app/app/Support/DomainCache.php` - Domain-versioned cache helper (caches default list views, flushed on writes)
- `app/app/Enums/*` - Typed enums (e.g. purchase-order status)
- `app/config/*` - Laravel configuration
- `app/tests/*` - API/feature test suite (run in CI)

## Setup
### Voraussetzungen
- Docker (with Docker Compose) installed and runnable
- Git

### Lokales Starten
> The whole stack (PHP app, Nginx, Node/Vite builder, MariaDB) runs in containers — no local PHP, Node or database installation is required.

### Start mit Container (Production)

1. **Clone the repository:**
    ```bash
   git clone [https://github.com/your-username/your-repo.git](https://github.com/your-username/your-repo.git)
   cd your-repo
   ```

2. **Initialize Environment Configuration** Copy the production environment example file to create your active production configuration file:
   ```bash
   cp .env.example .env
   ```

3. **Generate a Secure App Encryption Key** Run this command once to generate a secure, cryptographically isolated `APP_KEY`:  
   ```bash
   docker run --rm php:8.4-fpm-alpine php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
   ```
   Copy the output string and paste it into your production `.env` file as `APP_KEY=base64:AsHgqwr...` (make sure there are no spaces around the `=` sign).

4. **Update Production Credentials** Change all default database passwords, usernames, and secret variables to strong, unique values.
   > ⚠️ **Warning:** If you skip modifying the passwords, the system will default to using `"password"`.

5. **Build and Start Production Services** Launch the production container configuration:  
   ```bash
   docker compose -f docker-compose.prod.yaml up --build -d
   ```

6. **Run Production Migrations Safely** Apply your database schema and initial data seeds using the safe production flags:  
   ```bash
   docker exec -it phpmylager_app_prod php artisan migrate --force && docker exec -it phpmylager_app_prod php artisan db:seed --force
   ```
7. **Access the Application** Open your browser and navigate to: **[http://localhost:8000](http://localhost:8000)**

While running `docker compose -f docker-compose.prod.yaml up --build -d`, the following services are launched automatically within the isolated `phpmylager_net` network:

| Service Name | Container Name | Technology | Ports (Host:Container) | Description |
| :--- | :--- | :--- | :--- | :--- |
| **app** | `phpmylager_app_prod` | PHP (Laravel) | Internal only | Backend application runtime; serves pre-built assets from the `prod_public` volume. |
| **web** | `phpmylager_web_prod` | Nginx | `${WEB_PORT:-8000}:80` | Web server acting as the entry point. |
| **db** | `warehouse_db_prod` | MariaDB | Internal only | Persistent relational database (no host port exposed). |

### Start mit Container (Lokal)

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
| **web** | `phpmylager_web` | Nginx | `${APP_PORT:-8000}:80` | Web server acting as the entry point. |
| **node** | `phpmylager_node` | Node | Internal only | Vite asset builder/watcher. |
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

#### Customer Endpoints
| Method | Path | Action | Min. Role | Status |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/customers` | List all active customers | `all roles` | ✅ |
| **GET** | `/api/customers/{id}` | View a specific customer | `all roles` | ✅ |
| **POST** | `/api/customers` | Create a new customer | `admin, writer` | ✅ |
| **PUT** | `/api/customers/{id}` | Update an existing customer | `admin, writer` | ✅ |
| **DELETE** | `/api/customers/{id}` | Soft-delete a customer | `admin` | ✅ |

#### Purchase Orders Endpoints
| Method | Path | Action | Min. Role | Status |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/purchase-orders` | List all Purchase Orders | `all roles` | ✅ |
| **GET** | `/api/purchase-orders/{id}` | View a specific Purchase Order | `all roles` | ✅ |
| **POST** | `/api/purchase-orders` | Create a new Purchase Order | `admin, writer` | ✅ |
| **PUT** | `/api/purchase-orders/{id}` | Update header / line items | `admin, writer` | ✅ |
| **PATCH** | `/api/purchase-orders/{id}/receive` | Receive a delivery — increments product stock, supports partial delivery | `admin, writer` | ✅ |
| **DELETE** | `/api/purchase-orders/{id}` | Cancel the order | `admin` | ✅ |

#### Supplier Endpoints
| Method | Path | Action | Min. Role | Status |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/suppliers` | List all suppliers | `all roles` | ✅ |
| **GET** | `/api/suppliers/{id}` | View a specific supplier | `all roles` | ✅ |
| **POST** | `/api/suppliers` | Create a new supplier | `admin, writer` | ✅ |
| **PUT** | `/api/suppliers/{id}` | Update an existing supplier | `admin, writer` | ✅ |
| **DELETE** | `/api/suppliers/{id}` | Soft-delete a supplier | `admin` | ✅ |


> Note: If the application production compose is running, RBAC validation checks will fail because the bypass script is disabled in that environment.
  
- Welche Randfälle wurden berücksichtigt?
   - Foreign Key Constraint, Soft Deletes and Atomic Transactions.

### Wie wurde getestet?
- Manuell getestet:
  - Each entity's web page (Products, Orders, Purchase Orders, Suppliers, Customers, Warehouse Groups) exercised through the browser UI: create/update/delete flows, search, filter, column sorting and "load more", plus RBAC behaviour per role (admin/writer/viewer).
- Automatisiert getestet:
  - Github Action pipeline triggers the php artisan tests on every pr on main and dev and runs api unit tests

### Testen des Projekts
1. Directly through Github Actions (manually triggering), click on the Laravel CI Pipeline and click on Run Workflow and start the workflow on the main branch, or
2. Running tests locally bu running: `docker exec -it phpmylager_app php artisan test` and not running on the production compose, or
3. By reacting a pr on the main/dev branch

## Users
| Email | Role | Passwort |
| --- | --- | --- |
| admin@example.com | admin | password set in the ENV file |
| writer@example.com | writer | password set in the ENV file |
| viewer@example.com | viewer | password set in the ENV file |

## Bekannte Einschränkungen
- Welche Punkte sind noch offen?
   - **Session-based API:** The API is authenticated via the session cookie (no Sanctum/token layer) there is no stateless token API for external clients.
   - **No standalone product group deletion:** Warehouse-group deletion is intentionally disabled to protect referential integrity (products reference their group).
   - **Database-backed cache:** Caching uses the database store (no Redis). Only the default, unfiltered list view is cached; any search/filter/sort runs live, and writes flush the affected domain.

- Welche Einschränkungen hat das Projekt?
   - **Pagination:** List pages use offset-based "load more" rather than cursor pagination.

- Was würde man in einer nächsten Version verbessern?
   - **Next version:** Implement Kubernetes orchestration for seamless cloud deployment. Add a token-authenticated API endpoint to allow automated, remote order placement from external sources.

## Contributors
<!--
| Name | Matrikel number |
| --- | --- |
| name | matrikel no |
| name | matrikel no |
-->
