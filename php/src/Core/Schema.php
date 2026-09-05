<?php
namespace App\Core;

class Schema {
    public static function migrate(\PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(64) NOT NULL,
            source_video_id VARCHAR(191) NOT NULL,
            slug VARCHAR(191) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            thumbnail VARCHAR(500) NULL,
            preview VARCHAR(500) NULL,
            embed_url VARCHAR(500) NULL,
            page_url VARCHAR(500) NULL,
            duration INT DEFAULT 0,
            views INT DEFAULT 0,
            rating DECIMAL(3,1) DEFAULT 0,
            quality VARCHAR(16) DEFAULT 'HD',
            is_featured TINYINT(1) DEFAULT 0,
            published_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_src (source, source_video_id),
            INDEX idx_views (views),
            INDEX idx_pub (published_at),
            FULLTEXT KEY ft_title (title, description)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(96) NOT NULL UNIQUE,
            name VARCHAR(96) NOT NULL,
            video_count INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(96) NOT NULL UNIQUE,
            name VARCHAR(96) NOT NULL,
            video_count INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS video_categories (
            video_id INT NOT NULL,
            category_id INT NOT NULL,
            PRIMARY KEY (video_id, category_id),
            INDEX idx_cat (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS video_tags (
            video_id INT NOT NULL,
            tag_id INT NOT NULL,
            PRIMARY KEY (video_id, tag_id),
            INDEX idx_tag (tag_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS sources (
            slug VARCHAR(64) PRIMARY KEY,
            label VARCHAR(128) NOT NULL,
            enabled TINYINT(1) DEFAULT 0,
            last_import_at DATETIME NULL,
            last_status TEXT NULL,
            config JSON NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS cache (
            k VARCHAR(191) PRIMARY KEY,
            v LONGTEXT NOT NULL,
            expires_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS ads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            position VARCHAR(32) NOT NULL,
            kind ENUM('banner','snippet') NOT NULL DEFAULT 'banner',
            title VARCHAR(191) NULL,
            image_url VARCHAR(500) NULL,
            link_url VARCHAR(500) NULL,
            snippet_html TEXT NULL,
            weight INT NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pos_active (position, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS search_queries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            q VARCHAR(191) NOT NULL,
            count INT NOT NULL DEFAULT 1,
            results_last INT NOT NULL DEFAULT 0,
            last_seen DATETIME NOT NULL,
            UNIQUE KEY uniq_q (q),
            INDEX idx_count (count)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
