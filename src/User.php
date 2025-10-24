<?php

namespace Hub;

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll()
    {
        return $this->db->fetchAll("SELECT * FROM users ORDER BY name ASC");
    }

    public function getById($id)
    {
        return $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public function updateRole($id, $role)
    {
        return $this->db->execute("UPDATE users SET role = ? WHERE id = ?", [$role, $id]);
    }

    public function deactivate($id)
    {
        return $this->db->execute("UPDATE users SET is_active = FALSE WHERE id = ?", [$id]);
    }

    public function activate($id)
    {
        return $this->db->execute("UPDATE users SET is_active = TRUE WHERE id = ?", [$id]);
    }

    public function approve($id, $approvedBy)
    {
        return $this->db->execute(
            "UPDATE users SET is_active = TRUE, approved_by = ?, approved_at = NOW() WHERE id = ?",
            [$approvedBy, $id]
        );
    }

    public function getPending()
    {
        return $this->db->fetchAll("SELECT * FROM users WHERE is_active = FALSE ORDER BY created_at DESC");
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM users WHERE id = ?", [$id]);
    }
}
