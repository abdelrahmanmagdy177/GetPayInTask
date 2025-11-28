
## Architecture Overview

The system addresses the challenge of high-traffic sales where multiple users compete for limited inventory. The solution is built on three core pillars:

1.  **Concurrency Control**: Uses database-level pessimistic locking (`lockForUpdate`) to serialize access to stock during critical operations.
2.  **Two-Phase Checkout**:
    *   **Hold**: Temporarily reserves stock for a short window (2 minutes).
    *   **Order**: Converts a valid hold into a permanent order.

## Technology Stack

*   **Framework**: Laravel 12.x
*   **Database**: MySQL (InnoDB)
*   **PHP**: 8.2+

## Installation & Setup

1.  **Clone the repository**
    ```bash
    git clone <repository-url>
    cd <repository-name>
    ```

2.  **Install Dependencies**
    ```bash
    composer install
    ```

    ```
    *Configure your database credentials in the `.env` file.*

3.  **Database Setup**
    Run migrations and seed the database with a test product (ID: 1, Stock: 10).
    ```bash
    php artisan migrate --seed
    ```

4.  **Start the Server**
    ```bash
    php artisan serve
    ```

## API Reference

### 1. Check Availability
**GET** `/api/products/{id}`

Returns the product details and the *currently available* stock (Total Stock minus Active Holds).

### 2. Reserve Stock (Hold)
**POST** `/api/holds`

Attempts to reserve stock for a product. If successful, returns a `hold_id` valid for 2 minutes.

**Body:**
```json
{
    "product_id": 1,
    "quantity": 1
}
```

### 3. Place Order
**POST** `/api/orders`

Finalizes a purchase by converting a valid `hold_id` into an order. Fails if the hold has expired.

**Body:**
```json
{
    "hold_id": "uuid-of-hold"
}
```

### 4. Process Payment
**POST** `/api/payments/webhook`

Simulates a payment gateway webhook. Updates the order status to `paid` or `failed`. If failed, stock is released.

**Body:**
```json
{
    "order_id": 1,
    "status": "success",
    "transaction_id": "unique-txn-id"
}
```

## Testing & Verification

### Automated Flow Test
A custom Artisan command is included to verify the entire lifecycle (Hold -> Order -> Payment) and check for idempotency.

```bash
php artisan test:flow
```
