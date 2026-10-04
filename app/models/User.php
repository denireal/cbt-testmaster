<?php
/**
 * app/models/User.php  —  Examinee / Admin account management + auth
 */
require_once dirname(__DIR__, 2) . '/config/database.php';

class User
{
    private PDO $db;

    public function __construct() { $this->db = Database::getConnection(); }

    public function findByRegNumber(string $regNumber): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE reg_number = :reg LIMIT 1");
        $stmt->execute(['reg' => strtoupper(trim($regNumber))]);
        return $stmt->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** bcrypt verify — never store or compare plain text in production */
    public function attemptLogin(string $regNumber, string $password): ?array
    {
        $user = $this->findByRegNumber($regNumber);
        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }
        unset($user['password']);
        return $user;
    }

    public function allExaminees(): array
    {
        $sql = "SELECT u.id, u.reg_number, u.full_name, u.email, u.created_at,
                       GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR ', ') AS group_names,
                       GROUP_CONCAT(g.id)                                  AS group_ids
                FROM users u
                LEFT JOIN user_groups ug ON ug.user_id = u.id
                LEFT JOIN groups g       ON g.id = ug.group_id
                WHERE u.role = 'examinee'
                GROUP BY u.id
                ORDER BY u.reg_number";
        return $this->db->query($sql)->fetchAll();
    }

    public function createExaminee(string $regNumber, string $fullName, ?string $email, string $password, array $groupIds = []): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO users (reg_number, full_name, email, password, role)
                 VALUES (:reg, :name, :email, :pass, 'examinee')"
            );
            $stmt->execute([
                'reg'   => strtoupper(trim($regNumber)),
                'name'  => trim($fullName),
                'email' => $email,
                'pass'  => password_hash($password, PASSWORD_BCRYPT),
            ]);

            $userId = (int)$this->db->lastInsertId();

            $ins = $this->db->prepare("INSERT INTO user_groups (user_id, group_id) VALUES (:uid, :gid)");
            foreach ($groupIds as $gid) {
                $ins->execute(['uid' => $userId, 'gid' => (int)$gid]);
            }

            $this->db->commit();
            return $userId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateExaminee(int $id, string $regNumber, string $fullName, ?string $email, array $groupIds = [], ?string $password = null): bool
    {
        $this->db->beginTransaction();
        try {
            if ($password !== null && $password !== '') {
                $stmt = $this->db->prepare(
                    "UPDATE users SET reg_number = :reg, full_name = :name, email = :email, password = :pass WHERE id = :id"
                );
                $stmt->execute([
                    'reg'   => strtoupper(trim($regNumber)),
                    'name'  => trim($fullName),
                    'email' => $email,
                    'pass'  => password_hash($password, PASSWORD_BCRYPT),
                    'id'    => $id,
                ]);
            } else {
                $stmt = $this->db->prepare(
                    "UPDATE users SET reg_number = :reg, full_name = :name, email = :email WHERE id = :id"
                );
                $stmt->execute([
                    'reg'   => strtoupper(trim($regNumber)),
                    'name'  => trim($fullName),
                    'email' => $email,
                    'id'    => $id,
                ]);
            }

            $this->db->prepare("DELETE FROM user_groups WHERE user_id = :uid")->execute(['uid' => $id]);
            $ins = $this->db->prepare("INSERT INTO user_groups (user_id, group_id) VALUES (:uid, :gid)");
            foreach ($groupIds as $gid) {
                $ins->execute(['uid' => $id, 'gid' => (int)$gid]);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare("DELETE FROM users WHERE id = :id")->execute(['id' => $id]);
    }

    public function countExaminees(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM users WHERE role = 'examinee'")->fetchColumn();
    }


    /**
     * Bulk import examinees from a CSV file.
     * Expected columns: reg_number, full_name, email, password, group_name
     * 
     * @return array ['created' => int, 'skipped' => int]
     */
    public function bulkCreateFromCsv(string $csvFilePath): array
    {
        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Cannot open the CSV file.");
        }

        // Read and skip the header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new \RuntimeException("Invalid or empty CSV file.");
        }

        // Strip UTF-8 BOM from the first header if Excel added it
        if (isset($headers[0]) && strncmp($headers[0], "\xEF\xBB\xBF", 3) === 0) {
            $headers[0] = substr($headers[0], 3);
        }

        $this->db->beginTransaction();
        $created = 0;
        $skipped = 0;
        $rowNumber = 1;

        try {
            $userStmt  = $this->db->prepare(
                "INSERT INTO users (reg_number, full_name, email, password, role) 
                 VALUES (:reg, :name, :email, :pass, 'examinee')"
            );
            $checkStmt = $this->db->prepare("SELECT id FROM users WHERE reg_number = :reg LIMIT 1");
            $groupStmt = $this->db->prepare("SELECT id FROM groups WHERE name = :name LIMIT 1");
            $linkStmt  = $this->db->prepare("INSERT INTO user_groups (user_id, group_id) VALUES (:uid, :gid)");

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                
                // Skip completely empty rows
                if (empty($row[0]) && empty($row[1])) {
                    continue;
                }

                $reg       = strtoupper(trim($row[0] ?? ''));
                $name      = trim($row[1] ?? '');
                $email     = trim($row[2] ?? '');
                $pass      = trim($row[3] ?? 'student123');
                $groupName = trim($row[4] ?? '');

                if (!$reg || !$name) {
                    $skipped++;
                    continue;
                }

                // Skip if Reg Number already exists
                $checkStmt->execute(['reg' => $reg]);
                if ($checkStmt->fetch()) {
                    $skipped++;
                    continue;
                }

                // Hash the password securely
                $hashedPass = password_hash($pass, PASSWORD_BCRYPT); 

                $userStmt->execute([
                    'reg'   => $reg,
                    'name'  => $name,
                    'email' => $email !== '' ? $email : null,
                    'pass'  => $hashedPass
                ]);
                
                $uid = (int)$this->db->lastInsertId();
                $created++;

                // Assign to group if provided and exists in the database
                if ($groupName !== '') {
                    $groupStmt->execute(['name' => $groupName]);
                    $group = $groupStmt->fetch();
                    if ($group) {
                        $linkStmt->execute(['uid' => $uid, 'gid' => (int)$group['id']]);
                    }
                }
            }

            fclose($handle);
            $this->db->commit();
            return ['created' => $created, 'skipped' => $skipped];

        } catch (Throwable $e) {
            $this->db->rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new \RuntimeException("Import failed at row {$rowNumber}: " . $e->getMessage());
        }
    }
}
