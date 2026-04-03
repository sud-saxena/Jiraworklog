<?php
require 'jira.php';

if (isset($_GET['action']) && $_GET['action'] === 'user-search') {
    $query = $_GET['q'] ?? '';
    header('Content-Type: application/json');
    echo json_encode(searchUsers($query));
    exit;
}
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
        $userAccountIds = $_POST['accountIds'] ?? '';
        $userAccountIds = $userAccountIds ? explode(',', $userAccountIds) : [];
        $data = getWorklogs($from, $to, $userAccountIds);
        $logs = $data['logs'];
        $total = $data['total'];

        if ($action === 'export' && !empty($logs)) {

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="jira_worklogs.csv"');

            $output = fopen('php://output', 'w');

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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <style>
        body { font-family: Arial; margin: 40px; }
        input, button { padding: 8px; margin: 5px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; }
        th { background: #f4f4f4; }
        #suggestions {
            border: 1px solid #ddd;
            max-width: 250px;
            position: absolute;
            background: #fff;
            z-index: 1000;
        }

        .suggestion-item {
            padding: 8px;
            cursor: pointer;
        }

        .suggestion-item:hover {
            background: #f0f0f0;
        }
.user-search-wrapper {
    position: relative;
    display: inline-block;
}
.user-input-box {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    border: 1px solid #ccc !important;
    padding: 4px;
    min-width: 250px;
    max-width: 350px;
    background: #fff;
}
.user-input-box:focus-within {
    border-color: #2684ff;
    box-shadow: 0 0 0 2px rgba(38,132,255,0.2);
}
#user-search {
    border: none;
    outline: none;
    flex: 1;
    min-width: 120px;
}

#user-loader {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    display: none;
    font-size: 14px;
}

#suggestions {
    position: absolute;
    top: 100%;        /* directly below input */
    left: 0;
    width: 100%;      /* match input width */
    border: 1px solid #ddd;
    background: #fff;
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
}

.suggestion-item {
    padding: 8px;
    cursor: pointer;
}

.suggestion-item:hover {
    background: #f0f0f0;
}
#page-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;

    background: rgba(255,255,255,0.85);
    z-index: 9999;

    display: none;
    align-items: center;
    justify-content: center;
}

.loader-box {
    text-align: center;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #ddd;
    border-top: 4px solid #333;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: auto;
}

.loader-text {
    margin-top: 10px;
    font-size: 14px;
    color: #333;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

#toggleFilters {
    padding: 6px 10px;
    margin-top: 15px;
    cursor: pointer;
}

#filterContainer input {
    margin-right: 10px;
}
.tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}
.tag {
    background: #e0e0e0;
    padding: 4px 8px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    font-size: 12px;
}

.tag .remove {
    margin-left: 6px;
    cursor: pointer;
    font-weight: bold;
}
    </style>
</head>
<div id="page-loader">
    <div class="loader-box">
        <div class="spinner"></div>
        <div class="loader-text">Generating report...</div>
    </div>
</div>
<body>

<h2>Jira Worklog Report</h2>

<form method="POST">
    From: <input type="date" name="from" value="<?= $_POST['from'] ?? '' ?>" required>
    To: <input type="date" name="to" value="<?= $_POST['to'] ?? '' ?>" required>
    
    <div class="user-search-wrapper">
    <div class="user-input-box">
        <div id="selected-users" class="tags-container"></div>
        <input type="text" id="user-search" placeholder="Search user...">
        <span id="user-loader">⏳</span>
    </div>

    <input type="hidden" name="accountIds" id="accountIds">
    <div id="suggestions"></div>
</div>
    <button type="submit" name="action" value="generate">Generate</button>
    <button type="submit" name="action" value="clear_cache" formnovalidate>Clear Cache</button>
    <button type="submit" name="action" value="export">Export</button>
    <button type="button" id="toggleFilters">Show Filters</button>
</form>

<?php if (!empty($logs)): ?>


