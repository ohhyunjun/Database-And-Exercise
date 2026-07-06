<?php
session_start();

// 1. 관리자 권한 체크 (Admin Role = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    die("<script>alert('관리자 권한이 없습니다.'); location.href='login.php';</script>");
}

// 2. 필수 파라미터 (ID) 체크
$post_id = $_GET['id'] ?? null;
if (!$post_id) {
    die("<script>alert('삭제할 공고 ID가 지정되지 않았습니다.'); location.href='admin_post_manage.php';</script>");
}

// 3. DB 연결
include 'db_conn.php';
if (!$conn) {
    die("<script>alert('DB 연결에 실패했습니다.'); location.href='admin_post_manage.php';</script>");
}

try {
    // 4. 삭제 쿼리 실행 (ON DELETE CASCADE 설정 가정)
    // HOUSINGPOST 레코드를 삭제하면, 외래키 설정에 따라 HOUSEDETAIL, ELIGIBILITYCRITERIA, USER_HOUSE의 연관 레코드가 모두 자동으로 삭제됩니다.
    $sql_delete = "DELETE FROM HousingPost WHERE post_id = :post_id";
    
    $stmt_delete = oci_parse($conn, $sql_delete);
    oci_bind_by_name($stmt_delete, ':post_id', $post_id);
    
    $result = @oci_execute($stmt_delete, OCI_NO_AUTO_COMMIT);
    
    if (!$result) {
        $e = oci_error($stmt_delete);
        throw new Exception('공고 삭제 실패: ' . $e['message']);
    }
    
    oci_commit($conn);
    
    echo "<script>
        alert('✅ 공고 ID: {$post_id}가 성공적으로 삭제되었습니다.');
        location.href='admin_post_manage.php';
    </script>";

} catch (Exception $e) {
    if ($conn) {
        oci_rollback($conn);
    }
    $error_msg = htmlspecialchars($e->getMessage());
    echo "<script>
        alert('❌ 삭제 처리 중 오류 발생: {$error_msg}');
        history.back();
    </script>";
}

if ($conn) {
    oci_close($conn);
}
?>