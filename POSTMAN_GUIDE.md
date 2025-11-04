# Postman Collection Auto-Generation Guide

## 📦 Package Used

-   **yasin_tgh/laravel-postman** v1.3
-   Documentation: https://github.com/yasintqvi/laravel-postman

## ✨ Features Enabled

### 1. **Auto Request Body Generation** ✅

The package automatically extracts validation rules from FormRequest classes and generates sample request bodies.

**How it works:**

-   Package reads `rules()` method from FormRequest
-   Automatically generates example values based on validation rules:
    -   `email` → `user12@example.com`
    -   `numeric` → Random number within min/max
    -   `string` → `"field_name sample value"`
    -   `boolean` → `true` or `false`

## 📊 Auto-Detected Query Parameters

The package has been **extended with automatic query parameter detection**! 🎉

### How It Works

The custom `QueryParameterExtractor` service automatically detects query parameters by:

1. **Pagination Detection**: Scans controller methods for `->paginate()`, `->simplePaginate()`, or `->cursorPaginate()` calls
2. **Query Parameter Detection**: Finds `$request->query()`, `$request->get()`, or `$request->input()` calls in GET methods
3. **Auto-Generation**: Adds them to the Postman collection with proper structure

### Example Auto-Detected Parameters

#### ✅ Pagination (ProductController::index)

```php
public function index()
{
    $products = Product::query()->paginate(10);  // ← Automatically detected!
    return response()->json($products);
}
```

**Generated Query Params:**

-   `page=1` - Page number for pagination
-   `per_page=10` - Items per page

#### ✅ Request Input (TenantBackupController::download)

```php
public function download($tenantId, Request $request)
{
    $filename = $request->input('file');  // ← Automatically detected!
    // ...
}
```

**Generated Query Param:**

-   `file=` - File (empty default value)

### Auto-Detected Endpoints

The following GET endpoints automatically have query parameters:

1. **GET /api/products**

    - ✅ `page=1`
    - ✅ `per_page=10`

2. **GET /api/central/tenants**

    - ✅ `page=1` (if pagination is used)
    - ✅ `per_page=10` (if pagination is used)

3. **GET /api/central/tenants/{id}/backups/download**
    - ✅ `file=` (detected from `$request->input('file')`)

### Technical Implementation

**Files Created:**

-   `app/Services/QueryParameterExtractor.php` - Scans controllers for pagination and query params
-   `app/Services/ExtendedRouteGrouper.php` - Extends package's RouteGrouper with query param support
-   `app/Providers/PostmanExtensionServiceProvider.php` - Registers custom services

**How to Use:**

```bash
php artisan postman:generate
```

That's it! Query parameters are automatically detected and added. ✨

### Adding Custom Query Parameters

If you want to add more query parameters manually, just use them in your GET controllers:

```php
public function index(Request $request)
{
    $search = $request->query('search');      // ← Will be detected
    $sortBy = $request->get('sort_by');       // ← Will be detected
    $order = $request->input('order');        // ← Will be detected

    $results = Model::query()
        ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
        ->orderBy($sortBy ?? 'id', $order ?? 'asc')
        ->paginate(10);                         // ← Pagination detected

    return response()->json($results);
}
```

Then regenerate:

```bash
php artisan postman:generate
```

Query params will include: `page`, `per_page`, `search`, `sort_by`, `order`

---

## 🔐 Authentication & Headers

-   **Bearer Token**: Auto-added to protected routes (routes with `auth:sanctum` middleware)
-   **API Keys**: Added to all requests
    -   `X-Tenant-API-Key` for tenant routes
    -   `X-Master-API-Key` for central routes

### 3. **Collection Variables** ✅

-   `{{base_url}}` - Your app URL
-   `{{auth_token}}` - Bearer token (set after login)
-   Environment variables in .env:
    -   `POSTMAN_AUTH_TOKEN`
    -   `POSTMAN_TENANT_API_KEY`
    -   `POSTMAN_MASTER_API_KEY`

### 4. **Smart Folder Organization** ✅

Routes are automatically grouped by **controller** for clean, efficient structure:

#### 📁 Collection Structure:

```
Laravel Multi-Tenant API
├── ⚙️ System Routes (debug, info - closure routes)
├── 🏢 Tenant Management (CRUD, health check, test connection)
├── 💾 Backup & Restore (backup, restore, download, stats)
├── 🔐 Authentication (register, login, logout, me)
└── 📦 Products (CRUD operations)
```

