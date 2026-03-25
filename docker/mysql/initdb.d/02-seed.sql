SET NAMES utf8mb4;

INSERT INTO users (id, username, password_hash, display_name) VALUES
    (1, 'demo', '$2y$12$4XKVoSHtdmUy0m8gpeX1yuFHfYfkiu85l499s.Wl/KOyp.c2gakvG', 'Тестовый пользователь');

INSERT INTO categories (id, name, parent_id) VALUES
    (1, 'Электроника', NULL),
    (2, 'Смартфоны', 1),
    (3, 'Ноутбуки', 1),
    (4, 'Дом', NULL),
    (5, 'Кухня', 4),
    (6, 'Аксессуары', 1);

INSERT INTO products (name, content, price, category_id, in_stock)
WITH RECURSIVE seq AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM seq WHERE n < 1000
)
SELECT
    CONCAT('Товар #', n) AS name,
    CONCAT('Описание товара #', n, '. Сгенерировано сидером.') AS content,
    ROUND(990 + ((n * 137) % 120000), 2) AS price,
    CASE n % 4
        WHEN 0 THEN 2
        WHEN 1 THEN 3
        WHEN 2 THEN 5
        ELSE 6
    END AS category_id,
    CASE WHEN n % 3 = 0 THEN 0 ELSE 1 END AS in_stock
FROM seq;
