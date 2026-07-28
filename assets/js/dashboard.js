document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    Promise.all([
        fetch(window.API_BASE + '/api/chart-data.php?metric=revenue').then(r => r.json()),
        fetch(window.API_BASE + '/api/chart-data.php?metric=expense').then(r => r.json()),
    ]).then(function ([revenue, expense]) {
        var labels = revenue.history.map(p => p.label);
        var ctx = document.getElementById('trendChart');
        if (!ctx) return;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Revenue', data: revenue.history.map(p => p.amount), borderColor: '#2F80ED', backgroundColor: 'rgba(47,128,237,0.1)', tension: 0.3 },
                    { label: 'Expense', data: expense.history.map(p => p.amount), borderColor: '#EB5757', backgroundColor: 'rgba(235,87,87,0.1)', tension: 0.3 },
                ],
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });
    });

    fetch(window.API_BASE + '/api/chart-data.php?metric=cash_flow').then(r => r.json()).then(function (data) {
        var ctx = document.getElementById('forecastChart');
        var note = document.getElementById('forecastNote');
        if (!ctx) return;
        if (!data.sufficient) {
            note.textContent = 'Not enough posted transaction history yet to generate a forecast (need at least 4 months of activity).';
            return;
        }
        var historyLabels = data.history.map(p => p.label);
        var forecastLabels = data.forecast.map(p => p.label);
        var labels = historyLabels.concat(forecastLabels);

        var actualSeries = data.history.map(p => p.amount).concat(forecastLabels.map(() => null));
        var lastActual = data.history[data.history.length - 1].amount;
        var forecastSeries = historyLabels.map(() => null).concat([lastActual]).concat(data.forecast.slice(1).map(p => p.amount));
        // Align forecastSeries length to labels length
        forecastSeries = historyLabels.slice(0, -1).map(() => null).concat([lastActual]).concat(data.forecast.map(p => p.amount));

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Actual Net Cash Flow', data: actualSeries, borderColor: '#2F80ED', backgroundColor: 'rgba(47,128,237,0.1)', tension: 0.3 },
                    { label: 'Forecast', data: forecastSeries, borderColor: '#27AE60', borderDash: [6, 4], backgroundColor: 'rgba(39,174,96,0.08)', tension: 0.3 },
                ],
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });
        note.textContent = 'Forecast uses a linear regression trend over the last 12 months of posted cash activity, projected 3 months forward.';
    });
});
