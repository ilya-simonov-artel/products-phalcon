# phalcon-test

Сервис каталога товаров на **PHP + Phalcon + MySQL** с простым web-интерфейсом на **Volt + Vue 3** и запуском через **Docker Compose**.

## Возможности

- Получение списка товаров с постраничной пагинацией.
- Фильтрация по категории с учётом иерархии подкатегорий.
- Фильтрация по наличию (`in_stock`).
- Агрегированная сводка по товарам **в наличии**:
  - количество;
  - суммарная стоимость.
- CRUD для товаров.
- CRUD для категорий с поддержкой вложенности.
- Полноценная авторизация по логину/паролю c выдачей JWT Bearer-токена.
- JSON-ответы и корректные HTTP-коды ошибок.
- Web UI для управления товарами и категориями.

> Поведение агрегатов: агрегаты учитывают применённые фильтры по категории, а фильтр `in_stock` для них всегда принудительно считается как `true`, поскольку сводка должна показывать только товары в наличии.

## Стек

- PHP 8.4 (в Docker-контейнере)
- Phalcon 5.9.2 (предустановлен в официальном образе cphalcon)
- MySQL 8.0
- Built-in PHP web server (в контейнере приложения)
- Volt templates
- Vue 3 (Composition/Options не критично, здесь используется глобальная сборка Vue 3 и реактивный UI для списка и форм)
- Docker Compose

## Структура проекта

- `public/index.php` — точка входа приложения.
- `app/Controllers` — web/API контроллеры.
- `app/Services` — бизнес-логика товаров и категорий.
- `app/Views/index/index.volt` — интерфейс.
- `public/assets/js/app.js` — Vue 3 клиент.
- `docker/mysql/initdb.d` — схема БД и стартовые данные.
- `Dockerfile`, `docker-compose.yml` — контейнеризация.

## Запуск через Docker

### 1. Подготовка

```bash
cp .env.example .env
```

При необходимости можно изменить JWT-параметры (`JWT_SECRET`, `JWT_ISSUER`, `JWT_TTL_HOURS`), путь к cache-директории Volt (`APP_CACHE_DIR`) и параметры БД в `.env`.

### 2. Сборка и запуск

```bash
docker compose up --build
```

Во время сборки образа выполняется `composer install` c проверкой наличия `vendor/autoload.php`.

Для локальной разработки фронтенда вместе с `app` теперь поднимается отдельный сервис `frontend`, который один раз устанавливает npm-зависимости через `npm install` (если отсутствуют) и запускает `vite build --watch`. Поэтому изменения в `src/**/*.ts`, `src/**/*.vue`, `src/**/*.scss` и CSS автоматически пересобираются в `public/dist` без перезапуска контейнеров.

При старте контейнера entrypoint дополнительно проверяет зависимости Composer: если `vendor/autoload.php` отсутствует или `composer.json` изменился, выполняется `composer install`; иначе установка пропускается.

После первого старта в каталоге проекта появится папка `vendor/` (entrypoint выполняет `composer install`, если зависимостей нет или изменился `composer.json`).

Чтобы избежать ошибок компиляции PECL-расширения Phalcon, Dockerfile использует официальный образ `phalconphp/cphalcon:v5.9.2-php8.4`, где расширение уже предустановлено и не требует локальной компиляции через PECL.

### 3. Доступ

- Web UI: `http://localhost:8080`
- MySQL: `localhost:3307`
- Тестовый пользователь: `demo` / `test12345`

## Остановка

```bash
docker compose down
```

Чтобы удалить volume базы данных:

```bash
docker compose down -v
```

## Модель данных

### Категории

Поля:
- `id`
- `name`
- `parent_id` — ссылка на родительскую категорию, допускает `NULL`
- `created_at`
- `updated_at`

### Товары

Поля:
- `id`
- `name`
- `content`
- `price`
- `category_id`
- `in_stock`
- `created_at`
- `updated_at`

## Индексы и краткое обоснование

### `categories.idx_categories_parent_id (parent_id)`
Оптимизирует:
- выборку дочерних категорий;
- рекурсивные запросы по дереву категорий;
- проверки перед удалением категории.

Почему выбран:
- все операции с иерархией упираются в поиск потомков/детей по `parent_id`, поэтому одиночный индекс на это поле даёт максимальную пользу при минимальной стоимости поддержки.

### `products.idx_products_category_stock_id (category_id, in_stock, id)`
Оптимизирует:
- выборку списка товаров по категории/подкатегориям;
- фильтрацию `category_id + in_stock`;
- сортировку/пагинацию по `id` при фильтрации.

Почему выбран:
- это основной сценарий чтения: список товаров с фильтром по категории и, опционально, по наличию. `id` добавлен в конец индекса, чтобы уменьшить стоимость сортировки и пагинации по стабильному ключу.

### `products.idx_products_stock_id (in_stock, id)`
Оптимизирует:
- агрегаты и выборки только по наличию;
- список товаров при фильтре только по `in_stock`;
- быстрый подсчёт и суммирование по товарам в наличии.

Почему выбран:
- агрегаты всегда считают только `in_stock = true`, поэтому отдельный индекс под этот сценарий полезен даже без фильтра по категории.

## API

Все API-ответы возвращаются в JSON.

Сначала выполните вход и получите Bearer-токен:

```bash
curl -X POST "http://localhost:8080/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"demo","password":"test12345"}'
```

Далее используйте полученный токен в заголовке:

```http
Authorization: Bearer <token>
```

---

## API: категории

### Получить дерево и плоский список категорий

