<?php

use Flux\Http\Controllers\DashboardController;
use Illuminate\View\View;

beforeEach(function () {
    $this->controller = new DashboardController();
});

describe('index method', function () {
    it('returns dashboard view', function () {
        $result = $this->controller->index();

        expect($result)->toBeInstanceOf(View::class)
            ->and($result->getName())->toBe('smart-migration::dashboard');
    });
});
