# Equipment Rental SaaS — Backend

A Laravel backend for a **multi-tenant equipment rental platform**. Multiple companies (tenants) use the same application and database, while each company's data remains isolated and protected.

## Tech Stack

* **Laravel 13**
* **PHP**
* **MySQL**
* **Laravel Sanctum** — API authentication
* **Eloquent ORM**
* **Laravel Queues** — background billing jobs
* **Laravel Notifications & Broadcasting**
* **PHPUnit** — automated testing

---

## Setup

### 1. Clone the repository

```bash
git clone https://github.com/muskan-fatima97/Equipment_Rental_Saas_App.git
cd Equipment_Rental_Saas_App
```

### 2. Install dependencies

```bash
composer install
```

### 3. Configure environment

Copy the example environment file:

```bash
cp .env.example .env
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Configure your MySQL database credentials in `.env`.

### 4. Generate application key

```bash
php artisan key:generate
```

### 5. Run migrations

```bash
php artisan migrate
```

### 6. Start the queue worker

The billing system uses queued jobs:

```bash
php artisan queue:work
```

### 7. Start the development server

```bash
php artisan serve
```

The API will then be available through the Laravel development server.

---

# Architecture Explanation

This project is a Laravel backend for a multi-tenant equipment rental platform. Multiple companies (tenants) share one application and one database, but their data must stay separate.

### Tenancy Approach

I chose a **shared database with a `tenant_id` column** on every tenant-owned table instead of using a separate database per tenant.

This approach is:

* Easier to operate and maintain
* Cheaper to run
* Simpler to migrate
* Easier to report across tenants when required

The trade-off is that tenant isolation depends on the application enforcing the correct boundaries.

To make this safer, every tenant-owned model uses a **global Eloquent scope** that automatically filters queries to the current tenant. Developers do not need to remember to add:

```php
where('tenant_id', ...)
```

to every query.

If the application cannot determine the current tenant, the scope **fails closed** and returns zero rows rather than exposing data from every tenant.

Cross-tenant access therefore requires explicitly removing the scope, making such operations visible during code review.

### User Model Exception

The `User` model does not use the automatic tenant scope.

Login happens before the application knows the tenant context. The system first finds the user and then resolves the tenant from the authenticated user's `tenant_id`.

If the `User` model were automatically scoped before authentication, login could not work because no tenant would be available yet.

User email addresses are therefore unique across the entire application.

---

# Booking Double-Booking Prevention

A simple availability check is not sufficient when two requests arrive at almost exactly the same time.

For example:

```text
Request A → Check availability → Available
Request B → Check availability → Available
Request A → Create booking
Request B → Create booking
```

Both requests could potentially pass the availability check before either booking is created.

To prevent this, booking creation runs inside a **database transaction** and locks the equipment row using:

```php
lockForUpdate()
```

The second request must wait until the first transaction completes before it can continue.

This means the second request sees the latest booking state and performs its availability check against current data.

---

# Booking States

Bookings follow a controlled state machine:

```text
pending → confirmed → active → completed → cancelled
```

Transitions must happen in the defined order.

Invalid transitions such as:

```text
pending → completed
completed → active
cancelled → confirmed
```

are rejected.

An invalid transition throws the custom:

```text
IllegalBookingTransitionException
```

This prevents invalid booking states from silently entering the system.

---

# Billing

When a booking is confirmed, a background queued job is dispatched to create its invoice.

The billing process:

1. Calculates the booking price
2. Uses the equipment's day rate
3. Calculates the number of rental days
4. Applies any applicable tenant discount
5. Creates an invoice

### Billing Failure Handling

If the billing job fails, Laravel retries the job.

After **3 failed attempts**, the tenant administrator is notified through:

* Database notification
* Broadcast/live notification

### Duplicate Invoice Protection

The billing job contains an application-level check to prevent an invoice from being created twice for the same booking.

There is also a **database-level unique constraint** on the invoices table.

Therefore, even if the application-level check is bypassed because of concurrent execution or an unexpected retry, the database itself rejects a duplicate invoice.

This provides two layers of protection:

```text
Application check
       +
Database unique constraint
       ↓
