<?php

declare(strict_types=1);

namespace App\Services\Maps;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Mengubah link Google Maps apa pun menjadi penanda yang bisa dipakai SerpApi.
 *
 * Alur:
 *   1. Terima input apa saja: short link (maps.app.goo.gl), URL panjang
 *      (google.com/maps/place/...), atau data_id langsung (0x...:0x...).
 *   2. Bila short link → ikuti redirect (HTTP HEAD/GET, tanpa browser).
 *   3. Ekstrak: data_id (!1s0x...), place_id (16s%2Fg%2F...), lat/lng, nama.
 *
 * TIDAK butuh browser/headless — hanya HTTP request biasa (bisa di shared hosting).
 */
class MapsLinkResolver
{
    /** Pola data_id Google Maps: "0x....:0x...." (0x ke-2 opsional). */
    private const DATA_ID_PATTERN = '/!1s(0x[0-9a-fA-F]+:(?:0x)?[0-9a-fA-F]+)/';

    /** Pola fallback bila data_id muncul tanpa prefiks !1s. */
    private const DATA_ID_LOOSE = '/(0x[0-9a-fA-F]{6,}:(?:0x)?[0-9a-fA-F]{6,})/';

    /** place_id: "g/11..." yang di URL ter-encode jadi "16s%2Fg%2F11...". */
    private const PLACE_ID_PATTERN = '/16s%2F(g%2F[0-9A-Za-z_\-]+)/i';

    private const LAT_PATTERN = '/!3d(-?\d+\.\d+)/';

    private const LNG_PATTERN = '/!4d(-?\d+\.\d+)/';

    public function __construct(
        private readonly int $timeout = 20,
    ) {}

    /**
     * Resolve input menjadi struktur data.
     *
     * @return array{
     *     data_id: ?string,
     *     place_id: ?string,
     *     latitude: ?float,
     *     longitude: ?float,
     *     name: ?string,
     *     resolved_url: ?string
     * }
     */
    public function resolve(string $input): array
    {
        $input = trim($input);

        // 1. Bila input SUDAH berupa data_id, langsung pakai.
        if (preg_match(self::DATA_ID_LOOSE, $input, $m) === 1 && ! str_contains($input, 'http')) {
            return $this->result($m[1], null, null, null, null, null);
        }

        // 2. Ikuti redirect bila short link (atau ambil body bila perlu).
        $finalUrl = $this->follow($input) ?? $input;

        return $this->parse($finalUrl);
    }

    /**
     * Ekstrak data dari URL panjang Google Maps.
     *
     * @return array{data_id: ?string, place_id: ?string, latitude: ?float, longitude: ?float, name: ?string, resolved_url: ?string}
     */
    public function parse(string $url): array
    {
        $dataId = null;
        if (preg_match(self::DATA_ID_PATTERN, $url, $m) === 1) {
            $dataId = $m[1];
        } elseif (preg_match(self::DATA_ID_LOOSE, $url, $m) === 1) {
            $dataId = $m[1];
        }

        $placeId = null;
        if (preg_match(self::PLACE_ID_PATTERN, $url, $m) === 1) {
            $placeId = rawurldecode($m[1]); // g/11xxxx
        }

        $lat = preg_match(self::LAT_PATTERN, $url, $m) === 1 ? (float) $m[1] : null;
        $lng = preg_match(self::LNG_PATTERN, $url, $m) === 1 ? (float) $m[1] : null;

        return $this->result($dataId, $placeId, $lat, $lng, $this->extractName($url), $url);
    }

    /**
     * Ikuti redirect untuk mendapatkan URL final. Mengembalikan null bila gagal
     * atau tidak ada redirect (berarti URL sudah panjang).
     */
    private function follow(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Tidak perlu follow bila sudah mengandung data_id/koordinat.
        if (preg_match(self::DATA_ID_PATTERN, $url) === 1) {
            return $url;
        }

        try {
            $response = $this->http()->withOptions([
                'allow_redirects' => ['max' => 5, 'track_redirects' => true],
            ])->get($url);

            $history = $response->header('X-Guzzle-Redirect-History');
            if (is_string($history) && $history !== '') {
                $parts = explode(', ', $history);
                $last = end($parts);

                if (is_string($last) && $last !== '') {
                    return $last;
                }
            }

            // Bila tidak ada history, coba efektif URL.
            return $response->effectiveUri() !== null
                ? (string) $response->effectiveUri()
                : $url;
        } catch (Throwable) {
            return null;
        }
    }

    private function extractName(string $url): ?string
    {
        if (preg_match('#/maps/place/([^/@]+)#', $url, $m) === 1) {
            return urldecode(str_replace('+', ' ', $m[1]));
        }

        return null;
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withHeaders([
                // User-Agent browser agar Google mau mengirim redirect.
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
            ]);
    }

    /**
     * @return array{data_id: ?string, place_id: ?string, latitude: ?float, longitude: ?float, name: ?string, resolved_url: ?string}
     */
    private function result(
        ?string $dataId,
        ?string $placeId,
        ?float $lat,
        ?float $lng,
        ?string $name,
        ?string $resolvedUrl,
    ): array {
        return [
            'data_id' => $dataId,
            'place_id' => $placeId,
            'latitude' => $lat,
            'longitude' => $lng,
            'name' => $name,
            'resolved_url' => $resolvedUrl,
        ];
    }
}
