<?php
require_once 'config.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$ticket_id = $_GET['ticket_id'] ?? null;
$ticket = null;
$comments = [];
$status_history = [];
$error = '';
$success = '';

// Validate ticket_id
if (!$ticket_id || !filter_var($ticket_id, FILTER_VALIDATE_INT)) {
    setFlashMessage('danger', t('ticket_not_found'));
    redirect(isSubmitter() ? 'user_dashboard.php' : 'dashboard.php');
}

try {
    // Fetch ticket details
    $stmt = $pdo->prepare("SELECT
                                t.ticket_id,
                                t.issue_type,
                                t.description,
                                t.priority,
                                t.status,
                                t.created_at,
                                t.updated_at,
                                t.submitter_id,
                                u.name AS submitter_name,
                                ta.team_name AS assigned_team
                            FROM
                                tickets t
                            JOIN
                                users u ON t.submitter_id = u.user_id
                            LEFT JOIN
                                teams ta ON t.team_id = ta.team_id
                            WHERE
                                t.ticket_id = :ticket_id");
    $stmt->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
    $stmt->execute();
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if ticket exists and if the user has permission to view it
    if (!$ticket) {
        setFlashMessage('danger', t('ticket_not_found'));
        redirect(isSubmitter() ? 'user_dashboard.php' : 'dashboard.php');
    }

    // Authorization check:
    // A submitter can only view their own tickets.
    // An admin or senior officer can view any ticket.
    if (isSubmitter() && $ticket['submitter_id'] !== $_SESSION['user_id']) {
        setFlashMessage('danger', t('ticket_not_found')); // Generic message for security
        redirect('user_dashboard.php');
    }

    // Fetch comments
    $stmtComments = $pdo->prepare("SELECT
                                    c.comment_text,
                                    c.created_at,
                                    u.name AS commented_by
                                FROM
                                    comments c
                                JOIN
                                    users u ON c.author_id = u.user_id -- CHANGED THIS LINE: from c.user_id to c.author_id
                                WHERE
                                    c.ticket_id = :ticket_id
                                ORDER BY
                                    c.created_at ASC");
    $stmtComments->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
    $stmtComments->execute();
    $comments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);

    // Fetch status history
    $stmtHistory = $pdo->prepare("SELECT
                                    sh.old_status,
                                    sh.new_status,
                                    sh.changed_at,
                                    u.name AS changed_by
                                FROM
                                    status_history sh
                                JOIN
                                    users u ON sh.user_id = u.user_id
                                WHERE
                                    sh.ticket_id = :ticket_id
                                ORDER BY
                                    sh.changed_at ASC");
    $stmtHistory->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
    $stmtHistory->execute();
    $status_history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

    // Handle comment submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            setFlashMessage('danger', t('invalid_csrf'));
            redirect($_SERVER['REQUEST_URI']); // Redirect back to refresh page and clear form data
        }

        $comment_text = trim($_POST['comment_text'] ?? '');

        if (empty($comment_text)) {
            setFlashMessage('danger', t('comment_empty_error'));
            redirect($_SERVER['REQUEST_URI']);
        }

        $pdo->beginTransaction();
        try {
            $stmtInsertComment = $pdo->prepare("INSERT INTO comments (ticket_id, author_id, comment_text) VALUES (:ticket_id, :author_id, :comment_text)"); // CHANGED THIS LINE: from user_id to author_id
            $stmtInsertComment->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
            $stmtInsertComment->bindParam(':author_id', $_SESSION['user_id'], PDO::PARAM_INT); // CHANGED THIS LINE: from user_id to author_id
            $stmtInsertComment->bindParam(':comment_text', $comment_text, PDO::PARAM_STR);
            $stmtInsertComment->execute();

            // Update ticket's updated_at timestamp
            $stmtUpdateTicket = $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE ticket_id = :ticket_id");
            $stmtUpdateTicket->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
            $stmtUpdateTicket->execute();

            $pdo->commit();
            setFlashMessage('success', t('comment_added_successfully'));
            redirect($_SERVER['REQUEST_URI']); // Reload to show new comment and clear form
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage('danger', t('failed_to_add_comment') . ' ' . $e->getMessage()); // In production, just generic error
            redirect($_SERVER['REQUEST_URI']);
        }
    }
} catch (PDOException $e) {
    $error = t('database_error') . ': ' . $e->getMessage();
}

// Generate a new CSRF token for the form
?>

