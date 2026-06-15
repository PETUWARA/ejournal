<?php
require_once 'functions.php';

// Check user authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
}

$message = '';
$error = '';

$subjectList = [
    'Mathematics', 'Physics', 'Chemistry', 'Biology', 'History', 'Geography', 
    'English', 'Literature', 'Computer Science', 'Physical Education', 'Economics'
];

$selectedClass = (int)($_GET['class_id'] ?? 0);
$selectedStudent = (int)($_GET['student_id'] ?? 0);

// Handle CRUD operations for teachers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isTeacher()) {
    $action = $_POST['action'] ?? '';

    // Add new grade
    if ($action === 'add') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $subject = sanitize($_POST['subject'] ?? '');
        $grade = (float)($_POST['grade'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');

        if ($student_id <= 0 || empty($subject) || $grade < 0 || $grade > 100) {
            $error = 'Please fill all required fields. Grade must be between 0-100.';
        } else {
            $sql = "INSERT INTO grades (student_id, subject, grade, description) VALUES (:student_id, :subject, :grade, :description)";
            if (query($sql, ['student_id' => $student_id, 'subject' => $subject, 'grade' => $grade, 'description' => $description])) {
                $message = 'Grade added successfully!';
            } else {
                $error = 'Failed to add grade.';
            }
        }
    }

    // Update existing grade
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $subject = sanitize($_POST['subject'] ?? '');
        $grade = (float)($_POST['grade'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');

        if (empty($subject) || $grade < 0 || $grade > 100) {
            $error = 'Please fill all fields correctly.';
        } else {
            $sql = "UPDATE grades SET subject = :subject, grade = :grade, description = :description WHERE id = :id";
            if (query($sql, ['subject' => $subject, 'grade' => $grade, 'description' => $description, 'id' => $id])) {
                $message = 'Grade updated successfully!';
            } else {
                $error = 'Failed to update grade.';
            }
        }
    }

    // Delete grade
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            if (query("DELETE FROM grades WHERE id = :id", ['id' => $id])) {
                $message = 'Grade deleted successfully!';
            } else {
                $error = 'Failed to delete grade.';
            }
        }
    }
}

// Fetch data based on user role
$classes = query("SELECT * FROM classes ORDER BY name") ?: [];

if (isTeacher()) {
    // Teacher data access
    $students = [];
    if ($selectedClass > 0) {
        $students = query("SELECT * FROM students WHERE class_id = :class_id ORDER BY last_name, first_name", ['class_id' => $selectedClass]) ?: [];
    }

    $grades = [];
    if ($selectedStudent > 0) {
        $sql = "SELECT g.*, s.first_name, s.last_name FROM grades g JOIN students s ON g.student_id = s.id WHERE g.student_id = :student_id ORDER BY g.created_at DESC";
        $grades = query($sql, ['student_id' => $selectedStudent]) ?: [];
    }
} else {
    // Student data access
    $studentInfo = query("SELECT * FROM students WHERE first_name = :username OR last_name = :username LIMIT 1", ['username' => $_SESSION['username']]);
    
    if (!empty($studentInfo)) {
        $selectedStudent = $studentInfo[0]['id'];
        $sql = "SELECT g.*, s.first_name, s.last_name FROM grades g JOIN students s ON g.student_id = s.id WHERE g.student_id = :student_id ORDER BY g.created_at DESC";
        $grades = query($sql, ['student_id' => $selectedStudent]) ?: [];
    } else {
        $grades = [];
    }
}

// Fetch global grade history
$allGradesSql = "SELECT g.*, s.first_name, s.last_name, c.name as class_name 
                 FROM grades g 
                 JOIN students s ON g.student_id = s.id 
                 JOIN classes c ON s.class_id = c.id 
                 ORDER BY g.created_at DESC LIMIT 50";
