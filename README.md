# Inventory and Maintenance Management System

A comprehensive system for managing inventory items, assets, maintenance schedules, and work orders.

## Features

- **Inventory Management**: Track stock levels, transactions, and locations
- **Asset Management**: Track equipment and assets requiring maintenance
- **Maintenance Scheduling**: Set up recurring maintenance tasks
- **Work Order Management**: Create and track repair and maintenance tasks
- **Reporting**: View dashboards with critical information

## Technical Stack

- **Backend**: PHP (compatible with PHP 7.4+)
- **Database**: MySQL with MySQLi extension
- **Frontend**: Bootstrap 5, jQuery, DataTables, Chart.js
- **Authentication**: Session-based

## Installation

### Prerequisites

- Web server (Apache/Nginx)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (optional, for future dependencies)

### Steps

1. **Clone the Repository**

   ```bash
   git clone <repository-url>
   cd mali-inventory
   ```

2. **Database Setup**

   - Create a MySQL database
   - Import the database schema:

   ```bash
   mysql -u username -p your_database_name < database.sql
   ```

3. **Configuration**

   - Edit `includes/db.php` with your database credentials:

   ```php
   $db_host = 'your_database_host';
   $db_user = 'your_database_username';
   $db_password = 'your_database_password';
   $db_name = 'your_database_name';
   ```

4. **Web Server Configuration**

   - Configure your web server to point to the project directory
   - Ensure the `htdocs` directory is the document root

5. **First Login**

   - Default credentials: 
     - Username: `admin`
     - Password: `admin123`
   - **IMPORTANT**: Change the default password immediately after first login

## File Structure

```
/mali-inventory/
├── admin/                      # Admin functionality
│   ├── dashboard.php           # Admin dashboard
│   ├── inventory.php           # Inventory management
│   ├── assets.php              # Asset management
│   ├── maintenance.php         # Maintenance scheduling
│   └── ...
├── ajax/                       # AJAX handlers
│   ├── get_inventory.php       # Inventory data
│   ├── process_inventory.php   # Process inventory actions
│   └── ...
├── assets/                     # Static resources
│   ├── css/
│   ├── js/
│   └── images/
├── includes/                   # Shared components
│   ├── db.php                  # Database connection
│   ├── functions.php           # Helper functions
│   ├── header.php              # Common header
│   ├── footer.php              # Common footer
│   └── ...
├── database.sql                # Database schema
├── index.php                   # Login page
└── README.md                   # This file
```

## Usage

### Inventory Management

1. Navigate to **Inventory** from the sidebar
2. Add new items, update stock levels, or manage existing items
3. Filter and search for specific items

### Asset Management

1. Navigate to **Assets** from the sidebar
2. Add new assets, update information, or retire old assets
3. View maintenance history and schedule maintenance tasks

### Maintenance Scheduling

1. Navigate to **Maintenance** from the sidebar
2. Create new maintenance schedules for assets
3. View upcoming and overdue maintenance tasks

### Work Orders

1. Navigate to **Work Orders** from the sidebar
2. Create new work orders for corrective or preventive maintenance
3. Track progress and update status of work orders

## Troubleshooting

- **Database Connection Issues**: Check your database credentials in `includes/db.php`
- **Page Not Found Errors**: Ensure your web server is correctly configured
- **Permission Issues**: Check file permissions (755 for directories, 644 for files)

## Security Recommendations

- Change the default admin password immediately
- Use HTTPS for production environments
- Regularly backup your database
- Keep PHP and all dependencies updated

## License

This project is proprietary and confidential.

## Support

For support or questions, please contact:
- Email: support@example.com
- Phone: +123-456-7890 