Duplicate billing prevented
```

---

# API

All API endpoints are organized under:

```text
/api/v1/...
```

API responses are returned through **Laravel API Resource classes** rather than exposing raw Eloquent models directly.

This keeps the API contract controlled and prevents database-specific fields from being unintentionally exposed.

---

# Rate Limiting

The default API rate limit is:

```text
60 requests per minute per tenant
```

The rate-limit configuration is stored on the tenant record, allowing different subscription tiers to have different limits.

For example:

```text
Basic      → 60 requests/minute
Premium    → Higher configurable limit
Enterprise → Higher configurable limit
```

This makes the rate limit tenant-aware rather than applying one hard-coded limit to the entire application.

---

# Roles & Permissions

The application supports three user roles:

| Role           | Permissions                         |
| -------------- | ----------------------------------- |
| `tenant_admin` | Full tenant access                  |
| `staff`        | Create, edit and delete tenant data |
| `read_only`    | View-only access                    |

### Cross-Tenant Protection

A user from Tenant A must never be able to access Tenant B's equipment, bookings, categories or other tenant-owned records.

This protection also applies when a user guesses another record's ID.

For example:

```text
GET /api/v1/equipment/123
```

If equipment `123` belongs to another tenant, the server does not allow the request to access that record.

Unauthorized cross-tenant access returns:

```text
403 Forbidden
```

rather than exposing the other tenant's data.

---

# Performance

The equipment listing endpoint is optimized to avoid the **N+1 query problem**.

The listing returns:

* Equipment
* Category
* Current booking status
* Average rating

while keeping the database query count at approximately **2 queries**, regardless of how many equipment records are returned.

### How

#### Current Booking Status

The current booking status is calculated using a subquery inside the main equipment query instead of executing a separate query for every equipment item.

#### Average Rating

Laravel's:

```php
withAvg()
```

is used to retrieve average ratings efficiently as part of the query rather than running a separate query for every equipment item.

#### Categories

Categories are loaded once for the complete equipment collection instead of querying the category separately for every equipment record.

### Before

With a naive implementation, query count could grow with the number of equipment records:

```text
1 equipment query
+ 1 category query per equipment
+ 1 rating query per equipment
```

For 100 equipment records, this could result in roughly:

```text
201 queries
```

### After

The optimized implementation keeps the query count flat:

```text
2 queries
```

even as the number of equipment records increases.

---

# Testing

Feature tests cover important business and security rules, including:

### Double Booking

Verifies that overlapping bookings for the same equipment cannot be created concurrently.

### Booking State Transitions

Verifies that illegal booking transitions throw:

```text
IllegalBookingTransitionException
```

### Tenant Isolation

Verifies that a user from one tenant cannot access another tenant's data, including when attempting to guess another record's ID.

---

# Scheduled Command

The application includes:

```bash
php artisan bookings:expire-pending
```

This command finds bookings that have remained in the `pending` state for more than **24 hours** and automatically cancels them.

The command is intended to run once every hour.

It also logs the number of bookings that were cancelled during each execution.

A scheduler/cron process can therefore run the command automatically in a production environment.

---

# Multi-Tenant Data Model

Tenant-owned data follows the tenant relationship:

```text
Tenant
 ├── Users
 ├── Categories
 ├── Equipment
 ├── Bookings
 └── Invoices
```

Tenant-owned records contain:

```text
tenant_id
```

which identifies the company that owns the record.

The global tenant scope ensures normal application queries automatically operate within the current tenant boundary.

---

# Security Principles

The project follows several defensive principles:

* **Tenant isolation by default**
* **Fail-closed tenant queries**
* **Explicit cross-tenant access**
* **Role-based authorization**
* **Database transactions for booking creation**
* **Row-level locking for concurrent bookings**
* **Database constraints for critical uniqueness rules**
* **API Resources instead of raw model responses**
* **Controlled booking state transitions**
* **Tenant-aware rate limiting**

The goal is to make the secure behavior the default rather than relying on developers to remember security checks throughout the application.

---

# Project Structure

The main application structure follows Laravel conventions:

```text
app/
├── Enums/
│   ├── BookingStatus.php
│   └── UserRole.php
│
├── Http/
│   ├── Controllers/
│   └── Middleware/
│
├── Models/
│   ├── Booking.php
│   ├── Category.php
│   ├── Equipment.php
│   ├── Tenant.php
│   └── User.php
│
├── Support/
│   └── TenantContext.php
│
└── Models/
    ├── Concerns/
    │   └── BelongsToTenant.php
    └── Scopes/
        └── TenantScope.php
```

Database migrations are located in:

```text
database/migrations/
```

and automated tests are located in:

```text
tests/
```

---

# API Versioning

The API is versioned under:

```text
/api/v1
```

This allows future API versions to be introduced without immediately breaking existing clients.

For example:

```text
/api/v1/equipment
/api/v1/categories
/api/v1/bookings
```

A future version could be introduced as:

```text
/api/v2/...
```

without forcing existing consumers to migrate immediately.

---

# Architecture Summary

The application combines several backend patterns to solve real-world SaaS problems:

```text
                 ┌────────────────────┐
                 │      API v1         │
                 └─────────┬──────────┘
                           │
                           ▼
                 ┌────────────────────┐
                 │ Authentication     │
                 │ & Authorization    │
                 └─────────┬──────────┘
                           │
                           ▼
                 ┌────────────────────┐
                 │ Tenant Context     │
                 └─────────┬──────────┘
                           │
                           ▼
                 ┌────────────────────┐
                 │ Global Tenant      │
                 │ Eloquent Scope     │
                 └─────────┬──────────┘
                           │
                           ▼
              ┌──────────────────────────┐
              │      Shared MySQL DB     │
              │                          │
              │ tenant_id-based isolation│
              └──────────────────────────┘

Booking Flow:

Request
   ↓
Transaction
   ↓
Lock Equipment Row
   ↓
Check Availability
   ↓
Create Booking
   ↓
Confirm Booking
   ↓
Queue Billing Job
   ↓
Create Invoice
```

---

# Architecture Explanation — Short Version

This project is a Laravel backend for a multi-tenant equipment rental platform. Multiple companies share one application and one database, but their data remains isolated.

**Tenancy:** The application uses a shared database with a `tenant_id` column on tenant-owned tables. A global Eloquent scope automatically restricts normal queries to the current tenant. If no tenant can be resolved, the scope fails closed and returns no rows. The `User` model is not automatically scoped because authentication must happen before tenant context can be established.

**Booking safety:** Booking creation runs inside a database transaction and locks the equipment row with `lockForUpdate()`. This prevents two concurrent requests from both passing an availability check and creating conflicting bookings.

**State machine:** Bookings follow fixed transitions from `pending` to `confirmed`, `active`, `completed`, and finally `cancelled`. Invalid transitions throw a custom exception.

**Billing:** Confirmed bookings trigger a queued billing job that calculates the rental price and creates an invoice. Application-level duplicate checks and a database unique constraint provide two layers of protection against duplicate invoices.

**Performance:** Equipment listings use subqueries, `withAvg()`, and eager loading to retrieve equipment, category, booking status, and ratings using a fixed query count rather than generating N+1 queries.

---

# License

This project is open-sourced under the [MIT License](https://opensource.org/licenses/MIT).
