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

## 3. Response Envelope
Every API response follows the same predictable shape. Frontend clients only need one error-handling path.

#### Success
```json
{ "data": { ... }, "message": "Optional human-readable confirmation." }
```

The `data` key holds the resource or array of resources. `message` is generally present on mutating operations (create, update, delete) or specific success states.

#### No Content
HTTP 204 with an empty body. Returned by all standard `DELETE` endpoints (except when soft-canceling or specifically returning a message).

#### Validation Error — 422 Unprocessable Entity
Returned when request fields fail validation rules or a business-logic constraint is violated (e.g., insufficient stock, invalid lagerplatz format).
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Specific error message."]
  }
}
```
#### Other Errors
All non-validation errors return a single `message` key with the appropriate HTTP status.

| Status | Meaning |
| :--- | :--- |
| 403 Forbidden | Authenticated but your role lacks permission.| 
| 404 Not Found | Resource does not exist. |
| 409 Conflict | Constraint violation, e.g., deleting a product still referenced by an order. |
| 500 Internal Server Error | Unexpected server-side failure. |

---

## 4. API Reference

All API routes are protected. Access is granted based on the user's assigned role (`admin`, `writer`, or `viewer`).

---

### Product Endpoints (Produkte)

| Method | Path | Action | Min. Role |
| :--- | :--- | :--- | :--- |
| **GET** | `/api/products` | List all active products | `viewer` |
| **GET** | `/api/products/{id}` | View a specific product | `viewer` |
| **GET** | `/api/products/{id}/stock-history` | View audit trail of manual stock adjustments | `viewer` |
| **POST** | `/api/products` | Create a new product | `writer` |
| **PUT** | `/api/products/{id}` | Update a product (stock cannot be updated here) | `writer` |
| **PATCH** | `/api/products/{id}/adjust-stock` | Manually adjust physical stock with a required reason | `admin` |
| **DELETE** | `/api/products/{id}` | Soft-delete a product | `admin` |


#### GET `/api/products`
Returns all active (non-deleted) products. The "has_stock_history" allows for an api request GET `/api/products/{id}/stock-history` for more detailed stock history change.

```json
{
  "data": [
    {
      "pArtikelNr": 10005,
      "bezeichnung": "Lupe 90mm",
      "fWgNr": 4,
      "ekPreis": 5,
      "vkPreis": 9,
      "bestand": 1010,
      "meldeBest": 400,
      "lagerplatz": "D01-12B",
      "has_stock_history": false
    },
    {
      "pArtikelNr": 10028,
      "bezeichnung": "Pruefschraubendreher-Set",
      "fWgNr": 2,
      "ekPreis": 13,
      "vkPreis": 25,
      "bestand": 680,
      "meldeBest": 210,
      "lagerplatz": "B04-02C",
      "has_stock_history": false
    },
    ...
  ],
  "message": "Products retrieved successfully."
}
```

#### GET `/api/products/{id}`
```json
{
  "data": {
    "pArtikelNr": 1,
    "bezeichnung": "Gaming Monitor",
    "fWgNr": 2,
    "ekPreis": 150.00,
    "vkPreis": 299.99,
    "bestand": 50,
    "meldeBest": 10,
    "lagerplatz": "A12-03B",
    "has_stock_history": false
  }
}
```

#### GET `/api/products/{id}/stock-history`
```json
{
  "data": [
    {
      "id": 1,
      "fArtikelNr": 1,
      "user_id": 2,
      "old_bestand": 50,
      "new_bestand": 45,
      "reason": "Damaged items removed from shelf.",
      "created_at": "2026-05-22T17:45:00.000000Z",
      "user": {
        "id": 2,
        "name": "Admin User"
      }
    }
  ]
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
  "meldeBest": 10,
  "lagerplatz": "A12-03B"
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
    "meldeBest": 10,
    "lagerplatz": "A12-03B"
  },
  "message": "Product created successfully."
}
```

#### PUT `/api/products/{id}`
**All fields are optional** only included fields are updated.
**Request body (partial update example):**
```json
{
  "vkPreis": 349.99,
  "lagerplatz": "B04-01A"
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
    "bestand": 50,
    "meldeBest": 10,
    "lagerplatz": "B04-01A"
  },
  "message": "Product updated successfully."
}
```

#### PATCH `/api/products/{id}/adjust-stock`
Manually override the physical stock level. This generates an immutable log entry.

**Request body:**
```json
{
  "bestand": 45,
  "reason": "Damaged items removed from shelf."
}
```
**Response:**
```json
{
  "data": {
    "pArtikelNr": 1,
    "bestand": 45,
    "...": "..."
  },
  "message": "Product stock level manually adjusted successfully."
}
```

#### DELETE `/api/products/{id}`
Soft-deletes the product. The record is retained in the database and still appears on any orders it belongs to (see `is_discontinued` in order responses).

**Success Response:**
```json
{ "message": "Product ID: 1 deleted successfully" }
```

---

### Warehouse Group Endpoints (Warengruppe)

| Method | Path | Action | Min. Role |
| :--- | :--- | :--- | :--- |
| **GET** | `/api/warehouse-groups` | List all warehouse groups | `viewer` |
| **GET** | `/api/warehouse-groups/{id}` | View a specific warehouse group | `viewer` |
| **GET** | `/api/warehouse-groups/{id}/products` | List all products to a specific warehouse group | `viewer` |
| **POST** | `/api/warehouse-groups` | Create a new warehouse group | `writer` |
| **PUT** | `/api/warehouse-groups/{id}` | Update a warehouse group name | `writer` |

#### GET `/api/warehouse-groups`
```json
{
  "data": [
    {
      "pWgNr": 1,
      "warengruppe": "Electronics"
    },
    ...
  ]
}
```

#### GET `/api/warehouse-groups/{id}/products`
```json
{
  "data": [
    {
      "pArtikelNr": 10056,
      "bezeichnung": "Isolier-Abstreifzaengleinchen",
      "fWgNr": 1,
      "ekPreis": 14,
      "vkPreis": 20,
      "bestand": 2400,
      "meldeBest": 250,
      "lagerplatz": "A01-03B"
    },
    {
      "pArtikelNr": 10057,
      "bezeichnung": "Adernendhuelsen-Zaengle",
      "fWgNr": 1,
      "ekPreis": 17,
      "vkPreis": 31,
      "bestand": 1750,
      "meldeBest": 220,
      "lagerplatz": "A01-04C"
    },
    ...
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
  "data": {
    "pWgNr": 3,
    "warengruppe": "Office Supplies"
  },
  "message": "Warehouse group created successfully."
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
  "data": {
    "pWgNr": 3,
    "warengruppe": "Office & Stationery"
  },
  "message": "Warehouse group updated successfully"
}
```

---

### Customer Endpoints (Kunden)

| Method | Path | Action | Min. Role |
| :--- | :--- | :--- | :--- |
| **GET** | `/api/customers` | List all active customers | `all roles` |
| **GET** | `/api/customers/{id}` | View a specific customer | `all roles` |
| **POST** | `/api/customers` | Create a new customer | `admin, writer` |
| **PUT** | `/api/customers/{id}` | Update an existing customer | `admin, writer` |
| **DELETE** | `/api/customers/{id}` | Soft-delete a customer | `admin` |



| Field | Type | Description |
| :--- | :--- | :--- |
| `data` | object | Wrapper object returned by the controller |
| `data.pKdNr` | integer | Customer primary key |
| `data.name` | string | Customer name |
| `data.strasse` | string | Street address |
| `data.plz` | integer | Postal code (validated as 5 digits) |
| `data.ort` | string | City |
| `data.email` | string | Email address |

#### GET `/api/customers`
Returns all active (non-deleted) customers.

```json
{
  "data": [
    {
      "pKdNr": 24001,
      "name": "Baumarkt Mueller",
      "strasse": "Postfach 134",
      "plz": 85579,
      "ort": "Neubiberg",
      "email": "mueller@baumarkt.de"
    },
    {
      "pKdNr": 24002,
      "name": "Friedrich Kunst",
      "strasse": "Mausweg 24",
      "plz": 72510,
      "ort": "Stetten a.k.M.",
      "email": "friedrich.kunst@mail.de"
    },
    ...
  ]
}
```

#### GET `/api/customers/{id}`
```json
{
  {
    "pKdNr": 24002,
    "name": "Friedrich Kunst",
    "strasse": "Mausweg 24",
    "plz": 72510,
    "ort": "Stetten a.k.M.",
    "email": "friedrich.kunst@mail.de"
  }
}
```

#### POST `/api/customers` — 201 Created
**Request body:**
```json
{
  "name": "Test Kunde",
  "strasse": "Musterstrasse 1",
  "plz": "80331",
  "ort": "Muenchen",
  "email": "testkunde@example.com"
}
```

**Response:**
```json
{
  "data": {
    "pKdNr": 24015,
    "name": "Test Kunde",
    "strasse": "Musterstrasse 1",
    "plz": 80331,
    "ort": "Muenchen",
    "email": "testkunde@example.com"
  },
  "message": "Customer created successfully."
}
```

#### PUT `/api/customers/{id}`
**Request body:**
```json
{
  "name": "Test Kunde Updated",
  "strasse": "Musterstrasse 2",
  "plz": "80331",
  "ort": "Muenchen",
  "email": "testkunde.updated@example.com"
}
```

**Response:**
```json
{
  "data": {
    "pKdNr": 24015,
    "name": "Test Kunde Updated",
    "strasse": "Musterstrasse 2",
    "plz": 80331,
    "ort": "Muenchen",
    "email": "testkunde.updated@example.com"
  },
  "message": "Customer updated successfully."
}
```

#### DELETE `/api/customers/{id}` — 204 No Content
Soft-deletes the customer. Returns an empty body.

---

### Supplier Endpoints (Lieferanten)

Suppliers represent the wholesale vendors and manufacturers that fulfill your inbound purchase orders. 

| Method | Path | Action | Min. Role |
| :--- | :--- | :--- | :--- |
| **GET** | `/api/suppliers` | List all registered suppliers | `viewer` |
| **GET** | `/api/suppliers/{id}` | View a specific supplier profile | `viewer` |
| **POST** | `/api/suppliers` | Register a new supplier | `writer` |
| **PUT** | `/api/suppliers/{id}` | Update supplier contact details | `writer` |
| **DELETE** | `/api/suppliers/{id}` | Hard-delete a supplier | `admin` |

#### GET `/api/suppliers`
Returns all active suppliers in the system.

```json
{
  "data": [
    {
      "pLiefNr": 5001,
      "name": "Remscheid Werkzeuge GmbH",
      "strasse": "Industriepark Nord 4",
      "plz": 42853,
      "ort": "Remscheid",
      "email": "vertrieb@remscheid-tools.de",
    },
    {
      "pLiefNr": 5002,
      "name": "Sheffield Steel Co.",
      "strasse": "22 Ironworks Lane",
      "plz": 54321,
      "ort": "Sheffield",
      "email": "orders@sheffieldsteel.co.uk",
    }
  ]
}
```

#### POST `/api/suppliers` — 201 Created
Registers a new supplier profile.

**Request body:**
```json
{
  "name": "Alpen Werkzeuge Import S.A.",
  "strasse": "Rue du Commerce 77",
  "plz": "10050",
  "ort": "Lausanne",
  "email": "info@alpenimport.ch",
}
```

**Response:**
```json
{
  "data": {
    "pLiefNr": 5003,
    "name": "Alpen Werkzeuge Import S.A.",
    "strasse": "Rue du Commerce 77",
    "plz": 10050,
    "ort": "Lausanne",
    "email": "info@alpenimport.ch",
  },
  "message": "Supplier created successfully."
}
```

#### PUT `/api/suppliers/{id}` — 200 OK
Updates an existing supplier's details.

**Request body:**
```json
{
  "name": "Alpen Werkzeuge Import S.A.",
  "strasse": "Rue du Commerce 77",
  "plz": "10050",
  "ort": "Lausanne",
  "email": "new-contact@alpenimport.ch",
}
```

**Response:**
```json
{
  "data": {
    "pLiefNr": 5003,
    "name": "Alpen Werkzeuge Import S.A.",
    "strasse": "Rue du Commerce 77",
    "plz": 10050,
    "ort": "Lausanne",
    "email": "new-contact@alpenimport.ch",
  },
  "message": "Supplier updated successfully."
}
```

#### DELETE `/api/suppliers/{id}` — 204 No Content
Hard-deletes the supplier.

**Success Response:**
HTTP `204 No Content` (Empty body).

---

### Outbound Order Endpoints (Kunden Bestellungen)

Outbound orders **decrease** the warehouse stock levels when created.

| Method | Path | Action | Min. Role |
| :--- | :--- | :--- | :--- |
| **GET** | `/api/orders` | List all orders | `viewer` |
| **GET** | `/api/orders/{id}` | View a specific order | `viewer` |
| **POST** | `/api/orders` | Create a new order | `writer` |
| **PUT** | `/api/orders/{id}` | Update an order (requires full item list) | `writer` |
| **DELETE** | `/api/orders/{id}` | Delete an order (restores stock) | `admin` |

#### Order Response Shape
All order responses share the same shape. The `items` array always reflects the full current state of the order.

needs to be improved
| Field | Type | Description |
| :--- | :--- | :--- |
| `order_info.pAufNr` | integer | Order primary key |
| `order_info.aufDat` | string (date) | Order date |
| `order_info.aufTermin` | string (date) | Requested delivery date |
| `order_info.fKdNr` | integer | Customer FK |
| `order_info.customer_name` | string | Name of the customer |
| `order_info.is_customer_deleted` | bool | If the customer has been soft deleted and is still displayed in the order |
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
{
  "data": [
    {
      "order_info": {
        "pAufNr": 22334,
        "aufDat": "2009-01-26 00:00:00",
        "aufTermin": "2009-02-18 00:00:00",
        "fKdNr": 24001,
        "customer_name": "Otto",
        "is_customer_deleted": true,
      },
      "items": [
        {
          "pAufPosNr": 1,
          "fArtikelNr": 10004,
          "bezeichnung": "Handlupe 90mm",
          "aufMenge": 20,
          "kaufPreis": 18,
          "line_total": 360,
          "is_discontinued": true
        },
        ...
      ],
      "order_total": 23,
      "preis_total": 366
    },
    ...
  ]
}
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

**Response:**
```json
{
  "data": {
    "order_info": {
      "pAufNr": 5,
      "aufDat": "2026-04-17 00:00:00",
      "aufTermin": "2026-04-25 00:00:00",
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
      },
      {
        "pAufPosNr": 13,
        "fArtikelNr": 51,
        "bezeichnung": "Ergonomic Keyboard",
        "aufMenge": 2,
        "kaufPreis": 49.99,
        "line_total": 99.98,
        "is_discontinued": false
      }
    ],
    "order_total": 7,
    "preis_total": 1599.93
  },
  "message": "Order created successfully."
}
```

#### PUT `/api/orders/{id}` — 200 OK
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

**Response:**
```json
{
  "data": {
    "order_info": {
      "pAufNr": 5,
      "aufDat": "2026-04-17 00:00:00",
      "aufTermin": "2026-04-30 00:00:00",
      "fKdNr": 101
    },
    "items": [
      {
        "pAufPosNr": 12,
        "fArtikelNr": 50,
        "bezeichnung": "Gaming Monitor",
        "aufMenge": 3,
        "kaufPreis": 299.99,
        "line_total": 899.97,
        "is_discontinued": false
      },
      {
        "pAufPosNr": 14,
        "fArtikelNr": 52,
        "bezeichnung": "Wireless Mouse",
        "aufMenge": 1,
        "kaufPreis": 25.00,
        "line_total": 25.00,
        "is_discontinued": false
      }
    ],
    "order_total": 4,
    "preis_total": 924.97
  },
  "message": "Order updated successfully."
}
```

* **Update:** Include `pAufPosNr` to update an existing line-item (quantity diff is applied to stock).
* **Add:** Omit `pAufPosNr` to add a new line-item (full quantity is deducted from stock).
* **Delete:** Omit a line-item entirely to delete it (full quantity is restored to stock).
* **Restriction:** Changing `fArtikelNr` on an existing `pAufPosNr` is not permitted—remove and re-add instead.


#### DELETE `/api/orders/{id}` — 204 No Content
Restores stock for every line-item before deleting the order and all its positions. Returns an empty body.

---

### Inbound Purchase Order Endpoints (Lieferanten Bestellungen)

Purchase orders manage stock restocking. Stock is **only increased** when a delivery is officially received.

| Method | Path | Action | Min. Role |
| :--- | :--- | :--- | :--- |
| **GET** | `/api/purchase-orders` | List all supplier orders | `viewer` |
| **GET** | `/api/purchase-orders/{id}` | View a specific supplier order | `viewer` |
| **POST** | `/api/purchase-orders` | Draft a new purchase order | `writer` |
| **PUT** | `/api/purchase-orders/{id}` | Update draft/sent order | `writer` |
| **PATCH** | `/api/purchase-orders/{id}/receive`| Register delivery & increase stock | `writer` |
| **DELETE** | `/api/purchase-orders/{id}` | Cancel order (mark as 'storniert') | `admin` |

#### Purchase Order Response Shape
All supplier procurement responses share this unified metadata tracking design structure. 

| Field | Type | Description |
| :--- | :--- | :--- |
| `order_info.pBestNr` | integer | Purchase order primary key tracking identifier (`pBestNr` from table `bestellkoepfe`). |
| `order_info.fLiefNr` | integer\|null | Supplier foreign key reference identity row identifier (`lieferanten.pLiefNr`). |
| `order_info.lieferant` | string\|null | Vendor company title name resolved from active supplier relations. |
| `order_info.is_supplier_deleted` | bool | If the Vendor has been soft deleted or not. |
| `order_info.bestDat` | string (datetime) | Timestamp designating precisely when the procurement request was sent. |
| `order_info.erwLieferDat` | string (datetime)\|null| Expected warehouse receiving dock arrival deadline submitted by shipping carrier. |
| `order_info.status` | string | Active pipeline workflow state: `'offen'`, `'bestellt'`, `'geliefert'`, or `'storniert'`. |
| `items[].pBestPosNr` | integer | Unique line item primary key reference position (`pBestPosNr` from table `bestellpositionen`). |
| `items[].fArtikelNr` | integer | Target product catalog link mapping reference identifier (`artikel.pArtikelNr`). |
| `items[].bezeichnung` | string\|null | Product name description text string resolved at retrieval time. |
| `items[].lagerplatz` | string\|null | Physical storage zone assignment target code defined for warehouse organization. |
| `items[].bestMenge` | integer | Total physical units originally ordered from the processing wholesale vendor. |
| `items[].gelieferteMenge` | integer | Count of item units arrived and checked into stock (supports split-shipments). |
| `items[].ekPreis` | float\|null | Agreed purchasing cost value index per unit item (`ekPreis` from table `bestellpositionen`). |
| `items[].line_total` | float | Calculated gross row valuation subtotal (`ekPreis` × `bestMenge`), rounded to 2 decimals. |
| `total_ordered` | integer | Accumulated total unit counts requested across all combined item positions. |
| `total_delivered` | integer | Cumulative unit count checked into inventory. |
| `total_value` | float | Total procurement financial liability value calculated by adding all `line_total` sums. |

#### GET `/api/purchase-orders`
```json
{
  "data": [
    {
      "order_info": {
        "pBestNr": 80001,
        "fLiefNr": 5001,
        "lieferant": "Remscheid Werkzeuge GmbH",
        "is_supplier_deleted": false,
        "bestDat": "2026-05-01 10:00:00",
        "erwLieferDat": "2026-05-08 14:00:00",
        "status": "bestellt"
      },
      "items": [
        {
          "pBestPosNr": 101,
          "fArtikelNr": 10059,
          "bezeichnung": "Schraubendreher-Set",
          "lagerplatz": "B05-01D",
          "bestMenge": 100,
          "gelieferteMenge": 0,
          "ekPreis": 11.00,
          "line_total": 1100.00
        }
      ],
      "total_ordered": 100,
      "total_delivered": 0,
      "total_value": 1100.00
    }
  ]
}
```

#### POST `/api/purchase-orders` — 201 Created
Drafts a new PO (status = `offen`). Stock is untouched.

**Request body:**
```json
{
  "fLiefNr": 5001,
  "bestDat": "2026-05-01",
  "erwLieferDat": "2026-05-08",
  "items": [
    { "fArtikelNr": 10059, "bestMenge": 100, "ekPreis": 11.00 }
  ]
}
```

#### PATCH `/api/purchase-orders/{id}/receive`
Marks goods as received (supports partial deliveries). **This is the action that actively increments your `artikel.bestand` in the database.**
Once `gelieferteMenge` meets `bestMenge` for all items, the order status auto-promotes to `geliefert`.

**Request body:**
```json
{
  "items": [
    { "pBestPosNr": 101, "gelieferteMenge": 50 }
  ]
}
```

#### DELETE `/api/purchase-orders/{id}`
Cancels the order. This is a soft-cancel that updates the `status` to `storniert` rather than a hard DB delete.

**Response:**
```json
{ "message": "Purchase order 80001 cancelled." }
```

---

## 5. Testing & Debugging

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