**Before** (deeply nested):

```
central/
  debug/
    tenants/
      [GET] api/central/debug/tenants
  tenants/
    [POST] api/central/tenants
    ...
```

**After** (flat, efficient):

```
🏢 Tenant Management/
  [POST] api/central/tenants
  [GET] api/central/tenants
  [GET] api/central/tenants/{id}
  ...
```

## 🚀 Usage

### Generate Collection

```bash
php artisan postman:generate
```

Output: `storage/postman/api_collection`

### Import to Postman

1. Open Postman
2. Click **Import** button
3. Select `storage/postman/api_collection`
4. Done! ✅

## 📝 How to Add Auto Body Generation to New Routes

### Step 1: Create FormRequest

```bash
php artisan make:request YourRequest
```

### Step 2: Define Validation Rules

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class YourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ⚠️ IMPORTANT: Set to true
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'required|numeric|min:18|max:100',
            'is_active' => 'boolean',
        ];
    }
}
```

### Step 3: Use in Controller

```php
use App\Http\Requests\YourRequest;

public function store(YourRequest $request)
{
    // Package will auto-detect this and generate body
    $data = $request->validated();

    // Your logic...
}
```

### Step 4: Regenerate

```bash
php artisan postman:generate
```

**Auto-generated body will be:**

```json
{
    "name": "name sample value",
    "email": "user42@example.com",
    "age": 25,
    "is_active": true
}
```

## ⚙️ Configuration

Edit `config/postman.php`:

### Filter Routes

```php
'routes' => [
    'prefix' => 'api',
    'include' => [
        'middleware' => ['api', 'tenant'], // Only include routes with these middleware
    ],
    'exclude' => [
        'patterns' => ['api/_ignition/*'], // Exclude debug routes
    ],
],
```

### Authentication

```php
'auth' => [
    'enabled' => true,
    'type' => 'bearer',
    'protected_middleware' => ['auth:sanctum', 'auth:api', 'tenant.token'],
],
```

### Headers

```php
'headers' => [
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
    'X-Tenant-API-Key' => '',
    'X-Master-API-Key' => env('MASTER_API_KEY'),
],
```

## 📊 Example Generated Requests

### POST /api/register

```json
{
    "name": "name sample value",
    "email": "user47@example.com",
    "password": "password",
    "password_confirmation": "password",
    "role": "role sample value"
}
```

### POST /api/central/tenants

```json
{
    "name": "name sample value",
    "db_host": "db_host sample value",
    "db_port": 3306,
    "db_name": "db_name sample value",
    "db_username": "db_username sample value",
    "db_password": "db_password sample value"
}
```

### POST /api/products

```json
{
    "name": "name sample value",
    "description": "description sample value",
    "price": 50
}
```

## 🎯 Best Practices

1. **Always use FormRequests** instead of `$request->validate()` for auto-generation
2. **Set `authorize()` to `true`** in FormRequests
3. **Use descriptive validation rules** for better sample data
4. **Regenerate after adding new routes** with `php artisan postman:generate`
5. **Commit the collection** to git for team collaboration

## 🔧 Troubleshooting

### Body not generated?

-   ✅ Check if you're using FormRequest (not `Validator::make()`)
-   ✅ Ensure `authorize()` returns `true`
-   ✅ Verify route is not excluded in config

### Headers not showing?

-   ✅ Check `config/postman.php` headers section
-   ✅ Ensure routes match the middleware filters

### Authentication not working?

-   ✅ Set `POSTMAN_AUTH_TOKEN` in `.env` after login
-   ✅ Check `protected_middleware` in config matches your routes

## 📚 Advanced Features

### Custom Body Type

```php
'structure' => [
    'requests' => [
        'default_body_type' => 'raw', // or 'formdata'
    ]
],
```

### Organization Strategy

```php
'structure' => [
    'folders' => [
        'strategy' => 'nested_path', // 'prefix', 'nested_path', or 'controller'
        'max_depth' => 5,
    ],
],
```

### Custom Naming

```php
'structure' => [
    'naming_format' => '[{method}] {uri}', // Placeholders: {method}, {uri}, {controller}, {action}
],
```

## 🎉 Summary

You now have:

-   ✅ Automatic request body generation from validation rules
-   ✅ Bearer token authentication on protected routes
-   ✅ API key headers (tenant + master)
-   ✅ Collection variables for easy configuration
-   ✅ Sample data based on your validation rules

Just run `php artisan postman:generate` after any route changes!
