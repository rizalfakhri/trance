<?php

use App\Enums\GenerationStatus;

test('only succeeded and failed generations are terminal', function (GenerationStatus $status, bool $isTerminal) {
    expect($status->isTerminal())->toBe($isTerminal);
})->with([
    [GenerationStatus::Queued, false],
    [GenerationStatus::Processing, false],
    [GenerationStatus::Succeeded, true],
    [GenerationStatus::Failed, true],
]);
