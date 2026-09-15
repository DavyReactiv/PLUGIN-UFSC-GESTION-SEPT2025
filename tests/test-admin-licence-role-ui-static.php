<?php
$root  = dirname( __DIR__ );
$admin = file_get_contents( $root . '/includes/admin/class-ffst-licence-role-ui.php' );
$front = file_get_contents( $root . '/templates/frontend/licence-form.php' );

$failed = false;
$assert = static function ( $condition, $message ) use ( &$failed ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failed = true;
    }
};

$assert( false !== strpos( $admin, "add_action( 'wp_ajax_ufsc_admin_licence_roles'" ), 'admin table exposes one read-only AJAX role lookup' );
$assert( false !== strpos( $admin, 'AS licence_id, role FROM' ), 'role lookup reads the canonical licence role field' );
$assert( false !== strpos( $admin, "roleHeader.textContent='Rôle au club'" ), 'admin licences table receives the Role au club column' );
$assert( false !== strpos( $admin, 'identityGrid.insertBefore(roleWrap,identityGrid.firstElementChild)' ), 'admin licence role is moved to the beginning of identity fields' );

foreach ( array( 'president', 'secretaire', 'tresorier', 'dirigeant', 'entraineur', 'encadrant', 'responsable_technique', 'instructeur', 'coach', 'educateur', 'enseignant' ) as $role ) {
    $assert( false !== strpos( $admin, "'{$role}'" ), "admin role {$role} is supported" );
    $assert( false !== strpos( $front, "'{$role}'" ), "front role {$role} is supported" );
}

foreach ( array( 'ville_naissance', 'departement_naissance', 'pays_naissance' ) as $field ) {
    $assert( false !== strpos( $admin, "'{$field}'" ), "admin conditional field {$field} is reused" );
    $assert( false !== strpos( $front, "name=\"{$field}\"" ), "front conditional field {$field} is reused" );
}

$role_pos   = strpos( $front, 'name="role"' );
$prenom_pos = strpos( $front, 'name="prenom"' );
$assert( false !== $role_pos && false !== $prenom_pos && $role_pos < $prenom_pos, 'front role is rendered before identity input fields' );
$assert( false !== strpos( $front, "var leaderRoles = ['president','secretaire','tresorier','entraineur','dirigeant','encadrant','responsable_technique'" ), 'front birth-place visibility is role-driven' );
$assert( false !== strpos( $front, "field.style.display = required ? '' : 'none'" ), 'front hides birth-place fields for ordinary adherents' );
$assert( false !== strpos( $admin, 'input.required=required' ), 'admin requirement follows the selected leadership role' );

// This UI layer must remain read-only: no licence/club row mutation is allowed here.
$assert( 0 === preg_match( '/\$wpdb\s*->\s*(insert|update|delete|replace)\s*\(/i', $admin ), 'admin role UI performs no database row mutation' );
$assert( false === stripos( $admin, 'DELETE FROM' ), 'admin role UI contains no DELETE statement' );
$assert( false === stripos( $admin, 'TRUNCATE' ), 'admin role UI contains no TRUNCATE statement' );

if ( $failed ) {
    exit( 1 );
}

echo "OK admin licence role UI\n";
