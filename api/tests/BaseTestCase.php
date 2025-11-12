<?php
use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase
{
    protected $conn;

    protected function setUp(): void
    {
        $this->conn = new PDO('sqlite::memory:');
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function tearDown(): void
    {
        $this->conn = null;
    }
}
