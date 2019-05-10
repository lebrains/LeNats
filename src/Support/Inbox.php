<?php

namespace LeNats\Support;

class Inbox
{
    public const DISCOVER_PREFIX = '_STAN.discover';

    public static function newInbox($prefix = '_INBOX.'): string
    {
        return uniqid($prefix, false);
    }

    public static function getDiscoverSubject($clusterId)
    {
        return self::DISCOVER_PREFIX . '.' . $clusterId;
    }
}
