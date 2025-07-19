<?php
require_once 'config.php';

if (!isSeniorOfficer()) {
    header("Location: index.php");
    exit();
}

$team_id = $_SESSION['team_id'];
$stmt = $conn->prepare("SELECT tickets.ticket_id, tickets.issue_type, tickets.description, tickets.priority, tickets.status, tickets.created_at, users.name AS submitter_name 
                        FROM tickets 
                        JOIN users ON tickets.submitter_id = users.user_id 
                        WHERE tickets.team_id = ? 
                        ORDER BY tickets.created_at DESC");
$stmt->bind_param("i", $team_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-5">
        <h2 class="mb-4">Team Tickets</h2>
        <?php if ($result->num_rows === 0) { ?>
            <div class="alert alert-info">No tickets assigned to your team.</div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Submitter</th>
                            <th>Issue Type</th>
                            <th>Description</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo $row['ticket_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['submitter_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['issue_type']); ?></td>
                                <td>
                                    <?php echo strlen($row['description']) > 50 ? htmlspecialchars(substr($row['description'], 0, 50)) . '...' : htmlspecialchars($row['description']); ?>
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#ticketModal<?php echo $row['ticket_id']; ?>">View</a>
                                </td>
                                <td>
                                    <span class="badge <?php echo $row['priority'] == 'High' ? 'bg-danger' : ($row['priority'] == 'Medium' ? 'bg-warning' : 'bg-success'); ?>">
                                        <?php echo $row['priority']; ?>
                                    </span>
                                </td>
                                <td><?php echo $row['status']; ?></td>
                                <td><?php echo $row['created_at']; ?></td>
                                <td>
                                    <a href="manage_ticket.php?ticket_id=<?php echo $row['ticket_id']; ?>" class="btn btn-sm btn-primary">Manage</a>
                                </td>
                            </tr>
                            <!-- Modal for ticket preview -->
                            <div class="modal fade" id="ticketModal<?php echo $row['ticket_id']; ?>" tabindex="-1" aria-labelledby="ticketModalLabel<?php echo $row['ticket_id']; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="ticketModalLabel<?php echo $row['ticket_id']; ?>">Ticket #<?php echo $row['ticket_id']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p><strong>Submitter:</strong> <?php echo htmlspecialchars($row['submitter_name']); ?></p>
                                            <p><strong>Issue Type:</strong> <?php echo htmlspecialchars($row['issue_type']); ?></p>
                                            <p><strong>Description:</strong> <?php echo htmlspecialchars($row['description']); ?></p>
                                            <p><strong>Priority:</strong> <?php echo $row['priority']; ?></p>
                                            <p><strong>Status:</strong> <?php echo $row['status']; ?></p>
                                            <p><strong>Created:</strong> <?php echo $row['created_at']; ?></p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <a href="manage_ticket.php?ticket_id=<?php echo $row['ticket_id']; ?>" class="btn btn-primary">Manage</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
    <?php $stmt->close(); ?>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>