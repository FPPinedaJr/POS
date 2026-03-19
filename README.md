# SMILE POS / Inventory System

A PHP + MySQL **POS (Point of Sale)** and **Inventory Management** web app designed to run locally on **XAMPP** (or any PHP server), with a modern Tailwind UI, item image uploads, receivables, reports, and PDF export.

> Main entrypoint: `public/` (browse `http://localhost/POS/public/` on XAMPP)

---

## Features

- **Authentication**
  - Email/password login & registration
  - **Google OAuth** login (creates user on first login)
  - Password change for email/password accounts
- **Portal dashboard**
  - “Start selling” → POS
  - “Manage inventory” → Inventory
  - “View reports” → Reports/Analytics
- **Inventory management**
  - Create/update/delete items
  - Category add/update/soft-delete (per user)
  - Image upload with auto-generated thumbnail/preview
  - Stock thresholds + low stock alerts in header notification panel
  - Item history log (audit trail)
- **POS terminal**
  - Product list/search and item selection modal
  - Checkout flow (cash / GCash / receivable)
  - Stock deduction with row locking to avoid double-selling
  - Today’s transactions list + receivables list
  - Void transactions (restores stock)
  - QR scanning support (client-side) for quick item lookup
- **Reports**
  - Sales report (cash / GCash / credit split)
  - Income report
  - Receivables report
  - Inventory report
  - Date range filtering
  - **Export to PDF** via Dompdf

---

## Tech stack

- **Backend**: PHP (PDO MySQL), session-based auth
- **Database**: MySQL / MariaDB
- **Frontend**: Tailwind CSS, jQuery, Font Awesome
- **Composer libraries**
  - `google/apiclient` (Google OAuth)
  - `intervention/image` (image processing)
  - `dompdf/dompdf` (PDF export)
- **Node tooling**
  - Tailwind CLI watch/build (`@tailwindcss/cli`)

---

## Project structure

High-level layout:

```
POS/
├─ composer.json              # Composer deps; vendor-dir is public/vendor
├─ package.json               # Tailwind scripts
├─ public/
│  ├─ index.php               # Login/registration (email + Google)
│  ├─ portal.php              # App dashboard
│  ├─ pos.php                 # POS terminal
│  ├─ inventory.php           # Inventory management
│  ├─ reports.php             # Reports/analytics UI
│  ├─ add_item.php            # Add item form (legacy/alternate flow)
│  ├─ .env                    # Runtime config (DB + Google OAuth)
│  ├─ assets/                 # CSS/JS/images/fontawesome
│  ├─ storage/uploads/        # Item images (originals/thumbs/previews)
│  ├─ vendor/                 # Composer vendor (generated)
│  └─ includes/               # Backend endpoints + shared PHP helpers
└─ node_modules/              # Node deps (generated)
```

---

## Pages (routes)

All pages are under `public/`:

- **`index.php`**: Login + registration UI (AJAX POST to auth endpoints). Redirects to `portal.php` if already logged in.
- **`portal.php`**: Main hub (launch POS / Inventory / Reports).
- **`inventory.php`**: Inventory list, filters, item CRUD modals, category CRUD UI, history sidebar, low-stock notifications.
- **`pos.php`**: POS terminal (product list/search, cart/checkout, today’s transactions, receivables, void flow, QR scanning UI).
- **`reports.php`**: Reports tabs (sales/income/receivables/inventory) with date filtering + export.
- **`add_item.php`**: Standalone add-item page (legacy/alternate entry).

---

## Backend endpoints (API)

All endpoints live in `public/includes/`. Most return JSON and require an active session (`$_SESSION['user_id']`), except login/register/OAuth callback.

### Auth & session

- **POST `includes/login.php`**: Email/password login (JSON response)
- **POST `includes/register.php`**: Register account (JSON response)
- **GET `includes/gClienAuth.php`**: Google OAuth bootstrap + callback handler (`?code=...`)
- **GET `includes/logout.php`**: Logout (clears session; Google token handling)
- **POST `includes/update_password.php`**: Change password (email/password users)

### Inventory CRUD

- **POST `includes/save_item.php`**: Create item (multipart/form-data + optional image)
- **POST `includes/update_item.php`**: Update item (multipart/form-data + optional image)
- **POST `includes/delete_item.php`**: Delete item + related history + image cleanup
- **GET/POST `includes/category_crud.php`**: Category CRUD via `action`:
  - `action=add` (create)
  - `action=update` (rename)
  - `action=delete` (soft-delete via `is_deleted=1`)

### POS & transactions

- **GET `includes/ajax_pos_items.php`**: Fetch items for POS (for syncing/refresh)
- **GET `includes/ajax_transactions.php`**: Fetch transactions/receivables (supports query modes)
- **POST `includes/save_transaction.php`**: Create transaction (JSON body: cart + payment flags)
- **POST `includes/void_transaction.php`**: Void transaction; restore stock + history log
- **POST `includes/pay_receivable.php`**: Mark credit transaction as settled/paid

### Reports data fetch

- **POST `includes/fetch_sales.php`**: Sales report (date range)
- **POST `includes/fetch_income.php`**: Income report (date range)
- **POST `includes/fetch_receivables.php`**: Receivables report (date range)
- **GET `includes/fetch_inventory.php`**: Inventory snapshot report
- **GET `includes/fetch_dashboard_overview.php`**: Dashboard metrics (summary widgets)
- **GET `includes/fetch_history.php`**: Item history feed (pagination parameters)

### Export

- **GET `includes/export_pdf.php`**: PDF export
  - Params: `report=sales|income|receivables|inventory`
  - `start_date=YYYY-MM-DD` / `end_date=YYYY-MM-DD` (where applicable)

