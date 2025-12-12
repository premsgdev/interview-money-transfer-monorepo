# 🏦 Fund Manager Service

**Secure Account Management & Fund Transfer Microservice (Symfony 7 + API Platform)**

This service is responsible for:

* Managing user financial accounts
* Performing **ACID-safe fund transfers**
* Enforcing account ownership with **JWT authentication**
* Maintaining a local user projection via **RabbitMQ events**
* Offering **REST APIs with API Platform**
* Ensuring **idempotent** financial operations using **Redis**
* Providing full functional test coverage for transfers

This service is part of the **Money Transfer Monorepo** and works together with the `users-service`.

---

## 📌 Features

### 🔐 Authentication
* Uses **LexikJWT** for decoding tokens issued by `users-service`.
* Stateless authentication on all `/api/*` routes.
* API Platform access control.

### 🧾 Accounts API (API Platform)
* Create account
* List user-owned accounts
* Retrieve single account
* All operations automatically restricted to the authenticated user

### 💰 Fund Transfers
* Transaction-safe balance modifications
* **Pessimistic locking** (`SELECT … FOR UPDATE`)
* **Idempotency key** support using Redis
* Proper validation & domain rules:
    * Sufficient balance
    * Same currency
    * User can transfer only between their own accounts

### 🔄 User Synchronization
* Consumes `UserUpdatedEvent` and `UserDeletedEvent` from **RabbitMQ**
* Keeps local `user_projection` table in sync
* Eliminates cross-service lookups during transfers

### 🧪 Test Suite
* Full functional tests using **Symfony WebTestCase**
* Clean test isolation via dedicated test DB

---
## 🏗️ Architecture Overview
This system is built using a microservice architecture consisting of two independent Symfony applications:
### 1. Users-Service (Identity & Authentication)
Responsible for:
  * User registration & login
  * Password hashing & authentication
  * JWT token issuance
  * Emitting user events (create/update/delete) to RabbitMQ

It owns the concept of a “user” and acts as the source of truth for identity.
### 2. Fund-Manager Service (Accounts & Money Transfers)
Responsible for:
 * Managing financial accounts
 * Executing safe, ACID-compliant fund transfers
 * Enforcing user ownership and access control using JWT
 * Maintaining a local projection of users (synced from RabbitMQ events)
 * Implementing high-performance operations using Redis for idempotency

It owns all financial data, ensuring transactional integrity without relying on external services.

### 🧠 Why This Architecture?
This design wasn’t chosen arbitrarily — it aligns with real financial-sector engineering practices and demonstrates architectural maturity.
Below are the key reasons this approach is used and valued in production systems:
#### ✅ 1. Clear Separation of Responsibilities (Single Responsibility Principle)
Each service focuses on one domain:

| Service | Responsibility |
| :--- | :--- |
| **Users-Service** | Identity, credentials, authorization |
| **Fund-Manager** | Accounts, balances, transfers |

This makes the system easier to reason about, test, extend, and secure.

#### ✅ 2. Event-Driven User Synchronization
Instead of `Fund-Manager` calling `Users-Service` synchronously:

* **Users-Service** publishes events (RabbitMQ)
* **Fund-Manager** consumes events and updates a local projection

This provides:
* **Loose coupling** between services
* **Fault tolerance** (if `Users-Service` is down, transfers still work)
* **High performance** (no cross-service HTTP calls during critical money operations)
* **Scalable fan-out** — more services can subscribe to user events in the future

This is a modern microservice best practice.

#### ✅ 3. Local User Projection in Fund-Manager
`Fund-Manager` keeps a local table (`user_projection`) synchronized from events.

**Why?**
* Financial operations must not depend on remote services
* Latency must be minimal under high load
* Handling thousands of transfers per second requires local lookups only

This approach mirrors event-sourcing patterns used by Stripe, Revolut, and Monzo.

#### ✅ 4. RabbitMQ for Reliable Asynchronous Communication
RabbitMQ provides:
* Message durability
* Guaranteed delivery
* Retry + dead letter queues
* Backpressure handling

Essential for event-driven financial workloads.

#### ✅ 5. Redis for Idempotency & High Load
Fund transfers must be **idempotent**, or users might accidentally trigger duplicate transfers.
Redis is used to store:
* Idempotency keys
* Temporary request fingerprints

This prevents double-charging and ensures correctness.

#### ✅ 6. Database Transaction Integrity
`Fund-Manager` uses:
* MySQL transactions
* Row-level locking (`SELECT FOR UPDATE`)

This prevents:
* Race conditions
* Double-spending
* Dirty reads / write skew

Banks use similar mechanisms.

#### ✅ 7. Independent Scaling
Each service scales based on its own bottlenecks:

* **Users-service** → CPU-bound (auth hashing, login)
* **Fund-manager** → IO & DB-bound (transactions, balance updates)

This enables cost-efficient horizontal scaling.

#### ✅ 8. Easy to Extend
This architecture allows adding more microservices easily:

* **Notifications Service** → subscribes to user events
* **Ledger Service** → subscribes to transfer events
* **Reporting Service** → consumes events for analytics

Zero modifications required to existing services.

## 🧱 Architecture Diagram

```mermaid
flowchart LR

subgraph Fund-Manager
    API[API Platform Accounts API]
    TRANSFER[Fund Transfer Service]
    PROJ[User Projection]
    DB[(MySQL Accounts DB)]
end

RabbitMQ[(User Events Queue)]

users-service --> RabbitMQ --> PROJ
API --> TRANSFER --> DB

```

## 🚀 Running the Service

### 1️⃣ Start infrastructure

```bash
make infra-up
```
Starts:
    * MySQL
    * Redis
    * RabbitMQ

