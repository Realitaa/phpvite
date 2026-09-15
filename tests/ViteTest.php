<?php

declare(strict_types=1);

namespace Realitaa\PhpVite\Tests;

use PHPUnit\Framework\TestCase;
use Realitaa\PhpVite\Vite\Vite;
use RuntimeException;

class ViteTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/phpvite_test_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '/dist/.vite', 0777, true);
        mkdir($this->tempDir . '/storage', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    public function testIsRunningReturnsFalseWhenHotFileDoesNotExist(): void
    {
        $vite = new Vite(
            rootPath: $this->tempDir,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $this->assertFalse($vite->isRunning());
    }

    public function testIsRunningReturnsTrueWhenHotFileExists(): void
    {
        $hotFile = $this->tempDir . '/storage/vite.hot';
        file_put_contents($hotFile, "http://localhost:5173/\n");

        $vite = new Vite(
            rootPath: $this->tempDir,
            hotFile: $hotFile
        );

        $this->assertTrue($vite->isRunning());
    }

    public function testDevelopmentAssetReturnsDevServerUrl(): void
    {
        $hotFile = $this->tempDir . '/storage/vite.hot';
        file_put_contents($hotFile, "http://localhost:5173/\n");

        $vite = new Vite(
            rootPath: $this->tempDir,
            hotFile: $hotFile
        );

        $this->assertSame(
            'http://localhost:5173/src/main.js',
            $vite->asset('src/main.js')
        );
    }

    public function testDevelopmentTagsGeneratesClientAndEntryScript(): void
    {
        $hotFile = $this->tempDir . '/storage/vite.hot';
        file_put_contents($hotFile, "http://localhost:5173/\n");

        $vite = new Vite(
            rootPath: $this->tempDir,
            hotFile: $hotFile
        );

        $expected = <<<HTML
<script type="module" src="http://localhost:5173/@vite/client"></script>
<script type="module" src="http://localhost:5173/src/main.js"></script>
HTML;

        $this->assertSame($expected, $vite->tags('src/main.js'));
    }

    public function testDevelopmentTagsForCssEntryGeneratesStylesheetLink(): void
    {
        $hotFile = $this->tempDir . '/storage/vite.hot';
        file_put_contents($hotFile, "http://localhost:5173/\n");

        $vite = new Vite(
            rootPath: $this->tempDir,
            hotFile: $hotFile
        );

        $expected = <<<HTML
<script type="module" src="http://localhost:5173/@vite/client"></script>
<link rel="stylesheet" href="http://localhost:5173/src/style.css">
HTML;

        $tags = $vite->tags('src/style.css');

        $this->assertSame($expected, $tags);
        $this->assertStringNotContainsString('<script type="module" src="http://localhost:5173/src/style.css">', $tags);
    }

    public function testProductionAssetReturnsResolvedUrl(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/main.js' => [
                'file' => 'assets/main-ABC123.js',
                'isEntry' => true,
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $this->assertSame('/dist/assets/main-ABC123.js', $vite->asset('src/main.js'));
    }

    public function testProductionTagsWithCssRendersStylesheetsAndEntryScript(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/main.js' => [
                'file' => 'assets/main-ABC123.js',
                'isEntry' => true,
                'css' => [
                    'assets/main-XYZ789.css',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $expected = <<<HTML
<link rel="stylesheet" href="/dist/assets/main-XYZ789.css">
<script type="module" src="/dist/assets/main-ABC123.js"></script>
HTML;

        $this->assertSame($expected, $vite->tags('src/main.js'));
    }

    public function testProductionTagsForCssEntryRendersAsStylesheet(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/style.css' => [
                'file' => 'assets/style-DEF456.css',
                'isEntry' => true,
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $expected = '<link rel="stylesheet" href="/dist/assets/style-DEF456.css">';

        $this->assertSame($expected, $vite->tags('src/style.css'));
    }

    public function testMultipleEntriesResolution(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/main.js' => [
                'file' => 'assets/main-APP111.js',
                'isEntry' => true,
                'css' => ['assets/main-APP111.css'],
            ],
            'src/other.js' => [
                'file' => 'assets/other-ADM222.js',
                'isEntry' => true,
                'css' => ['assets/other-ADM222.css'],
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $this->assertSame('/dist/assets/main-APP111.js', $vite->asset('src/main.js'));
        $this->assertSame('/dist/assets/other-ADM222.js', $vite->asset('src/other.js'));

        $this->assertStringContainsString('/dist/assets/main-APP111.js', $vite->tags('src/main.js'));
        $this->assertStringContainsString('/dist/assets/other-ADM222.js', $vite->tags('src/other.js'));
    }

    public function testDynamicImportsAreNotRenderedAsScriptTags(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/main.js' => [
                'file' => 'assets/main-ABC123.js',
                'isEntry' => true,
                'dynamicImports' => [
                    'src/dynamic.js',
                ],
            ],
            'src/dynamic.js' => [
                'file' => 'assets/dynamic-XYZ789.js',
                'isDynamicEntry' => true,
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $tags = $vite->tags('src/main.js');

        $this->assertStringContainsString('assets/main-ABC123.js', $tags);
        $this->assertStringNotContainsString('dynamic-XYZ789.js', $tags);
    }

    public function testMissingManifestThrowsRuntimeException(): void
    {
        $nonExistentManifest = $this->tempDir . '/non-existent/manifest.json';

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $nonExistentManifest,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vite production manifest not found');

        $vite->asset('src/main.js');
    }

    public function testInvalidJsonManifestThrowsRuntimeException(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, '{ malformed json: true, }');

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vite production manifest contains invalid JSON');

        $vite->asset('src/main.js');
    }

    public function testNonArrayManifestThrowsRuntimeException(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, '"hello world"');

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vite production manifest is invalid: expected JSON object/array');

        $vite->asset('src/main.js');
    }

    public function testMissingEntryThrowsRuntimeException(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/main.js' => [
                'file' => 'assets/main-ABC123.js',
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vite entry [src/missing.js] not found in manifest');

        $vite->asset('src/missing.js');
    }

    public function testMissingFileInEntryThrowsRuntimeException(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/main.js' => [
                'name' => 'main',
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Vite manifest entry [src/main.js] does not specify a valid 'file'");

        $vite->asset('src/main.js');
    }

    public function testMalformedCssFieldThrowsRuntimeException(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/main.js' => [
                'file' => 'assets/main-ABC123.js',
                'css' => 'not-an-array',
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Vite manifest entry [src/main.js] has a malformed 'css' field");

        $vite->tags('src/main.js');
    }

    public function testManifestIsCachedDuringRequest(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/main.js' => [
                'file' => 'assets/main-ORIGINAL.js',
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $firstResolution = $vite->asset('src/main.js');
        $this->assertSame('/dist/assets/main-ORIGINAL.js', $firstResolution);

        file_put_contents($manifestPath, json_encode([
            'src/main.js' => [
                'file' => 'assets/main-UPDATED.js',
            ],
        ], JSON_THROW_ON_ERROR));

        $secondResolution = $vite->asset('src/main.js');
        $this->assertSame('/dist/assets/main-ORIGINAL.js', $secondResolution);

        $vite->clearManifestCache();
        $thirdResolution = $vite->asset('src/main.js');
        $this->assertSame('/dist/assets/main-UPDATED.js', $thirdResolution);
    }

    public function testHtmlEscapingInTags(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/special.js' => [
                'file' => 'assets/special<test>&foo="bar"\'.js',
                'css' => [
                    'assets/style<1>&2="3"\'.css',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $tags = $vite->tags('src/special.js');

        $this->assertStringNotContainsString('<test>', $tags);
        $this->assertStringNotContainsString('&foo=', $tags);
        $this->assertStringContainsString('special&lt;test&gt;&amp;foo=&quot;bar&quot;&#039;.js', $tags);
        $this->assertStringContainsString('style&lt;1&gt;&amp;2=&quot;3&quot;&#039;.css', $tags);
    }

    public function testHtmlEscapingInDevTags(): void
    {
        $hotFile = $this->tempDir . '/storage/vite.hot';
        file_put_contents($hotFile, 'http://localhost:5173');

        $vite = new Vite(
            rootPath: $this->tempDir,
            hotFile: $hotFile
        );

        $tags = $vite->tags('src/test<script>.js?foo=1&bar=2');

        $this->assertStringNotContainsString('test<script>', $tags);
        $this->assertStringContainsString('test&lt;script&gt;.js?foo=1&amp;bar=2', $tags);
    }

    public function testFilesystemPathsAreDecoupledFromPublicUrls(): void
    {
        $customDist = $this->tempDir . '/custom_dist_location';
        mkdir($customDist . '/.vite', 0777, true);

        $manifestPath = $customDist . '/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/main.js' => [
                'file' => 'assets/main-123.js',
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(
            rootPath: $this->tempDir,
            buildDirectory: $customDist,
            buildUrl: 'https://cdn.example.com/assets',
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        );

        $this->assertSame($customDist, $vite->getBuildDirectory());
        $this->assertSame('https://cdn.example.com/assets', $vite->getBuildUrl());
        $this->assertSame('https://cdn.example.com/assets/assets/main-123.js', $vite->asset('src/main.js'));
    }

    public function testEmptyHotFileThrowsRuntimeException(): void
    {
        $hotFile = $this->tempDir . '/storage/vite.hot';
        file_put_contents($hotFile, "   \n");

        $vite = new Vite(
            rootPath: $this->tempDir,
            hotFile: $hotFile
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vite hot file [' . $hotFile . '] is empty');

        $vite->asset('src/main.js');
    }

    public function testMalformedUrlInHotFileThrowsRuntimeException(): void
    {
        $hotFile = $this->tempDir . '/storage/vite.hot';
        file_put_contents($hotFile, "not-a-valid-url\n");

        $vite = new Vite(
            rootPath: $this->tempDir,
            hotFile: $hotFile
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contains an invalid URL');

        $vite->asset('src/main.js');
    }

    public function testGlobalViteHelperFunction(): void
    {
        $defaultVite = vite();
        $this->assertInstanceOf(Vite::class, $defaultVite);
        $this->assertSame($defaultVite, vite());

        $customVite = new Vite(buildUrl: '/custom-url');
        $injectedVite = vite($customVite);
        $this->assertSame($customVite, $injectedVite);
        $this->assertSame('/custom-url', vite()->getBuildUrl());

        vite(new Vite());
    }

    public function testIndexPhpRendersWithProductionManifest(): void
    {
        $manifestPath = $this->tempDir . '/dist/.vite/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'src/main.js' => [
                'file' => 'assets/main-TEST123.js',
                'css' => ['assets/main-TEST123.css'],
                'isEntry' => true,
            ],
        ], JSON_THROW_ON_ERROR));

        vite(new Vite(
            rootPath: $this->tempDir,
            manifestPath: $manifestPath,
            hotFile: $this->tempDir . '/storage/vite.hot'
        ));

        ob_start();
        include __DIR__ . '/../index.php';
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('<div id="app"></div>', $output);
        $this->assertStringContainsString('/dist/assets/main-TEST123.css', $output);
        $this->assertStringContainsString('/dist/assets/main-TEST123.js', $output);

        vite(new Vite());
    }
}
