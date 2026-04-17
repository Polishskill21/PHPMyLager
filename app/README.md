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
| **DELETE** | `/api/products/{id}` | Soft deletes a product | `products.destroy` |
<br>
| **GET** | `/api/warehouse-groups` | List all warehouse groups | `warehouse-groups.index` |
| **GET** | `/api/warehouse-groups/{id}` | View a specific warehouse group | `warehouse-groups.show` |
| **POST** | `/api/warehouse-groups` | Create a new warehouse group | `warehouse-groups.store` |
| **PUT** | `/api/warehouse-groups/{id}` | Update a warehouse group name | `warehouse-groups.update` |
<br>
| **GET** | `/api/orders` | List all orders | `orders.index` |
| **GET** | `/api/orders/{id}` | View a specific order | `orders.show` |
| **POST** | `/api/orders` | Create a new order | `orders.store` |
| **PUT** | `/api/orders/{id}` | Update a order (**Requires full item submission**) | `orders.update` |
| **DELETE** | `/api/orders/{id}` | Delete a order (Restores stock) | `orders.destroy` |
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

### Order Update Behavior (Important)
The `PUT /api/orders/{id}` endpoint operates on a **"Full State"** principle. This means the server expects the `items` array to represent the complete, intended state of the order.

* **Omitted Items:** Any existing order positions (`pAufPosNr`) currently in the database that are **not** included in the `items` array of your `PUT` request will be **automatically deleted**.
* **Stock Restoration:** When an item is automatically deleted via an update, its quantity is automatically credited back to the product's stock (`bestand`).
* **Item Identification:** To update an existing item, you must include its `pAufPosNr`. If you omit the ID but include the product, it will be treated as a new line-item (stock will be deducted again).
* **Immutability:** You cannot change the `fArtikelNr` (Product ID) of an existing position. To change the product of a line-item, you must delete the old position and add a new one.

### Example Commands

* **Updating an Order (Full Submission):**
    If you wish to keep item #1 but delete item #2, send only item #1:
    ```bash
    http PUT http://localhost:8000/api/orders/5 \
      aufDat="2026-04-17" \
      fKdNr:=101 \
      aufTermin="2026-04-25" \
      items:='[{"pAufPosNr": 1, "fArtikelNr": 50, "aufMenge": 5}]'
    ```