### Start the users-service

```bash
cd services/users-service
php -S 127.0.0.1:8001 -d
```

### Start the Fund Manager API

```bash
cd services/fund-manager
php -S 127.0.0.1:8002 -t public
```
Swagger UI: 👉 http://127.0.0.1:8002/api/docs

###  Start the message consumer

```bash
cd services/fund-manager
php bin/console messenger:consume user_events -vv
```
This keeps the user projection in sync with users-service.

## Also these services can be start in its own Docker containers
### Start users-service in its own Docker container

```bash
make start-users-local
```
This will:
  * Install vendor dependencies via Composer (inside a temp container)
  * Mount your local services/users-service folder
  * Start a PHP local server inside the container

Expose the service at:
  * 👉 http://127.0.0.1:8001

You can now hit:
  * POST /api/register
  * POST /api/login
  * GET /api/docs (Swagger UI)

### Start fund-manager in its own Docker container
```
make start-fund-local
```
This will:
  * Install dependencies inside the container
  * Mount your local services/fund-manager folder
  * Start PHP server inside container

Expose the service at:
  * 👉 http://127.0.0.1:8002

Available endpoints include:
  * POST /api/accounts
  * GET /api/accounts
  * POST /api/transfers
  * GET /api/docs

### Users-service Swagger

👉 http://127.0.0.1:8001/api/docs

### Fund-manager Swagger

👉 http://127.0.0.1:8002/api/docs

If both open → you're good to go 🚀

### 🧪 Running Tests

### Create the test database

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

### Run tests

```bash
vendor/bin/simple-phpunit
```

## 📡 API Workflows
Below is the complete flow of how the frontend or API client interacts with both microservices.

```mermaid
flowchart LR
  classDef svc fill:#f8f9fb,stroke:#2b6fb3,stroke-width:1px;
  classDef infra fill:#fff7e6,stroke:#b36b00,stroke-width:1px;
  classDef db fill:#f0fff4,stroke:#0b7a3a,stroke-width:1px;
  classDef client fill:#ffffff,stroke:#6b7280,stroke-width:1px,stroke-dasharray: 4 2;

  Client[Client<br/>Web / Mobile / CLI]:::client

  subgraph AuthBound[Users-Service]
    direction TB
    US_API[Users API<br/><b>Register / Login / JWT</b>]:::svc
    US_EVENTS[Emit UserUpdatedEvent / UserDeletedEvent]:::svc
  end

  subgraph Messaging[ ]
    direction TB
    Rabbit[RabbitMQ<br/>Exchange → Queue]:::infra
  end

  subgraph FinanceBound[Fund-Manager]
    direction TB
    FM_CONSUME[Consume User Events]:::svc
    PROJECTION[User Projection<br/>user table]:::db
    ACCOUNTS[Accounts API<br/>API Platform]:::svc
    TRANSFER[Transfer Engine<br/>Transactional, locks]:::svc
    CACHE[Redis<br/>Idempotency & Cache]:::infra
    ACCT_DB[MySQL<br/>accounts_db]:::db
  end

  %% Client interactions
  Client -->|1. register / login| US_API
  Client -->|2. use JWT to call| ACCOUNTS
  Client -->|3. call transfer endpoint| TRANSFER

  %% Users-service publishes events
  US_EVENTS -->|publish:user_events| Rabbit

  %% RabbitMQ -> fund-manager
  Rabbit -->|deliver events| FM_CONSUME
  FM_CONSUME -->|upsert| PROJECTION

  %% Fund-manager internals
  ACCOUNTS -->|create/list accounts | PROJECTION
  TRANSFER -->|SELECT ... FOR UPDATE / COMMIT| ACCT_DB
  TRANSFER -->|check/store idempotency key| CACHE
  TRANSFER -->|read ownership from| PROJECTION
  TRANSFER -->|audit / insert transfer| ACCT_DB
```

### 🧩 1. Authentication & User Management (users-service)
Base URL: http://127.0.0.1:8001

### 1️⃣ Register User (users-service)
URL: http://127.0.0.1:8001/api/register

```bash
curl -X POST http://127.0.0.1:8001/api/register \
  -H "Content-Type: application/json" \
  -d '{"email":"alice@example.com","password":"secret"}'
```

### 2️⃣ Login to obtain JWT (users-service)
URL: http://127.0.0.1:8001/api/login
```bash
curl -X POST http://127.0.0.1:8001/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"alice@example.com","password":"secret"}'
```

### 3️⃣ Create an Account (fund-manager)
URL: http://127.0.0.1:8002/api/accounts

```bash
curl -X POST http://127.0.0.1:8002/api/accounts \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"balance":"1000.00","currency":"INR"}'
```

### 4️⃣ List Accounts (fund-manager)
URL: http://127.0.0.1:8002/api/accounts

```bash
curl -X GET http://127.0.0.1:8002/api/accounts \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

### 5️⃣ Transfer Funds (fund-manager)
URL: http://127.0.0.1:8002/api/transfers

```bash
curl -X POST http://127.0.0.1:8002/api/transfers \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -H "Idempotency-Key: unique-key-123" \
  -H "Content-Type: application/json" \
  -d "{
        \"fromAccountUuid\": \"<FROM_UUID>\",
        \"toAccountUuid\":   \"<TO_UUID>\",
        \"amount\": \"250.00\",
        \"currency\": \"INR\"
      }"
```

### 6️⃣ Verify Updated Balances (fund-manager)
URL: http://127.0.0.1:8002/api/accounts

```bash
curl -X GET http://127.0.0.1:8002/api/accounts \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

## AI Tools used
  * google.geminicodeassist 2.63
  * Chat GPT 5.1
### Aproximate time taken : ~ 6hrs