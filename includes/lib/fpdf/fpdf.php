<?php
if ( ! class_exists( 'FPDF' ) ) {
    /**
     * Minimal FPDF-compatible wrapper used for UFSC attestations.
     * This is not a full implementation of the FPDF library but provides
     * the small subset needed to render simple text documents.
     */
    class FPDF {
        protected $lines = array();
        protected $title = '';

        public function __construct() {}

        public function AddPage() {}

        public function SetFont( $family, $style = '', $size = 12 ) {}

        public function SetTitle( $title ) {
            $this->title = $title;
        }

        public function Cell( $w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '' ) {
            if ( $txt !== '' ) {
                $this->lines[] = $txt;
            }
        }

        public function Ln( $h = null ) {
            $this->lines[] = '';
        }

        /**
         * Output the generated document. Only the file output mode (dest = 'F') is supported.
         */
        public function Output( $dest = '', $name = '' ) {
            $file = ( 'F' === $dest ) ? $name : sys_get_temp_dir() . '/ufsc_tmp.pdf';
            require_once __DIR__ . '/../class-simple-pdf.php';
            UFSC_Simple_PDF::generate( $file, $this->lines, $this->title );
            if ( 'F' !== $dest ) {
                return file_get_contents( $file );
            }
            return $file;
        }
    }
}
