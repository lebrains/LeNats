<?php

namespace LeNats\Services;

use Illuminate\Support\Str;

class EventTypeResolver
{
    /** @var string[] */
    private $types;

    public function __construct(array $types)
    {
        $this->types = $types;
    }

    public function getClass($eventType)
    {
        $class = null;

        foreach ($this->types as $typeWildcard => $eventClass) {
            if ($typeWildcard === $eventType || Str::is($typeWildcard, $eventType)) {
                $class = $eventClass;
                break;
            }
        }

        return $class;
    }
}
