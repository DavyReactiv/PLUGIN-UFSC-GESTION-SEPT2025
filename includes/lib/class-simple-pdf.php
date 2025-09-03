<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Simple PDF generation utility for UFSC
 * Generates very basic PDFs without external dependencies
 */
class UFSC_Simple_PDF {
    /**
     * Generate a basic PDF file with provided lines of text.
     *
     * @param string $file_path Destination file path
     * @param array  $lines     Lines of text to include in PDF
     * @param string $title     Optional document title
     * @return bool             True on success
     */
    public static function generate( $file_path, $lines, $title = '' ) {
        $escape = function( $text ) {
            return str_replace( [ '\\', '(', ')' ], [ '\\\\', '\\(', '\\)' ], $text );
        };

        $content = "BT\n/F1 16 Tf\n50 780 Td\n";
        if ( ! empty( $title ) ) {
            $content .= '(' . $escape( $title ) . ') Tj\n0 -24 Td\n';
        }
        foreach ( $lines as $line ) {
            $content .= '(' . $escape( $line ) . ') Tj\n0 -18 Td\n';
        }
        $content .= "ET";

        $objects = [];
        $objects[] = "<</Type/Catalog/Pages 2 0 R>>"; // 1: Catalog
        $objects[] = "<</Type/Pages/Count 1/Kids[3 0 R]>>"; // 2: Pages
        $objects[] = "<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>"; // 3: Page
        $objects[] = "<</Length " . strlen( $content ) . ">>stream\n$content\nendstream"; // 4: Contents
        $objects[] = "<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>"; // 5: Font

        $pdf = "%PDF-1.4\n";
        $offsets = [ 0 ];
        foreach ( $objects as $i => $obj ) {
            $offsets[] = strlen( $pdf );
            $pdf .= ( $i + 1 ) . " 0 obj\n$obj\nendobj\n";
        }
        $xref_pos = strlen( $pdf );
        $pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ( $i = 1; $i <= count( $objects ); $i++ ) {
            $pdf .= sprintf( '%010d 00000 n %s', $offsets[ $i ], "\n" );
        }
        $pdf .= "trailer<</Size " . ( count( $objects ) + 1 ) . "/Root 1 0 R>>\n";
        $pdf .= "startxref\n" . $xref_pos . "\n%%EOF";

        return file_put_contents( $file_path, $pdf ) !== false;
    }
}
