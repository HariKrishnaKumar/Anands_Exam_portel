<?php
/**
 * Seed Script: 50 Students · 5 Colleges · 5 Tests
 * 
 * Run from project root: php sql/seed_test_data.php
 * 
 * Creates:
 *   - 5 colleges, 5 courses, 5 batches (10 students each = 50 total)
 *   - 5 tests (one per batch) with 5 MCQ questions each
 *   - Submissions + answers for every student (evaluated)
 *   - Student login password: student123
 */

// ─── Bootstrap ──────────────────────────────────────────────
require_once __DIR__ . '/../src/php/config/db.php';

echo "🌱 Seeding test data...\n\n";

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // ─── Password hash (student123) ─────────────────────────
    $passwordHash = password_hash('student123', PASSWORD_BCRYPT);
    echo "   Password hash generated\n";

    // ─── 1. COLLEGES ─────────────────────────────────────────
    $colleges = [
        ['name' => 'Sri Ramachandra Institute of Technology',     'address' => 'Chennai, Tamil Nadu'],
        ['name' => 'St. Joseph\'s College of Engineering',        'address' => 'Bangalore, Karnataka'],
        ['name' => 'BMS College of Engineering',                  'address' => 'Bangalore, Karnataka'],
        ['name' => 'PES Institute of Technology',                 'address' => 'Bangalore, Karnataka'],
        ['name' => 'RV College of Engineering',                  'address' => 'Bangalore, Karnataka'],
    ];

    $collegeIds = [];
    $stmt = $pdo->prepare("INSERT INTO colleges (name, address) VALUES (?, ?)");
    foreach ($colleges as $c) {
        $stmt->execute([$c['name'], $c['address']]);
        $collegeIds[] = $pdo->lastInsertId();
        echo "   + College: {$c['name']}\n";
    }

    // ─── 2. COURSES (one per college) ──────────────────────
    $courses = [
        ['college_idx' => 0, 'name' => 'B.Tech Computer Science & Engineering'],
        ['college_idx' => 1, 'name' => 'B.Tech Information Science & Engineering'],
        ['college_idx' => 2, 'name' => 'B.Tech Electronics & Communication'],
        ['college_idx' => 3, 'name' => 'B.Tech Mechanical Engineering'],
        ['college_idx' => 4, 'name' => 'B.Tech Artificial Intelligence & ML'],
    ];

    $courseIds = [];
    $stmt = $pdo->prepare("INSERT INTO courses (college_id, name) VALUES (?, ?)");
    foreach ($courses as $c) {
        $stmt->execute([$collegeIds[$c['college_idx']], $c['name']]);
        $courseIds[] = $pdo->lastInsertId();
        echo "   + Course: {$c['name']}\n";
    }

    // ─── 3. BATCHES (one per course) ───────────────────────
    $batches = [
        ['course_idx' => 0, 'name' => 'CSE Batch 2026'],
        ['course_idx' => 1, 'name' => 'ISE Batch 2026'],
        ['course_idx' => 2, 'name' => 'ECE Batch 2026'],
        ['course_idx' => 3, 'name' => 'ME Batch 2026'],
        ['course_idx' => 4, 'name' => 'AIML Batch 2026'],
    ];

    $batchIds = [];
    $stmt = $pdo->prepare("INSERT INTO batches (course_id, name) VALUES (?, ?)");
    foreach ($batches as $b) {
        $stmt->execute([$courseIds[$b['course_idx']], $b['name']]);
        $batchIds[] = $pdo->lastInsertId();
        echo "   + Batch: {$b['name']}\n";
    }

    // ─── 4. STUDENTS (10 per batch = 50 total) ─────────────
    $firstNames = ['Aarav','Vivaan','Aditya','Vihaan','Arjun','Sai','Ishaan','Ayaan','Dhruv','Reyansh',
                   'Ananya','Diya','Myra','Sara','Aadhya','Avni','Ira','Anaya','Jiya','Kavya',
                   'Rohan','Rahul','Ravi','Sunil','Manoj','Vijay','Rajesh','Nitin','Deepak','Arun',
                   'Priya','Neha','Pooja','Ritu','Anjali','Sneha','Kiran','Lata','Meena','Rekha',
                   'Akash','Bharat','Chandan','Dinesh','Eknath','Farhan','Gaurav','Hitesh','Irfan','Jatin'];

    $lastNames = ['Sharma','Verma','Patel','Reddy','Kumar','Singh','Gupta','Joshi','Nair','Menon',
                  'Desai','Iyer','Rao','Murthy','Shetty','Naidu','Choudhury','Banerjee','Mukherjee','Das',
                  'Pillai','Thakur','More','Pawar','Khanna','Kapoor','Mehta','Seth','Bajaj','Agarwal',
                  'Saxena','Trivedi','Upadhyay','Dwivedi','Chaturvedi','Pandey','Mishra','Tiwari','Dubey','Shukla',
                  'Nayak','Jena','Swain','Patnaik','Mohanty','Padhy','Sahoo','Behera','Das','Pradhan'];

    $branches = ['Computer Science','Information Science','Electronics','Mechanical','AIML'];
    $domains  = ['srit.edu','sjce.edu','bmsce.edu','pes.edu','rvce.edu'];

    $studentData = [];
    $studentIdx = 0;

    $stmt = $pdo->prepare("INSERT INTO students (batch_id, name, phone, email, gender, college_name, branch, roll_number, year_of_joining, course_name, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    for ($b = 0; $b < 5; $b++) {
        $collegeName = $colleges[$b]['name'];
        $branchName  = $branches[$b];
        $courseName  = $courses[$b]['name'];
        $domain      = $domains[$b];
        $batchId     = $batchIds[$b];

        for ($s = 0; $s < 10; $s++) {
            $idx = $studentIdx++;
            $name  = $firstNames[$idx] . ' ' . $lastNames[$idx];
            $phone = '98765' . str_pad(mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT);
            $email = 'student' . ($idx + 1) . '@' . $domain;
            $gender = ($idx < 15 || ($idx >= 20 && $idx < 35)) ? 'male' : 'female'; // mix
            $roll   = strtoupper(substr($courseName, 0, 3)) . str_pad(($idx + 1), 4, '0', STR_PAD_LEFT);
            $year   = 2024;

            $stmt->execute([$batchId, $name, $phone, $email, $gender, $collegeName, $branchName, $roll, $year, $courseName, $passwordHash]);
            $studentData[] = [
                'id'     => $pdo->lastInsertId(),
                'name'   => $name,
                'email'  => $email,
                'batch'  => $b,
            ];
        }
        echo "   + Students x10 in batch {$batches[$b]['name']}\n";
    }

    // ─── 5. TESTS (one per batch) ──────────────────────────
    $testTitles = [
        'CSE Core Fundamentals',
        'Data Structures & Algorithms',
        'Digital Electronics Basics',
        'Thermodynamics & Fluids',
        'Introduction to Machine Learning',
    ];

    $testData = [];

    $stmt = $pdo->prepare("INSERT INTO tests (batch_id, title, description, duration_minutes, start_time, end_time, status, max_tab_switches, shuffle_questions, created_by) VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'active', 5, 1, 1)");

    for ($t = 0; $t < 5; $t++) {
        $stmt->execute([$batchIds[$t], $testTitles[$t], 'Assessment for ' . $batches[$t]['name'], 30]);
        $testId = $pdo->lastInsertId();
        $testData[] = [
            'id'    => $testId,
            'batch' => $t,
            'title' => $testTitles[$t],
        ];
        echo "   + Test: {$testTitles[$t]}\n";
    }

    // ─── 6. QUESTIONS (5 MCQ per test) ─────────────────────
    $mcqQuestions = [
        [   // CSE Core
            ['q' => 'Which data structure uses LIFO principle?',
             'opts' => [['key'=>'A','text'=>'Queue'],['key'=>'B','text'=>'Stack'],['key'=>'C','text'=>'Array'],['key'=>'D','text'=>'Tree'],['key'=>'E','text'=>'Graph']],
             'ans' => 'B', 'marks' => 2],
            ['q' => 'What is the time complexity of binary search?',
             'opts' => [['key'=>'A','text'=>'O(n)'],['key'=>'B','text'=>'O(log n)'],['key'=>'C','text'=>'O(n²)'],['key'=>'D','text'=>'O(1)'],['key'=>'E','text'=>'O(n log n)']],
             'ans' => 'B', 'marks' => 2],
            ['q' => 'Which of the following is a relational database?',
             'opts' => [['key'=>'A','text'=>'MongoDB'],['key'=>'B','text'=>'Redis'],['key'=>'C','text'=>'MySQL'],['key'=>'D','text'=>'Neo4j'],['key'=>'E','text'=>'Cassandra']],
             'ans' => 'C', 'marks' => 2],
            ['q' => 'What does CPU stand for?',
             'opts' => [['key'=>'A','text'=>'Central Process Unit'],['key'=>'B','text'=>'Computer Personal Unit'],['key'=>'C','text'=>'Central Processing Unit'],['key'=>'D','text'=>'Core Processing Unit'],['key'=>'E','text'=>'Central Program Unit']],
             'ans' => 'C', 'marks' => 2],
            ['q' => 'Which protocol is used for web browsing?',
             'opts' => [['key'=>'A','text'=>'FTP'],['key'=>'B','text'=>'SMTP'],['key'=>'C','text'=>'HTTP'],['key'=>'D','text'=>'TCP'],['key'=>'E','text'=>'UDP']],
             'ans' => 'C', 'marks' => 2],
        ],
        [   // DSA
            ['q' => 'Which sorting algorithm has the best average-case time complexity?',
             'opts' => [['key'=>'A','text'=>'Bubble Sort'],['key'=>'B','text'=>'Quick Sort'],['key'=>'C','text'=>'Selection Sort'],['key'=>'D','text'=>'Insertion Sort'],['key'=>'E','text'=>'Merge Sort']],
             'ans' => 'B', 'marks' => 2],
            ['q' => 'What is a hash table used for?',
             'opts' => [['key'=>'A','text'=>'Sorting data'],['key'=>'B','text'=>'Fast data retrieval'],['key'=>'C','text'=>'Graph traversal'],['key'=>'D','text'=>'File compression'],['key'=>'E','text'=>'Encryption']],
             'ans' => 'B', 'marks' => 2],
            ['q' => 'Which tree traversal visits left → root → right?',
             'opts' => [['key'=>'A','text'=>'Preorder'],['key'=>'B','text'=>'Postorder'],['key'=>'C','text'=>'Inorder'],['key'=>'D','text'=>'Level order'],['key'=>'E','text'=>'Reverse order']],
             'ans' => 'C', 'marks' => 2],
            ['q' => 'What is the worst-case time complexity of Quick Sort?',
             'opts' => [['key'=>'A','text'=>'O(n)'],['key'=>'B','text'=>'O(log n)'],['key'=>'C','text'=>'O(n log n)'],['key'=>'D','text'=>'O(n²)'],['key'=>'E','text'=>'O(1)']],
             'ans' => 'D', 'marks' => 2],
            ['q' => 'Which data structure is used for BFS?',
             'opts' => [['key'=>'A','text'=>'Stack'],['key'=>'B','text'=>'Queue'],['key'=>'C','text'=>'Array'],['key'=>'D','text'=>'Heap'],['key'=>'E','text'=>'Linked List']],
             'ans' => 'B', 'marks' => 2],
        ],
        [   // Digital Electronics
            ['q' => 'What is the binary equivalent of decimal 10?',
             'opts' => [['key'=>'A','text'=>'1010'],['key'=>'B','text'=>'1100'],['key'=>'C','text'=>'1001'],['key'=>'D','text'=>'1110'],['key'=>'E','text'=>'1000']],
             'ans' => 'A', 'marks' => 2],
            ['q' => 'Which gate outputs 1 only when both inputs are 1?',
             'opts' => [['key'=>'A','text'=>'OR'],['key'=>'B','text'=>'XOR'],['key'=>'C','text'=>'NAND'],['key'=>'D','text'=>'AND'],['key'=>'E','text'=>'NOR']],
             'ans' => 'D', 'marks' => 2],
            ['q' => 'How many bits are in a byte?',
             'opts' => [['key'=>'A','text'=>'4'],['key'=>'B','text'=>'8'],['key'=>'C','text'=>'16'],['key'=>'D','text'=>'32'],['key'=>'E','text'=>'64']],
             'ans' => 'B', 'marks' => 2],
            ['q' => 'Which flip-flop is edge-triggered?',
             'opts' => [['key'=>'A','text'=>'SR Latch'],['key'=>'B','text'=>'JK Flip-Flop'],['key'=>'C','text'=>'D Latch'],['key'=>'D','text'=>'Gated SR'],['key'=>'E','text'=>'Transparent Latch']],
             'ans' => 'B', 'marks' => 2],
            ['q' => 'What is the hexadecimal equivalent of 255?',
             'opts' => [['key'=>'A','text'=>'FF'],['key'=>'B','text'=>'F0'],['key'=>'C','text'=>'EF'],['key'=>'D','text'=>'FE'],['key'=>'E','text'=>'AA']],
             'ans' => 'A', 'marks' => 2],
        ],
        [   // Thermodynamics
            ['q' => 'What is the SI unit of temperature?',
             'opts' => [['key'=>'A','text'=>'Celsius'],['key'=>'B','text'=>'Fahrenheit'],['key'=>'C','text'=>'Kelvin'],['key'=>'D','text'=>'Rankine'],['key'=>'E','text'=>'Newton']],
             'ans' => 'C', 'marks' => 2],
            ['q' => 'Which law states energy cannot be created or destroyed?',
             'opts' => [['key'=>'A','text'=>'Newton\'s First Law'],['key'=>'B','text'=>'Zeroth Law'],['key'=>'C','text'=>'First Law of Thermodynamics'],['key'=>'D','text'=>'Second Law'],['key'=>'E','text'=>'Third Law']],
             'ans' => 'C', 'marks' => 2],
            ['q' => 'What is entropy a measure of?',
             'opts' => [['key'=>'A','text'=>'Temperature'],['key'=>'B','text'=>'Pressure'],['key'=>'C','text'=>'Disorder'],['key'=>'D','text'=>'Volume'],['key'=>'E','text'=>'Density']],
             'ans' => 'C', 'marks' => 2],
            ['q' => 'Which process occurs at constant pressure?',
             'opts' => [['key'=>'A','text'=>'Isothermal'],['key'=>'B','text'=>'Isochoric'],['key'=>'C','text'=>'Isobaric'],['key'=>'D','text'=>'Adiabatic'],['key'=>'E','text'=>'Isentropic']],
             'ans' => 'C', 'marks' => 2],
            ['q' => 'What is the efficiency of a Carnot engine dependent on?',
             'opts' => [['key'=>'A','text'=>'Working fluid'],['key'=>'B','text'=>'Temperatures'],['key'=>'C','text'=>'Engine size'],['key'=>'D','text'=>'Fuel type'],['key'=>'E','text'=>'Pressure ratio']],
             'ans' => 'B', 'marks' => 2],
        ],
        [   // ML
            ['q' => 'Which algorithm is used for classification?',
             'opts' => [['key'=>'A','text'=>'Linear Regression'],['key'=>'B','text'=>'Logistic Regression'],['key'=>'C','text'=>'K-Means'],['key'=>'D','text'=>'PCA'],['key'=>'E','text'=>'Apriori']],
             'ans' => 'B', 'marks' => 2],
            ['q' => 'What is overfitting?',
             'opts' => [['key'=>'A','text'=>'Model underperforms on training data'],['key'=>'B','text'=>'Model memorizes training data, fails on new data'],['key'=>'C','text'=>'Model is too simple'],['key'=>'D','text'=>'Model trains too fast'],['key'=>'E','text'=>'Model needs more data']],
             'ans' => 'B', 'marks' => 2],
            ['q' => 'Which is a supervised learning algorithm?',
             'opts' => [['key'=>'A','text'=>'K-Means Clustering'],['key'=>'B','text'=>'Decision Tree'],['key'=>'C','text'=>'PCA'],['key'=>'D','text'=>'Apriori'],['key'=>'E','text'=>'t-SNE']],
             'ans' => 'B', 'marks' => 2],
            ['q' => 'What does CNN stand for?',
             'opts' => [['key'=>'A','text'=>'Convergent Neural Network'],['key'=>'B','text'=>'Convolutional Neural Network'],['key'=>'C','text'=>'Central Neural Network'],['key'=>'D','text'=>'Connected Neural Network'],['key'=>'E','text'=>'Complex Neural Network']],
             'ans' => 'B', 'marks' => 2],
            ['q' => 'Which metric is used for regression evaluation?',
             'opts' => [['key'=>'A','text'=>'Accuracy'],['key'=>'B','text'=>'Precision'],['key'=>'C','text'=>'Recall'],['key'=>'D','text'=>'Mean Squared Error'],['key'=>'E','text'=>'F1 Score']],
             'ans' => 'D', 'marks' => 2],
        ],
    ];

    $questionIds = []; // [test_idx][question_idx] => id

    $stmtQ = $pdo->prepare("INSERT INTO questions (test_id, type, question_text, options_json, correct_answer, marks, sort_order) VALUES (?, 'mcq', ?, ?, ?, ?, ?)");

    for ($t = 0; $t < 5; $t++) {
        $testId = $testData[$t]['id'];
        $questions = $mcqQuestions[$t];
        $questionIds[$t] = [];

        foreach ($questions as $qi => $q) {
            $stmtQ->execute([
                $testId,
                $q['q'],
                json_encode($q['opts']),
                $q['ans'],
                $q['marks'],
                $qi + 1
            ]);
            $questionIds[$t][] = $pdo->lastInsertId();
        }
        echo "   + Questions x5 for test: {$testTitles[$t]}\n";
    }

    // ─── 7. SUBMISSIONS + ANSWERS ───────────────────────────
    $stmtSub = $pdo->prepare("INSERT INTO submissions (student_id, test_id, status, started_at, submitted_at, total_marks_obtained, total_marks) VALUES (?, ?, 'evaluated', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), ?, ?)");

    $stmtAns = $pdo->prepare("INSERT INTO student_answers (submission_id, question_id, answer_json, marks_obtained, evaluated_at) VALUES (?, ?, ?, ?, NOW())");

    foreach ($studentData as $sd) {
        $batch   = $sd['batch'];
        $testIdx = $batch; // test index matches batch index
        $testId  = $testData[$testIdx]['id'];
        $questions = $mcqQuestions[$testIdx];
        $qIds      = $questionIds[$testIdx];

        // Calculate marks: give random correct/incorrect answers
        $totalMarks = 0;
        $obtained   = 0;
        $answers    = [];

        foreach ($questions as $qi => $q) {
            $marks = $q['marks'];
            $totalMarks += $marks;

            // 70% chance of correct answer
            $isCorrect = (mt_rand(1, 100) <= 70);
            if ($isCorrect) {
                $selected = $q['ans'];
                $obtained += $marks;
            } else {
                // Pick a wrong answer randomly
                $wrongOptions = array_filter($q['opts'], fn($o) => $o['key'] !== $q['ans']);
                $wrongKeys = array_map(fn($o) => $o['key'], $wrongOptions);
                $selected = $wrongKeys[array_rand($wrongKeys)];
            }

            $answers[] = [
                'qid'    => $qIds[$qi],
                'answer' => json_encode(['selected' => $selected]),
                'marks'  => $isCorrect ? $marks : 0,
            ];
        }

        // Create submission
        $stmtSub->execute([$sd['id'], $testId, $obtained, $totalMarks]);
        $submissionId = $pdo->lastInsertId();

        // Insert answers
        foreach ($answers as $a) {
            $stmtAns->execute([$submissionId, $a['qid'], $a['answer'], $a['marks']]);
        }
    }
    echo "   + Submissions + answers for all 50 students\n";

    // ─── 8. PCI RECORDS ─────────────────────────────────────
    $stmtPci = $pdo->prepare("INSERT INTO pci_records (student_id, test_id, pci_score, mcq_score, coding_score, explanation_score, mcq_weight, coding_weight, explanation_weight) VALUES (?, ?, ?, ?, 0, 0, 100, 0, 0)");

    foreach ($studentData as $sd) {
        $batch   = $sd['batch'];
        $testId  = $testData[$batch]['id'];
        // Calculate a random PCI score
        $pci = round(mt_rand(4000, 9500) / 100, 2);
        $stmtPci->execute([$sd['id'], $testId, $pci, $pci]);
    }
    echo "   + PCI records for all 50 students\n";

    $pdo->commit();

    // ─── SUMMARY ─────────────────────────────────────────────
    echo "\n✅ Seed complete!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "   Colleges: 5\n";
    echo "   Courses:  5\n";
    echo "   Batches:  5\n";
    echo "   Students: 50 (10 per batch)\n";
    echo "   Tests:    5 (1 per batch)\n";
    echo "   Questions: 25 total (5 per test)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "   Login password for all students: student123\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // Print student emails by batch
    $currentBatch = -1;
    foreach ($studentData as $sd) {
        if ($sd['batch'] !== $currentBatch) {
            $currentBatch = $sd['batch'];
            echo "\n📚 {$batches[$currentBatch]['name']}:\n";
        }
        echo "   {$sd['email']}\n";
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
