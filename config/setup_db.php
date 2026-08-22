<?php
// config/setup_db.php
require_once __DIR__ . '/db.php';

try {
    // 1. Create Tables
    
    // Roles
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Permissions
    $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
        role_id INT NOT NULL,
        module_name VARCHAR(50) NOT NULL,
        can_view TINYINT(1) DEFAULT 0,
        can_create TINYINT(1) DEFAULT 0,
        can_edit TINYINT(1) DEFAULT 0,
        can_delete TINYINT(1) DEFAULT 0,
        PRIMARY KEY (role_id, module_name),
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role_id INT NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES roles(id)
    ) ENGINE=InnoDB;");

    // Categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Properties
    $pdo->exec("CREATE TABLE IF NOT EXISTS properties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        location VARCHAR(255) NOT NULL,
        price DECIMAL(15, 2) NOT NULL,
        price_unit VARCHAR(50) DEFAULT 'Sq. Yard',
        description TEXT,
        beds INT DEFAULT 0,
        baths INT DEFAULT 0,
        area_sqft INT DEFAULT 0,
        featured TINYINT(1) DEFAULT 0,
        is_kisan_kota TINYINT(1) DEFAULT 0,
        status ENUM('active', 'inactive', 'sold') DEFAULT 'active',
        main_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB;");

    // Property Gallery
    $pdo->exec("CREATE TABLE IF NOT EXISTS property_gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // Inquiries
    $pdo->exec("CREATE TABLE IF NOT EXISTS inquiries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        message TEXT,
        status ENUM('new', 'contacting', 'qualified', 'lost', 'closed') DEFAULT 'new',
        assigned_to INT NULL,
        source ENUM('website', 'meta_ads') DEFAULT 'website',
        campaign_name VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB;");

    // Meta Ads Click Tracking
    $pdo->exec("CREATE TABLE IF NOT EXISTS meta_ads_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_name VARCHAR(255) NOT NULL,
        ad_name VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Settings for credentials & API Mock Toggle
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    echo "Tables created successfully.\n";

    // 2. Seeding Roles
    $defaultRoles = [
        ['Admin', 'Full administrator access to all settings and modules'],
        ['Sales Employee', 'Access to listings, categories, and assigned inquiries'],
        ['Meta Manager', 'Access to Meta Ads Campaigns, click tracking, and inquiries'],
        ['Meta Employee', 'Read-only access to Meta Ads Campaigns and click tracking']
    ];

    $stmtRole = $pdo->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?) ON DUPLICATE KEY UPDATE description=VALUES(description)");
    foreach ($defaultRoles as $r) {
        $stmtRole->execute($r);
    }
    echo "Roles seeded.\n";

    // Get Role IDs
    $rolesMap = [];
    foreach ($pdo->query("SELECT id, role_name FROM roles")->fetchAll() as $row) {
        $rolesMap[$row['role_name']] = $row['id'];
    }

    // 3. Seeding Role Permissions (WordPress Style Module Permissions Matrix)
    // Modules: properties, categories, inquiries, meta_ads, roles, users
    $permissionsSeed = [
        'Admin' => [
            'properties' => [1,1,1,1],
            'categories' => [1,1,1,1],
            'inquiries'  => [1,1,1,1],
            'meta_ads'   => [1,1,1,1],
            'roles'      => [1,1,1,1],
            'users'      => [1,1,1,1]
        ],
        'Sales Employee' => [
            'properties' => [1,0,0,0], // Read properties
            'categories' => [1,0,0,0], // Read categories
            'inquiries'  => [1,1,1,0], // View, add/assign, edit inquiries
            'meta_ads'   => [0,0,0,0],
            'roles'      => [0,0,0,0],
            'users'      => [0,0,0,0]
        ],
        'Meta Manager' => [
            'properties' => [1,0,0,0],
            'categories' => [0,0,0,0],
            'inquiries'  => [1,1,1,0],
            'meta_ads'   => [1,1,1,1],
            'roles'      => [0,0,0,0],
            'users'      => [0,0,0,0]
        ],
        'Meta Employee' => [
            'properties' => [0,0,0,0],
            'categories' => [0,0,0,0],
            'inquiries'  => [1,0,0,0], // View meta lead inquiries only
            'meta_ads'   => [1,0,0,0], // View campaigns and clicks
            'roles'      => [0,0,0,0],
            'users'      => [0,0,0,0]
        ]
    ];

    $pdo->exec("DELETE FROM role_permissions");
    $stmtPerm = $pdo->prepare("INSERT INTO role_permissions (role_id, module_name, can_view, can_create, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($permissionsSeed as $roleName => $modules) {
        $rId = $rolesMap[$roleName];
        foreach ($modules as $modName => $p) {
            $stmtPerm->execute([$rId, $modName, $p[0], $p[1], $p[2], $p[3]]);
        }
    }
    echo "Permissions seeded.\n";

    // 4. Seeding Default Users
    // Admin password: admin123
    $adminPasswordHash = password_hash('admin123', PASSWORD_BCRYPT);
    $stmtUser = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, 'active') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)");
    $stmtUser->execute(['admin', 'admin@primehashtag.com', $adminPasswordHash, $rolesMap['Admin']]);

    // Sales user password: sales123
    $salesPasswordHash = password_hash('sales123', PASSWORD_BCRYPT);
    $stmtUser->execute(['sales_agent', 'sales@primehashtag.com', $salesPasswordHash, $rolesMap['Sales Employee']]);

    // Meta Manager user password: meta123
    $metaPasswordHash = password_hash('meta123', PASSWORD_BCRYPT);
    $stmtUser->execute(['meta_manager', 'meta@primehashtag.com', $metaPasswordHash, $rolesMap['Meta Manager']]);
    echo "Users seeded.\n";

    // 5. Seeding Default Categories
    $categoriesSeed = [
        ['name' => 'Residential Plots', 'slug' => 'residential-plots', 'description' => 'Premium plots ready for home construction'],
        ['name' => 'Kisan Kota (8% Quota) Plots', 'slug' => 'kisan-kota-plots', 'description' => 'Original allottee Kisaan Quota plots with high appreciation potential'],
        ['name' => 'Villas', 'slug' => 'villas', 'description' => 'Luxury independent houses and villas'],
        ['name' => 'Apartments', 'slug' => 'apartments', 'description' => 'Modern, cozy and premium multi-family flats'],
        ['name' => 'Commercial Properties', 'slug' => 'commercial', 'description' => 'Shops, offices, and plots for business setups']
    ];

    $stmtCat = $pdo->prepare("INSERT INTO categories (name, slug, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)");
    foreach ($categoriesSeed as $cat) {
        $stmtCat->execute([$cat['name'], $cat['slug'], $cat['description']]);
    }
    echo "Categories seeded.\n";

    // Get Category IDs
    $catMap = [];
    foreach ($pdo->query("SELECT id, slug FROM categories")->fetchAll() as $row) {
        $catMap[$row['slug']] = $row['id'];
    }

    // 6. Seeding Default Properties (matching client's images)
    $propertiesSeed = [
        [
            'category_id' => $catMap['residential-plots'],
            'title' => 'Wave City Extension Plots NH-24',
            'slug' => 'wave-city-extension-plots-nh24',
            'location' => 'NH-24, Ghaziabad',
            'price' => 32990.00,
            'price_unit' => 'Sq. Yard',
            'description' => 'Your Space. Your Future. Just Next Door. Premium freehold construction-ready plots adjacent to Wave City. Excellent connectivity, gated township, high appreciation potential, and secure investment.',
            'beds' => 0,
            'baths' => 0,
            'area_sqft' => 1080, // 120 Sq yards = 1080 Sq ft
            'featured' => 1,
            'is_kisan_kota' => 0,
            'status' => 'active',
            'main_image' => 'assets/images/wave-city-ext.jpg'
        ],
        [
            'category_id' => $catMap['residential-plots'],
            'title' => 'Wave City Premium Plots',
            'slug' => 'wave-city-premium-plots',
            'location' => 'Wave City, NH-24, Ghaziabad',
            'price' => 60000.00,
            'price_unit' => 'Sq. Yard',
            'description' => 'Premium Residential Plots with green & serene surroundings. Possession handover, well-planned development, secure your future today. Plot sizes available: 120 to 250 Sq. Yards.',
            'beds' => 0,
            'baths' => 0,
            'area_sqft' => 2250, // 250 Sq yards
            'featured' => 1,
            'is_kisan_kota' => 0,
            'status' => 'active',
            'main_image' => 'assets/images/wave-city-premium.jpg'
        ],
        [
            'category_id' => $catMap['kisan-kota-plots'],
            'title' => 'Kisan Kota Original Allottee Plots',
            'slug' => 'kisan-kota-original-allottee-plots',
            'location' => 'Wave City, Ghaziabad',
            'price' => 32990.00,
            'price_unit' => 'Sq. Yard',
            'description' => 'Original Allottee 8% plots by Wave City. Allotment awaited. Gated township, wide roads, green environment, future-ready investment with massive appreciation potential.',
            'beds' => 0,
            'baths' => 0,
            'area_sqft' => 1350, // 150 Sq yards
            'featured' => 1,
            'is_kisan_kota' => 1,
            'status' => 'active',
            'main_image' => 'assets/images/kisan-kota-awaited.jpg'
        ],
        [
            'category_id' => $catMap['kisan-kota-plots'],
            'title' => 'Kisaan Quota 8% Developed Phase Plots',
            'slug' => 'kisaan-quota-8-developed-phase-plots',
            'location' => 'Wave City, NH-24, Ghaziabad',
            'price' => 60000.00,
            'price_unit' => 'Sq. Yard',
            'description' => 'Own your dream plot today! Limited 8% Kisaan Quota Plots. Developed Phase, possession handover, ready for construction. Plot sizes: 120-250 Sq. Yards. Smart investment in a trusted developer project.',
            'beds' => 0,
            'baths' => 0,
            'area_sqft' => 1800, // 200 Sq yards
            'featured' => 0,
            'is_kisan_kota' => 1,
            'status' => 'active',
            'main_image' => 'assets/images/kisan-kota-developed.jpg'
        ]
    ];

    $pdo->exec("DELETE FROM properties");
    $stmtProp = $pdo->prepare("INSERT INTO properties (category_id, title, slug, location, price, price_unit, description, beds, baths, area_sqft, featured, is_kisan_kota, status, main_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($propertiesSeed as $prop) {
        $stmtProp->execute([
            $prop['category_id'],
            $prop['title'],
            $prop['slug'],
            $prop['location'],
            $prop['price'],
            $prop['price_unit'],
            $prop['description'],
            $prop['beds'],
            $prop['baths'],
            $prop['area_sqft'],
            $prop['featured'],
            $prop['is_kisan_kota'],
            $prop['status'],
            $prop['main_image']
        ]);
    }
    echo "Properties seeded.\n";

    // 7. Seed dummy settings
    $stmtSet = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $stmtSet->execute(['meta_mock_mode', '1']); // 1 = Mock, 0 = Live API
    $stmtSet->execute(['meta_access_token', 'EAA...MOCK_TOKEN']);
    $stmtSet->execute(['meta_ad_account_id', 'act_1234567890']);
    echo "Settings seeded.\n";

    // 8. Seed Dummy Inquiries
    $inqSeed = [
        [null, 'Rahul Sharma', 'rahul@example.com', '9876543210', 'Interested in Kisan Kota plots, please send pricing options.', 'new', null, 'website', null],
        [1, 'Amit Patel', 'amit.patel@example.com', '9988776655', 'I want to visit the site tomorrow for Wave City Extension plots.', 'contacting', 2, 'website', null],
        [3, 'Sneha Gupta', 'sneha.g@example.com', '9123456789', 'Lead captured via Facebook Ad Campaign: Kisan Kota Allotment Awaited.', 'qualified', null, 'meta_ads', 'Kisan Kota Plots Campaign']
    ];

    $pdo->exec("DELETE FROM inquiries");
    $stmtInq = $pdo->prepare("INSERT INTO inquiries (property_id, name, email, phone, message, status, assigned_to, source, campaign_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($inqSeed as $inq) {
        $stmtInq->execute($inq);
    }
    echo "Inquiries seeded.\n";

    echo "Database setup completed successfully!\n";

} catch (\PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
?>
