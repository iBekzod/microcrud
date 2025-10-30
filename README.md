<p align="center">
  <img src="https://banners.beyondco.de/MicroCRUD.png?theme=light&packageManager=composer+require&packageName=ibekzod%2Fmicrocrud&pattern=architect&style=style_1&description=A+powerful+Laravel+package+for+building+RESTful+APIs+with+advanced+CRUD+operations&md=1&showWatermark=0&fontSize=100px&images=code" alt="MicroCRUD Banner">
</p>

<p align="center">
  <a href="https://packagist.org/packages/ibekzod/microcrud"><img src="https://img.shields.io/packagist/dt/ibekzod/microcrud" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/ibekzod/microcrud"><img src="https://img.shields.io/packagist/v/ibekzod/microcrud" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/ibekzod/microcrud"><img src="https://img.shields.io/packagist/l/ibekzod/microcrud" alt="License"></a>
  <a href="https://packagist.org/packages/ibekzod/microcrud"><img src="https://img.shields.io/packagist/php-v/ibekzod/microcrud" alt="PHP Version"></a>
</p>

# MicroCRUD

**MicroCRUD** is a comprehensive Laravel package that eliminates boilerplate code and accelerates API development. Build production-ready RESTful APIs in minutes with advanced features like type-aware filtering, intelligent caching, queue support, and automatic validation.

## Why MicroCRUD?

Stop writing the same CRUD logic over and over. MicroCRUD provides:

