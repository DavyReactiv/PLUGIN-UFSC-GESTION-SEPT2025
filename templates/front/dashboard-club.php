<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$documents = UFSC_Documents::get_club_documents( $club_id );
?>
<div class="ufsc-documents-grid">
    <?php if ( ! empty( $documents ) ) : ?>
        <?php foreach ( $documents as $doc ) :
            $nonce = wp_create_nonce( 'ufsc_download_doc_' . $doc->id );
            $download_url = add_query_arg(
                array(
                    'ufsc_doc' => $doc->id,
                    'nonce'    => $nonce,
                ),
                home_url( '/' )
            );
        ?>
            <div class="ufsc-doc-item">
                <a href="<?php echo esc_url( $download_url ); ?>" class="ufsc-doc-link">
                    <span class="dashicons <?php echo esc_attr( UFSC_Documents::get_file_icon( $doc->mime_type ) ); ?> ufsc-doc-icon" aria-hidden="true"></span>
                    <span class="ufsc-doc-title"><?php echo esc_html( $doc->file_name ); ?></span>
                </a>
                <div class="ufsc-doc-actions">
                    <a href="<?php echo esc_url( $download_url ); ?>" class="ufsc-action" title="<?php esc_attr_e( 'Télécharger', 'ufsc-clubs' ); ?>">
                        <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    </a>
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
    position:relative;
    padding:20px;
    border:1px solid #ddd;
    border-radius:4px;
    text-align:center;
}
.ufsc-doc-icon{
    font-size:40px;
    display:block;
    margin-bottom:10px;
}
.ufsc-doc-actions{
    position:absolute;
    top:10px;
    right:10px;
    display:none;
}
.ufsc-doc-item:hover .ufsc-doc-actions{
    display:block;
}
</style>
