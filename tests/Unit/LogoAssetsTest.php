<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LogoAssetsTest extends TestCase
{
    public function test_main_logo_has_the_required_header_dimensions_and_identity(): void
    {
        $logo = simplexml_load_file(dirname(__DIR__, 2).'/resources/images/logo.svg');

        $this->assertNotFalse($logo);
        $this->assertSame('504', (string) $logo['width']);
        $this->assertSame('116', (string) $logo['height']);
        $this->assertSame('0 0 504 116', (string) $logo['viewBox']);
        $this->assertSame('Shree Vrindavan Tour Packages', (string) $logo->title);
        $this->assertSame('A premium devotional logo with a Krishna-inspired peacock feather.', (string) $logo->desc);
    }

    public function test_favicon_is_a_square_svg_with_the_company_identity(): void
    {
        $favicon = simplexml_load_file(dirname(__DIR__, 2).'/resources/images/favicon.svg');

        $this->assertNotFalse($favicon);
        $this->assertSame('64', (string) $favicon['width']);
        $this->assertSame('64', (string) $favicon['height']);
        $this->assertSame('0 0 64 64', (string) $favicon['viewBox']);
        $this->assertSame('Shree Vrindavan Tour Packages', (string) $favicon->title);
    }
}
