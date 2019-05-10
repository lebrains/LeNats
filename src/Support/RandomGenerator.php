<?php

namespace LeNats\Support;

class RandomGenerator
{
    /**
     * A simple wrapper on random_bytes.
     *
     * @param integer $length Length of the string.
     *
     * @return string Random string.
     * @throws \Exception
     */
    public function generateString(int $length): string
    {
        return bin2hex(random_bytes($length));
    }
}