---

## Environment configuration (`public/.env`)

`public/includes/connect_db.php` loads the environment file from `public/.env` and configures the PDO connection.

**Expected keys:**

- **Database**
  - `DB_HOST`
  - `DB_PORT` (default `3306`)
  - `DB_NAME`
  - `DB_USER`
  - `DB_PASS`
  - `DB_CHARSET` (default `utf8mb4`)
- **Google OAuth**
  - `IS_DEV` (`1` uses DEV credentials; otherwise PROD)
  - `DEV_GOOGLE_CLIENT_ID`
  - `DEV_GOOGLE_CLIENT_SECRET`
  - `DEV_GOOGLE_REDIRECT_URI`
  - `PROD_GOOGLE_CLIENT_ID`
  - `PROD_GOOGLE_CLIENT_SECRET`
  - `PROD_GOOGLE_REDIRECT_URI`

**Important security note**

- Treat `.env` as **secret** (DB credentials + OAuth secrets).
- Do not commit it to Git.
- Ensure your web server is configured to **deny direct access** to `.env` files.

---

## Database schema (inferred from code)

This repo does not ship a `.sql` dump; below is the schema implied by the SQL queries used across `public/includes/`.

### Tables used

- **`users`**
  - `id` (PK)
  - `google_id` (nullable)
  - `name`
  - `email`
  - `password` (nullable for Google accounts)
  - `picture` (URL)

- **`category`**
  - `category_id` (PK)
  - `user_id` (FK → users.id)
  - `category_name`
  - `is_deleted` (0/1)

- **`item`**
  - `item_id` (PK)
  - `category_id` (FK → category.category_id)
  - `user_id` (FK → users.id)
  - `item_name`
  - `unit`
  - `value`
  - `retail_price`
  - `wholesale_price`
  - `stock_threshold`
  - `current_stock`
  - `image_basename` (nullable)
  - `image_thumb_path` (nullable)
  - `image_preview_path` (nullable)

- **`item_history`**
  - `history_uuid` (PK or unique)
  - `transaction_uuid` (nullable; links to transaction_header.transaction_uuid)
  - `item_id` (FK → item.item_id)
  - `item_count` (note: used as **absolute stock after change** in many places)
  - `description`
  - `created_at` (timestamp/datetime)

- **`transaction_header`**
  - `transaction_uuid` (PK)
  - `transaction_number` (human readable like `TRX-XXXXXX`)
  - `customer`
  - `total_amount`
  - `is_unpaid` (credit/receivable)
  - `is_gcash`
  - `void_date` (nullable)
  - `settle_date` (nullable)
  - `user_id` (FK → users.id)
  - `created_at`

- **`transaction_item`**
  - `item_uuid` (PK or unique)
  - `transaction_uuid` (FK → transaction_header.transaction_uuid)
  - `item_id` (FK → item.item_id)
  - `quantity`
  - `unit_price_at_sale`
  - `is_wholesale`

### Seed behavior

On successful registration / first Google login, default categories are inserted for the user:

- `Supplies`, `Equipment`, `Furniture`

---

## Local setup (XAMPP on Windows)

### 1) Requirements

- PHP (XAMPP)
  - **PDO MySQL** enabled
  - **GD or Imagick** enabled (required for image resizing)
  - `fileinfo` extension enabled (MIME detection)
- MySQL / MariaDB
- Node.js + npm (for Tailwind builds)
- Composer

### 2) Place the project

Put the repository in:

```
C:\xampp\htdocs\POS
```

Then browse:

- `http://localhost/POS/public/`

### 3) Install PHP dependencies (Composer)

From the repo root:

```bash
composer install
```

This project configures Composer to output vendor packages into `public/vendor/`.

### 4) Install frontend tooling (Tailwind)

From the repo root:

```bash
npm install
npm run dev
```

`npm run dev` watches:

- `public/assets/css/input.css` → builds to `public/assets/css/output.css`

### 5) Configure `.env`

Create/update:

- `public/.env`

Set DB credentials and Google OAuth credentials (see “Environment configuration”).

### 6) Create DB tables

Create the database and tables matching the schema above. (If you want, I can generate a ready-to-import `schema.sql` based on the queries in this repo.)

---

## Uploads & storage

- Upload directory: `public/storage/uploads/`
  - `originals/`
  - `thumbs/`
  - `previews/`
- Upload restrictions (enforced in `ImageService.php`):
  - Max file size: **5MB**
  - Allowed MIME: JPEG / PNG / GIF / WebP

Make sure XAMPP/PHP has write permission to `public/storage/uploads/`.

---

## UI/Theming notes

The header applies page-based theme accents:

- POS: teal
- Inventory: indigo
- Reports: fuchsia

Inventory also shows a **low-stock notification bell** (items where `current_stock <= stock_threshold` and `stock_threshold > 0`).

---

## Troubleshooting

- **Blank page / 500 errors**
  - Check Apache + PHP error logs in XAMPP.
  - Verify `public/.env` exists and has correct DB settings.
- **Images not uploading / resizing fails**
  - Enable **GD** or **Imagick** in `php.ini`.
  - Ensure `fileinfo` extension is enabled.
  - Confirm `public/storage/uploads/` is writable.
- **Google login redirect mismatch**
  - Ensure `DEV_GOOGLE_REDIRECT_URI` matches your local URL exactly (including `/POS/public/index.php`).
- **Styles look unstyled**
  - Run `npm install` and `npm run dev` to generate `public/assets/css/output.css`.

---

## License

See `LICENSE`.
