<?php
/**
 * Store-mode zip64 assembly cost probe for the P3 gallery spike (G-00).
 *
 *   docker run --rm -v "$PWD/spikes:/spikes:ro" php:8.4-cli php /spikes/zip-stream.php [MB]
 *
 * A ZIP of JPEGs is written with compression method 0 (store), so the only
 * per-byte CPU work is the CRC-32 every local file header requires plus the
 * copy itself. This measures those two primitives at 8 MiB chunks — the shape
 * of an S3 GET body streamed into an S3 multipart PUT — and reports peak RSS
 * to show the writer is O(chunk), not O(archive).
 */

$mb    = (int) ($argv[1] ?? 512);
$chunk = 8 * 1024 * 1024;
$src   = sys_get_temp_dir() . '/zipbench.bin';

if (!file_exists($src) || filesize($src) < $mb * 1048576) {
    // Incompressible bytes: a JPEG is already entropy-coded, so random data is
    // the honest stand-in. Using zeros would flatter every number here.
    $h = fopen($src, 'wb');
    for ($i = 0; $i < $mb; $i++) {
        fwrite($h, random_bytes(1048576));
    }
    fclose($h);
}

function bench(string $label, callable $fn, int $bytes): void {
    $t = hrtime(true);
    $fn();
    $s = (hrtime(true) - $t) / 1e9;
    printf(
        "%-38s %7.2f s  %8.1f MB/s   %5.1f min per 30 GB\n",
        $label, $s, $bytes / 1048576 / $s, 30720 / ($bytes / 1048576 / $s) / 60
    );
}

$bytes = $mb * 1048576;
$sink  = fopen('/dev/null', 'wb');

bench('read only (8 MiB chunks)', function () use ($src, $chunk) {
    $h = fopen($src, 'rb');
    while (fread($h, $chunk) !== '') {}
    fclose($h);
}, $bytes);

bench('read + crc32b (incremental)', function () use ($src, $chunk) {
    $h = fopen($src, 'rb'); $c = hash_init('crc32b');
    while (($b = fread($h, $chunk)) !== '') { hash_update($c, $b); }
    hash_final($c); fclose($h);
}, $bytes);

bench('read + crc32b + write  (= store zip)', function () use ($src, $chunk, $sink) {
    $h = fopen($src, 'rb'); $c = hash_init('crc32b');
    while (($b = fread($h, $chunk)) !== '') { hash_update($c, $b); fwrite($sink, $b); }
    hash_final($c); fclose($h);
}, $bytes);

bench('read + sha256 + write  (ingest verify)', function () use ($src, $chunk, $sink) {
    $h = fopen($src, 'rb'); $c = hash_init('sha256');
    while (($b = fread($h, $chunk)) !== '') { hash_update($c, $b); fwrite($sink, $b); }
    hash_final($c); fclose($h);
}, $bytes);

bench('deflate level 1 (what NOT to do)', function () use ($src, $chunk, $sink) {
    $h = fopen($src, 'rb'); $d = deflate_init(ZLIB_ENCODING_RAW, ['level' => 1]);
    while (($b = fread($h, $chunk)) !== '') { fwrite($sink, deflate_add($d, $b)); }
    fwrite($sink, deflate_add($d, '', ZLIB_FINISH)); fclose($h);
}, $bytes);

printf("\nstreamed %d MB · peak PHP heap %.1f MB · peak RSS %.1f MB · memory_limit %s\n",
    $mb,
    memory_get_peak_usage(true) / 1048576,
    (int) (explode(' ', file_get_contents('/proc/self/statm'))[1] ?? 0) * 4096 / 1048576,
    ini_get('memory_limit'));
