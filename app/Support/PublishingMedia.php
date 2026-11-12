<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublishingMedia
{
    /** @return array{image_id: string, path: string, url: string, width: int, height: int, bytes: int} */
    public function store(string $contents): array
    {
        $info = $this->inspect($contents);
        $id = hash('sha256', $contents).'.'.$info['extension'];
        $path = 'posts/mcp/'.$id;
        if (! Storage::disk('public')->put($path, $contents, ['visibility' => 'public'])) {
            throw ValidationException::withMessages(['image' => 'The image could not be saved. No post was published.']);
        }

        return ['image_id' => $id, 'path' => $path, 'url' => PublicSeo::url('home').'storage/'.$path,
            'width' => $info['width'], 'height' => $info['height'], 'bytes' => strlen($contents)];
    }

    /** @return array{image_id: string, path: string, url: string, width: int, height: int, bytes: int} */
    public function fromBase64(string $encoded): array
    {
        if (strlen($encoded) > (int) ceil(config('publishing.max_image_bytes') / 3) * 4) {
            throw ValidationException::withMessages(['image_base64' => 'The image exceeds the 4 MB limit.']);
        }
        $contents = base64_decode($encoded, true);
        if ($contents === false) {
            throw ValidationException::withMessages(['image_base64' => 'Provide actual base64 image bytes, without a data URL prefix.']);
        }

        return $this->store($contents);
    }

    /** @return array{image_id: string, path: string, url: string, width: int, height: int, bytes: int} */
    public function fromUrl(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $allowed = array_map('strtolower', config('publishing.image_hosts'));
        if (! filter_var($url, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https'
            || ! in_array($host, $allowed, true) || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)
            || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\\\\]/', $url)) {
            throw ValidationException::withMessages(['image_url' => 'Use an HTTPS image URL on an explicitly approved BLOG_MCP_IMAGE_HOSTS hostname, without credentials, ports, or redirects.']);
        }
        if (! extension_loaded('curl')) {
            throw ValidationException::withMessages(['image_url' => 'Remote image downloads require the PHP cURL extension. Use the authenticated image upload endpoint instead.']);
        }
        $addresses = $this->resolveAddresses($host);
        if ($addresses === [] || array_filter($addresses, fn (string $address): bool => ! filter_var(
            $address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ))) {
            throw ValidationException::withMessages(['image_url' => 'The image hostname must resolve only to public IPv4 addresses.']);
        }
        try {
            $response = Http::connectTimeout(5)->timeout(20)
                ->withHeaders(['Accept' => 'image/jpeg,image/png,image/webp'])
                ->withOptions([
                    'allow_redirects' => false, 'stream' => true, 'proxy' => '',
                    'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$addresses[0]]],
                ])->get($url);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['image_url' => 'The image server could not be reached. No post was published.']);
        }
        $stream = $response->toPsrResponse()->getBody();
        try {
            if (! $response->successful()) {
                throw ValidationException::withMessages(['image_url' => 'The image server did not return a successful response. Redirects are not followed.']);
            }
            $contents = '';
            while (! $stream->eof()) {
                $contents .= $stream->read(65536);
                if (strlen($contents) > config('publishing.max_image_bytes')) {
                    throw ValidationException::withMessages(['image_url' => 'The image exceeds the 4 MB limit.']);
                }
            }
        } finally {
            $stream->close();
        }

        return $this->store($contents);
    }

    /** @return list<string> */
    protected function resolveAddresses(string $host): array
    {
        return gethostbynamel($host) ?: [];
    }

    public function path(string $id): string
    {
        if (! preg_match('/\A[a-f0-9]{64}\.(?:jpg|png|webp)\z/D', $id)
            || ! Storage::disk('public')->exists('posts/mcp/'.$id)) {
            throw ValidationException::withMessages(['featured_image_id' => 'Upload a featured image first and use its returned image_id.']);
        }
        $contents = Storage::disk('public')->get('posts/mcp/'.$id);
        $info = $this->inspect($contents);
        if ($id !== hash('sha256', $contents).'.'.$info['extension']) {
            throw ValidationException::withMessages(['featured_image_id' => 'The uploaded image has changed. Upload it again before publishing.']);
        }

        return 'posts/mcp/'.$id;
    }

    /** @return array{extension: string, width: int, height: int} */
    private function inspect(string $contents): array
    {
        if (strlen($contents) > config('publishing.max_image_bytes')) {
            throw ValidationException::withMessages(['image' => 'The image exceeds the 4 MB limit.']);
        }
        $info = @getimagesizefromstring($contents);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (! $info || ! isset($extensions[$info['mime']]) || $info[0] * $info[1] > 40000000) {
            throw ValidationException::withMessages(['image' => 'Provide a valid JPG, PNG, or WebP image up to 40 megapixels.']);
        }

        return ['extension' => $extensions[$info['mime']], 'width' => $info[0], 'height' => $info[1]];
    }
}