<!DOCTYPE html>
<html lang="<?php echo getHtmlLangAttribute(); ?>" dir="<?php echo getHtmlDirAttribute(); ?>" class="<?php echo getThemeClass(); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('ticket_details'); ?> - <?php echo htmlspecialchars($ticket['ticket_id'] ?? 'N/A'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-5">
        <?php echo getFlashMessage(); ?>

        <?php if ($error) : ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php elseif ($ticket) : ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><?php echo t('ticket_details'); ?> #<?php echo htmlspecialchars($ticket['ticket_id']); ?></h4>
                    <div>
                        <?php if (isSubmitter() && ($ticket['status'] == 'Open' || $ticket['status'] == 'In Progress')) : ?>
                            <a href="edit_ticket.php?ticket_id=<?php echo htmlspecialchars($ticket['ticket_id']); ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit me-1"></i> <?php echo t('edit_ticket'); ?>
                            </a>
                        <?php elseif (isAdmin() || isSeniorOfficer()) : ?>
                            <a href="manage_ticket.php?ticket_id=<?php echo htmlspecialchars($ticket['ticket_id']); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-tasks me-1"></i> <?php echo t('manage_ticket'); ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo isSubmitter() ? 'user_dashboard.php' : 'dashboard.php'; ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> <?php echo t('back_to_dashboard'); ?>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6"><strong><?php echo t('submitter'); ?>:</strong> <?php echo htmlspecialchars($ticket['submitter_name']); ?></div>
                        <div class="col-md-6"><strong><?php echo t('issue_type'); ?>:</strong> <?php echo htmlspecialchars($ticket['issue_type']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12"><strong><?php echo t('description'); ?>:</strong><br><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4"><strong><?php echo t('priority'); ?>:</strong>
                            <span class="badge <?php
                                                if ($ticket['priority'] == 'High') {
                                                    echo 'bg-danger';
                                                } elseif ($ticket['priority'] == 'Medium') {
                                                    echo 'bg-warning text-dark';
                                                } else {
                                                    echo 'bg-success';
                                                }
                                                ?>"><?php echo htmlspecialchars($ticket['priority']); ?></span>
                        </div>
                        <div class="col-md-4"><strong><?php echo t('status'); ?>:</strong>
                            <span class="badge <?php
                                                if ($ticket['status'] == 'Open') {
                                                    echo 'bg-info';
                                                } elseif ($ticket['status'] == 'In Progress') {
                                                    echo 'bg-primary';
                                                } else {
                                                    echo 'bg-secondary';
                                                }
                                                ?>"><?php echo htmlspecialchars($ticket['status']); ?></span>
                        </div>
                        <div class="col-md-4"><strong><?php t('assigned_team'); ?>:</strong> <?php echo htmlspecialchars($ticket['assigned_team'] ?? t('unassigned')); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6"><strong><?php echo t('created'); ?>:</strong> <?php echo htmlspecialchars($ticket['created_at']); ?></div>
                        <div class="col-md-6"><strong><?php echo t('last_updated'); ?>:</strong> <?php echo htmlspecialchars($ticket['updated_at']); ?></div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-comments me-2"></i> <?php echo t('comments'); ?></h5>
                </div>
                <div class="card-body">
                    <?php if (empty($comments)) : ?>
                        <p class="text-muted"><?php echo t('no_comments_yet'); ?></p>
                    <?php else : ?>
                        <div class="list-group">
                            <?php foreach ($comments as $comment) : ?>
                                <div class="list-group-item list-group-item-action flex-column align-items-start mb-2">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($comment['commented_by']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($comment['created_at']); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <h6 class="mt-4 mb-3"><?php echo t('add_comment'); ?></h6>
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="mb-3">
                            <label for="comment_text" class="form-label visually-hidden"><?php echo t('comment'); ?></label>
                            <textarea class="form-control" id="comment_text" name="comment_text" rows="3" placeholder="<?php echo t('enter_your_comment'); ?>" required></textarea>
                        </div>
                        <button type="submit" name="add_comment" class="btn btn-success"><i class="fas fa-comment-dots me-1"></i> <?php echo t('add_comment'); ?></button>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-history me-2"></i> <?php echo t('status_history'); ?></h5>
                </div>
                <div class="card-body">
                    <?php if (empty($status_history)) : ?>
                        <p class="text-muted"><?php echo t('no_status_changes_yet'); ?></p>
                    <?php else : ?>
                        <ul class="list-group">
                            <?php foreach ($status_history as $history) : ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <?php echo htmlspecialchars($history['changed_by']); ?>
                                        <?php echo t('changed_status_from'); ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($history['old_status']); ?></span>
                                        <?php echo t('to'); ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($history['new_status']); ?></span>
                                    </span>
                                    <small class="text-muted"><?php echo htmlspecialchars($history['changed_at']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
</body>

</html>