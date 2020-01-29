<?php

declare(strict_types=1);

namespace Datashaman\Tongs\Plugins\Tests;

use Datashaman\Tongs\Plugins\CollectionsPlugin;
use Datashaman\Tongs\Plugins\FeedPlugin;
use Datashaman\Tongs\Tongs;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class FeedPluginTest extends TestCase
{
    public function testHandle()
    {
        $tongs = new Tongs($this->fixture('basic'));
        $files = $tongs
            ->use(new CollectionsPlugin())
            ->use(new FeedPlugin(
                [
                    'collection' => 'posts',
                    'feed' => [
                        'title' => 'Example Feed',
                        'link' => 'http://example.org/',
                        'author' => [
                            'name' => 'John Doe',
                        ],
                    ],
                    'limit' => 2,
                ]
            ))
            ->build();

        $this->assertFiles($this->fixture('basic/files.json'), $files);
        $this->assertDirEquals($this->fixture('basic/expected'), $this->fixture('basic/build'));
    }

    protected function assertFiles(string $expected, array $actual)
    {
        $this->assertEquals(
            json_decode(File::get($expected), true),
            json_decode(json_encode($actual), true),
        );
    }
}
