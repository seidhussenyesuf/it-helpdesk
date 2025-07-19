<?php
require_once 'config.php';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="index.php">IT Help Desk</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <?php if (isLoggedIn()) { ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isSeniorOfficer() ? 'dashboard.php' : 'user_dashboard.php'; ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="submit_ticket.php">Submit Ticket</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                <?php } else { ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </div>
</nav>