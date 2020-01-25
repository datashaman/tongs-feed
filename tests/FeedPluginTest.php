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
            ->use(new CollectionsPlugin($tongs))
            ->use(new FeedPlugin(
                $tongs,
                [
                    'collection' => 'posts',
                    'feed' => [
                        'title' => 'Example Feed',
                        'link' => 'http://example.org/',
                        'author' => [
                            'name' => 'John Doe',
                        ],
                    ],
                ]
            ))
            ->build();

        $this->assertFiles($this->fixture('basic/files.json'), $files);
        $this->assertDirEquals($this->fixture('basic/expected'), $this->fixture('basic/build'));
    }

    protected function assertFiles(string $expected, Collection $actual)
    {
        $this->assertEquals(
            trim(File::get($expected)),
            trim(json_encode(
                $actual->all(),
                JSON_PRETTY_PRINT
            ))
        );
    }
}