```bash
curl -X GET 'http://localhost:8080/api/categories' \
  -H 'Authorization: Bearer <token>'
```

Пример ответа:

```json
{
  "success": true,
  "status": 200,
  "data": {
    "tree": [
      {
        "id": 1,
        "name": "Электроника",
        "parent_id": null,
        "children": [
          {
            "id": 2,
            "name": "Смартфоны",
            "parent_id": 1,
            "children": []
          }
        ]
      }
    ],
    "items": [
      {
        "id": 1,
        "name": "Электроника",
        "parent_id": null,
        "full_name": "Электроника"
      }
    ]
  }
}
```

### Создать категорию

```bash
curl -X POST 'http://localhost:8080/api/categories' \
  -H 'Authorization: Bearer <token>' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Планшеты","parent_id":1}'
```

### Обновить категорию

```bash
curl -X PUT 'http://localhost:8080/api/categories/2' \
  -H 'Authorization: Bearer <token>' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Смартфоны и гаджеты","parent_id":1}'
```

### Удалить категорию

```bash
curl -X DELETE 'http://localhost:8080/api/categories/6' \
  -H 'Authorization: Bearer <token>'
```

---

## API: товары

### Получить список товаров

```bash
curl -X GET 'http://localhost:8080/api/products?page=1&limit=10&category_id=1&in_stock=true' \
  -H 'Authorization: Bearer <token>'
```

Пример ответа:

```json
{
  "success": true,
  "status": 200,
  "data": {
    "items": [
      {
        "id": 5,
        "name": "USB-C Dock",
        "content": "Док-станция с HDMI и Ethernet.",
        "price": 8990,
        "in_stock": true,
        "category_id": 6,
        "category": "Аксессуары"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 10,
      "total": 3,
      "pages": 1
    },
    "aggregate": {
      "in_stock_count": 3,
      "in_stock_total_value": 113970
    }
  }
}
```

### Получить агрегаты отдельно

```bash
curl -X GET 'http://localhost:8080/api/products/aggregate?category_id=1' \
  -H 'Authorization: Bearer <token>'
```

### Создать товар

```bash
curl -X POST 'http://localhost:8080/api/products' \
  -H 'Authorization: Bearer <token>' \
  -H 'Content-Type: application/json' \
  -d '{
    "name":"Tablet Air",
    "content":"Лёгкий планшет для поездок.",
    "price":45990,
    "category_id":1,
    "in_stock":true
  }'
```

### Обновить товар

```bash
curl -X PUT 'http://localhost:8080/api/products/1' \
  -H 'Authorization: Bearer <token>' \
  -H 'Content-Type: application/json' \
  -d '{
    "name":"Phalcon Phone X (2026)",
    "content":"Обновлённый флагманский смартфон.",
    "price":82990,
    "category_id":2,
    "in_stock":true
  }'
```

### Удалить товар

```bash
curl -X DELETE 'http://localhost:8080/api/products/1' \
  -H 'Authorization: Bearer <token>'
```

---

## Ошибки и HTTP-коды

Примеры кодов:

- `200 OK` — успешное чтение/обновление/удаление.
- `201 Created` — успешное создание.
- `400 Bad Request` — некорректный JSON.
- `401 Unauthorized` — отсутствует или неверный Bearer Token.
- `404 Not Found` — сущность не найдена.
- `409 Conflict` — конфликт удаления категории, если есть дочерние категории или товары.
- `422 Unprocessable Entity` — ошибка валидации.
- `500 Internal Server Error` — необработанная ошибка сервера.

Пример ответа с ошибкой:

```json
{
  "success": false,
  "status": 422,
  "error": {
    "message": "Validation failed.",
    "details": {
      "category_id": "Valid category is required."
    }
  }
}
```

## Web-интерфейс

На главной странице доступны:
- фильтры списка товаров;
- таблица товаров с пагинацией;
- форма создания/редактирования товара;
- дерево категорий;
- форма создания/редактирования категории;
- карточки агрегированной статистики по товарам в наличии.

## Стартовые данные

При первом запуске автоматически создаются:
- корневые категории и подкатегории;
- несколько товаров в наличии и вне наличия.

Это позволяет сразу проверить:
- фильтрацию;
- пагинацию;
- агрегацию;
- CRUD операции.

## Требования для запуска

- Docker и Docker Compose (рекомендуется)
- Node.js >= 18 и npm (только если требуется локальная сборка фронтенда)
- Для разработки с автодополнением Phalcon — PHP >= 8.1 и composer (локально, только для IDE)

## Быстрый старт (Docker)

1. Клонируйте репозиторий и перейдите в папку проекта.
2. Скопируйте .env:
   ```bash
   cp .env.example .env
   ```
3. Соберите и запустите контейнеры:
   ```bash
   docker compose up --build
   ```
4. Откройте http://localhost:8080

## Сборка фронтенда вручную (опционально)

Если вы хотите пересобрать JS/Vue-клиент:

1. Установите зависимости:
   ```bash
   npm install
   ```
2. Соберите фронтенд:
   ```bash
   npm run build
   ```
   После сборки ассеты появятся в папке `dist/` (корень проекта).

## Важно
- Не размещайте папку сборки Vite (`dist/`) внутри `public/` — это предотвращает конфликты и предупреждения Vite.
- Для автодополнения Phalcon в IDE выполните локально:
   ```bash
   composer install --ignore-platform-req=ext-phalcon --ignore-platform-req=ext-pdo_mysql
   ```
   Это установит phalcon/ide-stubs в vendor для вашей IDE, но не попадёт в production-контейнер.
