<?php
/**
 * Structural safeguards for the stylesheet used by [ufsc_add_licence].
 */

$root = dirname( __DIR__ );
$path = $root . '/assets/css/ufsc-frontend.css';
$css  = file_get_contents( $path );
$css  = false === $css ? false : str_replace( array( "\r\n", "\r" ), "\n", $css );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== $css, 'The UFSC frontend stylesheet must be readable.' );

// Count structural braces while ignoring comments and quoted strings.
$depth      = 0;
$line       = 1;
$in_comment = false;
$quote      = null;
$length     = strlen( $css );

for ( $index = 0; $index < $length; $index++ ) {
    $character = $css[ $index ];
    $next      = $index + 1 < $length ? $css[ $index + 1 ] : '';

    if ( "\n" === $character ) {
        $line++;
    }

    if ( $in_comment ) {
        if ( '*' === $character && '/' === $next ) {
            $in_comment = false;
            $index++;
        }
        continue;
    }

    if ( null !== $quote ) {
        if ( '\\' === $character ) {
            $index++;
            continue;
        }
        if ( $character === $quote ) {
            $quote = null;
        }
        continue;
    }

    if ( '/' === $character && '*' === $next ) {
        $in_comment = true;
        $index++;
        continue;
    }

    if ( '"' === $character || "'" === $character ) {
        $quote = $character;
        continue;
    }

    if ( '{' === $character ) {
        $depth++;
    } elseif ( '}' === $character ) {
        $depth--;
        $assert( $depth >= 0, "Unexpected closing brace at line {$line}." );
    }
}

$assert( ! $in_comment, 'CSS comments must be closed.' );
$assert( null === $quote, 'CSS quoted strings must be closed.' );
$assert( 0 === $depth, "CSS braces must be balanced; final depth is {$depth}." );

// Reject nested style rules: this catches balanced but accidentally embedded blocks.
$structure = preg_replace( '#/\*.*?\*/#s', '', $css );
$structure = preg_replace( '/"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/s', '""', $structure );
$tokens    = preg_split( '/([{};])/', $structure, -1, PREG_SPLIT_DELIM_CAPTURE );
$blocks    = array();
$prelude   = '';

foreach ( $tokens as $token ) {
    if ( ';' === $token ) {
        $prelude = '';
        continue;
    }

    if ( '{' === $token ) {
        $candidate = trim( $prelude );
        $parent    = empty( $blocks ) ? null : end( $blocks );
        $assert( '' !== $candidate, 'Every CSS block must have a selector or at-rule prelude.' );

        if ( 0 === strpos( $candidate, '@media' ) ) {
            $assert( null === $parent, '@media blocks must not be nested inside style rules.' );
            $blocks[] = 'media';
        } elseif ( 0 === strpos( $candidate, '@keyframes' ) ) {
            $assert( null === $parent, '@keyframes blocks must be top-level.' );
            $blocks[] = 'keyframes';
        } elseif ( 'keyframes' === $parent ) {
            $blocks[] = 'keyframe-step';
        } else {
            $assert( 'rule' !== $parent && 'keyframe-step' !== $parent, "Accidental nested style rule: {$candidate}" );
            $blocks[] = 'rule';
        }

        $prelude = '';
        continue;
    }

    if ( '}' === $token ) {
        $assert( ! empty( $blocks ), 'Every closing brace must match a known CSS block.' );
        array_pop( $blocks );
        $prelude = '';
        continue;
    }

    $prelude .= $token;
}

$assert( empty( $blocks ), 'All CSS rule, @media and @keyframes blocks must close.' );

// Tokens and generic element selectors must remain local to the licence portal.
$assert( 0 === preg_match( '/^\s*:root\s*\{/m', $css ), 'Global :root tokens are forbidden in this stylesheet.' );
$assert( false !== strpos( $css, ".ufsc-add-licence-section {\n    --ufsc-primary:" ), 'Design tokens must be scoped to the licence portal.' );
$assert(
    0 === preg_match( '/^\s*(?:a|button|input|select|textarea|\[tabindex\]|table\s+tr)(?=\s*[:,{])/m', $css ),
    'Generic element selectors must be scoped to .ufsc-add-licence-section.'
);
$assert( false === strpos( $css, 'justify-content: between' ), 'Invalid justify-content values are forbidden.' );

echo "UFSC frontend CSS structural safeguards OK\n";
