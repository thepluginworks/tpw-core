<?php
use TPW_Feedback_Model;
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.', 'tpw-core' ) );
}
$chart_data = TPW_Feedback_Model::get_chart_data();
$chart_data_json = wp_json_encode($chart_data);

// Pagination and fetch latest 50 feedback records
$per_page = 50;
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($paged - 1) * $per_page;

global $wpdb;
$table = $wpdb->prefix . 'tpw_rsvp_feedback';

$total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$results = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset)
);
?>

<div class="tpw-admin-ui"><div class="wrap">
    <h1>RSVP Feedback Overview</h1>

    <canvas id="easeChart" style="max-width: 500px; margin-bottom: 40px;"></canvas>
    <canvas id="clarityChart" style="max-width: 500px; margin-bottom: 40px;"></canvas>
    <canvas id="timeChart" style="max-width: 500px; margin-bottom: 40px;"></canvas>

    <h2>All Feedback Submissions</h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>Submission ID</th>
                <th>Ease</th>
                <th>Clarity</th>
                <th>Time</th>
                <th>Suggestions</th>
                <th>Origin</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody id="tpw-feedback-table">
            <?php foreach ($results as $row): ?>
                <tr>
                    <td><?php echo esc_html($row->submission_id); ?></td>
                    <td><?php echo esc_html($row->ease_rating); ?></td>
                    <td><?php echo $row->clarity_ok === '1' ? 'Yes' : 'No'; ?></td>
                    <td>
                        <?php
                        echo match ($row->time_under_2min) {
                            1 => 'Under 2min',
                            0 => '2–5min',
                            2 => 'Over 5min',
                            default => 'N/A'
                        };
                        ?>
                    </td>
                    <td><?php echo esc_html($row->suggestions); ?></td>
                    <td><?php echo esc_html($row->origin); ?></td>
                    <td><?php echo esc_html(date('Y-m-d', strtotime($row->created_at))); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="tablenav">
        <div class="tablenav-pages">
            <?php
            $total_pages = ceil($total / $per_page);
            $base_url = admin_url('tools.php?page=tpw-feedback');

            if ($total_pages > 1) {
                for ($i = 1; $i <= $total_pages; $i++) {
                    $current = ($i === $paged) ? ' class="current-page"' : '';
                    echo "<a href='" . esc_url(add_query_arg('paged', $i, $base_url)) . "'$current>$i</a> ";
                }
            }
            ?>
        </div>
    </div>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartData = <?php echo $chart_data_json; ?>;

    const easeLabels = ['1', '2', '3', '4', '5'];
    const easeData = easeLabels.map(label => chartData.ease[label] || 0);

    const clarityData = [
        chartData.clarity[1] || 0,
        chartData.clarity[0] || 0
    ];

    const timeLabels = ['Under 2min', '2-5min', 'Over 5min'];
    const timeData = [
        chartData.time[1] || 0,
        chartData.time[0] || 0,
        chartData.time[2] || 0
    ];

    new Chart(document.getElementById('easeChart'), {
        type: 'bar',
        data: {
            labels: easeLabels,
            datasets: [{
                label: 'Ease Rating',
                data: easeData,
                backgroundColor: 'rgba(54, 162, 235, 0.6)'
            }]
        }
    });

    new Chart(document.getElementById('clarityChart'), {
        type: 'doughnut',
        data: {
            labels: ['Yes', 'No'],
            datasets: [{
                data: clarityData,
                backgroundColor: ['#4CAF50', '#F44336']
            }]
        }
    });

    new Chart(document.getElementById('timeChart'), {
        type: 'pie',
        data: {
            labels: timeLabels,
            datasets: [{
                data: timeData,
                backgroundColor: ['#2196F3', '#FFC107', '#9C27B0']
            }]
        }
    });
});
</script>