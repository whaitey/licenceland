# LicenceLand Plugin - TODO List

## 🚨 Critical Issues to Fix

### 1. Order Resend Email System
- **Status**: ✅ FIXED - Emails now being sent properly
- **Issue**: Order Resend functionality not working properly
- **Priority**: HIGH
- **Description**: 
  - ✅ Customer emails now being sent when using Order Resend
  - ✅ Improved WooCommerce email integration with better error handling
  - ✅ Added fallback email system using wp_mail
  - ✅ Added comprehensive debugging and testing tools
  - ✅ Enhanced email templates for better user experience

### 2. Parsedown Loading Issue
- **Status**: ⚠️ PARTIALLY FIXED - May still occur
- **Issue**: Parsedown class not found in Plugin Update Checker
- **Priority**: MEDIUM
- **Description**:
  - Refactored to bf-events structure but may need further testing
  - Monitor for any remaining Parsedown errors

## 🔧 Features to Implement

### 3. Rendelés Exportálás (Order Export)
- **Status**: ❌ NOT STARTED
- **Priority**: HIGH
- **Description**: 
  - Export orders to CSV/Excel format
  - Include all order data, customer info, CD keys
  - Filter by date range, status, shop type
  - Bulk export functionality

### 4. Csomag Lebontás Exportnál (Package Breakdown on Export)
- **Status**: ❌ NOT STARTED
- **Priority**: MEDIUM
- **Description**:
  - Show individual items when exporting orders
  - Break down bundled products
  - Include CD keys per item

### 5. Árukereső Integráció
- **Status**: ❌ NOT STARTED
- **Priority**: LOW
- **Description**:
  - Integration with Árukereső.hu
  - Product feed generation
  - Price synchronization

### 6. Pepita Integráció
- **Status**: ❌ NOT STARTED
- **Priority**: LOW
- **Description**:
  - Integration with Pepita.hu
  - Product feed generation
  - Price synchronization

### 7. Telepítők Elérhetősége (fájlok) - Installer Availability
- **Status**: ❌ NOT STARTED
- **Priority**: MEDIUM
- **Description**:
  - File management system for installers
  - Secure file downloads
  - Version control for installer files
  - Admin interface for file management

### 8. Számlázási Adatok Ellenőrzése (Fraud Detection)
- **Status**: ❌ NOT STARTED
- **Priority**: HIGH
- **Description**:
  - Check billing data for fraud on consumer side
  - Detect company names (KFT, BT, etc.) in consumer orders
  - Flag suspicious orders for review
  - Admin alerts for potential fraud

## 🎯 Additional Features

### 9. IP Cím Alapján Keresés Rendelések Között
- **Status**: ❌ NOT STARTED
- **Priority**: LOW
- **Description**:
  - Search orders by IP address
  - Track IP usage patterns
  - Fraud detection based on IP

### 10. Termék Megjelenés Kikapcsolási Lehetőség
- **Status**: ❌ NOT STARTED
- **Priority**: MEDIUM
- **Description**:
  - Hide/show products per shop type (Lakossági/Üzleti)
  - Product visibility controls
  - Category-based visibility

### 11. Termék Készlet Követés Email
- **Status**: ❌ NOT STARTED
- **Priority**: LOW
- **Description**:
  - Email notifications for stock changes
  - Low stock alerts
  - Out of stock notifications

### 12. Kulcs Sorrendben Történő Árusítása
- **Status**: ❌ NOT STARTED
- **Priority**: LOW
- **Description**:
  - Sequential CD key assignment
  - Track key usage order
  - Prevent duplicate key usage

## 🔄 Maintenance Tasks

### 13. Code Optimization
- **Status**: ⚠️ NEEDS REVIEW
- **Priority**: LOW
- **Description**:
  - Review and optimize database queries
  - Improve performance
  - Clean up unused code

### 14. Documentation Updates
- **Status**: ⚠️ NEEDS UPDATE
- **Priority**: LOW
- **Description**:
  - Update README with new features
  - Add API documentation
  - Create user guides

### 15. Testing
- **Status**: ❌ NEEDED
- **Priority**: MEDIUM
- **Description**:
  - Test all features thoroughly
  - Cross-browser testing
  - Performance testing
  - Security testing

## 📋 Completed Features ✅

- ✅ CD Key Management System
- ✅ Dual Shop System (Lakossági/Üzleti)
- ✅ Backorder System
- ✅ Payment-Based Order Creation
- ✅ Abandoned Cart Reminder System
- ✅ Order Resend System (UI implemented, email sending fixed)
- ✅ GitHub Update Checker Integration
- ✅ Plugin Update Checker Refactoring

## 🎯 Next Priority Actions

1. **Fix Order Resend Email System** - Critical for functionality
2. **Implement Order Export** - High business value
3. **Add Fraud Detection** - Security improvement
4. **Create Installer File Management** - User experience
5. **Test and optimize existing features** - Stability

## 📝 Notes

- All new features should follow WordPress coding standards
- Maintain backward compatibility
- Add proper error handling and logging
- Include translation support for all new features
- Test thoroughly before releasing 