<?php
include 'db.php';
if (isset($_POST['reset'])) {
    $conn->query("UPDATE bin_status SET is_full=0 ORDER BY id DESC LIMIT 1");
    header("Location: dashboard.php");
}
?>