<div id="filterContainer" style="display:none; margin-top:10px;">
    <input type="text" id="filterUser" placeholder="Filter by User">
    <input type="text" id="filterJira" placeholder="Filter by Jira ID">
    <input type="date" id="filterFrom">
    <input type="date" id="filterTo">
</div>
<table id="worklogTable">
    <thead>
        <tr>
            <th>Jira ID</th>
            <th>Summary</th>
            <th>Hours</th>
            <th>Comment</th>
            <th>Date</th>
            <th>User</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td>
                <a href="https://carscommerce.atlassian.net/browse/<?= $log['key'] ?>">
                    <?= $log['key'] ?>
                </a>
            </td>
            <td><?= $log['summary'] ?></td>
            <td><?= $log['hours'] ?>h</td>
            <td><?= $log['comment'] ?></td>
            <td><?= $log['date'] ?? '-' ?></td>
            <td><?= $log['user'] ?? '-' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h3>Total: <span id="totalHours"><?= $total ?></span>h</h3>

<?php else : ?>
<table> <thead>
        <tr>
            <th colspan="6">No Data! Please refine your query using above query editor and generate report.</th>
        </tr>
        <thead>
</table>
<?php endif; ?>

<script>
$(document).ready(function () {

    if ($('#worklogTable').length) {
        function recalculateTotal(table) {
            let total = 0;

            // Only visible (filtered) rows
            table.rows({ search: 'applied' }).every(function () {
                const data = this.data();

                // Column index 2 = Hours (e.g. "1.5h")
                let hours = data[2];

                if (!hours) return;

                hours = parseFloat(hours.toString().replace('h', '').trim());

                if (!isNaN(hours)) {
                    total += hours;
                }
            });

            document.getElementById('totalHours').innerText = total.toFixed(2);
        }
        const table = $('#worklogTable').DataTable({
            pageLength: 25,
            order: [[4, 'desc']],

            dom: 'Bfrtip',

            buttons: [
                {
                    extend: 'csv',
                    text: 'Export CSV',
                    exportOptions: {
                        columns: ':visible',
                        format: {
                            body: function (data, row, column) {

                                let text = typeof data === 'string'
                                    ? data.replace(/<[^>]*>/g, '').trim()
                                    : data;

                                if (column === 2) {
                                    return parseFloat(text.replace('h', '')) || 0;
                                }

                                return text;
                            }
                        }
                    }
                },
                {
                    extend: 'excel',
                    text: 'Export Excel',
                    title: 'Jira_Worklogs',
                    exportOptions: {
                        columns: ':visible',
                        format: {
                            body: function (data, row, column) {

                                // Remove HTML safely
                                let text = typeof data === 'string'
                                    ? data.replace(/<[^>]*>/g, '').trim()
                                    : data;

                                // Fix hours column
                                if (column === 2) {
                                    return parseFloat(text.replace('h', '')) || 0;
                                }

                                return text;
                            }
                        }
                    }
                },
                {
                    extend: 'print',
                    text: 'Print',
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ]
        });
        
        $('#filterUser').on('keyup', function () {
            table.column(5).search(this.value).draw();
        });

        $('#filterJira').on('keyup', function () {
            table.column(0).search(this.value).draw();
        });

        $.fn.dataTable.ext.search.push(function (settings, data) {
            const from = $('#filterFrom').val();
            const to = $('#filterTo').val();
            const date = data[4];

            if (!from && !to) return true;
            if (from && date < from) return false;
            if (to && date > to) return false;

            return true;
        });

        $('#filterFrom, #filterTo').on('change', function () {
            table.draw();
        });
        table.on('draw', function () {
            recalculateTotal(table);
        });
    }
});
</script>
<script>
document.querySelector('form').addEventListener('submit', function (e) {

    const action = document.activeElement.value;

    // ❌ Don't show loader for cache clear
    if (action === 'clear_cache') return;

    // ✅ Show loader
    document.getElementById('page-loader').style.display = 'flex';
});
</script>
<script>
const toggleBtn = document.getElementById('toggleFilters');
const filterBox = document.getElementById('filterContainer');

const savedState = localStorage.getItem('filtersVisible');

if (savedState === 'true') {
    filterBox.style.display = 'block';
    toggleBtn.innerText = 'Hide Filters';
}

toggleBtn.addEventListener('click', function () {
    const isHidden = filterBox.style.display === 'none';

    filterBox.style.display = isHidden ? 'block' : 'none';
    toggleBtn.innerText = isHidden ? 'Hide Filters' : 'Show Filters';

    localStorage.setItem('filtersVisible', isHidden);
});
</script>
<script>
const input = document.getElementById('user-search');
const suggestions = document.getElementById('suggestions');
const loader = document.getElementById('user-loader');
const hiddenInput = document.getElementById('accountIds');
const selectedContainer = document.getElementById('selected-users');

let timer;
let controller;

const selectedUsers = {}; // {id: name}

// --------------------
// SEARCH USERS
// --------------------
input.addEventListener('keyup', function () {
    const query = this.value;

    if (query.length < 3) {
        suggestions.innerHTML = '';
        loader.style.display = 'none';
        return;
    }

    clearTimeout(timer);

    timer = setTimeout(() => {

        if (controller) controller.abort();
        controller = new AbortController();

        loader.style.display = 'block';
        suggestions.innerHTML = '';

        fetch(`?action=user-search&q=${encodeURIComponent(query)}`, {
            signal: controller.signal
        })
        .then(res => res.json())
        .then(data => {
            loader.style.display = 'none';

            if (!data.length) {
                suggestions.innerHTML = '<div class="suggestion-item">No results</div>';
                return;
            }

            suggestions.innerHTML = data.map(user => `
                <div class="suggestion-item"
                     data-id="${user.accountId}"
                     data-name="${user.name}">
                    ${user.name}
                </div>
            `).join('');
        })
        .catch(() => {
            loader.style.display = 'none';
        });

    }, 300);
});

// --------------------
// ADD TAG
// --------------------
suggestions.addEventListener('click', function (e) {
    const item = e.target.closest('.suggestion-item');
    if (!item) return;

    const id = item.dataset.id;
    const name = item.dataset.name;

    if (selectedUsers[id]) return;

    selectedUsers[id] = name;

    renderTag(id, name);
    updateHidden();

    input.value = '';
    suggestions.innerHTML = '';
});

// --------------------
// REMOVE TAG
// --------------------
selectedContainer.addEventListener('click', function (e) {
    if (!e.target.classList.contains('remove')) return;

    const tag = e.target.parentElement;
    const id = tag.dataset.id;

    delete selectedUsers[id];
    tag.remove();

    updateHidden();
});

// --------------------
// RENDER TAG
// --------------------
function renderTag(id, name) {
    const tag = document.createElement('div');
    tag.className = 'tag';
    tag.dataset.id = id;
    tag.innerHTML = `${name} <span class="remove">×</span>`;
    selectedContainer.appendChild(tag);
}

// --------------------
// UPDATE HIDDEN INPUT
// --------------------
function updateHidden() {
    hiddenInput.value = Object.keys(selectedUsers).join(',');
}

// --------------------
// RESTORE FROM POST (CLEAN WAY)
// --------------------
document.addEventListener('DOMContentLoaded', function () {

    if (!hiddenInput.value) return;

    const ids = hiddenInput.value.split(',');

    // fetch names again from API
    ids.forEach(id => {

        fetch(`?action=user-search&q=${id}`)
            .then(res => res.json())
            .then(data => {

                const user = data.find(u => u.accountId === id);

                const name = user ? user.name : id;

                selectedUsers[id] = name;
                renderTag(id, name);
                updateHidden();
            });
    });
});
</script>
</body>
</html>
