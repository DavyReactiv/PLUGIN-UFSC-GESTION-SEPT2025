<?php
use PHPUnit\Framework\TestCase;

class UFSC_Export_CSV_Test extends TestCase {
    private function generate_csv( array $cols, array $row ) {
        $fh = fopen( 'php://temp', 'r+' );
        fputcsv( $fh, $cols );
        fputcsv( $fh, array_map( fn( $c ) => $row[ $c ] ?? '', $cols ) );
        rewind( $fh );
        $csv = stream_get_contents( $fh );
        fclose( $fh );
        return $csv;
    }

    public function test_clubs_export_alignment() {
        $cols = array( 'id', 'nom', 'email' );
        $row  = array( 'nom' => 'Club Test', 'email' => 'club@example.com' );
        $csv  = $this->generate_csv( $cols, $row );
        $lines = array_map( 'str_getcsv', array_filter( explode( "\n", trim( $csv ) ) ) );
        $this->assertSame( $cols, $lines[0] );
        $this->assertSame( array( '', 'Club Test', 'club@example.com' ), $lines[1] );
    }

    public function test_licences_export_alignment() {
        $cols = array( 'licence_id', 'club_id', 'status', 'email' );
        $row  = array( 'email' => 'lic@example.com', 'licence_id' => 123 );
        $csv  = $this->generate_csv( $cols, $row );
        $lines = array_map( 'str_getcsv', array_filter( explode( "\n", trim( $csv ) ) ) );
        $this->assertSame( $cols, $lines[0] );
        $this->assertSame( array( '123', '', '', 'lic@example.com' ), $lines[1] );
    }
}
