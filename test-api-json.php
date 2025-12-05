<?php
// Get the exact JSON response for author_id 1
$api_response = file_get_contents("http://localhost/scrollnovels/api/supporters/get-top-supporters.php?author_id=1&limit=200");
echo "Raw API Response:\n";
echo $api_response . "\n\n";

$data = json_decode($api_response, true);
echo "Decoded structure:\n";
echo "  data.success: " . ($data['success'] ? 'true' : 'false') . "\n";
echo "  data.total: " . $data['total'] . "\n";
echo "  data.data type: " . gettype($data['data']) . "\n";
echo "  data.data length: " . count($data['data']) . "\n\n";

if (is_array($data['data'])) {
    foreach ($data['data'] as $i => $supporter) {
        echo "Supporter $i:\n";
        echo "  supporter_id: " . ($supporter['supporter_id'] ?? 'NULL') . "\n";
        echo "  username: " . ($supporter['username'] ?? 'NULL') . "\n";
        echo "  points_total: " . ($supporter['points_total'] ?? 'NULL') . "\n";
        echo "  tip_amount: " . ($supporter['tip_amount'] ?? 'NULL') . "\n";
        echo "  profile_image: " . ($supporter['profile_image'] ?? 'NULL') . "\n";
        echo "  status: " . ($supporter['status'] ?? 'NULL') . "\n";
    }
}
