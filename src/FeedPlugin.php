<?php

namespace Datashaman\Tongs\Plugins;

use Datashaman\Tongs\Plugins\Plugin;
use Datashaman\Tongs\Tongs;
use DateTime;
use DOMDocument;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use SimpleXMLElement;

class FeedPlugin extends Plugin
{
    /**
     * @param Tongs $tongs
     * @param array $options
     */
    public function __construct(Tongs $tongs, array $options = [])
    {
        $options = $this->normalize($options);

        parent::__construct($tongs, $options);
    }

    /**
     * Handle files passed down the pipeline, and call the next plugin in the pipeline.
     *
     * @param Collection $files
     * @param callable $next
     *
     * @return Collection
     */
    public function handle(Collection $files, callable $next): Collection
    {
        $metadata = $this->tongs->metadata();

        if (!Arr::get($metadata, 'collections')) {
            throw new Exception('No collections configured, use collections');
        }

        $collection = $this->options['collection'];

        $feed = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><feed xmlns="http://www.w3.org/2005/Atom"></feed>');

        $this->addChildren($feed, $this->options['feed']);

        collect(Arr::get($metadata, "collections.{$collection}"))
            ->take($this->options['limit'])
            ->each(
                function ($file, $path) use ($feed) {
                    $link = $file['url'] ?? "{$feed->link}{$file['path']}";

                    $entry = $feed->addChild('entry');

                    $children = [
                        'title' => Arr::get($file, 'title', ''),
                        'link' => $link,
                        'summary' => Arr::get(
                            $file,
                            Arr::get($this->options, 'excerpt', 'excerpt'),
                            Arr::get($file, 'contents')
                        ),
                        // 'updated' => Arr::get($file, 'updated'),
                    ];

                    if (Arr::has($file, 'date')) {
                        $children['published'] = $file['date']->format('c');
                    }

                    $this->addChildren($entry, $children);
                }
        );

        $files[$this->options['destination']] = [
            'contents' => $this->createOutput($feed),
            'mode' => '0644',
            'path' => $this->options['destination'],
        ];

        return $next($files);
    }

    /**
     * Add an array recursively to the XML node.
     *
     * @param SimpleXMLElement $element
     * @param array $data
     */
    protected function addChildren(
        SimpleXMLElement $element,
        array $data
    ) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $element->addChild($key);
                $this->addChildren($child, $value);
            } else if ($value instanceof DateTime) {
                $element->addChild($key, $value->format(DateTime::ATOM));
            } else {
                $element->addChild($key, $value);
            }
        }
    }

    /**
     * Normalize options to a consistent internal form, converting
     * strings to arrays or whatever else is needed.
     *
     * @param array $options
     *
     * @return array
     */
    protected function normalize(array $options): array
    {
        if (!Arr::has($options, 'collection')) {
            throw new Exception('collection is required');
        }

        if (!Arr::has($options, 'feed')) {
            throw new Exception('feed is required');
        }

        $defaults = [
            'limit' => 20,
            'destination' => 'feed.xml',
        ];

        return array_merge(
            $defaults,
            $options
        );
    }

    /**
     * Transform an individual file's metadata.
     *
     * @param array $file
     * @param string $path
     *
     * @return array
     */
    protected function transform(array $file, string $path): array
    {
        $file['contents'] = $file['title'];

        return $file;
    }

    /**
     * @param SimpleXMLElement $feed
     *
     * @return string
     */
    protected function createOutput(SimpleXMLElement $feed): string
    {
        $dom = new DOMDocument();
        $dom->preserveWhitespace = false;
        $dom->formatOutput = true;
        $dom->loadXML($feed->asXML());

        return $dom->saveXML();
    }
}
