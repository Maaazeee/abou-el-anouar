/* Espace surveillant — interactions */

// Sidebar mobile
(function () {
    const toggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }
})();

// Modales génériques
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const modal = document.getElementById(btn.dataset.modalOpen);
            if (modal) modal.classList.add('open');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.modal-overlay').classList.remove('open');
        });
    });
    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });
    // Confirmation de suppression
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirm)) e.preventDefault();
        });
    });
    // Recherche instantanée dans les tableaux
    document.querySelectorAll('table.data').forEach(function (table) {
        var body = table.querySelector('tbody');
        if (!body) return;
        var rows = Array.prototype.slice.call(body.querySelectorAll('tr')).filter(function (tr) {
            return !tr.querySelector('.empty-state');
        });
        if (rows.length < 2) return;

        var input = document.createElement('input');
        input.type = 'search';
        input.placeholder = table.getAttribute('data-search') || 'Rechercher…';
        input.className = 'table-search-input';
        var box = document.createElement('div');
        box.className = 'table-search';
        box.appendChild(input);
        var wrap = table.closest('.table-wrap');
        (wrap || table).insertAdjacentElement('beforebegin', box);

        var noRes = document.createElement('tr');
        noRes.style.display = 'none';
        noRes.innerHTML = '<td colspan="' + (table.querySelectorAll('thead th').length || 1) + '">' +
            '<div class="empty-state"><i class="fas fa-search"></i><p>Aucun résultat.</p></div></td>';
        body.appendChild(noRes);

        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            var any = false;
            rows.forEach(function (tr) {
                var show = tr.textContent.toLowerCase().indexOf(q) !== -1;
                tr.style.display = show ? '' : 'none';
                if (show) any = true;
            });
            noRes.style.display = (q && !any) ? '' : 'none';
        });
    });
});
