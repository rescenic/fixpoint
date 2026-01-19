<?php
// TEST FILE - Simplified version
$chartLabels = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15];
$chartData = [85.5, 90.2, 88.7, 92.1, 87.3, 95.5, 89.2, 91.4, 93.2, 88.1, 90.5, 92.3, 87.8, 91.1, 89.5];
$standar = 90;
?>
<!DOCTYPE html>
<html>
<head>
<title>Test Chart Modal</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
  <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#chartModal1">
    Open Chart Modal
  </button>
</div>

<!-- Modal -->
<div class="modal fade" id="chartModal1" tabindex="-1">
  <div class="modal-dialog modal-xl" style="max-width:95%;">
    <div class="modal-content" style="height:90vh;">
      <div class="modal-header">
        <h5>Test Chart</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body" style="height:calc(100% - 120px);">
        <canvas id="chart1" style="width:100%; height:100%;"></canvas>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
let myChart1;

$('#chartModal1').on('shown.bs.modal', function () {
  const ctx = document.getElementById('chart1');
  const labels = <?= json_encode($chartLabels) ?>;
  const data = <?= json_encode($chartData) ?>;
  const standar = <?= $standar ?>;
  
  console.log('Labels:', labels);
  console.log('Data:', data);
  console.log('Standar:', standar);
  
  if (myChart1) {
    myChart1.destroy();
  }
  
  myChart1 = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Persentase Harian',
        data: data,
        borderColor: 'rgb(75, 192, 192)',
        tension: 0.4
      }, {
        label: 'Standar ' + standar + '%',
        data: Array(labels.length).fill(standar),
        borderColor: 'rgb(255, 99, 132)',
        borderDash: [5, 5]
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false
    }
  });
  
  console.log('Chart created!', myChart1);
});

$('#chartModal1').on('hidden.bs.modal', function () {
  if (myChart1) {
    myChart1.destroy();
  }
});
</script>

</body>
</html>