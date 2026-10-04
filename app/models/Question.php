<?php
/**
 * app/models/Question.php  —  MCQ question + options CRUD, diagram uploads
 */
require_once dirname(__DIR__, 2) . '/config/database.php';

class Question
{
    private PDO $db;

    public function __construct() { $this->db = Database::getConnection(); }

    /** Questions for an exam, optionally shuffled, with options attached */
    public function forExam(int $examId, bool $shuffleQuestions = false, bool $shuffleOptions = false): array
    {
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE exam_id = :eid ORDER BY id ASC");
        $stmt->execute(['eid' => $examId]);
        $questions = $stmt->fetchAll();

        if ($shuffleQuestions) {
            shuffle($questions);
        }

        // ADDED 'is_correct' TO THE SELECT STATEMENT BELOW
        $optStmt = $this->db->prepare("SELECT id, option_text, option_letter, is_correct FROM options WHERE question_id = :qid ORDER BY id ASC");
        
        foreach ($questions as &$q) {
            $optStmt->execute(['qid' => $q['id']]);
            $opts = $optStmt->fetchAll();
            if ($shuffleOptions) {
                shuffle($opts);
                // Re-letter A, B, C... after shuffling
                foreach ($opts as $i => $o) {
                    $opts[$i]['option_letter'] = chr(65 + $i);
                }
            }
            $q['options'] = $opts;
        }
        unset($q);

        return $questions;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $q = $stmt->fetch();
        if (!$q) {
            return null;
        }
        $optStmt = $this->db->prepare("SELECT * FROM options WHERE question_id = :qid ORDER BY id ASC");
        $optStmt->execute(['qid' => $id]);
        $q['options'] = $optStmt->fetchAll();
        return $q;
    }

