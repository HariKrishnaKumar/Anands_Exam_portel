<?php
/**
 * Seed script: Create 50 students + 5 MCQs, submit & grade all.
 * Run: php -f _seed_test.php
 */
require __DIR__ . '/src/php/config/db.php';
$pdo = getDB();

echo "=== SEEDING 50 STUDENTS + 5 MCQ TEST ===\n\n";

$batchId = 1;
$testId = 2; // Midterm Exam 2026 (already exists, batch 1)

// ─── Step 0: Clean old data ──────────────────────────────
echo "Step 0: Cleaning old data for test #$testId...\n";
$pdo->exec("DELETE sa FROM student_answers sa JOIN submissions s ON s.id=sa.submission_id WHERE s.test_id=$testId");
$pdo->exec("DELETE FROM pci_records WHERE test_id=$testId");
$pdo->exec("DELETE FROM tab_switch_logs WHERE submission_id IN (SELECT id FROM submissions WHERE test_id=$testId)");
$pdo->exec("DELETE FROM submissions WHERE test_id=$testId");
$pdo->exec("DELETE FROM questions WHERE test_id=$testId");
$pdo->exec("DELETE FROM students WHERE email LIKE 'student%@test.edu'");
echo "  → Cleaned\n";

// ─── Step 1: Create 50 random students ───────────────────
echo "\nStep 1: Creating 50 students in batch #$batchId...\n";
$firstNames = ['Aarav','Vivaan','Aditya','Vihaan','Arjun','Sai','Pranav','Dhruv','Krishna','Shaurya',
               'Aadhya','Ananya','Diya','Ishita','Myra','Sara','Navya','Riya','Saanvi','Anika',
               'Rohan','Karan','Amit','Ravi','Raj','Vikram','Akash','Deepak','Sunil','Manoj',
               'Priya','Neha','Sneha','Divya','Pooja','Kavita','Anjali','Shweta','Nidhi','Ritu',
               'Arun','Rahul','Vijay','Siddharth','Kunal','Harsh','Gaurav','Nitin','Tushar','Aryan'];
$lastNames = ['Sharma','Verma','Patel','Singh','Kumar','Gupta','Reddy','Joshi','Das','Nair',
              'Iyer','Menon','Desai','Shah','Mehta','Thakur','Chopra','Malhotra','Bajaj','Agarwal'];

