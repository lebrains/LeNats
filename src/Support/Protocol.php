<?php

namespace LeNats\Support;

abstract class Protocol
{
    public const CR_LF = "\r\n";
    public const EMPTY = '';
    public const SPC = ' ';
    public const OK = '+OK';
    public const ERR = '-ERR';
    public const MSG = 'MSG';
    public const PING = 'PING';
    public const PONG = 'PONG';
    public const INFO = 'INFO';
    public const SUB = 'SUB';
    public const PUB = 'PUB';
    public const UNSUB = 'UNSUB';
    public const CONNECT = 'CONNECT';

    public static function getServerMethods(): array
    {
        return [
            self::INFO,
            self::MSG,
            self::PING,
            self::PONG,
            self::ERR,
            self::OK,
        ];
    }

    public static function getClientMethods(): array
    {
        return [
            self::CONNECT,
            self::PUB,
            self::SUB,
            self::UNSUB,
            self::PING,
            self::PONG,
        ];
    }
}
