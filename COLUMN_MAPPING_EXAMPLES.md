# UFSC Column Mapping System Usage Examples

This document provides examples of how to use the new column mapping system introduced in this update.

## Filter Hook Examples

### Customizing Club Column Mappings

To change the phone column from 'telephone' to 'tel':

```php
add_filter('ufsc_clubs_columns_map', function($map) {
    $map['phone'] = 'tel'; 
    return $map; 
});
```

To change multiple club columns:

```php
add_filter('ufsc_clubs_columns_map', function($map) {
    $map['phone'] = 'tel';
    $map['manager_user_id'] = 'user_responsable';
    $map['validated'] = 'is_validated';
    return $map;
});
```

### Customizing Licence Column Mappings

To change season column from 'season' to 'saison':

```php
add_filter('ufsc_licences_columns_map', function($map) {
    $map['season'] = 'saison';
    return $map;
});
```

To change payment-related columns:

```php
add_filter('ufsc_licences_columns_map', function($map) {
    $map['season'] = 'saison';
    $map['paid_flag'] = 'is_paid';
    $map['paid_season'] = 'season_paid';
    return $map;
});
```

## Default Mappings

### Clubs Table
- id => 'id'
- name => 'nom'
- email => 'email'
- phone => 'telephone'
- region => 'region'
- city => 'ville'
- zipcode => 'code_postal'
- address => 'adresse'
- status => 'statut'
- validated => 'validated'
- created_at => 'date_creation'
- manager_user_id => 'responsable_id'

### Licences Table
- id => 'id'
- club_id => 'club_id'
- first_name => 'prenom'
- last_name => 'nom'
- email => 'email'
- status => 'statut'
- season => 'season'
- paid_flag => 'is_paid'
- paid_season => 'paid_season'
- created_at => 'date_inscription'

## Function Usage

### Direct Column Lookups

```php
// Get the actual database column name for a logical key
$name_column = ufsc_club_col('name'); // Returns 'nom'
$email_column = ufsc_club_col('email'); // Returns 'email'
$phone_column = ufsc_club_col('phone'); // Returns 'telephone' (or 'tel' if filtered)

$first_name_column = ufsc_lic_col('first_name'); // Returns 'prenom'
$status_column = ufsc_lic_col('status'); // Returns 'statut'
```

### Safe Column Existence Checking

```php
// Check if a column exists and get mapped name
$clubs_table = ufsc_get_clubs_table();
$phone_col = ufsc_get_mapped_column_if_exists($clubs_table, 'phone', 'clubs');

if ($phone_col) {
    // Column exists, safe to use in queries
    $query = "SELECT {$phone_col} FROM {$clubs_table} WHERE id = %d";
} else {
    // Column doesn't exist, handle gracefully
    error_log('Phone column not found in clubs table');
}
```

## Use Cases

### Database Migration Compatibility

When your database uses different column names:

```php
// If your database uses 'tel' instead of 'telephone'
add_filter('ufsc_clubs_columns_map', function($map) {
    $map['phone'] = 'tel';
    return $map;
});

// If your database uses 'saison' instead of 'season'
add_filter('ufsc_licences_columns_map', function($map) {
    $map['season'] = 'saison';
    return $map;
});
```

### Multi-language Databases

For databases with column names in different languages:

```php
// French column names
add_filter('ufsc_clubs_columns_map', function($map) {
    $map['address'] = 'adresse_complete';
    $map['city'] = 'ville_nom';
    $map['zipcode'] = 'code_postal_fr';
    return $map;
});

add_filter('ufsc_licences_columns_map', function($map) {
    $map['first_name'] = 'prenom_fr';
    $map['last_name'] = 'nom_fr';
    $map['status'] = 'statut_fr';
    return $map;
});
```

### Legacy System Integration

When integrating with existing systems:

```php
// Legacy system compatibility
add_filter('ufsc_clubs_columns_map', function($map) {
    $map['manager_user_id'] = 'legacy_responsible_user_id';
    $map['validated'] = 'legacy_is_approved';
    return $map;
});
```

## Testing Your Mappings

You can test your mappings with simple debugging:

```php
// Debug club mappings
$clubs_map = ufsc_sql_columns_map('clubs');
error_log('Clubs mapping: ' . print_r($clubs_map, true));

// Debug licence mappings  
$licences_map = ufsc_sql_columns_map('licences');
error_log('Licences mapping: ' . print_r($licences_map, true));

// Test specific column lookups
error_log('Phone column: ' . ufsc_club_col('phone'));
error_log('Season column: ' . ufsc_lic_col('season'));
```