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

# API Documentation

## 3. API Reference

All API routes are protected. Access is granted based on the user's assigned role (`admin`, `writer`, or `viewer`).

---

### Product Endpoints

| Method | Path | Action | Min. Role |
| :--- | :--- | :--- | :--- |
| **GET** | `/api/products` | List all active products | `viewer` |
| **GET** | `/api/products/{id}` | View a specific product | `viewer` |
| **POST** | `/api/products` | Create a new product | `writer` |
| **PUT** | `/api/products/{id}` | Update a product | `writer` |
| **DELETE** | `/api/products/{id}` | Soft-delete a product | `admin` |

#### GET `/api/products`
Returns all active (non-deleted) products.

```json
{
  "products": [
    {
      "pArtikelNr": 1,
      "bezeichnung": "Gaming Monitor",
      "fWgNr": 2,
      "ekPreis": 150.00,
      "vkPreis": 299.99,
      "bestand": 50,
      "meldeBest": 10
    }
  ]
}
```

#### GET `/api/products/{id}`
```json
{
  "productsById": {
    "pArtikelNr": 1,
    "bezeichnung": "Gaming Monitor",
    "fWgNr": 2,
    "ekPreis": 150.00,
    "vkPreis": 299.99,
    "bestand": 50,
    "meldeBest": 10
  }
}
```

#### POST `/api/products` — 201 Created
**Request body:**
```json
{
  "bezeichnung": "Gaming Monitor",
  "fWgNr": 2,
  "ekPreis": 150.00,
  "vkPreis": 299.99,
  "bestand": 50,
  "meldeBest": 10
}
```
**Response:**
```json
{
  "data": {
    "pArtikelNr": 1,
    "bezeichnung": "Gaming Monitor",
    "fWgNr": 2,
    "ekPreis": 150.00,
    "vkPreis": 299.99,
    "bestand": 50,
    "meldeBest": 10
  },
  "message": "Product created successfully"
}
```

#### PUT `/api/products/{id}`
**All fields are optional** only included fields are updated.
**Request body (partial update example):**
```json
{
  "vkPreis": 349.99,
  "bestand": 45
}
```
**Response:**
```json
{
  "data": {
    "pArtikelNr": 1,
    "bezeichnung": "Gaming Monitor",
    "fWgNr": 2,
    "ekPreis": 150.00,
    "vkPreis": 349.99,
    "bestand": 45,
    "meldeBest": 10
  },
  "message": "Product updated successfully"
}
```

#### DELETE `/api/products/{id}`
Soft-deletes the product. The record is retained in the database and still appears on any orders it belongs to (see `is_discontinued` in order responses).

**Success Response:**
```json
{ "message": "Product ID: 1 deleted successfully" }
```

**Error — product is referenced by an order (409 Conflict):**
```json
{ "error": "This product cannot be deleted because it is used in one or more orders." }
```

---

### Warehouse Group Endpoints

| Method | Path | Action | Min. Role |
| :--- | :--- | :--- | :--- |
| **GET** | `/api/warehouse-groups` | List all warehouse groups | `viewer` |
| **GET** | `/api/warehouse-groups/{id}` | View a specific warehouse group | `viewer` |
| **POST** | `/api/warehouse-groups` | Create a new warehouse group | `writer` |
| **PUT** | `/api/warehouse-groups/{id}` | Update a warehouse group name | `writer` |

#### GET `/api/warehouse-groups`
```json
{
  "warehouse_groups": [
    {
      "pWgNr": 1,
      "warengruppe": "Electronics"
    },
    {
      "pWgNr": 2,
      "warengruppe": "Peripherals"
    }
  ]
}
```

#### POST `/api/warehouse-groups` — 201 Created
**Request body:**
```json
{ "warengruppe": "Office Supplies" }
```
**Response:**
```json
{
  "message": "Warehouse group created successfully",
  "data": {
    "pWgNr": 3,
    "warengruppe": "Office Supplies"
  }
}
```

