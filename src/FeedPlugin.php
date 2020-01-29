<?php

namespace Datashaman\Tongs\Plugins;

use Datashaman\Tongs\Plugins\Plugin;
use Datashaman\Tongs\Tongs;
use DateTime;
use DOMDocument;
use Exception;
use SimpleXMLElement;

class FeedPlugin extends Plugin
{
    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $options = $this->normalize($options);

        parent::__construct($options);
    }

    /**
     * Handle files passed down the pipeline, and call the next plugin in the pipeline.
     *
     * @param array $files
     * @param callable $next
     *
     * @return array
     */
    public function handle(array $files, callable $next): array
    {
        $metadata = $this->tongs()->metadata();

        if (!isset($metadata['collections'])) {
            throw new Exception('No collections configured, use collections plugin');
        }

        $feed = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><feed xmlns="http://www.w3.org/2005/Atom"></feed>');

        $this->addChildren($feed, $this->options['feed']);

        $collection = $this->options['collection'];

        $collectionFiles = array_slice(
            $metadata['collections'][$collection],
            0,
            $this->options['limit']
        );

        foreach ($collectionFiles as $path => $file) {
            $link = $file['path'] ?? $path;
            $link = "{$feed->link}{$link}";

            $entry = $feed->addChild('entry');

            $children = [
                'title' => $file['title'] ?? '',
                'link' => $link,
                'summary' => $file[$this->options['excerpt'] ?? 'excerpt']
                    ?? $file['contents']
                    ?? '',
            ];

            if (isset($file['date'])) {
                $children['published'] = $file['date']->format('c');
            }

            $this->addChildren($entry, $children);
        }

        $files[$this->options['destination']] = [
            'contents' => $this->createOutput($feed),
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
        if (!isset($options['collection'])) {
            throw new Exception('collection is required');
        }

        if (!isset($options['feed'])) {
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
