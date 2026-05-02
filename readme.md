# Ecom Store

### Objective:
A Multi-User Order & Inventory System (Mini SaaS Style).

1. Simplified Authentication
• Login System: Implement a basic login using Laravel Sanctum.
• Single User Type: All authenticated users have the same permissions to view products and create
orders.

2. Core Database Design
• users: id, name, email, password.
• products: id, name, price, stock.
• orders: id, user_id, total_price.
• order_items: id, order_id, product_id, quantity, price.

3. Backend API (Laravel)
• Auth APIs: Basic login and logout endpoints.
• Product List: A simple GET endpoint to retrieve available products.
• Order Creation: A POST endpoint to submit a new order.

### Tech Stacks:
`Laravel 12` `sqlite` `vue 3`  `tailwindcss`  `axios` 

### Screenshots

### Installation

```shell
git clone https://github.com/Y2theK/ecom.git
```

- For Backend

```shell
cd backend
```

```shell
cp .env.example .env
```

```shell
touch database/database.sqlite
```

```shell
composer install
```

```shell
php artisan key:generate
```

```shell
php artisan migrate:fresh --seed
```

```shell
php artisan serve
```

- For Frontend
```shell
cd backend
```

```shell
cp .env.example .env
```
```shell
npm install
```

```shell
npm run dev 
```

### Demo Credentials

| Name | Email | Password |
|---|---|---|
| Test User | test@example.com | password |