$allGrades = query($allGradesSql) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grades - Grade Journal</title>
    <style>
        /* CSS resets and styles */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; color: #333; }
        .login-page { display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .login-container h1 { margin-bottom: 5px; color: #2c3e50; }
        .subtitle { color: #7f8c8d; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background 0.2s; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-full { width: 100%; }
        .btn-small { padding: 6px 10px; font-size: 12px; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-secondary { background: #6c757d; color: white; }
        .navbar { background: #343a40; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: #ccc; text-decoration: none; margin-left: 20px; font-weight: bold; }
        .navbar a.active, .navbar a:hover { color: white; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card h2 { margin-bottom: 15px; border-bottom: 2px solid #f4f6f9; padding-bottom: 10px; }
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th, .table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .table th { background-color: #f8f9fa; color: #495057; }
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 8px; width: 100%; max-width: 400px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">📚 Grade Journal</div>
        <div class="nav-links">
            <span style="color: #fff; margin-right: 15px;">Logged in as: <strong style="color: #ffc107;"><?= sanitize($_SESSION['username']) ?></strong> (<?= isTeacher() ? 'Teacher' : 'Student' ?>)</span>
            <a href="?action=logout" style="color: #ff6b6b;">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1>Grades Dashboard</h1>

        <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

        <?php if (isTeacher()): ?>
            <div class="card">
                <h2>Step 1: Select Group</h2>
                <form method="GET">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Group</label>
                            <select name="class_id" onchange="this.form.submit()">
                                <option value="">Select group...</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>" <?= $selectedClass == $class['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($class['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($selectedClass > 0 && !empty($students)): ?>
                        <div class="form-group">
                            <label>Step 2: Select Student</label>
                            <select name="student_id" onchange="this.form.submit()">
                                <option value="">Select student...</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $selectedStudent == $s['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($s['last_name'] . ', ' . $s['first_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php elseif ($selectedClass > 0): ?>
                        <div class="form-group">
                            <label>Students</label>
                            <p style="color:#888; padding-top:10px;">No students in this group.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if ($selectedStudent > 0): ?>
            <div class="card">
                <h2>Step 3: Add Grade</h2>
                <form method="POST" action="?class_id=<?= $selectedClass ?>&student_id=<?= $selectedStudent ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="student_id" value="<?= $selectedStudent ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Subject *</label>
                            <select name="subject" required>
                                <option value="">Select subject...</option>
                                <?php foreach ($subjectList as $subj): ?>
                                    <option value="<?= $subj ?>"><?= $subj ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Grade (0-100) *</label>
                            <input type="number" name="grade" min="0" max="100" step="0.5" placeholder="85" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" name="description" placeholder="e.g. Midterm exam">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Grade</button>
                </form>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($selectedStudent > 0): ?>
        <div class="card">
            <h2>Grades for <?= htmlspecialchars(($grades[0]['first_name'] ?? '') . ' ' . ($grades[0]['last_name'] ?? '')) ?></h2>
            <?php if (!empty($grades)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Grade</th>
                        <th>Description</th>
                        <?php if (isTeacher()): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grades as $row): ?>
                    <tr>
                        <td><?= sanitize($row['subject']) ?></td>
                        <td><strong><?= $row['grade'] ?> (<?= getLetterGrade($row['grade']) ?>)</strong></td>
                        <td><?= sanitize($row['description']) ?></td>
                        <?php if (isTeacher()): ?>
                        <td>
                            <button class="btn btn-small btn-warning" onclick="editGrade(<?= $row['id'] ?>, '<?= sanitize($row['subject']) ?>', <?= $row['grade'] ?>, '<?= sanitize($row['description']) ?>')">Edit</button>
                            <button class="btn btn-small btn-danger" onclick="confirmDeleteGrade(<?= $row['id'] ?>, '<?= sanitize($row['subject']) ?>')">Delete</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p class="no-data">No grades yet.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (isTeacher()): ?>
        <div class="card">
            <h2>Overall Grades History</h2>
            <?php if (!empty($allGrades)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Group</th>
                        <th>Subject</th>
                        <th>Grade</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allGrades as $row): ?>
                    <tr>
                        <td style="color: #777; font-size: 14px;"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></td>
                        <td><strong><?= sanitize($row['last_name'] . ' ' . $row['first_name']) ?></strong></td>
                        <td><span style="background: #e9ecef; padding: 3px 6px; border-radius: 4px; font-size: 13px;"><?= sanitize($row['class_name']) ?></span></td>
                        <td><?= sanitize($row['subject']) ?></td>
                        <td><strong><?= $row['grade'] ?> (<?= getLetterGrade($row['grade']) ?>)</strong></td>
                        <td><?= sanitize($row['description']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p class="no-data">No grades in the journal yet.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div id="editModal" class="modal" style="display:none">
        <div class="modal-content">
            <h2>Edit Grade</h2>
            <form method="POST" action="?class_id=<?= $selectedClass ?>&student_id=<?= $selectedStudent ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject" id="edit_subject" required>
                        <?php foreach ($subjectList as $subj): ?>
                            <option value="<?= $subj ?>"><?= $subj ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Grade (0-100) *</label>
                    <input type="number" name="grade" id="edit_grade" min="0" max="100" step="0.5" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" id="edit_description">
                </div>
                <div class="form-row">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal" style="display:none">
        <div class="modal-content">
            <h2>⚠️ Confirm Delete</h2>
            <p id="deleteMessage"></p>
            <form method="POST" action="?class_id=<?= $selectedClass ?>&student_id=<?= $selectedStudent ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="form-row">
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editGrade(id, subject, grade, desc) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_subject').value = subject;
            document.getElementById('edit_grade').value = grade;
            document.getElementById('edit_description').value = desc;
            document.getElementById('editModal').style.display = 'flex';
        }
        function confirmDeleteGrade(id, subject) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteMessage').innerText = 'Are you sure you want to delete the grade for ' + subject + '?';
            document.getElementById('deleteModal').style.display = 'flex';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>
</body>
</html>