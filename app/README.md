# PhpMyLager: Setup & API Documentation

## 1. Initial Setup

Before launching the application, you must configure local environment variables to ensure the system is secure and correctly connected to database.

1.  **Create .env file:** Copy the provided example template to create active configuration file: `cp .env.example .env`
2.  **Update Variables:** Open the `.env` file and set your desired credentials. 
    > **⚠️ Warning:** If you skip this step, the system will use the default password `"password"`.

---

## 2. Database Management

Once Docker containers are running, use the following commands to manage database schema and data from host terminal.

| Goal | Command | Description |
| :--- | :--- | :--- |
| **First-Time Setup** | `docker exec -it phpmylager_app php artisan migrate:fresh --seed` | Wipes the database, recreates the structure, and populates it with default seed data. |
| **Update Schema** | `docker exec -it phpmylager_app php artisan migrate` | Applies new migrations only. Keeps existing data intact while making structural changes. |
| **Complete Reset** | `docker exec -it phpmylager_app php artisan migrate:fresh` | Drops all tables and re-runs migrations from scratch. Leaves the database entirely empty. |

---

## 3. API Reference

All API routes are protected. Access is granted based on the user's assigned role (`admin`, `writer`, or `viewer`).

### Product Endpoints

| Method | Path | Action | Route Name |
| :--- | :--- | :--- | :--- |
| **GET** | `/api/products` | List all products | `products.index` |
| **GET** | `/api/products/{id}` | View a specific product | `products.show` |
| **POST** | `/api/products` | Create a new product | `products.store` |
| **PUT** | `/api/products/{id}` | Update a product (Full) | `products.update` |
| **DELETE** | `/api/products/{id}` | Delete a product | `products.destroy` |

---

## 4. Testing & Debugging

The application includes a specialized debug bypass for local development, allowing you to test different roles without a manual login flow.

### Requirements
* **Environment:** This bypass only works when `APP_ENV` is set to `local`. It is strictly disabled in `production`.

### Example Commands

* **Request as Default Admin:**
  If no role is specified, the system defaults to an admin context.
  ```bash
  http GET http://localhost:8000/api/products
  ```

* **Request as a Specific Role:**
  To test permissions for a specific role (e.g., `writer` or `viewer`), pass the `X-Debug-Role` header:
  ```bash
  http POST http://localhost:8000/api/products \
    X-Debug-Role:writer \
    bezeichnung="Gaming Monitor" \
    fWgNr:=1 \
    ekPreis:=150.00 \
    vkPreis:=299.99 \
    bestand:=50 \
    meldeBest:=10
  ```