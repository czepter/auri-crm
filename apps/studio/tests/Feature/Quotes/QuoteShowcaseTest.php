<?php

use App\Catalog\Models\Package;
use App\Crm\Models\Project;
use App\Quotes\Actions\SeedQuoteFromPackage;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('copies the bytes onto the quote, so removing the package picture leaves it standing', function () {
    Storage::fake('local');

    $package = Package::factory()->create();
    $package->addShowcaseImage(UploadedFile::fake()->image('schaufenster.jpg'));

    $quote = Quote::factory()->create(['project_id' => Project::factory()->create()->id]);

    app(SeedQuoteFromPackage::class)($quote, $package);

    $copied = $quote->fresh()->showcasePaths();

    expect($copied)->toHaveCount(1)
        ->and($copied[0])->not->toBe($package->showcasePaths()[0]);

    $package->removeShowcaseImage(0);

    expect($quote->fresh()->showcasePaths())->toHaveCount(1)
        ->and(Storage::disk('local')->exists($copied[0]))->toBeTrue();
});

it('drops the file with the path', function () {
    Storage::fake('local');

    $package = Package::factory()->create();
    $package->addShowcaseImage(UploadedFile::fake()->image('schaufenster.jpg'));
    $path = $package->showcasePaths()[0];

    $package->removeShowcaseImage(0);

    expect($package->fresh()->showcasePaths())->toBe([])
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});