    /**
     * Insert a question with its options in one transaction.
     * $options = [['option_text' => '...', 'is_correct' => 0|1], ...]
     */
    public function create(int $examId, string $text, ?string $diagramPath, ?string $explanation, array $options): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO questions (exam_id, question_text, diagram_path, explanation)
                 VALUES (:eid, :text, :diagram, :expl)"
            );
            $stmt->execute([
                'eid'     => $examId,
                'text'    => trim($text),
                'diagram' => $diagramPath,
                'expl'    => $explanation,
            ]);
            $qid = (int)$this->db->lastInsertId();
            $this->saveOptions($qid, $options);
            $this->db->commit();
            return $qid;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, string $text, ?string $diagramPath, ?string $explanation, array $options): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE questions SET question_text = :text, diagram_path = :diagram, explanation = :expl WHERE id = :id"
            );
            $stmt->execute(['text' => trim($text), 'diagram' => $diagramPath, 'expl' => $explanation, 'id' => $id]);
            $this->db->prepare("DELETE FROM options WHERE question_id = :qid")->execute(['qid' => $id]);
            $this->saveOptions($id, $options);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $q = $this->find($id);
        if ($q && !empty($q['diagram_path'])) {
            $file = APP_ROOT . '/public' . $q['diagram_path'];
            if (is_file($file)) {
                @unlink($file);
            }
        }
        return $this->db->prepare("DELETE FROM questions WHERE id = :id")->execute(['id' => $id]);
    }

    private function saveOptions(int $questionId, array $options): void
    {
        $ins = $this->db->prepare(
            "INSERT INTO options (question_id, option_text, is_correct, option_letter)
             VALUES (:qid, :text, :correct, :letter)"
        );
        $i = 0;
        foreach ($options as $opt) {
            $text = trim((string)($opt['option_text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $ins->execute([
                'qid'     => $questionId,
                'text'    => $text,
                'correct' => !empty($opt['is_correct']) ? 1 : 0,
                'letter'  => chr(65 + $i),
            ]);
            $i++;
        }
    }

    /**
     * Secure diagram upload -> hashed filename under public/uploads/diagrams/
     * Returns the public path (e.g. "/uploads/diagrams/9f3c...webp") or null.
     */
    public static function uploadDiagram(array $file): ?string
    {
        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if ($file['size'] > MAX_IMG_BYTES) {
            throw new RuntimeException('Diagram exceeds the 3 MB size limit.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_IMG_EXT, true)) {
            throw new RuntimeException('Only PNG, JPG, WEBP, GIF and SVG diagrams are allowed.');
        }

        // Verify the file really is an image (SVG skipped: xml markup)
        if ($ext !== 'svg') {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false) {
                throw new RuntimeException('Uploaded file is not a valid image.');
            }
        }

        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0775, true);
        }

        $hashedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $target     = UPLOAD_PATH . $hashedName;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Failed to move uploaded diagram into public/uploads/diagrams/.');
        }

        return '/uploads/diagrams/' . $hashedName;
    }

    public function countForExam(int $examId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = :eid");
        $stmt->execute(['eid' => $examId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Bulk import questions from a CSV file.
     * Returns the number of successfully imported questions.
     */
    public function bulkCreateFromCsv(int $examId, string $csvFilePath): int
    {
        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Cannot open the CSV file.");
        }

        // Read and skip the header row
        $headers = fgetcsv($handle);
        if (!$headers || count($headers) < 6) {
            fclose($handle);
            throw new \RuntimeException("Invalid CSV format. Missing required columns.");
        }

        $this->db->beginTransaction();
        $count = 0;
        $rowNumber = 1;

        try {
            $qStmt = $this->db->prepare(
                "INSERT INTO questions (exam_id, question_text, explanation) VALUES (:eid, :text, :expl)"
            );
            $oStmt = $this->db->prepare(
                "INSERT INTO options (question_id, option_text, is_correct, option_letter) VALUES (:qid, :text, :correct, :letter)"
            );

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                // Skip completely empty rows
                if (empty($row[0])) continue;

                $qText    = trim($row[0] ?? '');
                $optA     = trim($row[1] ?? '');
                $optB     = trim($row[2] ?? '');
                $optC     = trim($row[3] ?? '');
                $optD     = trim($row[4] ?? '');
                $correct  = strtoupper(trim($row[5] ?? 'A'));
                $expl     = trim($row[6] ?? '');

                if ($qText === '' || $optA === '') {
                    throw new \RuntimeException("Row {$rowNumber}: Question text and Option A are required.");
                }
                if (!in_array($correct, ['A', 'B', 'C', 'D', 'E'])) {
                    throw new \RuntimeException("Row {$rowNumber}: correct_option must be A, B, C, or D.");
                }

                // 1. Insert Question
                $qStmt->execute([
                    'eid'  => $examId,
                    'text' => $qText,
                    'expl' => $expl
                ]);
                $qid = (int)$this->db->lastInsertId();

                // 2. Insert Options
                $options = [
                    ['text' => $optA, 'correct' => ($correct === 'A' ? 1 : 0), 'letter' => 'A'],
                    ['text' => $optB, 'correct' => ($correct === 'B' ? 1 : 0), 'letter' => 'B'],
                    ['text' => $optC, 'correct' => ($correct === 'C' ? 1 : 0), 'letter' => 'C'],
                    ['text' => $optD, 'correct' => ($correct === 'D' ? 1 : 0), 'letter' => 'D'],
                ];

                foreach ($options as $opt) {
                    if ($opt['text'] !== '') {
                        $oStmt->execute([
                            'qid'     => $qid,
                            'text'    => $opt['text'],
                            'correct' => $opt['correct'],
                            'letter'  => $opt['letter']
                        ]);
                    }
                }
                $count++;
            }

            fclose($handle);
            $this->db->commit();
            return $count;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            if (is_resource($handle)) fclose($handle);
            throw new \RuntimeException("Import failed at row {$rowNumber}: " . $e->getMessage());
        }
    }
}
