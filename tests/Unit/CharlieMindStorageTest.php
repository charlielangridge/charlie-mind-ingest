<?php

use App\Services\CharlieMindStorage;
use Tests\TestCase;

uses(TestCase::class);

test('object path prepends the configured root', function () {
    config(['charliemind.root' => 'charliemind']);

    expect(app(CharlieMindStorage::class)->objectPath('inbox/audio/test.m4a'))
        ->toBe('charliemind/inbox/audio/test.m4a');
});

test('object path trims duplicate slashes', function () {
    config(['charliemind.root' => '/charliemind/']);

    expect(app(CharlieMindStorage::class)->objectPath('/inbox//audio/test.m4a'))
        ->toBe('charliemind/inbox/audio/test.m4a');
});

test('empty root returns the vault relative path without a leading slash', function () {
    config(['charliemind.root' => '']);

    expect(app(CharlieMindStorage::class)->objectPath('/inbox/audio/test.m4a'))
        ->toBe('inbox/audio/test.m4a');
});
