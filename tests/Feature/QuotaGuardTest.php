<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SerpApiUsage;
use App\Services\SerpApi\QuotaGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotaGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_and_recording(): void
    {
        $guard = new QuotaGuard(dailyLimit: 10);

        $this->assertSame(10, $guard->remaining());
        $this->assertTrue($guard->hasRemaining(10));
        $this->assertFalse($guard->hasRemaining(11));

        $guard->record(4);

        $this->assertSame(6, $guard->remaining());
        $this->assertSame(4, SerpApiUsage::query()->value('searches'));
    }

    public function test_limit_reached(): void
    {
        $guard = new QuotaGuard(dailyLimit: 2);

        $guard->record(2);

        $this->assertSame(0, $guard->remaining());
        $this->assertFalse($guard->hasRemaining());
    }
}
