### UFSC Gestion Enhancement - Shortcode Usage Examples

This document provides examples of how to use the new shortcodes implemented in the UFSC Gestion plugin.

## Club Dashboard Shortcode

Display a comprehensive dashboard for club managers:

```
[ufsc_club_dashboard]
```

### Parameters:
- `show_sections` (optional): Comma-separated list of sections to display
  - Available: `basic`, `region`, `status`, `quota`
  - Default: `basic,region,status,quota`

### Examples:

**Full dashboard:**
```
[ufsc_club_dashboard]
```

**Only basic information:**
```
[ufsc_club_dashboard show_sections="basic"]
```

**Basic info and quota:**
```
[ufsc_club_dashboard show_sections="basic,quota"]
```

### Features:
- Shows club name and affiliation number
- Displays region, status, and quota information
- Responsive design with professional styling
- Security: Only shows data for the logged-in club manager
- Integrates with WooCommerce account pages if available

## Club Affiliation Form Shortcode

Display a form for creating new clubs:

```
[ufsc_affiliation_form]
```

### Parameters:
- `redirect_to` (optional): URL to redirect to after successful creation
- `show_title` (optional): Whether to show the form title (1 or 0, default: 1)

### Examples:

**Basic form:**
```
[ufsc_affiliation_form]
```

**Form with custom redirect:**
```
[ufsc_affiliation_form redirect_to="/mon-compte/tableau-de-bord/"]
```

**Form without title:**
```
[ufsc_affiliation_form show_title="0"]
```

### Features:
- Complete club creation form with validation
- Required fields: name, email, region
- Optional fields: address, postal code, city, phone
- Mandatory acceptance of terms and conditions
- Transactional and idempotent processing
- Automatic association with logged-in user
- Responsive design with modern styling

### Form Fields:
- **Club Name** (required)
- **Club Email** (required)
- **Region** (required, dropdown)
- **Address** (optional)
- **Postal Code** (optional)
- **City** (optional)
- **Phone** (optional)
- **Accept Terms** (required checkbox)

## Usage in WordPress

### In Pages/Posts
Add the shortcodes directly in the WordPress editor:

1. Edit your page/post
2. Add a shortcode block
3. Enter the shortcode with desired parameters

### In Templates
Use `do_shortcode()` in your theme templates:

```php
echo do_shortcode('[ufsc_club_dashboard]');
```

### With WooCommerce
The club dashboard automatically adds a "Mon Club" tab to WooCommerce My Account pages when WooCommerce is active.

## Security & Permissions

- **Club Dashboard**: Requires user login, only shows data for clubs where the user is the manager
- **Affiliation Form**: Requires user login, prevents duplicate clubs for the same user
- **Data Protection**: All inputs are sanitized and validated
- **Nonce Protection**: All forms use WordPress nonces for security

## Database Features

### Transaction Safety
- All club creation operations use database transactions
- Automatic retry on deadlocks with exponential backoff
- Idempotent operations prevent duplicate data

### Performance
- Database indexes for optimal query performance
- Unique constraints prevent data conflicts
- InnoDB engine for ACID compliance

## Admin Features

The enhanced admin lists provide:

### For Clubs:
- Advanced filtering by region, status, creation date, quota range
- Search by name or email
- Sortable columns (name, region, creation date)
- Pagination with configurable page sizes
- Document completeness badges

### For Licences:
- Filtering by club, region, status, payment status, category, gender
- Medical certificate filtering
- Date range filtering
- Search across name, email, licence number
- Enhanced display with club information

## CSS Customization

The shortcodes include built-in responsive styling. You can override styles in your theme:

```css
/* Dashboard customization */
.ufsc-club-dashboard {
    /* Your custom styles */
}

/* Form customization */
.ufsc-affiliation-form {
    /* Your custom styles */
}
```

## Error Handling

- Form validation errors are displayed clearly
- Database errors are logged and user-friendly messages shown
- Fallback behavior when user permissions are insufficient
- Graceful handling of missing data