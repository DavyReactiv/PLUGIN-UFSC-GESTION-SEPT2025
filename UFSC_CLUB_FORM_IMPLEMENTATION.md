# UFSC Club Form Implementation

## Overview
This implementation adds a complete frontend Club Form to the UFSC plugin with secure handling, i18n support, and compatibility with existing SQL mapping.

## Shortcode Usage

### Basic Usage
```php
[ufsc_club_form]
```

### With Parameters
```php
// For affiliation mode (stricter validation + WooCommerce integration)
[ufsc_club_form affiliation="1"]

// For editing existing club (permission-controlled)
[ufsc_club_form club_id="123"]

// Combined
[ufsc_club_form affiliation="1" club_id="456"]
```

## Features Implemented

### 1. Form Sections
- **General Information**: Name, region, address, contact details
- **Logo & Web**: Logo upload, website, social media links
- **Legal & Financial**: SIREN, RNA, IBAN, declaration details
- **Legal Documents**: Statuts, Récépissé, JO, PV AG, CER, Attestation CER
- **Dirigeants**: President, Secretary, Treasurer, Coach (optional)
- **User Association**: For affiliation mode - current/create/existing user
- **Admin Fields**: Status, quota (admin-only)

### 2. Validation
- **Required Fields**: All essential club information
- **Format Validation**: Email, dates, postal codes, IBAN
- **File Validation**: MIME types, file sizes
- **Dirigeants**: Required president, secretary, treasurer info
- **Affiliation Mode**: Additional required documents

### 3. File Uploads
- **Logo**: JPG, PNG, GIF up to 2MB
- **Documents**: PDF, JPG, PNG up to 5MB
- **Security**: MIME type validation, WordPress media handling
- **Storage**: URLs saved to database, attachment IDs managed

### 4. Permissions
- **Create**: Must be logged in
- **Edit**: Admin OR club responsable_id
- **User Association**: Create new users for affiliation

### 5. User Experience
- **Responsive Design**: Mobile-friendly interface
- **Conditional Fields**: Show/hide based on selections
- **Form Validation**: Real-time client-side + server-side
- **Status Messages**: Success/error feedback
- **Accessibility**: ARIA labels, screen reader support

## Database Integration

### Document Fields Added
```php
'doc_statuts' => 'Document Statuts',
'doc_recepisse' => 'Document Récépissé', 
'doc_jo' => 'Document JO',
'doc_pv_ag' => 'Document PV AG',
'doc_cer' => 'Document CER',
'doc_attestation_cer' => 'Document Attestation CER'
```

### Status Management
- Default status: 'en_attente' (or first available)
- Frontend users get pending status
- Admins can set any status

## Security Features

### Input Sanitization
- `wp_unslash()` + `sanitize_text_field()`
- `sanitize_email()` for emails
- `esc_url_raw()` for URLs
- `sanitize_user()` for usernames

### File Upload Security
- MIME type validation via `wp_check_filetype_and_ext()`
- File size limits enforced
- WordPress media system integration
- No direct trust of `$_FILES['type']`

### Permissions
- Nonce verification: `wp_verify_nonce()`
- User capability checks
- Club ownership validation
- Admin-only features protected

## WooCommerce Integration

For affiliation mode with WooCommerce:
```php
// Add club to cart and redirect to checkout
if (function_exists('ufsc_add_affiliation_to_cart') && function_exists('wc_get_checkout_url')) {
    ufsc_add_affiliation_to_cart($club_id);
    wp_safe_redirect(wc_get_checkout_url());
}
```

## Notifications

### Admin Notifications
- Email sent to admin for new non-affiliation clubs
- Contains basic club information
- Links to admin for validation

### User Notifications  
- New user creation sends password email
- Success/error messages with redirects
- Transient-based messaging system

## Internationalization

All user-facing strings use WordPress i18n:
```php
__('Text to translate', 'ufsc-clubs')
esc_html__('Text to translate', 'ufsc-clubs')
```

Text domain: `ufsc-clubs`

## File Structure

```
includes/
├── frontend/
│   ├── class-club-form.php           # Form rendering & shortcode
│   └── class-club-form-handler.php   # Form processing & validation
├── core/
│   ├── class-permissions.php         # Permission checking
│   ├── class-uploads.php            # Secure file handling
│   ├── class-utils.php              # Enhanced validation
│   └── class-sql.php                # Updated with document fields

assets/frontend/
├── css/
│   └── ufsc-club-form.css           # Form styling
└── js/
    └── ufsc-club-form.js            # Conditional fields & validation
```

## Form Processing Flow

1. **Form Submission** → `admin_post_ufsc_save_club`
2. **Nonce Verification** → Security check
3. **Permission Check** → Create/edit permissions
4. **Data Collection** → Sanitize all inputs
5. **Validation** → Required fields + formats
6. **File Uploads** → Secure upload handling
7. **User Association** → Handle user creation/linking
8. **Database Save** → Insert/update club record
9. **Post-Save Actions** → Notifications, WooCommerce
10. **Redirect** → Success/error messaging

## Testing Completed

✅ All PHP files syntax validation  
✅ Class loading verification  
✅ Document fields integration  
✅ WordPress hooks registration  
✅ Form structure validation  

## Usage Examples

### Simple Club Creation
```php
// In a page or post
[ufsc_club_form]
```

### Affiliation Request
```php
// Stricter validation + WooCommerce flow
[ufsc_club_form affiliation="1"]
```

### Edit Existing Club
```php
// Permission-controlled editing
[ufsc_club_form club_id="123"]
```

## Administrative Integration

The form integrates seamlessly with the existing admin interface:
- Uses same field definitions from `UFSC_SQL::get_club_fields()`
- Respects same status values from `UFSC_SQL::statuses()`
- Follows same validation patterns
- Stores in same database table structure

## Future Enhancements

The implementation includes hooks for extensibility:
```php
// Custom actions after club save
do_action('ufsc_club_saved', $club_id, $affiliation, $is_edit);

// Custom field filtering
apply_filters('ufsc_club_fields', $fields);
apply_filters('ufsc_regions_list', $regions);
```