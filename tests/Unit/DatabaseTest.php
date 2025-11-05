<?php

namespace Tests\Unit;

use Hub\Database;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Database class
 *
 * Note: These tests require database connection.
 * Skipping if DB not available.
 */
class DatabaseTest extends TestCase
{
    private ?Database $db = null;

    protected function setUp(): void
    {
        try {
            $this->db = Database::getInstance();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        if (!$this->db) {
            $this->markTestSkipped('Database not available');
        }

        $db1 = Database::getInstance();
        $db2 = Database::getInstance();

        $this->assertSame($db1, $db2, 'Database should be a singleton');
    }

    public function testPrepareReturnsStatement(): void
    {
        if (!$this->db) {
            $this->markTestSkipped('Database not available');
        }

        $stmt = $this->db->prepare('SELECT 1 as test');

        $this->assertInstanceOf(\PDOStatement::class, $stmt);
    }

    public function testQueryExecution(): void
    {
        if (!$this->db) {
            $this->markTestSkipped('Database not available');
        }

        $stmt = $this->db->prepare('SELECT 1 + 1 as result');
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(2, $result['result']);
    }

    /** @test */
    public function it_gets_pdo_connection()
    {
        $pdo = $this->db->getConnection();

        $this->assertInstanceOf(\PDO::class, $pdo);
    }

    /** @test */
    public function it_executes_query_with_params()
    {
        $this->db->beginTransaction();

        // Create temp table for testing
        $this->db->execute("CREATE TEMPORARY TABLE test_query (id INT, name VARCHAR(50))");
        $this->db->execute("INSERT INTO test_query VALUES (?, ?)", [1, 'Test']);

        $result = $this->db->query("SELECT * FROM test_query WHERE id = ?", [1]);

        $this->assertInstanceOf(\PDOStatement::class, $result);
        $row = $result->fetch();
        $this->assertEquals('Test', $row['name']);

        $this->db->rollBack();
    }

    /** @test */
    public function it_fetches_all_rows()
    {
        $this->db->beginTransaction();

        $this->db->execute("CREATE TEMPORARY TABLE test_fetchall (id INT, value VARCHAR(20))");
        $this->db->execute("INSERT INTO test_fetchall VALUES (1, 'First'), (2, 'Second'), (3, 'Third')");

        $results = $this->db->fetchAll("SELECT * FROM test_fetchall ORDER BY id");

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertEquals('First', $results[0]['value']);
        $this->assertEquals('Second', $results[1]['value']);
        $this->assertEquals('Third', $results[2]['value']);

        $this->db->rollBack();
    }

    /** @test */
    public function it_fetches_one_row()
    {
        $this->db->beginTransaction();

        $this->db->execute("CREATE TEMPORARY TABLE test_fetchone (id INT, data VARCHAR(20))");
        $this->db->execute("INSERT INTO test_fetchone VALUES (1, 'SingleRow'), (2, 'Another')");

        $result = $this->db->fetchOne("SELECT * FROM test_fetchone WHERE id = ?", [1]);

        $this->assertIsArray($result);
        $this->assertEquals('SingleRow', $result['data']);

        $this->db->rollBack();
    }

    /** @test */
    public function it_returns_false_for_fetchone_with_no_results()
    {
        $this->db->beginTransaction();

        $this->db->execute("CREATE TEMPORARY TABLE test_empty (id INT)");

        $result = $this->db->fetchOne("SELECT * FROM test_empty WHERE id = ?", [999]);

        $this->assertFalse($result);

        $this->db->rollBack();
    }

    /** @test */
    public function it_executes_sql()
    {
        $this->db->beginTransaction();

        $this->db->execute("CREATE TEMPORARY TABLE test_execute (id INT)");
        $result = $this->db->execute("INSERT INTO test_execute VALUES (1), (2)");

        $this->assertInstanceOf(\PDOStatement::class, $result);

        $count = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM test_execute");
        $this->assertEquals(2, $count['cnt']);

        $this->db->rollBack();
    }

    /** @test */
    public function it_returns_last_insert_id()
    {
        $this->db->beginTransaction();

        $this->db->execute("CREATE TEMPORARY TABLE test_lastid (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20))");
        $this->db->execute("INSERT INTO test_lastid (name) VALUES ('Test')");

        $lastId = $this->db->lastInsertId();

        $this->assertGreaterThan(0, $lastId);

        $this->db->rollBack();
    }

    /** @test */
    public function it_begins_transaction()
    {
        $result = $this->db->beginTransaction();

        $this->assertTrue($result);
        $this->assertTrue($this->db->inTransaction());

        $this->db->rollBack();
    }

    /** @test */
    public function it_commits_transaction()
    {
        $this->db->beginTransaction();

        $this->db->execute("CREATE TEMPORARY TABLE test_commit (id INT)");
        $this->db->execute("INSERT INTO test_commit VALUES (1)");

        $result = $this->db->commit();

        $this->assertTrue($result);
        $this->assertFalse($this->db->inTransaction());
    }

    /** @test */
    public function it_rolls_back_transaction()
    {
        $this->db->beginTransaction();

        $this->db->execute("CREATE TEMPORARY TABLE test_rollback (id INT)");
        $this->db->execute("INSERT INTO test_rollback VALUES (1)");

        $result = $this->db->rollBack();

        $this->assertTrue($result);
        $this->assertFalse($this->db->inTransaction());
    }

    /** @test */
    public function it_checks_if_in_transaction()
    {
        $this->assertFalse($this->db->inTransaction());

        $this->db->beginTransaction();
        $this->assertTrue($this->db->inTransaction());

        $this->db->rollBack();
        $this->assertFalse($this->db->inTransaction());
    }

    /** @test */
    public function it_inserts_record_using_helper()
    {
        $this->db->beginTransaction();

        $this->db->execute("CREATE TEMPORARY TABLE test_insert_helper (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50),
            email VARCHAR(50),
            active TINYINT
        )");

        $lastId = $this->db->insert('test_insert_helper', [
            'name' => 'John Doe',
            'email' => 'john@test.com',
            'active' => 1
        ]);

        $this->assertGreaterThan(0, $lastId);

        $record = $this->db->fetchOne("SELECT * FROM test_insert_helper WHERE id = ?", [$lastId]);
        $this->assertEquals('John Doe', $record['name']);
        $this->assertEquals('john@test.com', $record['email']);
        $this->assertEquals(1, $record['active']);

        $this->db->rollBack();
    }

    /** @test */
    public function it_updates_record_using_helper()
    {
        $this->db->beginTransaction();

        $this->db->execute("CREATE TEMPORARY TABLE test_update_helper (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50),
            status VARCHAR(20)
        )");

        $id = $this->db->insert('test_update_helper', [
            'name' => 'Original',
            'status' => 'pending'
        ]);

        $result = $this->db->update('test_update_helper', $id, [
            'name' => 'Updated',
            'status' => 'active'
        ]);

        $this->assertTrue($result);

        $record = $this->db->fetchOne("SELECT * FROM test_update_helper WHERE id = ?", [$id]);
        $this->assertEquals('Updated', $record['name']);
        $this->assertEquals('active', $record['status']);

        $this->db->rollBack();
    }

    /** @test */
    public function it_handles_multiple_inserts_in_transaction()
    {
        $this->db->beginTransaction();

        $this->db->execute("CREATE TEMPORARY TABLE test_multi (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(20))");

        $id1 = $this->db->insert('test_multi', ['value' => 'First']);
        $id2 = $this->db->insert('test_multi', ['value' => 'Second']);
        $id3 = $this->db->insert('test_multi', ['value' => 'Third']);

        $this->assertGreaterThan($id1, $id2);
        $this->assertGreaterThan($id2, $id3);

        $count = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM test_multi");
        $this->assertEquals(3, $count['cnt']);

        $this->db->rollBack();
    }

    /** @test */
    public function it_resets_singleton_instance()
    {
        $instance1 = Database::getInstance();

        Database::resetInstance();

        $instance2 = Database::getInstance();

        // After reset, should be different instances
        $this->assertNotSame($instance1, $instance2);
    }
}
