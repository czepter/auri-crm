<?php

use App\Media\Support\PerceptualHash;

function solidSquarePng(int $r, int $g, int $b): string
{
    $image = imagecreatetruecolor(32, 32);
    imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
    $path = tempnam(sys_get_temp_dir(), 'phash').'.png';
    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

it('is deterministic and renders as 16 hex chars', function () {
    $path = solidSquarePng(120, 40, 200);

    $hash = (new PerceptualHash)->hash($path);

    expect($hash)->toBe((new PerceptualHash)->hash($path))
        ->and($hash)->toMatch('/^[0-9A-F]{16}$/');
});

it('produces a hash with structure for an image that has structure', function () {
    $image = imagecreatetruecolor(32, 32);
    for ($x = 0; $x < 32; $x++) {
        for ($y = 0; $y < 32; $y++) {
            $color = ($x + $y) % 2 === 0
                ? imagecolorallocate($image, 255, 255, 255)
                : imagecolorallocate($image, 0, 0, 0);
            imagesetpixel($image, $x, $y, $color);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'phash').'.png';
    imagepng($image, $path);
    imagedestroy($image);

    expect((new PerceptualHash)->hash($path))->not->toBe('0000000000000000');
});

it('throws rather than returning garbage for a source it cannot decode', function () {
    $path = tempnam(sys_get_temp_dir(), 'phash').'.png';
    file_put_contents($path, 'not a png');

    expect(fn () => (new PerceptualHash)->hash($path))->toThrow(RuntimeException::class);
});
