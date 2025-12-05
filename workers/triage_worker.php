<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config.php';

if (php_sapi_name() !== 'cli') {
    $apiKey = $_GET['key'] ?? '';
    if ($apiKey !== 'TRIAGE_KEY') {
        http_response_code(403);
        echo "Access denied";
        exit;
    }
}

try {
    global $pdo;
    
    error_log("[TRIAGE] Worker started");
    
    $stmt = $pdo->prepare("SELECT * FROM triage_rules WHERE enabled = 1 ORDER BY priority DESC LIMIT 50");
    $stmt->execute();
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedCount = 0;
    
    foreach ($rules as $rule) {
        $stmt = $pdo->prepare("SELECT * FROM reports WHERE status = 'open' LIMIT 100");
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reports as $report) {
            $conditions = json_decode($rule['conditions'], true) ?? [];
            $shouldApply = true;
            
            foreach ($conditions as $cond) {
                $field = $cond['field'] ?? '';
                $operator = $cond['operator'] ?? '=';
                $value = $cond['value'] ?? '';
                $reportVal = $report[$field] ?? '';
                
                if ($operator === '=') {
                    $shouldApply = $shouldApply && ($reportVal == $value);
                } elseif ($operator === '!=') {
                    $shouldApply = $shouldApply && ($reportVal != $value);
                } elseif ($operator === '>') {
                    $shouldApply = $shouldApply && ($reportVal > $value);
                } elseif ($operator === '<') {
                    $shouldApply = $shouldApply && ($reportVal < $value);
                } elseif ($operator === 'contains') {
                    $shouldApply = $shouldApply && (strpos((string)$reportVal, (string)$value) !== false);
                }
            }
            
            if ($shouldApply) {
                $stmt = $pdo->prepare("SELECT * FROM triage_actions WHERE rule_id = ?");
                $stmt->execute([$rule['id']]);
                $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($actions as $act) {
                    if ($act['action_type'] === 'assign') {
                        $assigneeId = (int)$act['action_value'];
                        $stmt = $pdo->prepare("UPDATE reports SET assignee_id = ?, status = 'in_review' WHERE id = ?");
                        $stmt->execute([$assigneeId, $report['id']]);
                    } elseif ($act['action_type'] === 'escalate') {
                        $stmt = $pdo->prepare("UPDATE reports SET status = 'escalated' WHERE id = ?");
                        $stmt->execute([$report['id']]);
                    } elseif ($act['action_type'] === 'warn_user') {
                        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, 'warning', 'Your content was flagged', NOW())");
                        $stmt->execute([$report['target_id']]);
                    }
                }
                
                $processedCount++;
            }
        }
    }
    
    error_log("[TRIAGE] Processed $processedCount reports");
    
} catch (Exception $e) {
    error_log("[TRIAGE ERROR] " . $e->getMessage());
}
?>