#### PUT `/api/warehouse-groups/{id}`
**Request body:**
```json
{ "warengruppe": "Office & Stationery" }
```
**Response:**
```json
{
  "message": "Warehouse group updated successfully",
  "data": {
    "pWgNr": 3,
    "warengruppe": "Office & Stationery"
  }
}
```

---

### Order Endpoints

| Method | Path | Action | Min. Role |
| :--- | :--- | :--- | :--- |
| **GET** | `/api/orders` | List all orders | `viewer` |
| **GET** | `/api/orders/{id}` | View a specific order | `viewer` |
| **POST** | `/api/orders` | Create a new order | `writer` |
| **PUT** | `/api/orders/{id}` | Update an order (requires full item list) | `writer` |
| **DELETE** | `/api/orders/{id}` | Delete an order (restores stock) | `admin` |

#### Order Response Shape
All order responses share the same shape. The `items` array always reflects the full current state of the order.

| Field | Type | Description |
| :--- | :--- | :--- |
| `order_info.pAufNr` | integer | Order primary key |
| `order_info.aufDat` | string (date) | Order date |
| `order_info.aufTermin` | string (date) | Requested delivery date |
| `order_info.fKdNr` | integer | Customer FK |
| `items[].pAufPosNr` | integer | Line-item primary key |
| `items[].fArtikelNr` | integer | Product FK |
| `items[].bezeichnung` | string\|null | Product name at time of response |
| `items[].aufMenge` | integer | Ordered quantity |
| `items[].kaufPreis` | float | Price snapshotted at time of order |
| `items[].line_total` | float | `kaufPreis` × `aufMenge`, rounded to 2 decimals |
| `items[].is_discontinued` | boolean | `true` if the product has since been soft-deleted |
| `order_total` | integer | Sum of all `aufMenge` values |
| `preis_total` | float | Sum of all `line_total` values |

#### GET `/api/orders`
```json
[
  {
    "order_info": {
      "pAufNr": 5,
      "aufDat": "2026-04-17",
      "aufTermin": "2026-04-25",
      "fKdNr": 101
    },
    "items": [
      {
        "pAufPosNr": 12,
        "fArtikelNr": 50,
        "bezeichnung": "Gaming Monitor",
        "aufMenge": 5,
        "kaufPreis": 299.99,
        "line_total": 1499.95,
        "is_discontinued": false
      }
    ],
    "order_total": 5,
    "preis_total": 1499.95
  }
]
```

#### POST `/api/orders` — 201 Created
Stock is decremented for each item. The `kaufPreis` is snapshotted from the product's current `vkPreis` at creation time.

**Request body:**
```json
{
  "aufDat": "2026-04-17",
  "fKdNr": 101,
  "aufTermin": "2026-04-25",
  "items": [
    { "fArtikelNr": 50, "aufMenge": 5 },
    { "fArtikelNr": 51, "aufMenge": 2 }
  ]
}
```

**Error — insufficient stock (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "items": [
      "Insufficient stock for product 50 (Gaming Monitor). Available: 3, requested: 5."
    ]
  }
}
```

#### PUT `/api/orders/{id}`
Operates on a **full-state principle**—the `items` array you send becomes the complete new state of the order. Any existing line-items not present in the request are deleted and their stock is restored.

**Request body:**
```json
{
  "aufDat": "2026-04-17",
  "fKdNr": 101,
  "aufTermin": "2026-04-30",
  "items": [
    { "pAufPosNr": 12, "fArtikelNr": 50, "aufMenge": 3 },
    { "fArtikelNr": 52, "aufMenge": 1 }
  ]
}
```
* **Update:** Include `pAufPosNr` to update an existing line-item (quantity diff is applied to stock).
* **Add:** Omit `pAufPosNr` to add a new line-item (full quantity is deducted from stock).
* **Delete:** Omit a line-item entirely to delete it (full quantity is restored to stock).
* **Restriction:** Changing `fArtikelNr` on an existing `pAufPosNr` is not permitted—remove and re-add instead.

#### DELETE `/api/orders/{id}` — 204 No Content
Restores stock for every line-item before deleting the order and all its positions. Returns an empty body.

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