<?php
require 'jira.php';

$logs = [];
$total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'generate';

    if ($action === 'clear_cache') {
        foreach (glob(__DIR__ . '/cache_*.json') as $file) {
            unlink($file);
        }

        echo "<p style='color:green;'>Cache cleared successfully!</p>";
    }

    if (in_array($action, ['generate', 'export'])) {

        $from = $_POST['from'];
        $to   = $_POST['to'];

        $data = getWorklogs($from, $to);
        $logs = $data['logs'];
        $total = $data['total'];

        // ✅ EXPORT CSV
        if ($action === 'export' && !empty($logs)) {

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="jira_worklogs.csv"');

            $output = fopen('php://output', 'w');

            // Header
            fputcsv($output, ['Jira ID', 'Summary', 'Hours', 'Comment', 'Date', 'User']);

            // Rows
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['key'],
                    $log['summary'],
                    $log['hours'],
                    $log['comment'],
                    $log['date'],
                    $log['user']
                ]);
            }

            fclose($output);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Jira Worklog</title>
    <style>
        body { font-family: Arial; margin: 40px; }
        input, button { padding: 8px; margin: 5px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>

<h2>Jira Worklog Report</h2>

<form method="POST">
    From: <input type="date" name="from" value="<?= $_POST['from'] ?? '' ?>" required>
    To: <input type="date" name="to" value="<?= $_POST['to'] ?? '' ?>" required>
    <button type="submit" name="action" value="generate">Generate</button>
    <button type="submit" name="action" value="clear_cache" formnovalidate>Clear Cache</button>
    <button type="submit" name="action" value="export">Export</button>
</form>

<?php if (!empty($logs)): ?>

<table>
    <tr>
        <th>Jira ID</th>
        <th>Summary</th>
        <th>Hours</th>
        <th>Comment</th>
        <th>Date</th>
        <th>User</th>
    </tr>

    <?php foreach ($logs as $log): ?>
    <tr>
        <td><a href="https://carscommerce.atlassian.net/browse/<?= $log['key'] ?>"><?= $log['key'] ?></a></td>
        <td><?= $log['summary'] ?></td>
        <td><?= $log['hours'] ?>h</td>
        <td><?= $log['comment'] ?></td>
        <td><?= $log['date'] ?? '-' ?></td>
        <td><?= $log['user'] ?? '-' ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h3>Total: <?= $total ?>h</h3>

<?php else : ?>
    <table>
        <tr>
            <td colspan="6">No Data</td>
        </tr>
        </table>
  <?php  endif;  ?>

</body>
</html>
