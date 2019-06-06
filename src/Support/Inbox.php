<?php

namespace LeNats\Support;

class Inbox
{
    public const DISCOVER_PREFIX = '_STAN.discover';

    public static function newInbox(string $prefix = '_INBOX.'): string
    {
        return uniqid($prefix, false);
    }

    public static function getDiscoverSubject(string $clusterId): string
    {
        return self::DISCOVER_PREFIX . '.' . $clusterId;
    }
}
