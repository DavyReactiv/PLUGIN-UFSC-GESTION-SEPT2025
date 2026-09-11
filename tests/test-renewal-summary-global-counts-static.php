<?php
$root = dirname(__DIR__);
$front = file_get_contents($root . '/includes/frontend/class-frontend-shortcodes.php');
if ($front === false) {
    fwrite(STDERR, "FAIL: unable to read frontend shortcode renderer\n");
    exit(1);
}

$failures = array();
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(
    false !== strpos($front, 'get_renewal_state_counts'),
    'renewal dashboard and assistant must use one canonical global state counter'
);

$assistantPos = strpos($front, 'private static function render_renewal_assistant');
$assistant = $assistantPos === false ? '' : substr($front, $assistantPos, 18000);
$assert(
    false === strpos($assistant, '$counts[ isset( $counts[ $state ] ) ? $state : \'blocked\' ]++;'),
    'renewal summary counts must not be incremented inside the paginated row loop'
);

$assert(
    false !== strpos($assistant, '$global_counts'),
    'renewal assistant must render global counters independent of current page and display filters'
);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo "OK renewal summary global counts contract\n";
