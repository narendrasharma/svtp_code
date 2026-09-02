<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamPageTest extends TestCase
{
    public function test_public_visitor_can_view_the_team_page(): void
    {
        $response = $this->get('http://localhost/our-team');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Static/Team'));
    }
}
