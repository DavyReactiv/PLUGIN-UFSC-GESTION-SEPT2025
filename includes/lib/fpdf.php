<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Minimal PDF generation library inspired by FPDF.
 * Provides just enough features for UFSC attestation generation.
 */
class FPDF {
    private $lines = array();
    private $title = '';

    public function AddPage() {
        // No page management needed for simple documents
    }

    public function SetTitle( $title ) {
        $this->title = $title;
    }

    public function SetFont( $family, $style = '', $size = 12 ) {
        // Font handling is not implemented in this minimal version
    }

    public function Cell( $w, $h = 0, $text = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '' ) {
        $this->lines[] = $text;
        if ( $ln > 0 ) {
            $this->lines[] = '';
        }
    }

    public function Ln( $h = null ) {
        $this->lines[] = '';
    }

    public function Output( $dest, $file_path ) {
        $escape = function( $text ) {
            return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $text );
        };

        $content = "BT\n/F1 16 Tf\n50 780 Td\n";
        if ( ! empty( $this->title ) ) {
            $content .= '(' . $escape( $this->title ) . ") Tj\n0 -24 Td\n";
        }
        foreach ( $this->lines as $line ) {
            if ( $line === '' ) {
                continue;
            }
            $content .= '(' . $escape( $line ) . ") Tj\n0 -18 Td\n";
        }
        $content .= "ET";

        $objects = array();
        $objects[] = "<</Type/Catalog/Pages 2 0 R>>";
        $objects[] = "<</Type/Pages/Count 1/Kids[3 0 R]>>";
        $objects[] = "<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>";
        $objects[] = "<</Length " . strlen( $content ) . ">>stream\n$content\nendstream";
        $objects[] = "<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>";

        $pdf = "%PDF-1.4\n";
        $offsets = array( 0 );
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

        if ( 'F' === $dest ) {
            return file_put_contents( $file_path, $pdf ) !== false;
        }

        echo $pdf;
        return true;
    }
}
