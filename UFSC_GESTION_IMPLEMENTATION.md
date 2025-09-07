# UFSC Gestion Plugin - Implementation Guide

## Overview

This document outlines the implementation of the UFSC Gestion plugin enhancements as per the requirements. The implementation follows the specified architecture and provides all requested functionality with clear integration points for existing database schemas.

## Implemented Features

### 1. Updated Admin Menu ✅

- **Location**: `includes/admin/class-admin-menu.php`
- **Deprecated**: `inc/admin/menu.php` removed
- **Changes**: 
  - Updated main menu icon to `dashicons-groups`
  - Added WooCommerce submenu
  - Changed menu title to "UFSC Gestion"

### 2. Common Regions Module ✅

- **Location**: `inc/common/regions.php`
- **Functions**:
  - `ufsc_get_regions_labels()`: Returns exact list of 14 regions
  - `ufsc_is_valid_region($region)`: Validates region against approved list  
  - `ufsc_region_select_options($selected)`: Generates HTML select options
- **Regions List** (exactly as specified):
  - Auvergne-Rhône-Alpes UFSC
  - Bourgogne-Franche-Comté UFSC
  - Bretagne UFSC
  - Centre-Val de Loire UFSC
  - Corse UFSC
  - Grand Est UFSC
  - Hauts-de-France UFSC
  - Île-de-France UFSC
  - Normandie UFSC
  - Nouvelle-Aquitaine UFSC
  - Occitanie UFSC
  - Pays de la Loire UFSC
  - Provence-Alpes-Côte d'Azur UFSC
  - DROM-COM UFSC

### 3. Settings System ✅

#### Core Settings (`inc/settings.php`)
- Table name configuration with sanitization (A-Za-z0-9_ only)
- Default values: `{$wpdb->prefix}ufsc_clubs`, `{$wpdb->prefix}ufsc_licences`
- Admin interface for table configuration
- Regions display (read-only, sourced from common module)

#### WooCommerce Settings (`inc/woocommerce/settings-woocommerce.php`)
- Product IDs configuration (default: 4823 for affiliation, 2934 for licenses)
- Quota configuration (default: 10 included licenses per pack)
- Season configuration (default: "2025-2026")
- Product validation when WooCommerce is active
- Admin interface with real-time product verification

### 4. License Form Sanitizer ✅

- **Location**: `inc/form-license-sanitizer.php`
- **Function**: `ufsc_sanitize_licence_post($post_data, $club_id)`
- **Features**:
  - WordPress-compliant sanitization (`wp_unslash`, `sanitize_text_field`, etc.)
  - Email validation with `is_email()`
  - Date normalization (Y-m-d or d/m/Y → Y-m-d)
  - Gender validation (M|F, default M)
  - Boolean fields converted to 1/0
  - Phone sanitization (digits and + only)
  - Postal code sanitization (alphanumeric, spaces, hyphens)
  - Region validation against approved list
  - Dependency validation (licence_delegataire requires numero_licence_delegataire)
  - Required fields: nom, prenom (configurable)

### 5. WooCommerce Integration ✅

#### Order Processing Hooks (`inc/woocommerce/hooks.php`)
- Hooks: `woocommerce_order_status_processing`, `woocommerce_order_status_completed`
- **Affiliation Pack Processing**:
  - Marks affiliation as paid for season
  - Credits included licenses quota
- **Additional License Processing**:
  - Marks specific licenses as paid (if IDs provided)
  - Credits prepaid licenses for future use
- **Stub Functions** (ready for database integration):
  - `ufsc_get_user_club_id()`
  - `ufsc_mark_affiliation_paid()`
  - `ufsc_mark_licence_paid()`
  - `ufsc_quota_add_included()`
  - `ufsc_quota_add_paid()`

#### Cart Integration (`inc/woocommerce/cart-integration.php`)
- Add to cart via secure form posting to `admin-post.php` with action `ufsc_add_to_cart`
- Meta data transfer from cart to order
- Cart item display enhancements
- `ufsc_add_affiliation_to_cart()` function for programmatic use

#### Admin Actions (`inc/woocommerce/admin-actions.php`)
- Order creation for additional licenses
- Payment link generation
- Email notifications to club responsible users
- Admin form handler: `ufsc_handle_admin_send_to_payment()`

### 6. Quota Management ✅

- **Function**: `ufsc_should_charge_license($club_id, $season)`
- **Logic**: Compares available quota vs used licenses
- **Implementation**: Stub with clear documentation for database integration

### 7. Security Features ✅

- Nonce verification on all forms
- Capability checks (`current_user_can('manage_options')`)
- Prepared statements structure for database queries
- Input sanitization and validation
- CSRF protection

## Database Integration Points

The following functions are implemented as stubs and need to be connected to your existing database schema:

### User-Club Relationship
```php
ufsc_get_user_club_id($user_id) // Get club ID for user
ufsc_get_club_responsible_user_id($club_id) // Get responsible user for club
```

### Affiliation Management  
```php
ufsc_mark_affiliation_paid($club_id, $season) // Mark affiliation as paid
```

### License Management
```php
ufsc_mark_licence_paid($license_id, $season) // Mark specific license as paid
```

### Quota Management
```php
ufsc_quota_add_included($club_id, $quantity, $season) // Add included licenses
ufsc_quota_add_paid($club_id, $quantity, $season) // Add paid licenses
ufsc_should_charge_license($club_id, $season) // Check if quota exhausted
```

### Data Retrieval
```php
ufsc_get_club_name($club_id) // Get club name for display
```

## File Structure

```
inc/
├── admin/
│   ├── class-ufsc-gestion-clubs-list-table.php   # Admin table for clubs
│   └── class-ufsc-gestion-licences-list-table.php # Admin table for licences
├── common/
│   ├── regions.php                 # Unified regions management
│   └── tables.php                  # Table name helpers
├── woocommerce/
│   ├── settings-woocommerce.php    # WooCommerce configuration
│   ├── hooks.php                   # Order processing hooks
│   ├── admin-actions.php           # Admin payment actions
│   └── cart-integration.php        # Cart and URL integration
├── settings.php                    # Core settings management
└── form-license-sanitizer.php     # License data validation
```

## Configuration

### Default Settings
- **Tables**: `wp_ufsc_clubs`, `wp_ufsc_licences`
- **Products**: Affiliation (4823), License (2934)  
- **Quota**: 10 included licenses per pack
- **Season**: "2025-2026"

### Admin Access
- Main menu: Admin → UFSC Gestion
- Sub-menus: Tableau de bord, Clubs, Licences, Paramètres, WooCommerce

## Testing

Basic functionality tests are included and passing:
- ✅ Regions: 14 regions loaded and validated correctly
- ✅ License Sanitizer: All validation rules working
- ✅ WooCommerce Settings: Default values and saving functional
- ✅ PHP Syntax: All files syntactically correct

## Next Steps

1. **Connect Database Stubs**: Implement the stub functions according to your existing database schema
2. **WP_List_Table Integration**: Connect Clubs and Licences admin pages to actual data
3. **Frontend Integration**: Update existing forms to use new regions and sanitizer
4. **Testing**: Test with real WooCommerce environment
5. **Documentation**: Update user documentation with new features

## Compliance

- ✅ WordPress coding standards
- ✅ Security best practices (nonce, capabilities, sanitization)
- ✅ No schema changes required
- ✅ Backward compatibility maintained
- ✅ Minimal code changes approach
- ✅ Clear separation of concerns
- ✅ Extensible architecture