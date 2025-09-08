<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$documents = UFSC_Documents::get_club_documents( $club_id );

// Get club info to display profile photo.
global $wpdb;
$settings = UFSC_SQL::get_settings();
$table    = $settings['table_clubs'];
$club     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $club_id ) );

require_once UFSC_CL_DIR . 'templates/partials/notice.php';
?>

<div class="ufsc-dashboard-header">
    <div class="ufsc-club-photo">
        <img src="<?php echo esc_url( ! empty( $club->profile_photo_url ) ? $club->profile_photo_url : 'https://via.placeholder.com/150?text=Club' ); ?>" alt="<?php esc_attr_e( 'Photo du club', 'ufsc-clubs' ); ?>" />
        <?php if ( ! empty( $club->profile_photo_url ) ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-remove-photo-form">
                <?php wp_nonce_field( 'ufsc_remove_profile_photo', 'ufsc_remove_profile_photo_nonce' ); ?>
                <input type="hidden" name="action" value="ufsc_remove_profile_photo" />
                <input type="hidden" name="club_id" value="<?php echo esc_attr( $club_id ); ?>" />
                <button type="submit" class="button ufsc-remove-photo"><?php esc_html_e( 'Supprimer', 'ufsc-clubs' ); ?></button>
            </form>
        <?php endif; ?>
    </div>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ufsc-upload-photo-form">
        <?php wp_nonce_field( 'ufsc_upload_profile_photo', 'ufsc_upload_profile_photo_nonce' ); ?>
        <input type="hidden" name="action" value="ufsc_upload_profile_photo" />
        <input type="hidden" name="club_id" value="<?php echo esc_attr( $club_id ); ?>" />
        <input type="hidden" name="MAX_FILE_SIZE" value="5242880" />
        <input type="file" name="profile_photo" accept=".jpg,.png,.webp" />
        <button type="submit" class="button ufsc-upload-photo">
            <?php echo ! empty( $club->profile_photo_url ) ? esc_html__( 'Changer la photo', 'ufsc-clubs' ) : esc_html__( 'Ajouter une photo', 'ufsc-clubs' ); ?>
        </button>
    </form>
</div>

<div class="ufsc-documents-grid">
    <?php if ( ! empty( $documents ) ) : ?>
        <?php foreach ( $documents as $doc ) :
            $nonce        = wp_create_nonce( 'ufsc_download_doc_' . $doc->id );
            $download_url = add_query_arg(
                array(
                    'ufsc_doc' => $doc->id,
                    'nonce'    => $nonce,
                ),
                home_url( '/' )
            );
            $extension    = strtoupper( pathinfo( $doc->file_name, PATHINFO_EXTENSION ) );
            $size         = size_format( (int) $doc->file_size );
            $status_text  = ( 'pending' === $doc->status ) ? '⏳ En cours' : '✅ Transmis';
            $status_class = ( 'pending' === $doc->status ) ? 'ufsc-status-pending' : 'ufsc-status-sent';
        ?>
            <div class="ufsc-doc-item">
                <span class="dashicons <?php echo esc_attr( UFSC_Documents::get_file_icon( $doc->mime_type ) ); ?> ufsc-doc-icon" aria-hidden="true"></span>
                <span class="ufsc-doc-title"><?php echo esc_html( $doc->file_name ); ?></span>
                <span class="ufsc-doc-meta"><?php echo esc_html( $size . ' - ' . $extension ); ?></span>
                <div class="ufsc-doc-footer">
                    <span class="ufsc-doc-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span>
                    <a href="<?php echo esc_url( $download_url ); ?>" class="button ufsc-doc-download"><?php esc_html_e( 'Télécharger', 'ufsc-clubs' ); ?></a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p><?php esc_html_e( 'Aucun document disponible.', 'ufsc-clubs' ); ?></p>
    <?php endif; ?>
</div>

<style>
.ufsc-documents-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:20px;
}
@media(max-width:1024px){
    .ufsc-documents-grid{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:600px){
    .ufsc-documents-grid{grid-template-columns:1fr;}
}
.ufsc-doc-item{
    padding:20px;
    border:1px solid #ddd;
    border-radius:4px;
    text-align:center;
    display:flex;
    flex-direction:column;
    gap:8px;
}
.ufsc-doc-icon{
    font-size:40px;
    display:block;
    margin:0 auto 10px;
}
.ufsc-doc-meta{font-size:13px;color:#555;}
.ufsc-doc-footer{
    margin-top:auto;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.ufsc-doc-status{
    padding:2px 6px;
    border-radius:4px;
    font-size:12px;
}
.ufsc-status-sent{background:#d4edda;color:#155724;}
.ufsc-status-pending{background:#fff3cd;color:#856404;}
.ufsc-dashboard-header{
    margin-bottom:20px;
    display:flex;
    align-items:center;
    gap:20px;
}
.ufsc-club-photo img{
    max-width:150px;
    height:auto;
    display:block;
}
.ufsc-upload-photo-form,
.ufsc-remove-photo-form{
    margin-top:10px;
}
</style>
