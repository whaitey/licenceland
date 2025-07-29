# LicenceLand - Unified E-commerce Solution

**Version:** 1.0.8  
**Author:** ZeusWeb  
**Requires:** WordPress 5.0+, WooCommerce 5.0+, PHP 7.4+

A comprehensive WordPress plugin that combines CD Key management and dual shop functionality (Lakossági/Üzleti) into a unified e-commerce solution with advanced WooCommerce integration.

## Features

### 🎮 CD Key Management
- **Product Integration**: Add CD keys directly to WooCommerce products
- **Automatic Assignment**: Keys are automatically assigned to orders
- **Stock Management**: Real-time stock tracking with low stock alerts
- **Email Integration**: CD keys are automatically included in order emails
- **Admin Interface**: Easy management of CD keys through WordPress admin
- **Retroactive Assignment**: Assign keys to existing orders
- **Usage Tracking**: Comprehensive logging of CD key usage
- **Backorder System**: Automatic handling of out-of-stock orders with CD key delivery when stock is replenished

### 🏪 Dual Shop System
- **Shop Types**: Support for "Consumer" (Lakossági) and "Business" (Üzleti) shops
- **Price Management**: Different pricing for each shop type
- **Product Availability**: Control which products are available in each shop
- **Payment Gateways**: Filter payment methods based on shop type
- **Elementor Integration**: Automatic header/footer switching
- **Cart Management**: Separate cart handling for different shop types
- **IP/Email Blocking**: Advanced blocking system for unwanted users

### 🔄 GitHub Updates
- **Automatic Updates**: Seamless updates directly from GitHub releases
- **Plugin Update Checker**: Uses the official Plugin Update Checker library for reliable updates
- **Version Management**: Automatic version comparison and update notifications
- **Release Tracking**: Monitors GitHub releases for new versions

### ⚙️ Advanced Features
- **Unified Settings**: Single admin interface for all plugin features
- **Internationalization**: Full translation support with .pot file
- **Security**: Nonce verification, input sanitization, and data validation
- **Performance**: Optimized database queries and caching
- **Clean Uninstall**: Complete data cleanup on plugin removal
- **Payment-Based Orders**: Orders are only created after successful payment completion

## Requirements

- **WordPress**: 5.0 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher

## Installation

### Method 1: Manual Installation
1. Download the plugin files
2. Upload to `/wp-content/plugins/licenceland/`
3. Activate the plugin through WordPress admin
4. Configure settings in **LicenceLand > Settings**

### Method 2: GitHub Installation
1. Clone the repository: `git clone https://github.com/whaitey/licenceland.git`
2. Upload to `/wp-content/plugins/licenceland/`
3. Activate the plugin through WordPress admin
4. Updates will be available automatically through WordPress admin

## Configuration

### General Settings
Navigate to **LicenceLand > Settings** to configure:

- **Plugin Status**: Enable/disable individual features
- **Update Settings**: Configure GitHub update preferences
- **GitHub Token**: Set personal access token for private repository updates
- **Logging**: Enable/disable debug logging

### CD Key Settings
- **Email Templates**: Customize CD key email content
- **Stock Alerts**: Configure low stock notification thresholds
- **Key Format**: Set custom CD key formats and validation

### Dual Shop Settings
- **Elementor Templates**: Set header/footer template IDs for each shop type
- **Payment Gateways**: Configure which payment methods are available per shop type
- **Blocking Lists**: Manage IP addresses and email domains to block

## Usage

### Managing CD Keys
1. Edit any WooCommerce product
2. Navigate to the "CD Keys" tab
3. Add CD keys in the provided textarea
4. Save the product
5. Keys will be automatically assigned to orders

### Backorder Management
1. When a product runs out of CD keys, orders are automatically placed in backorder
2. Customers receive a notification email about the backorder
3. When new CD keys are added to the product, backorders are automatically processed
4. CD keys are sent to customers via email
5. Admin can manually process backorders from the product editor
6. View backorder status in the "Backorders" tab of each product

### Dual Shop Functionality
1. Configure shop type settings in **LicenceLand > Settings > Dual Shop**
2. Set up Elementor templates for each shop type
3. Configure payment gateway availability
4. The plugin will automatically handle shop switching based on user selection

