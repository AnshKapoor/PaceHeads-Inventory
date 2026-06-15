-- Phase 1: introduce the category relationship without removing products.category.
--
-- Run this migration once after taking a database backup.
-- The existing category name remains available until the application has been
-- updated to read and write products.category_id.

ALTER TABLE products
    ADD COLUMN category_id INT(11) NULL AFTER category;

-- Backfill only category names that identify exactly one categories row.
-- Duplicate category names and missing category names remain NULL for review.
UPDATE products AS p
INNER JOIN (
    SELECT
        name,
        MIN(category_id) AS category_id
    FROM categories
    GROUP BY name
    HAVING COUNT(*) = 1
) AS category_map
    ON category_map.name = p.category
SET p.category_id = category_map.category_id
WHERE p.category_id IS NULL;

ALTER TABLE products
    ADD INDEX idx_products_category_id (category_id),
    ADD CONSTRAINT fk_products_category
        FOREIGN KEY (category_id)
        REFERENCES categories (category_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;

-- Audit 1: product category names that could not be mapped.
SELECT
    p.category,
    COUNT(*) AS product_count
FROM products AS p
WHERE p.category_id IS NULL
  AND p.category IS NOT NULL
  AND TRIM(p.category) <> ''
GROUP BY p.category
ORDER BY p.category;

-- Audit 2: blank product categories that still need a business decision.
SELECT
    COUNT(*) AS products_without_category
FROM products
WHERE category_id IS NULL
  AND (category IS NULL OR TRIM(category) = '');

-- Audit 3: duplicate category names that must be merged or renamed.
SELECT
    name,
    COUNT(*) AS duplicate_count,
    GROUP_CONCAT(category_id ORDER BY category_id) AS category_ids
FROM categories
GROUP BY name
HAVING COUNT(*) > 1
ORDER BY name;
