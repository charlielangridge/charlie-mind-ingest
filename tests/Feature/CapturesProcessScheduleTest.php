<?php

use Illuminate\Console\Scheduling\Schedule;

test('capture processing is scheduled hourly', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'captures:process --limit=20'));

    expect($event)->not->toBeNull()
        ->and($event->getExpression())->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
