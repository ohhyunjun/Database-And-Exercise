<?php
session_start();

// 1. 로그인 체크
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

// 2. POST 파라미터 확인
if (!isset($_POST['post_id'])) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = $_POST['post_id'];

// 3. DB 연결
include 'db_conn.php';

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}

try {
    // ================================================================
    // STEP 1: 이미 찜했는지 확인
    // ================================================================
    $sql_check = "SELECT COUNT(*) AS CNT FROM User_House 
                  WHERE user_id = :user_id AND post_id = :post_id";
    
    $stmt_check = oci_parse($conn, $sql_check);
    oci_bind_by_name($stmt_check, ':user_id', $user_id);
    oci_bind_by_name($stmt_check, ':post_id', $post_id);
    oci_execute($stmt_check);
    
    $row = oci_fetch_array($stmt_check, OCI_ASSOC);
    $already_liked = ($row['CNT'] > 0);
    
    // ================================================================
    // STEP 2: 토글 처리 (있으면 삭제, 없으면 추가)
    // ================================================================
    if ($already_liked) {
        // 이미 찜한 상태 → 삭제
        $sql_delete = "DELETE FROM User_House 
                       WHERE user_id = :user_id AND post_id = :post_id";
        
        $stmt_delete = oci_parse($conn, $sql_delete);
        oci_bind_by_name($stmt_delete, ':user_id', $user_id);
        oci_bind_by_name($stmt_delete, ':post_id', $post_id);
        
        if (oci_execute($stmt_delete, OCI_NO_AUTO_COMMIT)) {
            oci_commit($conn);
            echo json_encode([
                'success' => true, 
                'action' => 'removed',
                'message' => '관심 공고에서 제거되었습니다.',
                'is_liked' => false
            ]);
        } else {
            throw new Exception('삭제 실패');
        }
        
    } else {
        // 찜하지 않은 상태 → 추가
        $sql_insert = "INSERT INTO User_House (user_id, post_id) 
                       VALUES (:user_id, :post_id)";
        
        $stmt_insert = oci_parse($conn, $sql_insert);
        oci_bind_by_name($stmt_insert, ':user_id', $user_id);
        oci_bind_by_name($stmt_insert, ':post_id', $post_id);
        
        if (oci_execute($stmt_insert, OCI_NO_AUTO_COMMIT)) {
            oci_commit($conn);
            echo json_encode([
                'success' => true, 
                'action' => 'added',
                'message' => '관심 공고에 등록되었습니다! ❤️',
                'is_liked' => true
            ]);
        } else {
            throw new Exception('추가 실패');
        }
    }
    
} catch (Exception $e) {
    oci_rollback($conn);
    echo json_encode([
        'success' => false, 
        'message' => '오류 발생: ' . $e->getMessage()
    ]);
}

// 4. DB 연결 종료
oci_close($conn);
?>