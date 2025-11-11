<?php
use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase
{
    protected static $conn;

    public static function setUpBeforeClass(): void
    {
        self::$conn = new PDO('sqlite::memory:');
        self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function tearDownAfterClass(): void
    {
        self::$conn = null;
    }
}
