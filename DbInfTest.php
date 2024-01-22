<?php

namespace Tests\Database\DbIntfTest;

use FpDbTest\DatabaseInterface;
use Testing\TestCase;

class DbIntfTest extends TestCase
{
    private DatabaseInterface $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new DatabaseInterface();
    }

    public function testBuildQuery(): void
    {
        $results = [];

        $results[] = $this->db->buildQuery('SELECT name FROM users WHERE user_id = 1');

        $results[] = $this->db->buildQuery(
            'SELECT * FROM users WHERE name = ? AND block = 0',
            ['Jack']
        );

        $results[] = $this->db->buildQuery(
            'SELECT ?# FROM users WHERE user_id = ?d AND block = ?d',
            [['name', 'email'], 2, true]
        );


        $results[] = $this->db->buildQuery(
            'UPDATE users SET ?a WHERE user_id = -1',
            [['name' => 'Jack', 'email' => null]]
        );

        foreach ([null, true] as $block) {
            $results[] = $this->db->buildQuery(
                'SELECT name FROM users WHERE ?# IN (?a){ AND block = ?d}',
                ['user_id', [1, 2, 3], $block ?? $this->db->skip()]
            );
        }

        $correct = [
            'SELECT name FROM users WHERE user_id = 1',
            'SELECT * FROM users WHERE name = \'Jack\' AND block = 0',
            'SELECT `name`, `email` FROM users WHERE user_id = 2 AND block = 1',
            'UPDATE users SET `name` = \'Jack\', `email` = NULL WHERE user_id = -1',
            'SELECT name FROM users WHERE `user_id` IN (1, 2, 3)',
            'SELECT name FROM users WHERE `user_id` IN (1, 2, 3) AND block = 1',
        ];

        $this->assertEquals($correct, $results);
    }

    public function testNotEnoughParams1()
    {
        $this->expectExceptionMessage("Not enough params!");
        $this->db->buildQuery(
            'SELECT * FROM users WHERE name = ? AND block = 0',
            []
        );
    }

    public function testNotEnoughParams2()
    {
        $this->expectExceptionMessage("Not enough params!");
        $this->db->buildQuery(
            'SELECT * FROM users WHERE name = ? AND block = ?d',
            ["Jack"]
        );
    }

    public function testNullValue()
    {
        $this->expectExceptionMessage("NULL value isn't allowed for the format specifier \"#\".");
        $this->db->buildQuery(
            'SELECT ?# FROM users WHERE user_id = ?d AND block = ?d',
            [null, 2, true]
        );
    }

    public function testInvalidType()
    {
        $this->expectExceptionMessage("Invalid parameter type for empty specifier!");
        $this->db->buildQuery(
            'UPDATE users SET ? WHERE user_id = -1',
            [['name' => 'Jack', 'email' => null]]
        );
    }

    public function testSpecifier()
    {
        $this->expectExceptionMessage("Invalid format specifier \"k\".");
        $this->db->buildQuery(
            'SELECT * FROM users WHERE name = ?k AND block = 0',
            ["Jack"]
        );
    }

    public function testInvalidType2()
    {
        $this->expectExceptionMessage("Invalid parameter type for format specifier \"d\"");
        $this->db->buildQuery(
            'SELECT * FROM users WHERE name = ? AND block = ?d',
            ["Jack", "Peter"]
        );
    }

    public function testInvalidType3()
    {
        $this->expectExceptionMessage("Invalid parameter type for format specifier \"d\"");
        $this->db->buildQuery(
            'SELECT * FROM users WHERE name = ? AND block = ?d',
            ["Jack", 2.1]
        );
    }

    public function testInvalidType4()
    {
        $this->expectExceptionMessage("Invalid parameter type for format specifier \"f\"");
        $this->db->buildQuery(
            'SELECT * FROM users WHERE name = ? AND block = ?f',
            ["Jack", "Peter"]
        );
    }

    public function testEmptyArray()
    {
        $this->expectExceptionMessage("Array param shouldn't be empty!");
        $this->db->buildQuery(
            'UPDATE users SET ?a WHERE user_id = -1',
            [[]]
        );
    }
}