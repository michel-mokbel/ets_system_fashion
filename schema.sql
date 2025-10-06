CREATE TABLE `barcodes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `barcode` varchar(50) NOT NULL,
  `item_id` int NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_shared` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_barcode_item` (`barcode`,`item_id`),
  KEY `item_id` (`item_id`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_price_shared` (`price`,`is_shared`),
  CONSTRAINT `barcodes_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `container_financial_summary` (
  `id` int NOT NULL AUTO_INCREMENT,
  `container_id` int NOT NULL,
  `base_cost` decimal(12,2) NOT NULL,
  `shipment_cost` decimal(10,2) DEFAULT '0.00',
  `total_all_costs` decimal(12,2) NOT NULL,
  `profit_margin_percentage` decimal(5,2) DEFAULT '0.00',
  `expected_selling_total` decimal(12,2) NOT NULL,
  `actual_selling_total` decimal(12,2) DEFAULT '0.00',
  `actual_profit` decimal(12,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `container_id` (`container_id`),
  CONSTRAINT `container_financial_summary_ibfk_1` FOREIGN KEY (`container_id`) REFERENCES `containers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `containers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `container_number` varchar(50) NOT NULL,
  `supplier_id` int NOT NULL,
  `total_weight_kg` decimal(10,2) NOT NULL,
  `price_per_kg` decimal(10,2) NOT NULL,
  `total_cost` decimal(12,2) NOT NULL,
  `shipment_cost` decimal(10,2) DEFAULT '0.00',
  `profit_margin_percentage` decimal(5,2) DEFAULT '0.00',
  `entry_mode` enum('bulk','detailed') DEFAULT 'detailed',
  `bulk_total_amount` decimal(12,2) DEFAULT NULL,
  `amount_paid` decimal(12,2) DEFAULT '0.00',
  `remaining_balance` decimal(12,2) NOT NULL,
  `arrival_date` date DEFAULT NULL,
  `status` enum('pending','received','processed','completed') DEFAULT 'pending',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `container_number` (`container_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_containers_entry_mode` (`entry_mode`),
  KEY `idx_containers_status_mode` (`status`,`entry_mode`),
  CONSTRAINT `containers_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `containers_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `expense_number` varchar(50) NOT NULL,
  `store_id` int NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `receipt_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `added_by` int NOT NULL,
  `approved_by` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expense_number` (`expense_number`),
  KEY `added_by` (`added_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_expenses_store` (`store_id`),
  KEY `idx_expenses_date` (`expense_date`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `inventory_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `category_id` int DEFAULT NULL,
  `subcategory_id` int DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `size` varchar(20) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `material` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('active','discontinued') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `category_id` (`category_id`),
  KEY `subcategory_id` (`subcategory_id`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_ibfk_2` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `inventory_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `store_id` int NOT NULL,
  `item_id` int NOT NULL,
  `barcode_id` int NOT NULL,
  `transaction_type` enum('in','out','adjustment','transfer') NOT NULL,
  `quantity` int NOT NULL,
  `reference_type` enum('container','sale','return','adjustment','transfer','shipment') NOT NULL,
  `reference_id` int DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `notes` text,
  `user_id` int DEFAULT NULL,
  `transaction_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `shipment_id` int DEFAULT NULL,
  `transfer_type` enum('outbound','inbound') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `barcode_id` (`barcode_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_inventory_transactions_store` (`store_id`),
  KEY `idx_inventory_transactions_date` (`transaction_date`),
  KEY `idx_inventory_transactions_shipment` (`shipment_id`),
  CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transactions_ibfk_3` FOREIGN KEY (`barcode_id`) REFERENCES `barcodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transactions_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transactions_ibfk_5` FOREIGN KEY (`shipment_id`) REFERENCES `transfer_shipments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=137 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `invoice_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `item_id` int NOT NULL,
  `barcode_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `barcode_id` (`barcode_id`),
  KEY `idx_invoice_items_invoice` (`invoice_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_ibfk_3` FOREIGN KEY (`barcode_id`) REFERENCES `barcodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `store_id` int NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('cash','card','mobile','credit') DEFAULT 'cash',
  `payment_status` enum('pending','paid','partial','refunded') DEFAULT 'pending',
  `status` enum('draft','completed','cancelled','returned') DEFAULT 'draft',
  `sales_person_id` int NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `sales_person_id` (`sales_person_id`),
  KEY `idx_invoices_store` (`store_id`),
  KEY `idx_invoices_date` (`created_at`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`sales_person_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `purchase_order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int NOT NULL,
  `item_id` int NOT NULL,
  `quantity` int NOT NULL,
  `description` text NOT NULL,
  `expected_weight_kg` decimal(10,2) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT '0.00',
  `total_price` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `purchase_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int NOT NULL,
  `container_id` int DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `status` enum('draft','pending','approved','received','cancelled') DEFAULT 'draft',
  `total_amount` decimal(12,2) DEFAULT '0.00',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `container_id` (`container_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`container_id`) REFERENCES `containers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `return_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `return_id` int NOT NULL,
  `invoice_item_id` int NOT NULL,
  `item_id` int NOT NULL,
  `barcode_id` int NOT NULL,
  `quantity_returned` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_refund` decimal(10,2) NOT NULL,
  `condition_notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `return_id` (`return_id`),
  KEY `invoice_item_id` (`invoice_item_id`),
  KEY `item_id` (`item_id`),
  KEY `barcode_id` (`barcode_id`),
  CONSTRAINT `return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_items_ibfk_2` FOREIGN KEY (`invoice_item_id`) REFERENCES `invoice_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_items_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_items_ibfk_4` FOREIGN KEY (`barcode_id`) REFERENCES `barcodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `returns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `return_number` varchar(50) NOT NULL,
  `original_invoice_id` int NOT NULL,
  `store_id` int NOT NULL,
  `return_reason` text,
  `total_amount` decimal(10,2) NOT NULL,
  `return_type` enum('full','partial') DEFAULT 'partial',
  `status` enum('pending','approved','rejected','processed') DEFAULT 'pending',
  `processed_by` int DEFAULT NULL,
  `return_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_date` timestamp NULL DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_number` (`return_number`),
  KEY `original_invoice_id` (`original_invoice_id`),
  KEY `processed_by` (`processed_by`),
  KEY `idx_returns_store` (`store_id`),
  KEY `idx_returns_date` (`return_date`),
  CONSTRAINT `returns_ibfk_1` FOREIGN KEY (`original_invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `returns_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `returns_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `store_inventory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `store_id` int NOT NULL,
  `item_id` int NOT NULL,
  `barcode_id` int NOT NULL,
  `current_stock` int DEFAULT '0',
  `minimum_stock` int DEFAULT '0',
  `selling_price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `location_in_store` varchar(100) DEFAULT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `aisle` varchar(20) DEFAULT NULL,
  `shelf` varchar(20) DEFAULT NULL,
  `bin` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_store_item_barcode` (`store_id`,`item_id`,`barcode_id`),
  KEY `barcode_id` (`barcode_id`),
  KEY `idx_store_inventory_store` (`store_id`),
  KEY `idx_store_inventory_item` (`item_id`),
  CONSTRAINT `store_inventory_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_inventory_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_inventory_ibfk_3` FOREIGN KEY (`barcode_id`) REFERENCES `barcodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `store_item_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `store_id` int NOT NULL,
  `item_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_store_item` (`store_id`,`item_id`),
  KEY `assigned_by` (`assigned_by`),
  KEY `idx_store_assignments_store` (`store_id`),
  KEY `idx_store_assignments_item` (`item_id`),
  KEY `idx_store_assignments_active` (`is_active`),
  CONSTRAINT `store_item_assignments_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_item_assignments_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_item_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `stores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `store_code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text,
  `phone` varchar(20) DEFAULT NULL,
  `manager_id` int DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_code` (`store_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `subcategories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `transfer_boxes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `box_number` int NOT NULL,
  `box_label` varchar(255) DEFAULT '',
  `total_items` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_box_per_shipment` (`shipment_id`,`box_number`),
  KEY `idx_transfer_boxes_shipment` (`shipment_id`),
  CONSTRAINT `transfer_boxes_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `transfer_shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `transfer_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `box_id` int DEFAULT NULL,
  `item_id` int NOT NULL,
  `barcode_id` int NOT NULL,
  `quantity_requested` int NOT NULL,
  `quantity_packed` int DEFAULT '0',
  `quantity_received` int DEFAULT '0',
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `barcode_id` (`barcode_id`),
  KEY `idx_transfer_items_shipment` (`shipment_id`),
  KEY `idx_transfer_items_item` (`item_id`),
  KEY `idx_transfer_items_box` (`box_id`),
  CONSTRAINT `transfer_items_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `transfer_shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transfer_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transfer_items_ibfk_3` FOREIGN KEY (`barcode_id`) REFERENCES `barcodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transfer_items_ibfk_4` FOREIGN KEY (`box_id`) REFERENCES `transfer_boxes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `transfer_shipments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_number` varchar(50) NOT NULL,
  `source_store_id` int NOT NULL,
  `destination_store_id` int NOT NULL,
  `total_items` int DEFAULT '0',
  `status` enum('pending','in_transit','received','cancelled') DEFAULT 'pending',
  `transfer_type` enum('box','direct') DEFAULT NULL,
  `notes` text,
  `created_by` int NOT NULL,
  `packed_by` int DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `packed_at` timestamp NULL DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipment_number` (`shipment_number`),
  KEY `created_by` (`created_by`),
  KEY `packed_by` (`packed_by`),
  KEY `received_by` (`received_by`),
  KEY `idx_transfer_shipments_source` (`source_store_id`),
  KEY `idx_transfer_shipments_destination` (`destination_store_id`),
  KEY `idx_transfer_shipments_status` (`status`),
  CONSTRAINT `transfer_shipments_ibfk_1` FOREIGN KEY (`source_store_id`) REFERENCES `stores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transfer_shipments_ibfk_2` FOREIGN KEY (`destination_store_id`) REFERENCES `stores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transfer_shipments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transfer_shipments_ibfk_4` FOREIGN KEY (`packed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transfer_shipments_ibfk_5` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','inventory_manager','transfer_manager','store_manager','sales_person','viewer') NOT NULL,
  `store_id` int DEFAULT NULL,
  `manager_password` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `store_id` (`store_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `warehouse_boxes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `box_number` varchar(50) NOT NULL,
  `box_name` varchar(255) NOT NULL,
  `box_type` varchar(100) DEFAULT NULL,
  `quantity` int DEFAULT 0,
  `unit_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'Unit cost per box in CFA currency format',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `box_number` (`box_number`),
  KEY `created_by` (`created_by`),
  KEY `idx_warehouse_boxes_number` (`box_number`),
  CONSTRAINT `warehouse_boxes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `container_boxes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `container_id` int NOT NULL,
  `box_type` enum('existing','new') NOT NULL DEFAULT 'existing',
  `warehouse_box_id` int DEFAULT NULL,
  `new_box_number` varchar(50) DEFAULT NULL,
  `new_box_name` varchar(255) DEFAULT NULL,
  `new_box_type` varchar(100) DEFAULT NULL,
  `new_box_notes` text,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'Unit cost per box in CFA currency format',
  `is_processed` tinyint(1) DEFAULT '0',
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `container_id` (`container_id`),
  KEY `warehouse_box_id` (`warehouse_box_id`),
  KEY `box_type` (`box_type`),
  KEY `is_processed` (`is_processed`),
  CONSTRAINT `container_boxes_ibfk_1` FOREIGN KEY (`container_id`) REFERENCES `containers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `container_boxes_ibfk_2` FOREIGN KEY (`warehouse_box_id`) REFERENCES `warehouse_boxes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `container_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `container_id` int NOT NULL,
  `item_type` enum('existing_item','new_item') NOT NULL DEFAULT 'existing_item',
  `item_id` int DEFAULT NULL,
  `quantity_in_container` int NOT NULL DEFAULT '1',
  `is_processed` tinyint(1) DEFAULT '0',
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ci_container` (`container_id`),
  KEY `fk_ci_item` (`item_id`),
  KEY `fk_ci_processed_by` (`processed_by`),
  CONSTRAINT `fk_ci_container` FOREIGN KEY (`container_id`) REFERENCES `containers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ci_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ci_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `container_item_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `container_item_id` int NOT NULL,
  `name` varchar(200) NOT NULL,
  `code` varchar(100) DEFAULT NULL,
  `description` text,
  `category_id` int DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `size` varchar(20) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `material` varchar(100) DEFAULT NULL,
  `unit_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_cid_container_item` (`container_item_id`),
  KEY `fk_cid_category` (`category_id`),
  CONSTRAINT `fk_cid_container_item` FOREIGN KEY (`container_item_id`) REFERENCES `container_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cid_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;