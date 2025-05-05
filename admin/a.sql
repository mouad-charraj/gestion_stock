-- Vérifier d'abord si la colonne 'name' existe
SET @exist := (SELECT COUNT(*)
               FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'name');

-- Si la colonne n'existe pas, l'ajouter
SET @query = IF(@exist = 0,
    'ALTER TABLE orders ADD COLUMN name VARCHAR(100) NULL',
    'ALTER TABLE orders MODIFY COLUMN name VARCHAR(100) NULL');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Modifier la colonne status 
ALTER TABLE orders 
    MODIFY COLUMN status ENUM('en attente', 'en cours', 'terminée', 'annulée') NOT NULL DEFAULT 'en attente';

-- Ajouter un index pour accélérer les requêtes sur sender_type et receiver_type
ALTER TABLE orders 
    ADD INDEX idx_order_types (sender_type, receiver_type);