(function() {
    function setCanvasHeight(id) {
        var canvas = document.getElementById(id);
        if (canvas) {
            canvas.height = 360;
        }
        return canvas;
    }

    function buildPie(el, dataset, key) {
        var labels = dataset.map(function(d) { return d[key] || 'Inconnu'; });
        var values = dataset.map(function(d) { return parseInt(d.total, 10); });
        return new Chart(el.getContext('2d'), {
            type: 'pie',
            data: { labels: labels, datasets: [{ data: values }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var data = window.ufscStatsData || {};

        var genderCanvas = setCanvasHeight('chart-gender');
        if (genderCanvas && data.gender) {
            buildPie(genderCanvas, data.gender, 'gender');
        }

        var practiceCanvas = setCanvasHeight('chart-practice');
        if (practiceCanvas && data.practice) {
            buildPie(practiceCanvas, data.practice, 'practice');
        }

        var ageCanvas = setCanvasHeight('chart-age');
        if (ageCanvas && data.age) {
            var labels = data.age.map(function(d) { return d.age_group; });
            var values = data.age.map(function(d) { return parseInt(d.total, 10); });
            new Chart(ageCanvas.getContext('2d'), {
                type: 'bar',
                data: { labels: labels, datasets: [{ data: values }] },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    });
})();
