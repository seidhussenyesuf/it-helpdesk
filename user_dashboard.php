<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$submitter_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT ticket_id, issue_type, description, priority, status, created_at FROM tickets WHERE submitter_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $submitter_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-5">
        <h2 class="mb-4">Your Tickets</h2>
        <a href="submit_ticket.php" class="btn btn-primary mb-3">Submit New Ticket</a>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Issue Type</th>
                        <th>Description</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo $row['ticket_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['issue_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td>
                                <span class="badge <?php echo $row['priority'] == 'High' ? 'bg-danger' : ($row['priority'] == 'Medium' ? 'bg-warning' : 'bg-success'); ?>">
                                    <?php echo $row['priority']; ?>
                                </span>
                            </td>
                            <td><?php echo $row['status']; ?></td>
                            <td><?php echo $row['created_at']; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php $stmt->close(); ?>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>