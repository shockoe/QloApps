<?php
function install_module() {
    $sql = array();
    $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'htl_external_cart_tokens` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `cart_token` VARCHAR(255) UNIQUE NOT NULL,
        `id_cart` INT NOT NULL,
        `id_customer` INT,
        `expires_at` DATETIME NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_cart_token` (`cart_token`),
        INDEX `idx_cart_id` (`id_cart`),
        INDEX `idx_expires` (`expires_at`)
    ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

    $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'htl_external_booking_refs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `booking_id` VARCHAR(100) UNIQUE NOT NULL,
        `id_order` INT NOT NULL,
        `confirmation_number` VARCHAR(50),
        `external_ref` VARCHAR(255),
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_booking_id` (`booking_id`),
        INDEX `idx_order_id` (`id_order`)
    ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

    foreach ($sql as $query) {
        if (Db::getInstance()->execute($query) == false) {
            return false;
        }
    }
    return true;
}
