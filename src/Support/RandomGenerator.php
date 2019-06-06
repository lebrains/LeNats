<?php

namespace LeNats\Support;

use Exception;

class RandomGenerator
{
    /**
     * A simple wrapper on random_bytes.
     *
     * @param int $length Length of the string.
     *
     * @throws Exception
     * @return string    Random string.
     */
    public function generateString(int $length): string
    {
        return bin2hex(random_bytes($length));
    }
}