$insertStudent = $pdo->prepare("INSERT INTO students (batch_id, name, phone, email, gender, college_name, branch, roll_number, year_of_joining, course_name, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$passwordHash = password_hash('student123', PASSWORD_BCRYPT);
$createdStudents = [];

for ($i = 1; $i <= 50; $i++) {
    $name = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    $email = 'student' . $i . '@test.edu';
    $phone = '98765' . str_pad($i, 5, '0');
    $gender = ['male','female','other'][rand(0,2)];
    $collegeName = 'MIT College of Engineering';
    $branch = ['CSE','ECE','EEE','Mech','Civil'][rand(0,4)];
    $roll = 'ROLL' . str_pad($i, 4, '0');
    $year = 2020 + rand(1, 4);
    $courseName = 'B.Tech Computer Science';
    $insertStudent->execute([$batchId, $name, $phone, $email, $gender, $collegeName, $branch, $roll, $year, $courseName, $passwordHash]);
    $createdStudents[] = (int)$pdo->lastInsertId();
}
echo "  → " . count($createdStudents) . " students created (IDs " . min($createdStudents) . "-" . max($createdStudents) . ")\n";

// ─── Step 2: Add 5 MCQs to test #2 ──────────────────────
echo "\nStep 2: Adding 5 MCQs to Midterm Exam (test #$testId)...\n";

$mcqs = [
    ['What does PHP stand for?', 'PHP: Hypertext Preprocessor', 'Personal Home Page', 'Private Hosting Protocol', 'Public HTML Pages', 'A', 2],
    ['Which function is used to connect to MySQL in PHP?', 'mysqli_connect()', 'mysql_connect()', 'db_connect()', 'connect_db()', 'A', 2],
    ['What is the correct way to declare a variable in PHP?', '$var_name', 'var_name', 'VAR_NAME', '@var_name', 'A', 2],
    ['Which superglobal holds POST data?', '$_POST', '$_GET', '$_REQUEST', '$_FORM', 'A', 2],
    ['What does "echo" do?', 'Outputs text', 'Returns a value', 'Creates a variable', 'Defines a function', 'A', 2],
];

$insertQ = $pdo->prepare("INSERT INTO questions (test_id, type, question_text, options_json, correct_answer, marks, sort_order, created_at) VALUES (?, 'mcq', ?, ?, ?, ?, ?, NOW())");
$questionIds = [];
$totalMarks = 0;

foreach ($mcqs as $i => $mcq) {
    list($qText, $optA, $optB, $optC, $optD, $correct, $marks) = $mcq;
    $options = json_encode(['A' => $optA, 'B' => $optB, 'C' => $optC, 'D' => $optD]);
    $insertQ->execute([$testId, $qText, $options, $correct, $marks, $i + 1]);
    $qId = (int)$pdo->lastInsertId();
    $questionIds[] = $qId;
    $totalMarks += $marks;
    echo "  MCQ #" . ($i + 1) . " (id=$qId): $qText (correct=$correct, {$marks}pts)\n";
}
echo "  Total MCQ marks: $totalMarks\n";

// ─── Step 3: Create submissions + answers for all 50 ─────
echo "\nStep 3: Creating submissions and random answers...\n";
$insertSub = $pdo->prepare("INSERT INTO submissions (student_id, test_id, status, started_at, submitted_at) VALUES (?, ?, 'submitted', NOW() - INTERVAL 30 MINUTE, NOW())");
$insertAns = $pdo->prepare("INSERT INTO student_answers (submission_id, question_id, answer_json, submitted_at) VALUES (?, ?, ?, NOW())");

foreach ($createdStudents as $sid) {
    $insertSub->execute([$sid, $testId]);
    $subId = (int)$pdo->lastInsertId();

    foreach ($questionIds as $qId) {
        $chosen = chr(65 + rand(0, 3)); // Random A, B, C, or D
        $insertAns->execute([$subId, $qId, json_encode(['selected' => $chosen])]);
    }
}
echo "  → " . count($createdStudents) . " submissions created with random answers\n";

// ─── Step 4: Auto-grade MCQs ─────────────────────────────
echo "\nStep 4: Auto-grading...\n";

// Get correct answers
$qInfo = [];
$stmt = $pdo->prepare("SELECT id, correct_answer, marks FROM questions WHERE test_id = ? ORDER BY id");
$stmt->execute([$testId]);
foreach ($stmt as $r) {
    $qInfo[$r['id']] = ['correct' => $r['correct_answer'], 'marks' => (int)$r['marks']];
}

$allSubs = $pdo->prepare("SELECT id, student_id FROM submissions WHERE test_id = ? AND status = 'submitted'");
$allSubs->execute([$testId]);
$allSubs = $allSubs->fetchAll();

$updateMarks = $pdo->prepare("UPDATE student_answers SET marks_obtained = ? WHERE submission_id = ? AND question_id = ?");
$updateSub = $pdo->prepare("UPDATE submissions SET status = 'evaluated', total_marks_obtained = ? WHERE id = ?");
$insertPCI = $pdo->prepare("INSERT INTO pci_records (student_id, test_id, pci_score, mcq_score, coding_score, explanation_score, generated_at) VALUES (?, ?, ?, ?, 0, 0, NOW())
    ON DUPLICATE KEY UPDATE pci_score=VALUES(pci_score), mcq_score=VALUES(mcq_score), generated_at=NOW()");

$scoreDist = [];
$correctDist = [];

foreach ($allSubs as $sub) {
    $obtained = 0;
    $correctCount = 0;

    foreach ($qInfo as $qId => $info) {
        $st = $pdo->prepare("SELECT answer_json FROM student_answers WHERE submission_id = ? AND question_id = ?");
        $st->execute([$sub['id'], $qId]);
        $ans = $st->fetchColumn();
        if ($ans) {
            $decoded = json_decode($ans, true);
            $selected = $decoded['selected'] ?? '';
            if (strtoupper($selected) === strtoupper($info['correct'])) {
                $obtained += $info['marks'];
                $correctCount++;
                $updateMarks->execute([$info['marks'], $sub['id'], $qId]);
            } else {
                $updateMarks->execute([0, $sub['id'], $qId]);
            }
        }
    }

    $updateSub->execute([$obtained, $sub['id']]);
    $pci = ($totalMarks > 0) ? round(($obtained / $totalMarks) * 100, 1) : 0;
    $insertPCI->execute([$sub['student_id'], $testId, $pci, $obtained]);

    $range = floor($pci / 10) * 10;
    $label = $range . '-' . ($range + 10) . '%';
    $scoreDist[$label] = ($scoreDist[$label] ?? 0) + 1;
    $correctDist[$correctCount] = ($correctDist[$correctCount] ?? 0) + 1;
}
echo "  → Grading complete!\n";

// ─── Step 5: Show results ────────────────────────────────
echo "\n" . str_repeat("=", 60);
echo "\n🎯 RESULTS SUMMARY\n";
echo str_repeat("=", 60) . "\n";

echo "\n── Score Distribution (PCI %) ──\n";
ksort($scoreDist);
foreach ($scoreDist as $range => $count) {
    $bar = str_repeat('█', $count);
    $pct = round(($count / count($allSubs)) * 100, 1);
    echo "  " . str_pad($range, 12) . "| {$bar} {$count} students ({$pct}%)\n";
}

echo "\n── Correct Answers Distribution ──\n";
ksort($correctDist);
foreach ($correctDist as $count => $numStudents) {
    $bar = str_repeat('█', $numStudents);
    $pct = round(($numStudents / count($allSubs)) * 100, 1);
    echo "  " . str_pad($count . "/5 correct", 14) . "| {$bar} {$numStudents} ({$pct}%)\n";
}

echo "\n── Top 5 Students ──\n";
$stmt = $pdo->prepare("SELECT s.name, sub.total_marks_obtained, p.pci_score FROM submissions sub JOIN students s ON s.id = sub.student_id LEFT JOIN pci_records p ON p.student_id = sub.student_id AND p.test_id = sub.test_id WHERE sub.test_id = ? AND sub.status = 'evaluated' ORDER BY sub.total_marks_obtained DESC LIMIT 5");
$stmt->execute([$testId]);
$rank = 1;
foreach ($stmt as $r) {
    echo "  #{$rank}: {$r['name']} — {$r['total_marks_obtained']}/{$totalMarks} pts (PCI: {$r['pci_score']}%)\n";
    $rank++;
}

echo "\n── Bottom 5 Students ──\n";
$stmt = $pdo->prepare("SELECT s.name, sub.total_marks_obtained, p.pci_score FROM submissions sub JOIN students s ON s.id = sub.student_id LEFT JOIN pci_records p ON p.student_id = sub.student_id AND p.test_id = sub.test_id WHERE sub.test_id = ? AND sub.status = 'evaluated' ORDER BY sub.total_marks_obtained ASC LIMIT 5");
$stmt->execute([$testId]);
$rank = 1;
foreach ($stmt as $r) {
    echo "  #{$rank}: {$r['name']} — {$r['total_marks_obtained']}/{$totalMarks} pts (PCI: {$r['pci_score']}%)\n";
    $rank++;
}

echo "\n── Overall Stats ──\n";
$stmt = $pdo->prepare("SELECT AVG(sub.total_marks_obtained) AS avg_marks, AVG(p.pci_score) AS avg_pci FROM submissions sub LEFT JOIN pci_records p ON p.student_id = sub.student_id AND p.test_id = sub.test_id WHERE sub.test_id = ? AND sub.status = 'evaluated'");
$stmt->execute([$testId]);
$avg = $stmt->fetch();
echo "  Average marks:  " . round($avg['avg_marks'], 1) . "/{$totalMarks}\n";
echo "  Average PCI:    " . round($avg['avg_pci'], 1) . "%\n";
echo "  Students tested: " . count($allSubs) . "\n";

echo "\n── Final DB Counts ──\n";
$tables = ['students','submissions','student_answers','pci_records'];
foreach ($tables as $t) {
    echo "  " . str_pad($t, 18) . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() . "\n";
}

echo "\n✅ SEED COMPLETE! All 50 students created, tested, and graded.\n";
echo "   Login: student1@test.edu / student123 (for any student)\n";
echo "   See admin reports at: http://localhost:8000/admin/reports.php\n";
