<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_check_ins_and_current_navigation(): void
    {
        Queue::fake();

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText('Life check-ins')
            ->assertSeeText('Add entry')
            ->assertSeeText('This week');

        foreach (['dashboard' => 'Dashboard', 'history' => 'History', 'stats' => 'Statistics'] as $routeName => $label) {
            $response->assertSee('href="'.route($routeName).'"', false)
                ->assertSeeText($label);
        }
    }
}
