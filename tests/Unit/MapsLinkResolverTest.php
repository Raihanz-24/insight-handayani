<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Maps\MapsLinkResolver;
use PHPUnit\Framework\TestCase;

class MapsLinkResolverTest extends TestCase
{
    private MapsLinkResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new MapsLinkResolver;
    }

    public function test_parses_long_url_with_data_id(): void
    {
        $url = 'https://www.google.com/maps/place/RM+Handayani/@-7.7176438,113.5381797,17.41z/'
            .'data=!4m6!3m5!1s0x2dd7036fcefb89b5:0xbaf316dbc59eefd5!8m2!3d-7.718079!4d113.5370401'
            .'!16s%2Fg%2F11c3nxf_3s';

        $out = $this->resolver->parse($url);

        $this->assertSame('0x2dd7036fcefb89b5:0xbaf316dbc59eefd5', $out['data_id']);
        $this->assertSame('g/11c3nxf_3s', $out['place_id']);
        $this->assertSame(-7.718079, $out['latitude']);
        $this->assertSame(113.5370401, $out['longitude']);
        $this->assertSame('RM Handayani', $out['name']);
    }

    public function test_accepts_raw_data_id_input(): void
    {
        $out = $this->resolver->resolve('0x2dd7036fcefb89b5:0xbaf316dbc59eefd5');

        $this->assertSame('0x2dd7036fcefb89b5:0xbaf316dbc59eefd5', $out['data_id']);
    }

    public function test_returns_nulls_for_unparseable_input(): void
    {
        $out = $this->resolver->parse('https://example.com/not-a-map');

        $this->assertNull($out['data_id']);
        $this->assertNull($out['place_id']);
    }
}
