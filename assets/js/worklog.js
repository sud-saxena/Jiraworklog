(function () {

    // -------------------- CONFIG --------------------
    const input = document.getElementById('user-search');
    const suggestions = document.getElementById('suggestions');
    const loader = document.getElementById('user-loader');
    const hiddenInput = document.getElementById('accountIds');
    const selectedContainer = document.getElementById('selected-users');

    const selectedUsers = {}; // {id: name}

    let timer;
    let controller;

    // -------------------- USER SEARCH --------------------
    if (input) {
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
    }

    // -------------------- ADD TAG --------------------
    if (suggestions) {
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
    }

    // -------------------- REMOVE TAG --------------------
    if (selectedContainer) {
        selectedContainer.addEventListener('click', function (e) {
            if (!e.target.classList.contains('remove')) return;

            const tag = e.target.parentElement;
            const id = tag.dataset.id;

            delete selectedUsers[id];
            tag.remove();

            updateHidden();
        });
    }

    // -------------------- RENDER TAG --------------------
    function renderTag(id, name) {
        const tag = document.createElement('div');
        tag.className = 'tag';
        tag.dataset.id = id;
        tag.innerHTML = `${name} <span class="remove">×</span>`;
        selectedContainer.appendChild(tag);
    }

    // -------------------- UPDATE HIDDEN --------------------
    function updateHidden() {
        hiddenInput.value = Object.keys(selectedUsers).join(',');
    }

    // -------------------- RESTORE TAGS --------------------
    document.addEventListener('DOMContentLoaded', function () {

        if (!hiddenInput || !hiddenInput.value) return;

        const ids = hiddenInput.value.split(',');

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

    // -------------------- DATATABLE --------------------
    if (window.$ && $('#worklogTable').length) {

        function recalculateTotal(table) {
            let total = 0;

            table.rows({ search: 'applied' }).every(function () {
                const data = this.data();
                let hours = data[2];

                if (!hours) return;

                hours = parseFloat(hours.toString().replace('h', '').trim());

                if (!isNaN(hours)) total += hours;
            });

            document.getElementById('totalHours').innerText = total.toFixed(2);
        }

        const table = $('#worklogTable').DataTable({
            pageLength: 25,
            order: [[4, 'desc']],
            dom: 'Bfrtip',
            buttons: ['csv', 'excel', 'print']
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

    // -------------------- FORM LOADER --------------------
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function () {
            const action = document.activeElement.value;
            if (action === 'clear_cache') return;

            document.getElementById('page-loader').style.display = 'flex';
        });
    }

    // -------------------- FILTER TOGGLE --------------------
    const toggleBtn = document.getElementById('toggleFilters');
    const filterBox = document.getElementById('filterContainer');

    if (toggleBtn && filterBox) {
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
    }

})();