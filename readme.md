# Ecom Store

#### Objective:
A Multi-User Order & Inventory System (Mini SaaS Style).

1. Authentication
• Login System: Implemented basic login using Laravel Sanctum (SPA).
• All authenticated users have the same permissions to view products and create
orders.

2. Database Design
• users: id, name, email, password.
• products: id, name, price, stock.
• orders: id, user_id, total_price.
• order_items: id, order_id, product_id, quantity, price.

3. Backend API (Laravel)
• Auth APIs: Basic login and logout endpoints.
• Product List: A simple GET endpoint to retrieve available products.
• Order Creation: A POST endpoint to submit a new order.

### Tech Stacks:
`Laravel 11` `sqlite` `vue 3`  `tailwindcss`  `axios` 

### Screenshots
<img src="https://github.com/Y2theK/ecom/blob/master/frontend/src/assets/image/home.png" width=50% height=50% alt= "Home">
<img src="https://github.com/Y2theK/ecom/blob/master/frontend/src/assets/image/login.png" width=50% height=50% alt= "Login">

### Installation

```shell
git clone https://github.com/Y2theK/ecom.git
```

```shell
cd ecom
```

- For Backend Setup

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

- For Frontend Setup

```shell
cd frontend
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

Visit http://localhost:5173 for frontend website.
Visit http://localhost:8000 for backend api.

### Demo Credentials

| Name | Email | Password |
|---|---|---|
| Test User | test@example.com | password |


