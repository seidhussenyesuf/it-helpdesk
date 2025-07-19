<?php
require_once 'config.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        $name = htmlspecialchars($_POST['name']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $team_id = $_POST['role'] === 'senior_officer' ? $_POST['team_id'] : null;

        $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, team_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $name, $email, $password, $role, $team_id);
            if ($stmt->execute()) {
                $success = "Registration successful! Please log in.";
            } else {
                $error = "Error: " . $conn->error;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-5">
        <h2 class="mb-4">Register</h2>
        <?php if ($error) { ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php } ?>
        <?php if ($success) { ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php } ?>
        <form action="register.php" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" name="name" id="name" class="form-control" required>
                <div class="invalid-feedback">Please provide your name.</div>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" required>
                <div class="invalid-feedback">Please provide a valid email.</div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required minlength="6">
                <div class="invalid-feedback">Password must be at least 6 characters.</div>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select name="role" id="role" class="form-select" required>
                    <option value="submitter">Submitter</option>
                    <option value="senior_officer">Senior Officer</option>
                </select>
                <div class="invalid-feedback">Please select a role.</div>
            </div>
            <div class="mb-3" id="team_id_container" style="display: none;">
                <label for="team_id" class="form-label">Team (for officers)</label>
                <select name="team_id" id="team_id" class="form-select">
                    <option value="">Select Team</option>
                    <?php
                    $result = $conn->query("SELECT team_id, team_name FROM teams");
                    while ($row = $result->fetch_assoc()) {
                        echo "<option value='{$row['team_id']}'>{$row['team_name']}</option>";
                    }
                    ?>
                </select>
                <div class="invalid-feedback">Please select a team.</div>
            </div>
            <button type="submit" class="btn btn-primary">Register</button>
        </form>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap form validation
        (function() {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        // Show/hide team_id based on role
        document.getElementById('role').addEventListener('change', function() {
            const teamContainer = document.getElementById('team_id_container');
            if (this.value === 'senior_officer') {
                teamContainer.style.display = 'block';
                document.getElementById('team_id').required = true;
            } else {
                teamContainer.style.display = 'none';
                document.getElementById('team_id').required = false;
            }
        });
    </script>
</body>

</html>