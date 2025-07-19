<?php
require_once 'config.php';

if (!isSeniorOfficer()) {
    header("Location: index.php");
    exit();
}

$ticket_id = $_GET['ticket_id'] ?? null;
if (!$ticket_id) {
    header("Location: dashboard.php");
    exit();
}

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        $status = $_POST['status'];
        $comment_text = htmlspecialchars($_POST['comment_text']);
        $author_id = $_SESSION['user_id'];

        // Update ticket status
        $stmt = $conn->prepare("UPDATE tickets SET status = ? WHERE ticket_id = ? AND team_id = ?");
        $stmt->bind_param("sii", $status, $ticket_id, $_SESSION['team_id']);
        $stmt->execute();

        // Log status change
        $stmt = $conn->prepare("INSERT INTO ticket_logs (ticket_id, status, changed_by) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $ticket_id, $status, $author_id);
        $stmt->execute();

        // Add comment if provided
        if (!empty($comment_text)) {
            $stmt = $conn->prepare("INSERT INTO comments (ticket_id, comment_text, author_id) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $ticket_id, $comment_text, $author_id);
            $stmt->execute();
        }

        // Send notification to submitter
        $stmt = $conn->prepare("SELECT users.email, users.name FROM tickets JOIN users ON tickets.submitter_id = users.user_id WHERE tickets.ticket_id = ?");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $submitter = $stmt->get_result()->fetch_assoc();
        if ($submitter) {
            require_once 'send_notification.php';
            $subject = "Ticket #$ticket_id Updated";
            $message = "Dear {$submitter['name']},\n\nYour ticket (ID: $ticket_id) has been updated to status: $status.\n";
            if (!empty($comment_text)) {
                $message .= "Comment: $comment_text\n";
            }
            $message .= "View details at http://localhost/it-helpdesk/user_dashboard.php.\n\nIT Help Desk";
            sendNotification($submitter['email'], $subject, $message);
        }

        $success = "Ticket updated successfully!";
        $stmt->close();
    }
}

// Fetch ticket details
$stmt = $conn->prepare("SELECT tickets.*, users.name AS submitter_name, users.email AS submitter_email 
                        FROM tickets 
                        JOIN users ON tickets.submitter_id = users.user_id 
                        WHERE tickets.ticket_id = ? AND tickets.team_id = ?");
$stmt->bind_param("ii", $ticket_id, $_SESSION['team_id']);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
if (!$ticket) {
    header("Location: dashboard.php");
    exit();
}

// Fetch comments
$stmt = $conn->prepare("SELECT comment_text, comments.created_at, users.name 
                        FROM comments 
                        JOIN users ON comments.author_id = users.user_id 
                        WHERE ticket_id = ? 
                        ORDER BY comments.created_at DESC");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$comments = $stmt->get_result();

// Fetch status logs
$stmt = $conn->prepare("SELECT ticket_logs.status, ticket_logs.created_at, users.name 
                        FROM ticket_logs 
                        JOIN users ON ticket_logs.changed_by = users.user_id 
                        WHERE ticket_id = ? 
                        ORDER BY ticket_logs.created_at DESC");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$logs = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Ticket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-5">
        <h2 class="mb-4">Manage Ticket #<?php echo $ticket_id; ?></h2>
        <?php if ($error) { ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php } ?>
        <?php if ($success) { ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php } ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Ticket Details</h5>
                <p><strong>Submitter:</strong> <?php echo htmlspecialchars($ticket['submitter_name']); ?> (<?php echo htmlspecialchars($ticket['submitter_email']); ?>)</p>
                <p><strong>Issue Type:</strong> <?php echo htmlspecialchars($ticket['issue_type']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($ticket['description']); ?></p>
                <p><strong>Priority:</strong>
                    <span class="badge <?php echo $ticket['priority'] == 'High' ? 'bg-danger' : ($ticket['priority'] == 'Medium' ? 'bg-warning' : 'bg-success'); ?>">
                        <?php echo $ticket['priority']; ?>
                    </span>
                </p>
                <p><strong>Status:</strong> <?php echo $ticket['status']; ?></p>
                <p><strong>Created:</strong> <?php echo $ticket['created_at']; ?></p>
                <p><strong>Last Updated:</strong> <?php echo $ticket['updated_at']; ?></p>
            </div>
        </div>
        <h3>Update Ticket</h3>
        <form action="manage_ticket.php?ticket_id=<?php echo $ticket_id; ?>" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select" required>
                    <option value="Open" <?php if ($ticket['status'] == 'Open') echo 'selected'; ?>>Open</option>
                    <option value="In Progress" <?php if ($ticket['status'] == 'In Progress') echo 'selected'; ?>>In Progress</option>
                    <option value="Closed" <?php if ($ticket['status'] == 'Closed') echo 'selected'; ?>>Closed</option>
                </select>
                <div class="invalid-feedback">Please select a status.</div>
            </div>
            <div class="mb-3">
                <label for="comment_text" class="form-label">Comment</label>
                <textarea name="comment_text" id="comment_text" class="form-control" rows="4"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Update Ticket</button>
        </form>
        <h3 class="mt-4">Comments</h3>
        <?php if ($comments->num_rows > 0) { ?>
            <div class="list-group mb-4">
                <?php while ($comment = $comments->fetch_assoc()) { ?>
                    <div class="list-group-item">
                        <p><strong><?php echo htmlspecialchars($comment['name']); ?> (<?php echo $comment['created_at']; ?>):</strong></p>
                        <p><?php echo htmlspecialchars($comment['comment_text']); ?></p>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <p>No comments yet.</p>
        <?php } ?>
        <h3 class="mt-4">Status Change History</h3>
        <?php if ($logs->num_rows > 0) { ?>
            <div class="list-group">
                <?php while ($log = $logs->fetch_assoc()) { ?>
                    <div class="list-group-item">
                        <p><strong><?php echo htmlspecialchars($log['name']); ?> (<?php echo $log['created_at']; ?>):</strong></p>
                        <p>Changed status to: <?php echo $log['status']; ?></p>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <p>No status changes yet.</p>
        <?php } ?>
    </div>
    <?php $stmt->close(); ?>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
</body>

</html>