- ⚡ **Rapid Development** - Create full CRUD APIs with just 3 classes
- 🎯 **Type-Aware Filtering** - Automatic search filters based on database column types
- 🔍 **Advanced Querying** - Range filters, dynamic sorting, soft deletes, pagination
- ✅ **Auto Validation** - Generate validation rules from database schema
- 💾 **Smart Caching** - Tag-based cache with automatic invalidation
- 🚀 **Queue Support** - Background processing for heavy operations
- 🌐 **Multi-Database** - MySQL, PostgreSQL, SQLite, SQL Server
- 🌍 **i18n Ready** - Multi-language support out of the box
- 📦 **Bulk Operations** - Process multiple records efficiently
- 🎨 **Highly Extensible** - Hooks, events, and customization points

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Architecture](#architecture)
- [Core Concepts](#core-concepts)
- [Features](#features)
  - [Dynamic Filtering](#dynamic-filtering)
  - [Validation System](#validation-system)
  - [Caching](#caching)
  - [Queue Jobs](#queue-jobs)
  - [Bulk Operations](#bulk-operations)
  - [Soft Deletes](#soft-deletes)
  - [Hooks & Events](#hooks--events)
- [API Documentation](#api-documentation)
- [Advanced Usage](#advanced-usage)
- [Middleware](#middleware)
- [Configuration](#configuration)
- [Examples](#examples)
- [Testing](#testing)
- [Contributing](#contributing)
- [Changelog](#changelog)
- [License](#license)

## Requirements

| Requirement | Version |
|------------|---------|
| PHP | ^7.0 \| ^8.0 \| ^8.1 \| ^8.2 \| ^8.3 |
| Laravel | 5.2 - 12.x |

## Installation

Install the package via Composer:

```bash
composer require ibekzod/microcrud
```

The package will automatically register itself via Laravel's package discovery.

### Publish Assets (Optional)

Publish configuration files and translations:

```bash
php artisan vendor:publish --provider="Microcrud\MicrocrudServiceProvider"
```

This will create:
- `config/microcrud.php` - Package configuration
- `config/schema.php` - Multi-schema database configuration (PostgreSQL)
- `lang/vendor/microcrud/` - Translation files (en, ru, uz)

## Quick Start

Create a complete CRUD API in 3 steps:

### Step 1: Create Your Model

```php
<?php

namespace App\Models;

use Microcrud\Abstracts\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'category_id',
        'is_active'
    ];

    // Define relationships as usual
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
```

### Step 2: Create Your Service

```php
<?php

namespace App\Services;

use Microcrud\Abstracts\Service;
use App\Models\Product;

class ProductService extends Service
{
    protected $model = Product::class;

    // That's it! You now have full CRUD functionality
    // Optionally enable advanced features:
    // protected $enableCache = true;
    // protected $useJob = true;
}
```

### Step 3: Create Your Controller

```php
<?php

namespace App\Http\Controllers;

use Microcrud\Http\CrudController;
use App\Services\ProductService;

class ProductController extends CrudController
{
    protected $service = ProductService::class;

    // Optionally override specific methods for custom logic
}
```

### Step 4: Register Routes

**Option 1: Use Route Macros** (Recommended - all POST endpoints):

```php
// routes/api.php

// Single resource
Route::microcrud('products', ProductController::class);

// Multiple resources
Route::microcruds([
    'products' => ProductController::class,
    'categories' => CategoryController::class,
    'orders' => OrderController::class,
]);
```

This creates 7 POST endpoints:
- `POST /products/create` → create()
- `POST /products/update` → update()
- `POST /products/show` → show()
- `POST /products/index` → index()
- `POST /products/delete` → delete()
- `POST /products/restore` → restore()
- `POST /products/bulk-action` → bulkAction()

**Option 2: RESTful Routes** (Standard Laravel):

```php
// routes/api.php
Route::apiResource('products', ProductController::class);
Route::post('products/{id}/restore', [ProductController::class, 'restore']);
Route::post('products/bulk', [ProductController::class, 'bulkAction']);
```

**That's it!** You now have a fully functional API with:
- ✅ List with pagination and filtering
- ✅ Create with validation
- ✅ Read single item
- ✅ Update with validation
- ✅ Delete (soft/hard)
- ✅ Restore soft-deleted items
- ✅ Bulk operations

## Architecture

MicroCRUD follows the **Service-Repository-Controller** pattern with a focus on separation of concerns:

```
┌─────────────────────────────────────────────────────────┐
│                    HTTP Request                         │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│                  Controller Layer                        │
│  • CrudController (Abstract)                            │
│  • ApiBaseController (Response Formatting)              │
│  • Your Controllers extend CrudController               │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│                  Service Layer                           │
│  • Service (Abstract) - Business Logic                  │
│  • Validation, Caching, Transactions                    │
│  • Query Building, Filtering                            │
│  • Job Dispatching                                      │
│  • Before/After Hooks                                   │
└────────────────────┬────────────────────────────────────┘
                     │
                     ├──────────────┬──────────────┬───────┐
                     ▼              ▼              ▼       ▼
              ┌──────────┐   ┌──────────┐   ┌────────┐  ┌────────┐
              │  Model   │   │  Cache   │   │  Jobs  │  │ Events │
              │  Layer   │   │  Layer   │   │ Layer  │  │ Layer  │
              └────┬─────┘   └──────────┘   └────────┘  └────────┘
                   │
                   ▼
              ┌──────────┐
              │ Database │
              └──────────┘
```

### Component Breakdown

| Component | Purpose | File Location |
|-----------|---------|---------------|
| **Model** | Eloquent ORM models | `Microcrud\Abstracts\Model` |
| **Service** | Business logic & operations | `Microcrud\Abstracts\Service` |
| **Controller** | HTTP handling & routing | `Microcrud\Http\CrudController` |
| **Resource** | API response transformation | `Microcrud\Responses\ItemResource` |
| **Middleware** | Request preprocessing | `Microcrud\Middlewares\*` |
| **Jobs** | Background processing | `Microcrud\Abstracts\Jobs\*` |
| **Exceptions** | Error handling | `Microcrud\Abstracts\Exceptions\*` |

## Core Concepts

### Services

Services contain all business logic. The base `Service` class provides:

```php
// Core CRUD operations
$service->index($data);        // List with filters
$service->show($id);           // Get single item
$service->create($data);       // Create new item
$service->update($id, $data);  // Update existing item
$service->delete($id);         // Delete (soft/hard)
$service->restore($id);        // Restore soft-deleted

// Bulk operations
$service->bulkCreate($items);
$service->bulkUpdate($items);
$service->bulkDelete($ids);
$service->bulkRestore($ids);

// Query manipulation
$service->setQuery($query);
$service->getQuery();
$service->applyDynamicFilters($query, $data);

// Validation
$service->indexRules();
$service->createRules();
$service->updateRules();

// Cache control
$service->enableCache();
$service->clearCache();
```

### Controllers

Controllers handle HTTP requests and delegate to services:

```php
class CrudController extends ApiBaseController
{
    // All methods return formatted JSON responses:
    index()      → 200 OK (paginated list)
    show($id)    → 200 OK (single item)
    create()     → 201 Created
    update($id)  → 202 Accepted
    delete($id)  → 202 Accepted
    restore($id) → 202 Accepted
    bulkAction() → 202 Accepted
}
```

### Resources

Resources transform model data for API responses:

```php
class ItemResource extends JsonResource
{
    // Automatically:
    // - Formats dates (Y-m-d H:i:s)
    // - Converts _id fields to nested objects
    // - Handles relationships

    public function toArray($request)
    {
        return $this->forModel();
    }
}
```

## Route Macros

MicroCRUD provides convenient Route macros for registering CRUD resources:

```php
// Single resource
Route::microcrud('products', ProductController::class);

// Multiple resources
Route::microcruds([
    'products' => ProductController::class,
    'categories' => CategoryController::class,
]);
```

**With Middleware & Prefix:**

```php
Route::prefix('v1')->middleware(['auth:api'])->group(function () {
    Route::microcruds([
        'products' => ProductController::class,
        'orders' => OrderController::class,
    ]);
});
```

**Benefits:**
- ✅ 75% less code than manual route definitions
- ✅ Consistent pattern across all resources
- ✅ Works with middleware, prefixes, and versioning
- ✅ All POST endpoints (production-tested pattern)

---

## Features

### Dynamic Filtering

MicroCRUD automatically detects column types and provides intelligent filtering:

#### String Columns → LIKE Search
```http
GET /products?search_by_name=laptop
# SELECT * FROM products WHERE name LIKE '%laptop%'
```

#### Numeric Columns → Exact Match + Range
```http
GET /products?search_by_price=999
# SELECT * FROM products WHERE price = 999

GET /products?search_by_price_min=100&search_by_price_max=500
# SELECT * FROM products WHERE price >= 100 AND price <= 500

GET /products?search_by_stock_min=10
# SELECT * FROM products WHERE stock >= 10
```

#### Date Columns → Exact Match + Range
```http
GET /products?search_by_created_at=2025-01-15
# SELECT * FROM products WHERE DATE(created_at) = '2025-01-15'

GET /products?search_by_created_at_from=2025-01-01&search_by_created_at_to=2025-01-31
# SELECT * FROM products WHERE DATE(created_at) >= '2025-01-01'
#                           AND DATE(created_at) <= '2025-01-31'
```

#### Boolean Columns → Exact Match
```http
GET /products?search_by_is_active=1
# SELECT * FROM products WHERE is_active = 1
```

#### Dynamic Sorting
```http
GET /products?order_by_price=asc
GET /products?order_by_created_at=desc
GET /products?order_by_name=asc&order_by_price=desc
```

#### Pagination
```http
GET /products?page=2&limit=50
GET /products?is_all=1  # Get all without pagination
```

#### Combining Filters
```http
GET /products?search_by_name=laptop
    &search_by_price_min=500
    &search_by_price_max=2000
    &search_by_is_active=1
    &order_by_price=asc
    &page=1
    &limit=20
```

### Validation System

Validation rules are auto-generated from your database schema:

```php
class ProductService extends Service
{
    protected $model = Product::class;

    // Override to customize rules
    public function createRules($rules = [], $replace = false)
    {
        return parent::createRules([
            'name' => 'required|string|max:255|unique:products',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'is_active' => 'boolean',
        ], $replace);
    }

    public function updateRules($rules = [], $replace = false)
    {
        return parent::updateRules([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
        ], $replace);
    }
}
```

**Auto-Generated Rules Based on Column Types:**
- `string` → `sometimes|nullable`
- `integer` → `sometimes|integer`
- `numeric` → `sometimes|numeric`
- `date` → `sometimes|date`
- `boolean` → `sometimes|boolean`

Plus automatic range validation:
- Integer: `search_by_{column}_min`, `search_by_{column}_max`
- Date: `search_by_{column}_from`, `search_by_{column}_to`

### Caching

Enable intelligent caching with automatic invalidation:

```php
class ProductService extends Service
{
    protected $model = Product::class;
    protected $enableCache = true;
    protected $cacheExpiration = 3600; // seconds

    // Cache is automatically:
    // ✓ Created on read operations
    // ✓ Tagged by model name
    // ✓ Invalidated on create/update/delete
    // ✓ Scoped to query parameters
}
```

**Manual cache control:**
```php
$service->enableCache();
$service->disableCache();
$service->clearCache();
```

### Queue Jobs

Process heavy operations in the background:

```php
class ProductService extends Service
{
    protected $model = Product::class;
    protected $useJob = true;
    protected $queueName = 'products';

    // Now create/update operations are queued automatically
}
```

**Available Jobs:**
- `StoreJob` - Background creation
- `UpdateJob` - Background updates
- `DeleteJob` - Background deletion

**Job Features:**
- ✅ `ShouldQueue` - Async processing
- ✅ `ShouldBeUnique` - Prevent duplicates
- ✅ Failed job logging
- ✅ Configurable queue names

### Bulk Operations

Process multiple records efficiently:

```php
// Bulk Create
POST /products/bulk
{
  "action": "create",
  "data": [
    {"name": "Product 1", "price": 100},
    {"name": "Product 2", "price": 200},
    {"name": "Product 3", "price": 300}
  ]
}

// Bulk Update
POST /products/bulk
{
  "action": "update",
  "data": [
    {"id": 1, "price": 150},
    {"id": 2, "price": 250}
  ]
}

// Bulk Delete
POST /products/bulk
{
  "action": "delete",
  "ids": [1, 2, 3, 4, 5]
}

// Bulk Restore
POST /products/bulk
{
  "action": "restore",
  "ids": [1, 2, 3]
}

// Bulk Show
POST /products/bulk
{
  "action": "show",
  "ids": [1, 2, 3]
}
```

**Bulk Features:**
- Transaction support (all or nothing)
- Queue support for large batches
- Validation for each item
- Progress tracking

### Soft Deletes

Full soft delete support with easy restoration:

```http
# Soft delete (default)
DELETE /products/1

# Force delete (permanent)
DELETE /products/1?force_delete=true

# Restore soft-deleted
POST /products/1/restore

# List with soft-deleted items
GET /products?with_trashed=true

# List only soft-deleted items
GET /products?only_trashed=true
```

```php
// In your service
$service->delete($id);              // Soft delete
$service->delete($id, true);        // Force delete
$service->restore($id);             // Restore
```

### Hooks & Events

Add custom logic at any point in the lifecycle:

```php
class ProductService extends Service
{
    protected $model = Product::class;

    // Before hooks (can modify data)
    public function beforeCreate($data)
    {
        $data['sku'] = 'PRD-' . strtoupper(uniqid());
        $data['slug'] = Str::slug($data['name']);
        return $data;
    }

    public function beforeUpdate($id, $data)
    {
        Log::info("Updating product {$id}", $data);
        return $data;
    }

    // After hooks (receive created/updated item)
    public function afterCreate($item)
    {
        Cache::tags(['products'])->flush();
        event(new ProductCreated($item));
        return $item;
    }

    public function afterUpdate($item)
    {
        event(new ProductUpdated($item));
        return $item;
    }

    public function afterDelete($item)
    {
        Storage::deleteDirectory("products/{$item->id}");
        return $item;
    }

    public function afterRestore($item)
    {
        event(new ProductRestored($item));
        return $item;
    }

    public function afterIndex()
    {
        // Called after listing items
    }
}
```

**Available Hooks:**
- `beforeCreate($data)`
- `afterCreate($item)`
- `beforeUpdate($id, $data)`
- `afterUpdate($item)`
- `beforeDelete($id)`
- `afterDelete($item)`
- `beforeRestore($id)`
- `afterRestore($item)`
- `afterIndex()`

## API Documentation

### Standard Response Format

#### Success Response (Single Item)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Laptop",
    "price": 999.99,
    "stock": 15,
    "is_active": true,
    "created_at": "2025-01-15 10:30:00",
    "updated_at": "2025-01-15 10:30:00"
  }
}
```

#### Success Response (Paginated List)
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Laptop",
      "price": 999.99
    },
    {
      "id": 2,
      "name": "Mouse",
      "price": 29.99
    }
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 20,
    "to": 20,
    "total": 100
  }
}
```

#### Error Response (Validation)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "price": ["The price must be at least 0."]
  }
}
```

#### Error Response (Not Found)
```json
{
  "success": false,
  "message": "Resource not found"
}
```

### HTTP Status Codes

| Method | Endpoint | Success Status | Description |
|--------|----------|----------------|-------------|
| GET | `/products` | 200 OK | List products |
| GET | `/products/{id}` | 200 OK | Get single product |
| POST | `/products` | 201 Created | Create product |
| PUT | `/products/{id}` | 202 Accepted | Update product |
| DELETE | `/products/{id}` | 202 Accepted | Delete product |
| POST | `/products/{id}/restore` | 202 Accepted | Restore product |
| POST | `/products/bulk` | 202 Accepted | Bulk operation |

### Error Status Codes

| Status Code | Method | Description |
|-------------|--------|-------------|
| 400 | `errorBadRequest()` | Bad Request |
| 401 | `errorUnauthorized()` | Unauthorized |
| 403 | `errorForbidden()` | Forbidden |
| 404 | `errorNotFound()` | Not Found |
| 422 | `errorValidation()` | Validation Error |
| 500 | `error()` | Internal Server Error |

## Advanced Usage

### Custom Resources

Create custom transformations for your API responses:

```php
<?php

namespace App\Http\Resources;

use Microcrud\Responses\ItemResource;

class ProductResource extends ItemResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => [
                'amount' => $this->price,
                'formatted' => '$' . number_format($this->price, 2),
                'currency' => 'USD',
            ],
            'stock' => [
                'quantity' => $this->stock,
                'status' => $this->stock > 0 ? 'in_stock' : 'out_of_stock',
                'low_stock' => $this->stock < 10,
            ],
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'is_active' => (bool) $this->is_active,
            'timestamps' => [
                'created' => $this->created_at->toISOString(),
                'updated' => $this->updated_at->toISOString(),
            ],
        ];
    }
}
```

Use in controller:
```php
class ProductController extends CrudController
{
    protected $service = ProductService::class;
    protected $resource = ProductResource::class;
}
```

### Transaction Management

Transactions are enabled by default. Control them per service:

```php
class ProductService extends Service
{
    protected $model = Product::class;
    protected $useTransaction = true; // default

    // Or disable for specific operations
    public function create($data)
    {
        $this->setIsTransactionEnabled(false);
        return parent::create($data);
    }
}
```

### Custom Query Scopes

Add custom query modifications:

```php
class ProductService extends Service
{
    protected $model = Product::class;

    public function index($data)
    {
        // Apply custom query before processing
        $query = $this->model::query()
            ->with(['category', 'images'])
            ->where('is_active', true)
            ->whereHas('category', function($q) {
                $q->where('active', true);
            });

        $this->setQuery($query);

        return parent::index($data);
    }
}
```

### Preventing N+1 Queries

Eager load relationships to prevent N+1 queries:

```php
class ProductService extends Service
{
    protected $model = Product::class;

    public function index($data)
    {
        // Eager load relationships
        $query = $this->model::with([
            'category',
            'images',
            'reviews' => function($q) {
                $q->where('approved', true);
            }
        ]);

        $this->setQuery($query);
        return parent::index($data);
    }

    public function show($id)
    {
        $query = $this->model::with(['category', 'images', 'reviews']);
        $this->setQuery($query);
        return parent::show($id);
    }
}
```

### Without Global Scopes

Remove global scopes temporarily:

```php
$service->withoutScopes(['ActiveScope'])->index($data);
$service->withoutScopes()->index($data); // Remove all scopes
```

### Parent-Child Relationships

For hierarchical data like categories or organizational structures:

```php
use Microcrud\Traits\ParentChildTrait;

class Category extends Model
{
    use ParentChildTrait;

    protected $fillable = ['name', 'parent_id'];
}
```

Usage:
```php
$category = Category::find(1);

// Get direct children
$children = $category->children;

// Get all descendants (recursive)
$allDescendants = $category->allChildren;

// Get parent
$parent = $category->parent;

// Get all descendant IDs
$ids = $category->getAllDescendantIds(); // [2, 3, 4, 5, ...]

// Get full tree from root
$tree = Category::getRootWithChildren();
```

**Auto-cascade delete:**
```php
$category->delete(); // Automatically deletes all children
```

### HTTP Client Service

Make external API calls with the built-in HTTP client:

```php
use Microcrud\Services\Curl\Services\CurlService;

$curl = new CurlService();

// GET request
$curl->setUrl('https://api.example.com/users')
     ->setHeaders([
         'Authorization' => 'Bearer ' . $token,
         'Accept' => 'application/json',
     ])
     ->setParams(['page' => 1, 'limit' => 20]);

$response = $curl->get();

// POST request
$curl->setUrl('https://api.example.com/users')
     ->setParams([
         'name' => 'John Doe',
         'email' => 'john@example.com',
     ]);

$response = $curl->post();

// Other methods
$curl->put();
$curl->patch();
$curl->delete();
```

## Middleware

### LocaleMiddleware

Automatically sets application locale from `Accept-Language` header:

```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    'locale' => \Microcrud\Middlewares\LocaleMiddleware::class,
];

// routes/api.php
Route::middleware(['locale'])->group(function () {
    Route::apiResource('products', ProductController::class);
});
```

**Request:**
```http
GET /api/products
Accept-Language: ru
```

**Behavior:**
- Checks `Accept-Language` header
- Matches against configured locales (`config('microcrud.locales')`)
- Falls back to `config('microcrud.locale')`

### TimezoneMiddleware

Sets timezone from `Timezone` header:

```php
protected $routeMiddleware = [
    'timezone' => \Microcrud\Middlewares\TimezoneMiddleware::class,
];

Route::middleware(['timezone'])->group(function () {
    // Your routes
});
```

**Request:**
```http
GET /api/products
Timezone: America/New_York
```

**Default:** Uses `config('microcrud.timezone', 'UTC')`

### LogHttpRequest

Logs all HTTP requests with URL, headers, and parameters:

```php
protected $routeMiddleware = [
    'log.http' => \Microcrud\Middlewares\LogHttpRequest::class,
];

Route::middleware(['log.http'])->group(function () {
    // Your routes
});
```

Logs to Laravel's default log channel.

## Configuration

### config/microcrud.php

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | Specify a custom database connection. Leave empty to use default.
    |
    */
    'connection' => env('MICROCRUD_DB_CONNECTION', ''),

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Enable/disable authorization checks in controllers.
    |
    */
    'authorize' => env('MICROCRUD_AUTHORIZE', true),

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | List of locales supported by your application.
    |
    */
    'locales' => ['en', 'ru', 'uz'],

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | The default locale for your application.
    |
    */
    'locale' => env('MICROCRUD_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Default Timezone
    |--------------------------------------------------------------------------
    |
    | The default timezone used by TimezoneMiddleware.
    |
    */
    'timezone' => env('MICROCRUD_TIMEZONE', 'UTC'),
];
```

### config/schema.php

For PostgreSQL multi-schema support:

```php
<?php

return [
    'notification' => env('DB_NOTIFICATION_SCHEMA', 'public'),
    'upload' => env('DB_UPLOAD_SCHEMA', 'public'),
    'user' => env('DB_USER_SCHEMA', 'public'),
    // Add your schemas here
];
```

### Environment Variables

Add to your `.env`:

```env
# MicroCRUD Configuration
MICROCRUD_DB_CONNECTION=mysql
MICROCRUD_AUTHORIZE=true
MICROCRUD_LOCALE=en
MICROCRUD_TIMEZONE=UTC

# PostgreSQL Schemas (if needed)
DB_NOTIFICATION_SCHEMA=notifications
DB_UPLOAD_SCHEMA=uploads
DB_USER_SCHEMA=users
```

## Examples

### Complete E-commerce Product API

```php
// app/Models/Product.php
namespace App\Models;

use Microcrud\Abstracts\Model;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'sale_price',
        'sku', 'stock', 'category_id', 'brand_id', 'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}

// app/Services/ProductService.php
namespace App\Services;

use Microcrud\Abstracts\Service;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductService extends Service
{
    protected $model = Product::class;
    protected $enableCache = true;
    protected $cacheExpiration = 3600;
    protected $useTransaction = true;

    public function beforeCreate($data)
    {
        $data['slug'] = Str::slug($data['name']);
        $data['sku'] = 'PRD-' . strtoupper(uniqid());
        return $data;
    }

    public function afterCreate($item)
    {
        Cache::tags(['products'])->flush();
        event(new ProductCreated($item));
        return $item;
    }

    public function createRules($rules = [], $replace = false)
    {
        return parent::createRules([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0|lt:price',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'is_active' => 'boolean',
        ], $replace);
    }

    public function index($data)
    {
        $query = $this->model::with(['category', 'brand', 'images'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        $this->setQuery($query);
        return parent::index($data);
    }
}

// app/Http/Controllers/ProductController.php
namespace App\Http\Controllers;

use Microcrud\Http\CrudController;
use App\Services\ProductService;
use App\Http\Resources\ProductResource;

class ProductController extends CrudController
{
    protected $service = ProductService::class;
    protected $resource = ProductResource::class;
}

// app/Http/Resources/ProductResource.php
namespace App\Http\Resources;

use Microcrud\Responses\ItemResource;

class ProductResource extends ItemResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'pricing' => [
                'regular' => $this->price,
                'sale' => $this->sale_price,
                'discount' => $this->sale_price
                    ? round((($this->price - $this->sale_price) / $this->price) * 100)
                    : 0,
            ],
            'inventory' => [
                'sku' => $this->sku,
                'stock' => $this->stock,
                'in_stock' => $this->stock > 0,
            ],
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'rating' => [
                'average' => round($this->reviews_avg_rating ?? 0, 1),
                'count' => $this->reviews_count ?? 0,
            ],
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### API Usage Examples

```bash
# List products with filters
curl -X GET "http://api.example.com/products?search_by_name=laptop&search_by_price_min=500&search_by_price_max=2000&order_by_price=asc&page=1&limit=20"

# Get single product
curl -X GET "http://api.example.com/products/1"

# Create product
curl -X POST "http://api.example.com/products" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Gaming Laptop",
    "description": "High-performance gaming laptop",
    "price": 1299.99,
    "stock": 50,
    "category_id": 5,
    "is_active": true
  }'

# Update product
curl -X PUT "http://api.example.com/products/1" \
  -H "Content-Type: application/json" \
  -d '{
    "price": 1199.99,
    "sale_price": 999.99
  }'

# Delete product (soft)
curl -X DELETE "http://api.example.com/products/1"

# Force delete
curl -X DELETE "http://api.example.com/products/1?force_delete=true"

# Restore product
curl -X POST "http://api.example.com/products/1/restore"

# Bulk create
curl -X POST "http://api.example.com/products/bulk" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create",
    "data": [
      {"name": "Product 1", "price": 99.99, "stock": 10, "category_id": 1},
      {"name": "Product 2", "price": 199.99, "stock": 5, "category_id": 1}
    ]
  }'
```

## Testing

While the package doesn't include a test suite, here's how to test your implementations:

```php
// tests/Feature/ProductApiTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Category;

class ProductApiTest extends TestCase
{
    public function test_can_list_products()
    {
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => ['id', 'name', 'price']
                     ],
                     'meta'
                 ]);
    }

    public function test_can_create_product()
    {
        $category = Category::factory()->create();

        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
            'category_id' => $category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('products', ['name' => 'Test Product']);
    }

    public function test_can_filter_products_by_price()
    {
        Product::factory()->create(['price' => 50]);
        Product::factory()->create(['price' => 150]);
        Product::factory()->create(['price' => 250]);

        $response = $this->getJson('/api/products?search_by_price_min=100&search_by_price_max=200');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
```

## Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup

```bash
git clone https://github.com/ibekzod/microcrud.git
cd microcrud
composer install
```

### Coding Standards

- Follow PSR-12 coding standards
- Write descriptive commit messages
- Add tests for new features
- Update documentation

## Changelog

### [Latest] - 2025-01-30

#### Added
- ✨ **Route Macros** - `Route::microcrud()` and `Route::microcruds()` for easy resource registration
- ✨ **Enhanced Exceptions** - Rich error context with toArray()/toJson() methods
- ✨ **Improved Middlewares** - Better security, logging, and validation
- 📚 **Comprehensive Documentation** - Enhanced code documentation throughout

#### Improved
- ⚡ **Better Error Handling** - ValidationException, CreateException, UpdateException, DeleteException, NotFoundException
- 📝 **Controller Documentation** - Full PHPDoc for all methods
- 🎨 **Code Quality** - Better structure, logging, and maintainability
- 🔒 **Security** - Sensitive data filtering in LogHttpRequest middleware

### Previous Releases

- **Type-aware dynamic search filters** - min/max for numeric, from/to for dates
- **DeleteJob** - Background deletion operations
- **Configurable timezone** - Via `config('microcrud.timezone')`
- **DYNAMIC search_by_column & order_by_column** - Added dynamic filtering
- **Bulk actions** - Implemented bulk operations

## Security

If you discover any security-related issues, please email erkinovbegzod.45@gmail.com instead of using the issue tracker.

## Credits

- **Author:** [iBekzod](https://github.com/ibekzod)
- **Email:** erkinovbegzod.45@gmail.com
- **Package:** [ibekzod/microcrud](https://packagist.org/packages/ibekzod/microcrud)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/ibekzod">iBekzod</a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/ibekzod/microcrud">Packagist</a> •
  <a href="https://github.com/ibekzod/microcrud">GitHub</a> •
  <a href="https://github.com/ibekzod/microcrud/issues">Issues</a>
</p>
