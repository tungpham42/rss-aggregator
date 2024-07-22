<?php
header('Content-Type: application/json');

$feed_url = $_GET['rssUrl'];

function removeCdataTags($input) {
    return str_replace(["<![CDATA[", "]]>"], "", $input);
}

function isGravatarUrl($url) {
    return strpos($url, 'gravatar.com') !== false;
}

function fetchRSS($url) {
    $rss = simplexml_load_file($url);
    $items = [];
    
    // Detect if the feed is RSS or Atom
    $isRSS = isset($rss->channel->item);
    $isAtom = isset($rss->entry);

    if ($isRSS) {
        foreach ($rss->channel->item as $item) {
            $content_encoded = (string) $item->children('content', true)->encoded;
            if (!$content_encoded) {
                $content_encoded = $item->description;
            }
            if (!$item->description || $content_encoded) {
                $item->description = $content_encoded;
            }
            
            // Extract image URL if available
            $image_url = '/rss_noimg.png';
            if (isset($item->enclosure)) {
                $image_url = (string) $item->enclosure->attributes()->url;
            } elseif ($item->children('media', true)->content && !preg_match('/<img[^>]+src=["\']([^">]+)["\']/i', $item->description, $matches)) {
                $image_url = (string) $item->children('media', true)->content->attributes()->url;
                if (isGravatarUrl($image_url)) {
                    foreach ($item->children('media', true)->content as $mediaContent) {
                        $url = (string) $mediaContent->attributes()->url;
                        if (!isGravatarUrl($url)) {
                            $image_url = $url;
                        } else {
                            $image_url = '/rss_noimg.png';
                        }
                    }
                }
            } elseif ($item->children('media', true)->thumbnail && !preg_match('/<img[^>]+src=["\']([^">]+)["\']/i', $item->description, $matches)) {
                $image_url = (string) $item->children('media', true)->thumbnail->attributes()->url;
            } elseif (preg_match('/<img[^>]+src=["\']([^">]+)["\']/i', $item->description, $matches)) {
                $image_url = $matches[1];
            }

            $items[] = [
                'title' => (string) html_entity_decode(removeCdataTags($item->title)),
                'link'  => (string) $item->link,
                'description' => (string) html_entity_decode(removeCdataTags(htmlspecialchars($item->description))),
                'content' => (string) html_entity_decode(removeCdataTags(htmlspecialchars($content_encoded))),
                'pubDate' => (string) $item->pubDate,
                'image' => $image_url,
            ];
        }
    } elseif ($isAtom) {
        foreach ($rss->entry as $entry) {
            $content_encoded = (string) $entry->content;
            $releaseDate = $entry->children('im', true)->releaseDate;
            if (!$releaseDate) {
                $releaseDate = $entry->updated;
            }
            if (!$content_encoded) {
                $content_encoded = $entry->summary;
            }
            if (!$entry->summary || $content_encoded) {
                $entry->summary = $content_encoded;
            }
            
            // Extract image URL if available
            $image_url = '/rss_noimg.png';
            if (preg_match('/<img[^>]+src=["\']([^">]+)["\']/i', $entry->summary, $matches)) {
                $image_url = $matches[1];
            }

            $items[] = [
                'title' => (string) html_entity_decode(removeCdataTags($entry->title)),
                'link'  => (string) $entry->link->attributes()->href,
                'description' => (string) html_entity_decode(removeCdataTags(htmlspecialchars($entry->summary))),
                'content' => (string) html_entity_decode(removeCdataTags(htmlspecialchars($content_encoded))),
                'pubDate' => (string) $releaseDate,
                'image' => $image_url,
            ];
        }
    }
    
    return $items;
}

echo json_encode(fetchRSS($feed_url));
?>
