-- Simple Shift Management Table
-- This table tracks user shifts with persistence beyond sessions

CREATE TABLE IF NOT EXISTS shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    store_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    status ENUM('active', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Add foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    
    -- Ensure only one active shift per user at a time
    UNIQUE KEY unique_active_shift (user_id, status),
    
    -- Index for performance
    INDEX idx_user_store_time (user_id, store_id, start_time),
    INDEX idx_store_active (store_id, status)
);

-- Add some helpful comments
ALTER TABLE shifts COMMENT = 'Tracks user work shifts with database persistence';