### IP Search Tool
1. Navigate to **LicenceLand > IP Search**
2. Enter an IP address to search
3. View order history and shop type usage for that IP

## API Reference

### Hooks and Filters

#### Actions
```php
// Fired when a CD key is assigned to an order
do_action('licenceland_cd_key_assigned', $order_id, $product_id, $cd_key);

// Fired when shop type changes
do_action('licenceland_shop_type_changed', $new_shop_type, $old_shop_type);

// Fired when plugin is activated
do_action('licenceland_activated');
```

#### Filters
```php
// Filter CD key email content
apply_filters('licenceland_cd_key_email_content', $content, $order, $cd_keys);

// Filter shop type determination
apply_filters('licenceland_determine_shop_type', $shop_type, $user_id);

// Filter product availability per shop type
apply_filters('licenceland_product_available_in_shop', $available, $product_id, $shop_type);
```

### Functions
```php
// Get current shop type
$shop_type = licenceland_get_current_shop_type();

// Check if product has CD keys
$has_keys = licenceland_product_has_cd_keys($product_id);

// Get CD keys for an order
$cd_keys = licenceland_get_order_cd_keys($order_id);

// Switch shop type
licenceland_switch_shop_type($new_shop_type);
```

## Troubleshooting

### Common Issues

**CD Keys not appearing in emails:**
- Check email template settings in **LicenceLand > Settings > CD Keys**
- Verify that the product has CD keys assigned
- Check if the order status triggers email sending

**Dual shop not switching:**
- Verify Elementor template IDs are correct
- Check payment gateway configuration
- Clear any caching plugins

**Updates not working:**
- Ensure the GitHub repository URL is correct
- Check if the repository has releases with proper version tags
- Verify server can access GitHub API

### Debug Mode
Enable debug logging in **LicenceLand > Settings > General** to troubleshoot issues.

## Changelog

### 1.0.8
- **Fixed**: Parsedown class not found error in Plugin Update Checker
- **Enhanced**: Manual loading of Parsedown dependency for release notes parsing
- **Improved**: Update checker reliability and error handling

### 1.0.7
- **Removed**: GitHub token functionality - no longer needed for public repository
- **Simplified**: Update checker configuration for public repository access
- **Cleaned**: Removed unnecessary authentication code and settings

### 1.0.6
- **Added**: Payment-based order creation - orders are only created after successful payment
- **Enhanced**: Security by preventing orders from being created before payment completion
- **Improved**: Order management with better payment flow integration

### 1.0.5
- **Added**: GitHub token support for private repository updates
- **Enhanced**: Update checker reliability and security
- **Improved**: Repository visibility management

### 1.0.4
- **Added**: GitHub token support for private repository updates
- **Enhanced**: Update checker reliability and security
- **Improved**: Repository visibility management

### 1.0.3
- **Added**: Backorder system for CD keys
- **Enhanced**: Automatic backorder processing when stock is replenished
- **Added**: Manual backorder management in admin interface
- **Improved**: Email notifications for backorders

### 1.0.2
- **Added**: Official Plugin Update Checker library integration
- **Improved**: GitHub update reliability and error handling
- **Enhanced**: Update notification system

### 1.0.1
- **Added**: Comprehensive documentation
- **Added**: Language template (.pot file)
- **Added**: Clean uninstall script
- **Added**: .gitignore file

### 1.0.0
- **Initial Release**: Combined CD Keys and Dual Shop functionality
- **Added**: Unified admin interface
- **Added**: GitHub update checker
- **Added**: Enhanced security features

## Support

- **GitHub Issues**: [Create an issue](https://github.com/whaitey/licenceland/issues)
- **Documentation**: [View documentation](https://github.com/whaitey/licenceland/wiki)
- **Contact**: Reach out to ZeusWeb for custom development

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- **Author**: ZeusWeb
- **WordPress Integration**: Built with WordPress best practices
- **WooCommerce Integration**: Advanced e-commerce functionality
- **Plugin Update Checker**: [YahnisElsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker)

---

**LicenceLand** - Your complete e-commerce solution for WordPress and WooCommerce.